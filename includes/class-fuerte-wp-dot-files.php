<?php

/**
 * Filesystem kill switches for Fuerte-WP.
 *
 * Server operators can disable the whole plugin or individual subsystems by
 * dropping marker files in the WordPress root (ABSPATH) or one level above
 * it (the docroot parent) — the same two locations WordPress searches when
 * wp-config.php is moved out of the web root:
 *
 *   /user/public_html/.fuertewp-disable
 *   /user/.fuertewp-disable          <- docroot parent, WP root below it
 *
 * Marker files:
 *
 *   .fuertewp-disable       — disable the entire plugin
 *   .fuertewp-disable-mfa   — disable the bundled Two-Factor subsystem
 *
 * Presence only: an empty file counts and content is never read, so
 * `touch .fuertewp-disable` is a complete emergency stop. Same trust tier
 * as the FUERTEWP_DISABLE / FUERTEWP_DISABLE_2FA constants, usable when
 * wp-config.php itself is not writable (provisioning, containers, incident
 * response).
 *
 * @since 1.12.0
 * @link       https://actitud.xyz
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

class Fuerte_Wp_Dot_Files
{
    /**
     * Marker file that disables the entire plugin.
     */
    private const FILE_DISABLE = '.fuertewp-disable';

    /**
     * Marker file that disables the Two-Factor subsystem only.
     */
    private const FILE_DISABLE_MFA = '.fuertewp-disable-mfa';

    /**
     * Per-request cache of marker lookups.
     *
     * @var array<string, bool>
     */
    private static array $cache = [];

    /**
     * Whether the master kill switch is present.
     *
     * @since 1.12.0
     *
     * @return bool True when the plugin must not boot at all.
     */
    public static function all_disabled(): bool
    {
        return self::exists(self::FILE_DISABLE);
    }

    /**
     * Whether the Two-Factor subsystem must stay off.
     *
     * The master switch implies this: when the plugin is fully disabled,
     * Two-Factor never boots either.
     *
     * @since 1.12.0
     *
     * @return bool
     */
    public static function mfa_disabled(): bool
    {
        return self::exists(self::FILE_DISABLE_MFA) || self::exists(self::FILE_DISABLE);
    }

    /**
     * Marker file lookup in the two wp-config.php-style locations.
     *
     * @since 1.12.0
     *
     * @param string $filename Marker file name.
     *
     * @return bool True when the marker exists in ABSPATH or its parent.
     */
    private static function exists(string $filename): bool
    {
        if (array_key_exists($filename, self::$cache)) {
            return self::$cache[$filename];
        }

        $found = file_exists(ABSPATH . $filename)
            || file_exists(dirname(ABSPATH) . '/' . $filename);

        self::$cache[$filename] = $found;

        return $found;
    }

    /**
     * Clear the per-request cache (tests and long-running processes).
     *
     * @since 1.12.0
     */
    public static function reset_cache(): void
    {
        self::$cache = [];
    }
}
