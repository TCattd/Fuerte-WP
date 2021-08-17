<?php
/**
 * Plugin Name: Fuerte-WP
 * Plugin URI: https://github.com/TCattd/WP-Fuerte
 * Description: Stronger WP. Limit access to critical WordPress areas, even other for admins.
 * Version: 1.2.0
 * Author: Esteban Cuevas
 * Author URI: https://actitud.xyz
 *
 * Requires at least: 5.8
 * Tested up to: 5.8
 * Requires PHP: 7.4
 *
 * Text Domain: fuerte-wp
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// No access outside WP
defined( 'ABSPATH' ) || die();

/**
 * Load WP-Fuerte config file
 */
if ( file_exists( ABSPATH . 'wp-config-fuerte.php' ) ) {
	require_once ABSPATH . 'wp-config-fuerte.php';
} else {
	return false;
}

/**
 * Exit if WPFUERTE_DISABLE is defined (in wp-config.php or wp-config-fuerte.php), like this:
 *
 * define( 'WPFUERTE_DISABLE', true );
 */
if ( defined( 'WPFUERTE_DISABLE' ) ) {
	return false;
}

/**
 * WP-Fuerte plugin base. Needed for self-protection.
 */
define( 'WPFUERTE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

/**
 * To force skip super_users abilities, for testing
 */
if ( ! defined( 'WPFUERTE_FORCE' ) ) {
	define( 'WPFUERTE_FORCE', false );
}

/**
 * Includes
 */
if ( file_exists( trailingslashit( plugin_dir_path(__FILE__) ) . 'classes/class-wpfuerte.php' ) ) {
	include_once trailingslashit( plugin_dir_path(__FILE__) ) . 'classes/class-wpfuerte.php';
}

/**
 * Init WP-Fuerte
 */
global $wpfuerte;

if ( isset( $wpfuerte ) && ! empty( $wpfuerte ) ) {
	add_action( 'plugins_loaded', array( WPFuerte::get_instance(), 'init' ) );
}
