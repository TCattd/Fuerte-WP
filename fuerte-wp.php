<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Fuerte-WP
 * Plugin URI:        https://github.com/TCattd/Fuerte-WP
 * Description:       Stronger WP. Limit access to critical WordPress areas, even other for admins.
 * Version:           1.5.1
 * Author:            Esteban Cuevas
 * Author URI:        https://actitud.xyz
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fuerte-wp
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.6
 * Requires PHP:      7.3
 *
 * @link              https://actitud.xyz
 * @since             1.3.0
 * @package           Fuerte_Wp
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('FUERTEWP_PLUGIN_BASE', plugin_basename(__FILE__));
define('FUERTEWP_VERSION', '1.5.1');
define('FUERTEWP_PATH', realpath(plugin_dir_path(__FILE__)) . '/');
define('FUERTEWP_URL',  trailingslashit(plugin_dir_url(__FILE__)),);

/**
 * Load Fuerte-WP config file if exist
 */
if (file_exists(ABSPATH . 'wp-config-fuerte.php')) {
	require_once ABSPATH . 'wp-config-fuerte.php';
}

/**
 * Exit if FUERTEWP_DISABLE is defined (in wp-config.php or wp-config-fuerte.php)
 */
if (defined('FUERTEWP_DISABLE') && true === FUERTEWP_DISABLE) {
	return false;
}

/**
 * Includes & Autoload
 */
function fuertewp_includes_autoload()
{
	if (file_exists(FUERTEWP_PATH . 'includes/helpers.php')) {
		require_once FUERTEWP_PATH . 'includes/helpers.php';
	}

	// Elementor has JS issues with Carbon-Fields being loaded while in his editor.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'elementor') {
		return;
	}

	if (file_exists(FUERTEWP_PATH . 'vendor/autoload.php')) {
		require_once FUERTEWP_PATH . 'vendor/autoload.php';

		// https://github.com/htmlburger/carbon-fields/issues/805#issuecomment-680959592
		// https://docs.carbonfields.net/learn/advanced-topics/compacting-input-vars.html
		define('Carbon_Fields\URL', FUERTEWP_URL . 'vendor/htmlburger/carbon-fields/');
		define('Carbon_Fields\\COMPACT_INPUT', true);
		define('Carbon_Field\\COMPACT_INPUT_KEY', 'fuertewp_carbonfields');

		Carbon_Fields\Carbon_Fields::boot();
	}
}
add_action('after_setup_theme', 'fuertewp_includes_autoload', 7);
//add_action( 'plugins_loaded', 'fuertewp_includes_autoload' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-fuerte-wp-activator.php
 */
function activate_fuerte_wp()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-fuerte-wp-activator.php';
	Fuerte_Wp_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-fuerte-wp-deactivator.php
 */
function deactivate_fuerte_wp()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-fuerte-wp-deactivator.php';
	Fuerte_Wp_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_fuerte_wp');
register_deactivation_hook(__FILE__, 'deactivate_fuerte_wp');

/**
 * Code that runs on plugins uninstallation
 */
function uninstall_fuerte_wp()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-fuerte-wp-uninstaller.php';
	Fuerte_Wp_Uninstaller::uninstall();
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-fuerte-wp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_fuerte_wp()
{
	$plugin = new Fuerte_Wp();
	$plugin->run();
}
run_fuerte_wp();

/**
 * htaccess security rules
 */
$fuertewp_htaccess = "
# BEGIN Fuerte-WP
# Avoid install.php and install-helper.php from being accessed directly
<FilesMatch \"^(wp-admin/)?(install|install-helper)\.php$\">
	Order allow,deny
	Deny from all
</FilesMatch>

# Disable running PHP scripts in the uploads directory
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteRule ^(wp-content/uploads/.*)\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|html|htm|shtml|sh|cgi|suspected)$ - [F,L]
</IfModule>
# END Fuerte-WP
";
