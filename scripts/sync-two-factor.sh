#!/usr/bin/env bash
#
# Sync the upstream Two-Factor plugin (https://github.com/WordPress/two-factor)
# into Fuerte-WP at includes/two-factor/.
#
# Fetches the maintainer-built release zip (Grunt-produced, .distignore-filtered)
# which contains the runtime assets the git source tree lacks (notably
# includes/qrcode-generator/qrcode.js used by the TOTP provider).
#
# Usage:
#   ./scripts/sync-two-factor.sh            # latest GitHub release
#   ./scripts/sync-two-factor.sh 0.16.0     # pin to a specific tag
#
# Exits non-zero on any failure. Network required (GitHub API + release asset).
# Run from anywhere; resolves the plugin root relative to this script.

set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="$PLUGIN_ROOT/includes/two-factor"
REPO="WordPress/two-factor"
ASSET_NAME="two-factor.zip"

VERSION="${1:-}"
ZIP_URL=""

if [[ -n "$VERSION" ]]; then
	# Explicit pin: construct the release-asset URL directly.
	ZIP_URL="https://github.com/${REPO}/releases/download/${VERSION}/${ASSET_NAME}"
else
	# Resolve latest release tag + asset URL via the GitHub API.
	# (Unauthenticated calls are rate-limited to 60/hour, plenty for manual syncs.)
	API_JSON="$(curl -fsSL -H "Accept: application/vnd.github+json" \
		"https://api.github.com/repos/${REPO}/releases/latest")"

	VERSION="$(printf '%s' "$API_JSON" \
		| grep -m1 '"tag_name"' \
		| sed -E 's/.*"tag_name"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/')"

	ZIP_URL="$(printf '%s' "$API_JSON" \
		| grep -oE '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]+"' \
		| grep -oE 'https://[^"]+' \
		| grep -E "/${ASSET_NAME}$" \
		| head -n1 || true)"

	# If the API payload didn't expose the asset for some reason, fall back.
	if [[ -z "$ZIP_URL" && -n "$VERSION" ]]; then
		ZIP_URL="https://github.com/${REPO}/releases/download/${VERSION}/${ASSET_NAME}"
	fi
fi

if [[ -z "$VERSION" || -z "$ZIP_URL" ]]; then
	echo "ERROR: could not resolve two-factor version or download URL." >&2
	exit 1
fi

echo "==> two-factor version: ${VERSION}"
echo "==> downloading ${ZIP_URL}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ZIP="$TMP/${ASSET_NAME}"
curl -fsSL -o "$ZIP" "$ZIP_URL"

# Unzip into a scratch dir; the archive extracts to a top-level two-factor/ dir.
UNPACK="$TMP/unpacked"
mkdir -p "$UNPACK"
unzip -q "$ZIP" -d "$UNPACK"

SRC="$UNPACK/two-factor"
if [[ ! -f "$SRC/two-factor.php" ]]; then
	echo "ERROR: archive did not contain a top-level two-factor/two-factor.php." >&2
	exit 1
fi

# Swap the destination atomically-ish: remove old, move new into place.
echo "==> installing to ${DEST}"
rm -rf "$DEST"
mkdir -p "$(dirname "$DEST")"
mv "$SRC" "$DEST"

# Stamp the installed version for drift detection / display.
printf '%s\n' "$VERSION" > "$DEST/.fuertewp-version"

echo "==> done. two-factor ${VERSION} installed at includes/two-factor/"
