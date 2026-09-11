# Two-Factor Bundling Strategy

Status: **Decision made** (revised after peer review, 2026-07-10)
Scope: How Fuerte-WP ships the upstream [WordPress/two-factor](https://github.com/WordPress/two-factor) functionality inside the plugin.

## Decision

**Bundle the upstream `two-factor` source unprefixed, detect-and-defer to a standalone install, block activation of the standalone plugin while Fuerte-WP's 2FA is enabled, and treat lockout recovery as a first-class requirement.**

Providers in scope: **TOTP**, **Email**, **Backup / recovery codes**. (WebAuthn/passkeys out of scope this phase.) Enforcement is **per-role**.

This decision **inverts** the original recommendation (Strauss prefixing). The inversion came out of source review and research, then verified against upstream source. The reasoning is below.

---

## The load-bearing constraint (verified)

The upstream plugin uses **class names as user-meta data keys**. From `class-two-factor-core.php`:

- `get_default_providers()` returns `['Two_Factor_Email' => '.../class-two-factor-email.php', ...]` — class name as array key.
- `_two_factor_enabled_providers` user meta stores literal strings: `['Two_Factor_Totp', 'Two_Factor_Email']`.
- `get_enabled_providers_for_user()` does `array_intersect($enabled_providers, array_keys($providers))`.
- Third-party providers extend `Two_Factor_Provider` (original name) and register via the `two_factor_providers` filter — a documented extension point with a real ecosystem.

This is the fact that decides everything. Any class-renaming strategy (Strauss, PHP-Scoper) hits an impedance mismatch:

- Rename the class but not the array-key string → `class_exists('Two_Factor_Totp')` is false → provider silently unset → **2FA breaks**.
- Rename both → user meta written by a prior standalone install (old keys) won't intersect → **2FA silently disabled** for migrated users.
- Third-party `extends Two_Factor_Provider` → fatal class-not-found if the standalone plugin is inactive.
- Strauss prefixes PHP only, not `dist/` CSS/JS assets.

Prefixing is structurally wrong for this dependency, not just fiddly.

---

## Why jetpack-autoloader is not a candidate

Considered seriously because `composer.json` already lists `automattic/jetpack-autoloader` in `allow-plugins`.

**What JPA does:** picks the highest package version of a class across all plugins that *also* use JPA, and loads that one copy. It is a **version-selection runtime manager for shared libraries** (think Guzzle, php-qrcode). It does not rename anything. It does not let two versions coexist.

**Why it does not fit `two-factor`:** `two-factor` is not a shared library. It is an application with global login hooks, provider singletons, and global user-meta keys. JPA's "one definition wins" model does not help when the definition includes side effects that must run and state that must not be shared. If the user also runs the standalone `two-factor` plugin (800k+ installs on .org, common case), JPA picks one copy by version number; the loser's hooks never register; both target the same user meta. Worse than prefixing (which isolates) and worse than soft-dep (where only one is ever installed).

JPA would be the right tool if Fuerte-WP pulled in a pure library (e.g. `chillerlan/php-qrcode` for QR generation) and wanted to share it with other plugins. Separate decision, does not affect bundling.

---

## The chosen approach (in detail)

### 1. Bundle unprefixed

Ship the upstream `two-factor` source inside `src/app/plugins/fuerte-wp/` (e.g. `includes/two-factor/`) **without renaming**. Boot the bundled core on `plugins_loaded` at an early priority so providers register before the login flow runs.

### 2. Detect-and-defer (avoid double-load)

On boot, check whether the standalone `two-factor/two-factor.php` is active:

- **Standalone active:** Fuerte-WP does **not** load its bundled copy. The user is already running 2FA through the standalone plugin; Fuerte-WP layers enforcement on top of it via filters.
- **Standalone inactive:** Fuerte-WP loads its bundled copy.

This keeps exactly one copy running in every state. No class collision, no duplicate hooks, no double-2FA.

### 3. Activation-block (the hardening that makes this safe)

The collision risk with detect-and-defer is "both copies somehow active." We do not tolerate that. Fuerte-WP **blocks activation of the standalone `two-factor/two-factor.php`** while its bundled copy is the active source, reusing the existing self-protection pattern (`Fuerte_Wp_Enforcer::self_protect()`, which blocks the actual `$_REQUEST` deactivate action, not just hides the UI link).

Coverage:

- **Single-row Activate link** (GET `action=activate&plugin=...`) — blocked on `admin_init`, which fires before `plugins.php` processes activation.
- **Bulk Activate** (POST `action=activate-selected&checked[]=...`) — blocked by scanning `$_REQUEST['checked']` and checking both `action` and `action2` fields.
- **Multisite network admin** (`network/plugins.php`) — same bulk mechanism, covered; the Activate link is also hidden via `network_admin_plugin_action_links`.

**Documented limitation:** WP-CLI (`wp plugin activate two-factor`) and the REST API (`PUT /wp/v2/plugins/...`) bypass `admin_init` entirely and are not blocked. Both require a trust tier (CLI/SSH implies filesystem access, REST requires `activate_plugins` capability) that already permits disabling Fuerte-WP wholesale; covering them would not raise the effective bar. This is a deliberate scope decision, not an oversight.

**Escape hatch:** an operator with filesystem access sets `FUERTEWP_DISABLE_2FA` to true in `wp-config-fuerte.php`. When set, Fuerte-WP stops loading its bundled copy entirely (not just enforcement), so the standalone plugin can be activated cleanly on the next request without a class-redeclare fatal. This is the recovery lever for both lockouts and for migrating to the standalone plugin.

Since 1.12.0 the same tier is reachable without editing `wp-config-fuerte.php`: a `.fuertewp-disable-mfa` marker file (WordPress root or its parent directory, wp-config.php-style lookup) keeps the bundled copy unloaded, and `.fuertewp-disable` disables the whole plugin. Presence only: an empty file counts.

### 4. Lockout recovery (first-class, not deferred)

A self-protecting plugin that can lock out its own administrators with no escape hatch is a worse outcome than any other con in this document. Recovery levers:

- **Super users bypass enforcement.** Users in `super_users` (the existing config list, mirrored in `wp-config-fuerte.php`) bypass per-role 2FA enforcement, exactly as they bypass every other Fuerte-WP restriction.
- **`FUERTEWP_DISABLE_2FA` kill-switch.** A constant in `wp-config-fuerte.php`, matching the existing `FUERTEWP_DISABLE` / `FUERTEWP_FORCE` naming convention. Unlike those, it uses a **lenient truthiness check** (accepts `true`, `1`, `'1'`, `'true'`) because it is the documented recovery lever and a silent no-op from a stringy value would lock the operator out of their own site. When set, it stops loading the bundled provider code so the standalone plugin (or no 2FA) can take over.
- **`.fuertewp-disable-mfa` marker file (1.12.0).** Same effect as the constant, at the same filesystem trust tier, but reachable when `wp-config-fuerte.php` is not writable (provisioned containers, incident response): create the file in the WordPress root or its parent and the bundled copy never loads. The master `.fuertewp-disable` marker implies it.
- **WP-CLI fallback.** Operators with CLI access can clear user meta (`_two_factor_*` keys) directly.

These three levers cover the standard recovery matrix (another admin, wp-cli/DB meta deletion, plugin deactivation via filesystem) that Fuerte-WP's self-protection otherwise removes for non-super-users.

---

## What this resolves

- **No Strauss blind spots.** No class-name-as-data impedance mismatch. Providers load and intersect correctly.
- **Third-party providers work natively.** `extends Two_Factor_Provider` resolves; the `two_factor_providers` filter ecosystem keeps working.
- **Assets load natively.** `dist/` CSS/JS (QR codes, backup-code UI, login scripts) served from the bundled path without Strauss's PHP-only limitation.
- **Self-protecting.** Does not depend on a second plugin's uptime (unlike soft dependency).
- **Coexists safely with standalone installs.** The activation-block prevents the double-load case; detect-and-defer handles the already-running case.
- **Recovery is real.** Super-user enforcement bypass + `FUERTEWP_DISABLE_2FA` bundled-load kill-switch + dot-file kill switches (`.fuertewp-disable`, `.fuertewp-disable-mfa`) + documented CLI path.
- **Clean uninstall.** Deleting Fuerte-WP purges bundled 2FA data (`_two_factor_*` user meta incl. TOTP secrets and backup codes, plus the `two_factor_enabled_providers` option) via `Two_Factor_Core::uninstall()` invoked from `uninstall.php`. No orphaned PII.

## What this costs

- **You own security patches for the bundled copy.** CVE in upstream means a Fuerte-WP release. This is true under any bundling strategy and is the price of depend-and-enforce.
- **Detection path has two branches to test.** Standalone-active vs standalone-inactive. Bounded and testable.
- **Larger plugin download.** `two-factor` is small.

## Effort

Bundling + detect-and-defer + activation-block + recovery constants: roughly 2 days for a first verifiable pass (boot sequence, detection branch, activation-block reusing the enforcer pattern, recovery constants, and an end-to-end login-flow test covering both branches).

---

## Rejected alternatives

- **Strauss prefixing (original recommendation).** Rejected: the class-name-as-data-key impedance mismatch (verified above) makes it structurally fragile for this dependency, not just fiddly. Third-party provider fatals and orphaned assets compound it.
- **Soft dependency** (recommend the standalone plugin, no bundling). Rejected: breaks Fuerte-WP's self-protecting model. A non-super-user deactivating the dependency silently disables 2FA for every admin.
- **Build native 2FA.** Rejected: out of scope this phase. Reimplements TOTP/RFC 6238, QR generation, second-step login interception, backup codes, rate limiting. High lockout risk during development. Contradicts the depend-and-enforce decision.
- **PHP-Scoper.** Rejected for the same reason as Strauss (class-name-as-data impedance mismatch), plus steeper setup (needs WordPress-symbol exclusion config). Not evaluated as a separate option since the impedance mismatch is what kills it.

## MU-plugin approach (noted, not chosen)

An earlier review floated bundling the two-factor code as an MU-plugin in `src/app/mu-plugins/`. MU-plugins truly cannot be deactivated from wp-admin, which is the strongest self-protection primitive available. Rejected for now only because AGENTS.md scopes changes to `src/app/plugins/fuerte-wp/`. Flagged as a future escalation if the activation-block approach proves insufficient in production.

---

## Open sub-questions (resolve during implementation)

- Whether to also hide the standalone `two-factor` plugin from the Plugins screen entirely when Fuerte-WP's 2FA is enabled (defense in depth on top of the activation-block).
- Whether to run `composer sync-two-factor` as part of the `deploy` script chain (ensures freshness in .org releases) or keep it dev-run-only.
- Test fixture for the detect-and-defer branch: a profile that simulates the standalone-active state without requiring the real plugin.

## Fetch mechanism (decided 2026-07-10)

The bundled source is delivered by a sync script, not by Composer. The git source tree lacks the built runtime assets (notably `includes/qrcode-generator/qrcode.js`, ~57KB, used by the TOTP provider for QR rendering); those only exist in the maintainer-built release zip.

- **Script:** `scripts/sync-two-factor.sh` (composer alias `composer sync-two-factor`).
- **Source:** the GitHub release asset `two-factor.zip` (Grunt-produced, `.distignore`-filtered — 0 dev cruft, only runtime files). URL: `https://github.com/WordPress/two-factor/releases/download/<tag>/two-factor.zip`.
- **Latest or pinned:** `./scripts/sync-two-factor.sh` resolves the latest release via the GitHub API; `./scripts/sync-two-factor.sh 0.16.0` pins.
- **Destination:** `includes/two-factor/` (stamped with `.fuertewp-version`).
- **Committed to git** (matches the Fuerte-WP convention for `vendor/` — the WordPress.org SVN release must be self-contained; .org users don't run composer/scripts).

Rejected: Composer VCS repository (pulled raw git source, missing built assets). Rejected: Composer `package` repo + dist URL (cleaner but loses auto-latest resolution the script gives). Rejected: WP.org download zip (GitHub release is the canonical built artifact and matches the `wordpress/two-factor` identity).

Rejected earlier (superseded): a Composer `require` of `wordpress/two-factor` via VCS, and a `composer/installers` `installer-paths` rule. Both removed from `composer.json`; two-factor is no longer a Composer dependency — it's a script-synced vendored artifact.

---

## Peer review record

This document was independently source-reviewed on 2026-07-10, with the goal of challenging the recommendation rather than rubber-stamping it.

- **Agreement:** JPA dismissal correct. Lockout recovery must be first-class. Class-name-as-data problem is real and verified.
- **Disagreement:** One pass recommended keep-Strauss + activation-block; another recommended drop-Strauss + detect-and-defer (= the rejected Option 3).
- **Resolution:** Drop-Strauss + detect-and-defer, with the activation-block hardening, wins. The activation-block neutralizes the collision risk that was the original reason for rejecting detect-and-defer. Detect-and-defer + activation-block has zero Strauss blind spots; Strauss keeps them even with the block.

### Path A implementation review (2026-07-10)

After the integration was built, a second independent review (same two peers) found the activation-block had real gaps. Both rated the build "ship with fixes."

- **Critical: bulk-activate bypass.** The block matched only the single-row GET path; bulk-activate (POST `activate-selected` + `checked[]`) and the multisite network admin screen sailed through. Fixed by scanning `$_REQUEST['checked']`, checking both `action` and `action2`, and hooking `network_admin_plugin_action_links`.
- **Critical: super-user escape hatch was a fatal trap.** The bundled copy loads at `plugins_loaded:1`, before `admin_init`, so even a super user following the abort message's instructions hit a class-redeclare fatal. Fixed by making `FUERTEWP_DISABLE_2FA` gate `boot()`'s `require_once` (not just enforcement), with a lenient truthiness check and an honest abort message.
- **High: uninstall orphans TOTP secrets and backup codes.** The bundled `register_uninstall_hook(__FILE__)` keys to a nested path WP never fires; separately, the Fuerte-WP uninstaller class was dead code (never registered). Fixed by invoking `Two_Factor_Core::uninstall()` directly from `uninstall.php` (the real cleanup path), loading only the provider base + core class to avoid `add_hooks()` side effects. **Follow-on:** removed the entire dead uninstall chain (`uninstall_fuerte_wp()` in `fuerte-wp.php` + the `Fuerte_Wp_Uninstaller` class file) since `uninstall.php` is the only path WP actually fires. Ported the class's intended `.htaccess` cleanup (marker-based removal of the `# BEGIN Fuerte-WP`…`# END Fuerte-WP` block) into `uninstall.php` — the class's `global $fuertewp_htaccess` was a no-op at uninstall time anyway (the global is defined in `fuerte-wp.php`, which is not loaded during uninstall).
- **Documented limitation:** WP-CLI and REST API activation bypass `admin_init` and are not blocked. Both require a trust tier that already permits disabling Fuerte-WP wholesale; covering them would not raise the effective bar.
- **One proposed fix rejected:** One suggestion was intercepting activation via the dynamic `activate_{$plugin}` hook. Verified incorrect — that hook fires *after* `plugin_sandbox_scrape()` includes the plugin file, so the fatal redeclare has already occurred. The `admin_init` approach is correct; it just needed bulk + multisite coverage.
