<?php
/**
 * Plugin Name: WP Fuerte
 * Plugin URI: https://github.com/TCattd/wp-fuerte
 * Description: Limit access to critical WordPress's areas
 * Version: 1.0.2
 * Author: Esteban Cuevas
 * Author URI: https://actitud.xyz
 *
 * Requires at least: 5.4
 * Tested up to: 5.5
 * Requires PHP: 7.2
 *
 * Text Domain: wp-fuerte
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
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
if ( defined( 'WPFUERTE_DISABLE' ) ) {
	return false;
}

/**
 * To force skip super_users abilities, for testing
 */
if ( ! defined( 'WPFUERTE_FORCE' ) ) {
	define( 'WPFUERTE_FORCE', false );
}

/**
 * WP Fuerte default config array
 */
global $wpfuerte;
if ( ! isset( $wpfuerte ) && empty( $wpfuerte ) ) {
	// Copy from here...
	$wpfuerte = [
		'config' => [
			'version'                   => '1.0.2',
			'not_allowed_message'       => 'Access denied.',
			'recovery_email'            => '', // if empty, dev@wpdomain.tld will be used
			'sender_email'              => '', // if empty, no-reply@wpdomain.tld will be used
			'autoupdate_core'           => true,
			'autoupdate_plugins'        => true,
			'autoupdate_themes'         => true,
			'autoupdate_translations'   => true,
			'disable_update_email'      => true,
			'disable_admin_create_edit' => true,
			'disable_app_passwords'     => true,
		],
		// user account's email address
		'super_users'    => [
			'esteban@attitude.cl',
		],
		// $pagenow & remove_menu_page, use: menu-slug
		'restricted_scripts' => [
			'export.php',
			'theme-editor.php',
			'plugins.php',
			'plugin-install.php',
			'theme-install.php',
			'update.php',
		],
		// admin.php?page=, use 'page' request value
		'restricted_pages' => [
			'wp_stream_settings',
			'envato-market',
		],
		// remove_menu_page, use: menu-slug
		'removed_menus' => [
			'backwpup', // BackWPup
			'check-email-status', // Check & Log Email
			'limit-login-attempts', // Limit Logins Attempts Reloaded
		],
		// remove_submenu_page, use: parent-menu-slug => submenu-slug
		'removed_submenus' => [
			'options-general.php' => 'mainwp_child_tab', // MainWP Child
			'tools.php'           => 'export.php', // WP export tool
		],
		// format: slug-name/main-file.php
		'recommended_plugins' => [
			'imsanity/imsanity.php', // Imsanity
			'safe-svg/safe-svg.php', // Save SVG
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', // Limit Login Attempts Reloaded
		],
		// format: check the included examples
		'discouraged_plugins' => [
			[
				// SEO Framework instead of Yoast SEO
				'discouraged_plugin' => 'wordpress-seo/wp-seo-main.php',
				'discouraged_name'   => 'Yoast SEO',
				'alternative_plugin' => 'autodescription/autodescription.php',
				'alternative_name'   => 'SEO Framework',
				'reason'             => 'SEO Framework is lightweight, have less bloat, same features and no promotionals nags like Yoast SEO.',
			],
			[
				// WP Core instead of Clean Filenames
				'discouraged_plugin' => 'sanitize-spanish-filenames/sanitize-spanish-filenames.php',
				'discouraged_name'   => 'Clean Filenames',
				'alternative_plugin' => '',
				'alternative_name'   => '',
				'reason'             => 'Feature included in WP core since version 5.6',
			],
		],
	];
	// ...to here. Paste into your wp-config.php file, and tweak it as you like.
}

/**
 * Main WP Fuerte Class
 */
class WPFuerte {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	public $pagenow;
	public $wpfuerte;

	/**
	 * Constructicon. Intentionally left public and empty, because Decepticons are bad.
	 */
	public function __construct() {}

