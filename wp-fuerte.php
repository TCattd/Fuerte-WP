<?php
/**
 * Plugin Name: WP Fuerte
 * Plugin URI: https://github.com/TCattd/wp-fuerte
 * Description: Limit access to critical WordPress's areas
 * Version: 1.0.0
 * Author: Esteban Cuevas
 * Author URI: https://actitud.xyz
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wpfuerte
 */

/**
 * Upload this script to /wp-content/plugins/mu-plugins/
 *
 * Set your own array of setting inside wp-config.php file
 * to avoid being overwritten by updates to this file.
 */

defined( 'ABSPATH' ) || die();

/**
 * Exit if WPFUERTE_DISABLE is defined in wp-config.php
 */
if ( defined('WPFUERTE_DISABLE') ) {
	return false;
}

if ( ! defined('WPFUERTE_FORCE') ) {
	define('WPFUERTE_FORCE', false);
}

global $wpfuerte;

/**
 * WP Fuerte default config array
 */
if ( ! isset( $wpfuerte ) && empty( $wpfuerte ) ) {
	// Copy from here...
	$wpfuerte = [
		'config' => [
			'recovery_email'          => 'esteban@attitude.cl', // https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/
			'autoupdate_plugins'      => true,
			'autoupdate_themes'       => true,
			'autoupdate_translations' => true,
		],
		// user account's email address
		'allowed_users'    => [
			'esteban@attitude.cl',
		],
		// $pagenow
		'restricted_scripts' => [
			'export.php',
			'theme-editor.php',
			'plugins.php',
			'plugin-install.php',
			'theme-install.php',
			'update.php',
		],
		// admin.php?page=
		'restricted_pages' => [
			'wp_stream_settings',
			'envato-market',
		],
	];
	// ...to here.
}

/**
 * TODO: Convert this to a proper Class
 */
function wpfuerte_main() {
	global $wpfuerte, $pagenow;

	/**
	 * Disable email notification for updates
	 */
	add_filter( 'auto_core_update_send_email', '__return_false' );

	/**
	 * Themes & Plugins auto updates
	 */
	if ( $wpfuerte['config']['autoupdate_plugins'] === true ) {
		add_filter( 'auto_update_plugin', '__return_true' );
	}
	if ( $wpfuerte['config']['autoupdate_themes'] === true ) {
		add_filter( 'auto_update_theme', '__return_true' );
	}
	if ( $wpfuerte['config']['autoupdate_themes'] === true ) {
		add_filter( 'autoupdate_translations', '__return_true' );
	}

	/**
	 * Change recovery mode email
	 */
	/*add_filter( 'recovery_mode_email', function( $email_data ) {
		$email_data['to'] = $wpfuerte['config']['autoupdate_themes']['recovery_email'];
		return $email_data;
	});*/
	define('RECOVERY_MODE_EMAIL', $wpfuerte['config']['autoupdate_themes']['recovery_email']);

	if ( is_admin() ) {
		$current_user = wp_get_current_user();

		if ( ! in_array( strtolower( $current_user->user_email ), $wpfuerte['allowed_users'] ) || WPFUERTE_FORCE === true ) {
			// No Plugins/Theme upload/install/update/remove
			define( 'DISALLOW_FILE_MODS', true );

			// Remove menu items
			add_action( 'admin_menu', 'wpfuerte_remove_wpadmin_menus' );

			// Disallowed wp-admin scripts
			if ( in_array( $pagenow, $wpfuerte['restricted_scripts'] ) && ! wp_doing_ajax() ) {
				wp_die('Can\'t touch this.');
				return false;
			}

			// Disallowed wp-admin pages
			if ( in_array( $_REQUEST['page'], $wpfuerte['restricted_pages'] ) && ! wp_doing_ajax() ) {
				wp_die('Can\'t touch this.');
				return false;
			}

			// No user switching
			if ( $_REQUEST['action'] == 'switch_to_user' ) {
				wp_die('Can\'t touch this.');
				return false;
			}

			// No protected users editing
			if ( $pagenow == 'user-edit.php' ) {
				if( ! empty( $_REQUEST['user_id'] ) && isset( $_REQUEST['user_id'] ) ) {
					$user_info = get_userdata( $_REQUEST['user_id'] );

					if ( in_array( strtolower( $user_info->user_email ), $wpfuerte['allowed_users'] ) ) {
						wp_die('Can\'t touch this.');
						return false;
					}
				}
			}

			// No protected users deletion
			if ( $pagenow == 'users.php' ) {
				if( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' ) {

					if( isset( $_REQUEST['users'] ) ) {
						// Single user
						foreach ($_REQUEST['users'] as $user) {
							$user_info = get_userdata( $user );

							if ( in_array( strtolower( $user_info->user_email ), $wpfuerte['allowed_users'] ) ) {
								wp_die('Can\'t touch this.');
								return false;
							}
						}
					} elseif( isset( $_REQUEST['user'] ) ) {
						// Batch deletion
						$user_info = get_userdata( $_REQUEST['user'] );

						if ( in_array( strtolower( $user_info->user_email ), $wpfuerte['allowed_users'] ) ) {
							wp_die('Can\'t touch this.');
							return false;
						}
					}
				}
			}

			// No ACF editor menu
			add_filter('acf/settings/show_admin', '__return_false');
		}
	} // is_admin()
}
add_action( 'plugins_loaded', 'wpfuerte_main' );

function wpfuerte_remove_wpadmin_menus() {
	global $wpfuerte;

	foreach ( $wpfuerte['restricted_scripts'] as $item ) {
		remove_menu_page( $item );
	}

	remove_submenu_page( 'options-general.php', 'mainwp_child_tab' ); // MainWP Child
	remove_submenu_page( 'tools.php', 'export.php' ); // Export
}
