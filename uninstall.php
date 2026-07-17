<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/DevinVinson/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://actitud.xyz
 * @since      1.3.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (isset($_REQUEST['plugin']) && $_REQUEST['plugin'] != 'fuerte-wp/fuerte-wp.php' && $_REQUEST['action'] != 'delete-plugin') {
    wp_die('Error uninstalling: wrong plugin.');
}

// Clears all Fuerte-WP options from DB
global $wpdb;

$fuertewp_options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_fuertewp_%'");

if (is_array($fuertewp_options) && !empty($fuertewp_options)) {
    foreach ($fuertewp_options as $option) {
        delete_option($option->option_name);
    }
}

// Clears Fuerte-WP transient
delete_transient('fuertewp_cache_config');

/**
 * Remove Fuerte-WP's .htaccess security block (everything between the
 * # BEGIN Fuerte-WP and # END Fuerte-WP markers, inclusive). Apache only.
 * Uses marker-based regex rather than exact-string matching so it survives
 * whitespace edits to the block. Mirrors how the enforcer writes it.
 */
if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false) {
    $htaccessFile = ABSPATH . '.htaccess';

    if (file_exists($htaccessFile) && is_writable($htaccessFile)) {
        $currentContent = file_get_contents($htaccessFile);

        if (false !== strpos($currentContent, '# BEGIN Fuerte-WP')) {
            $pattern = '/# BEGIN Fuerte-WP.*?# END Fuerte-WP\s*/s';
            $newContent = preg_replace($pattern, '', $currentContent);
            file_put_contents($htaccessFile, $newContent);
        }
    }
}

/**
 * Clean up bundled Two-Factor data: user meta (TOTP secrets, backup codes,
 * nonces, rate-limit counters) and the enabled-providers option.
 *
 * uninstall.php runs outside the plugin's normal load context, so the bundled
 * two-factor classes are not autoloaded and two-factor.php itself is NOT
 * included (it registers login hooks via add_hooks, which we do not want
 * during uninstall). Load only the provider base + core class, then call
 * the core's own uninstall method, which also covers provider-specific keys.
 */
if (!defined('TWO_FACTOR_DIR')) {
    define('TWO_FACTOR_DIR', dirname(__FILE__) . '/includes/two-factor/');
}

if (!defined('TWO_FACTOR_VERSION')) {
    define('TWO_FACTOR_VERSION', '0.16.0');
}
$tf_provider = TWO_FACTOR_DIR . 'providers/class-two-factor-provider.php';
$tf_core = TWO_FACTOR_DIR . 'class-two-factor-core.php';

if (is_readable($tf_provider) && is_readable($tf_core)) {
    require_once $tf_provider;
    require_once $tf_core;

    if (class_exists('Two_Factor_Core') && method_exists('Two_Factor_Core', 'uninstall')) {
        Two_Factor_Core::uninstall();
    }
}