	/**
	 * Access this plugin instance
	 */
	public static function get_instance() {
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Init the plugin
	 */
	public function init() {
		global $wpfuerte, $pagenow;

		$this->wpfuerte = $wpfuerte;
		$this->pagenow  = $pagenow;

		$this->main();
	}

	/**
	 * Main method
	 */
	protected function main() {
		/**
		 * Disable email notification for updates
		 */
		if ( true === $this->wpfuerte['config']['disable_update_email'] ) {
			add_filter( 'auto_core_update_send_email', '__return_false' );
		}

		/**
		 * Themes & Plugins auto updates
		 */
		if ( true === $this->wpfuerte['config']['autoupdate_core'] ) {
			add_filter( 'auto_update_core', '__return_true' );
		}

		if ( true === $this->wpfuerte['config']['autoupdate_plugins'] ) {
			add_filter( 'auto_update_plugin', '__return_true' );
		}

		if ( true === $this->wpfuerte['config']['autoupdate_themes'] ) {
			add_filter( 'auto_update_theme', '__return_true' );
		}

		if ( true === $this->wpfuerte['config']['autoupdate_translations'] ) {
			add_filter( 'autoupdate_translations', '__return_true' );
		}

		/**
		 * Change recovery mode email
		 */
		add_filter( 'recovery_mode_email', array( __CLASS__, 'recovery_email_address' ) );

		/**
		 * Change WP sender email address
		 */
		add_filter( 'wp_mail_from', array( __CLASS__, 'sender_email_address' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'sender_email_address' ) );

		if ( is_admin() ) {
			$current_user = wp_get_current_user();

			if ( ! in_array( strtolower( $current_user->user_email ), $this->wpfuerte['super_users'] ) || true === WPFUERTE_FORCE ) {
				// No Plugins/Theme upload/install/update/remove
				define( 'DISALLOW_FILE_MODS', true );

				// Disable Application Passwords
				if ( true === $this->wpfuerte['config']['disable_app_passwords'] ) {
					add_filter( 'wp_is_application_passwords_available', '__return_false' );
				}

				// Custom Javascript
				add_filter( 'admin_footer', array( __CLASS__, 'custom_javascript' ) );

				// Custom CSS
				//add_filter( 'admin_head', array( __CLASS__, 'custom_css' ) );

				// Remove menu items
				add_filter( 'admin_menu', array( __CLASS__, 'remove_menus' ), 9999 );

				// Disallow create/edit admin users
				if ( true === $this->wpfuerte['config']['disable_admin_create_edit'] ) {
					add_filter( 'editable_roles', array( __CLASS__, 'create_edit_role_check' ) );
				}

				// Disallowed wp-admin scripts
				if ( in_array( $this->pagenow, $this->wpfuerte['restricted_scripts'] ) && ! wp_doing_ajax() ) {
					wp_die( $this->wpfuerte['config']['not_allowed_message'] );
					return false;
				}

				// Disallowed wp-admin pages
				if ( isset( $_REQUEST['page'] ) && in_array( $_REQUEST['page'], $this->wpfuerte['restricted_pages'] ) && ! wp_doing_ajax() ) {
					wp_die( $this->wpfuerte['config']['not_allowed_message'] );
					return false;
				}

				// No user switching
				if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'switch_to_user' ) {
					wp_die( $this->wpfuerte['config']['not_allowed_message'] );
					return false;
				}

				// No protected users editing
				if ( $this->pagenow == 'user-edit.php' ) {
					if ( isset( $_REQUEST['user_id'] ) && ! empty( $_REQUEST['user_id'] ) ) {
						$user_info = get_userdata( $_REQUEST['user_id'] );

						if ( in_array( strtolower( $user_info->user_email ), $this->wpfuerte['super_users'] ) ) {
							wp_die( $this->wpfuerte['config']['not_allowed_message'] );
							return false;
						}
					}
				}

				// No protected users deletion
				if ( $this->pagenow == 'users.php' ) {
					if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' ) {

						if ( isset( $_REQUEST['users'] ) ) {
							// Single user
							foreach ($_REQUEST['users'] as $user) {
								$user_info = get_userdata( $user );

								if ( in_array( strtolower( $user_info->user_email ), $this->wpfuerte['super_users'] ) ) {
									wp_die( $this->wpfuerte['config']['not_allowed_message'] );
									return false;
								}
							}
						} elseif ( isset( $_REQUEST['user'] ) ) {
							// Batch deletion
							$user_info = get_userdata( $_REQUEST['user'] );

							if ( in_array( strtolower( $user_info->user_email ), $this->wpfuerte['super_users'] ) ) {
								wp_die( $this->wpfuerte['config']['not_allowed_message'] );
								return false;
							}
						}
					}
				}

				// No ACF editor menu
				add_filter( 'acf/settings/show_admin', '__return_false' );
			}
		} // is_admin()
	}

	/**
	 * Set WP sender email address
	 *
	 * @return string   email adress
	 */
	static function sender_email_address() {
		global $wpfuerte;

		if ( empty( $wpfuerte['config']['sender_email'] ) ) {
			$sender_email_address = 'no-reply@' . parse_url( home_url() )['host'];
		} else {
			$sender_email_address = $wpfuerte['config']['sender_email'];
		}

		return $sender_email_address;
	}

	/**
	 * Remove wp-admin menus
	 */
	static function remove_menus() {
		global $wpfuerte;

		if ( isset( $wpfuerte['restricted_scripts'] ) && ! empty( $wpfuerte['restricted_scripts'] ) ) {
			foreach ( $wpfuerte['restricted_scripts'] as $item ) {
				remove_menu_page( $item );
			}
		}

		if ( isset( $wpfuerte['removed_menus'] ) && ! empty( $wpfuerte['removed_menus'] ) ) {
			foreach ( $wpfuerte['removed_menus'] as $slug ) {
				remove_menu_page($slug);
			}
		}

		if ( isset( $wpfuerte['removed_submenus'] ) && ! empty( $wpfuerte['removed_submenus'] ) ) {
			foreach ( $wpfuerte['removed_submenus'] as $slug => $subitem ) {
				remove_submenu_page( $slug, $subitem );
			}
		}
	}

	/**
	 * Change WP recovery email adresss
	 *
	 * @return string   email adress
	 */
	static function recovery_email_address() {
		global $wpfuerte;

		if ( empty( $wpfuerte['config']['recovery_email'] ) ) {
			$recovery_email = 'dev@' . parse_url( home_url() )['host'];
		} else {
			$recovery_email = $wpfuerte['config']['recovery_email'];
		}

		$email_data['to'] = $recovery_email;

		return $email_data;
	}

	/**
	 * Check if a role can be created/edited
	 *
	 * @return array    Roles array
	 */
	static function create_edit_role_check( $roles ) {
		unset( $roles['administrator'] );

		return $roles;
	}

	/**
	 * Custom Javascript at footer
	 */
	static function custom_javascript() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function() {
			// Disable typing a custom password on new user and editing profiles
			if ( jQuery('body').is('.user-new-php, .user-edit-php, .profile-php') ) {
				jQuery('#pass1').attr('readonly', 'readonly');
			}
		});
		</script>
		<?php
	}

	/**
	 * Custom CSS at header
	 */
	static function custom_css() {
		?>
		<style type="text/css">
		</style>
		<?php
	}

	static function recommended_plugins() {
		global $wpfuerte, $pagenow;

		$show_notice            = false;
		$plugin_recommendations = [];

		if ( ! isset ( $wpfuerte['recommended_plugins'] ) || empty( $wpfuerte['recommended_plugins'] ) ) {
			return;
		}

		if ( current_user_can( 'activate_plugins' ) && ( ! wp_doing_ajax() ) ) {
			if ( is_array( $wpfuerte['recommended_plugins'] ) ) {
				foreach ( $wpfuerte['recommended_plugins'] as $plugin ) {
					if ( ! is_plugin_active( $plugin ) && ! is_plugin_active_for_network( $plugin ) ) {
						$show_notice              = true;
						$plugin_recommendations[] = $plugin;
					}
				}
			}
		}

		if ( true === $show_notice && ( $pagenow == 'plugins.php' || ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'wc-settings' ) || $pagenow == 'options-general.php' ) ) {
			//add_action( 'admin_notices', 'wpfuerte_recommended_plugins_notice' );
		}
	}
} // Class WPFuerte

add_action( 'plugins_loaded', array( WPFuerte::get_instance(), 'init' ) );
