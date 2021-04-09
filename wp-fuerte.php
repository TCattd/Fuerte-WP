<?php
/**
 * Plugin Name: WP Fuerte
 * Plugin URI: https://github.com/TCattd/wp-fuerte
 * Description: Limit access to critical WordPress's areas
 * Version: 1.1.1
 * Author: Esteban Cuevas
 * Author URI: https://actitud.xyz
 *
 * Requires at least: 5.6
 * Tested up to: 5.7
 * Requires PHP: 7.2
 *
 * Text Domain: wp-fuerte
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Installation:
 *
 * Upload wp-fuerte.php to /wp-content/plugins/mu-plugins/wp-fuerte.php
 *
 * Configure your settings inside the wp-config-fuerte.php file.
 *
 * Upload wp-config-fuerte.php to your WP's root directory. This usually is where your wp-config.php file resides. WP Fuerte will not run at all if wp-config-fuerte.php file doesn't exist in that location.
 */

defined( 'ABSPATH' ) || die();

/**
 * Load WP Fuerte config file
 */
if ( file_exists( ABSPATH . 'wp-config-fuerte.php' ) ) {
	require_once ABSPATH . 'wp-config-fuerte.php';
}

/**
 * Exit if WPFUERTE_DISABLE is defined (in wp-config.php or wp-config-fuerte.php).
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
 * Main WP Fuerte Class
 */
class WPFuerte
{
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
	public function __construct()
	{
	}

	/**
	 * Access this plugin instance
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Init the plugin
	 */
	public function init()
	{
		global $wpfuerte, $pagenow;

		$this->wpfuerte = $wpfuerte;
		$this->pagenow  = $pagenow;

		$this->main();
	}

	/**
	 * Main method
	 */
	protected function main()
	{
		/**
		 * Themes & Plugins auto updates
		 */
		if ( true === $this->wpfuerte['general']['autoupdate_core'] ) {
			add_filter( 'auto_update_core', '__return_true', 9999 );
			add_filter( 'allow_minor_auto_core_updates', '__return_true', 9999 );
			add_filter( 'allow_major_auto_core_updates', '__return_true', 9999 );
		}

		if ( true === $this->wpfuerte['general']['autoupdate_plugins'] ) {
			add_filter( 'auto_update_plugin', '__return_true', 9999 );
		}

		if ( true === $this->wpfuerte['general']['autoupdate_themes'] ) {
			add_filter( 'auto_update_theme', '__return_true', 9999 );
		}

		if ( true === $this->wpfuerte['general']['autoupdate_translations'] ) {
			add_filter( 'autoupdate_translations', '__return_true', 9999 );
		}

		/**
		 * Change recovery mode email
		 */
		add_filter( 'recovery_mode_email', array(__CLASS__, 'recovery_email_address'), 9999 );

		/**
		 * Change WP sender email address
		 */
		add_filter( 'wp_mail_from', array(__CLASS__, 'sender_email_address'), 9999 );
		add_filter( 'wp_mail_from_name', array(__CLASS__, 'sender_email_address'), 9999 );

		/**
		 * Disable WP notification emails
		 */
		if ( false === $this->wpfuerte['emails']['comment_awaiting_moderation'] ) {
			add_filter( 'notify_moderator', '__return_false', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['comment_has_been_published'] ) {
			add_filter( 'notify_post_author', '__return_false', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['user_reset_their_password'] ) {
			remove_action( 'after_password_reset', 'wp_password_change_notification', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['user_confirm_personal_data_export_request'] ) {
			remove_action( 'user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['automatic_updates'] ) {
			add_filter( 'auto_core_update_send_email', '__return_false', 9999 );
			add_filter( 'send_core_update_notification_email', '__return_false', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['new_user_created'] ) {
			remove_action( 'register_new_user', 'wp_send_new_user_notifications', 9999 );
			remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 9999 );
			remove_action( 'network_site_new_created_user', 'wp_send_new_user_notifications', 9999 );
			remove_action( 'network_site_users_created_user', 'wp_send_new_user_notifications', 9999 );
			remove_action( 'network_user_new_created_user', 'wp_send_new_user_notifications', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['network_new_site_created'] ) {
			add_filter( 'send_new_site_email', '__return_false', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['network_new_user_site_registered'] ) {
			add_filter( 'wpmu_signup_blog_notification', '__return_false', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['network_new_site_activated'] ) {
			remove_action( 'wp_initialize_site', 'newblog_notify_siteadmin', 9999 );
		}

		if ( false === $this->wpfuerte['emails']['fatal_error'] ) {
			define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
		}

		$current_user = wp_get_current_user();

		// Check if current user should be affected by WP Fuerte
		if ( ! in_array( strtolower( $current_user->user_email ), $this->wpfuerte['super_users'] ) || true === WPFUERTE_FORCE ) {
			// Everywhere tweaks (wp-admin or not)
			// Custom Javascript
			add_filter( 'admin_footer', array(__CLASS__, 'custom_javascript'), 9999 );
			add_filter( 'login_head', array(__CLASS__, 'custom_javascript'), 9999 );

			// Custom CSS
			add_filter( 'admin_head', array(__CLASS__, 'custom_css'), 9999 );
			add_filter( 'login_head', array(__CLASS__, 'custom_css'), 9999 );

			// wp-admin only tweaks
			if ( is_admin() ) {
				// No Plugins/Theme upload/install/update/remove
				define( 'DISALLOW_FILE_MODS', true );

				// Disable Application Passwords
				if ( true === $this->wpfuerte['general']['disable_app_passwords'] ) {
					add_filter( 'wp_is_application_passwords_available', '__return_false', 9999 );
				}

				// Remove menu items
				add_filter( 'admin_menu', array(__CLASS__, 'remove_menus'), 9999 );

				// Disallow create/edit admin users
				if ( true === $this->wpfuerte['general']['disable_admin_create_edit'] ) {
					add_filter( 'editable_roles', array(__CLASS__, 'create_edit_role_check'), 9999 );
				}

				// Disallowed wp-admin scripts
				if ( in_array( $this->pagenow, $this->wpfuerte['restricted_scripts'] ) && ! wp_doing_ajax() ) {
					wp_die( $this->wpfuerte['general']['access_denied_message'] );
					return false;
				}

				// Disallowed wp-admin pages
				if (isset($_REQUEST['page']) && in_array($_REQUEST['page'], $this->wpfuerte['restricted_pages']) && !wp_doing_ajax()) {
					wp_die($this->wpfuerte['general']['access_denied_message']);
					return false;
				}

				// No user switching
				if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'switch_to_user') {
					wp_die($this->wpfuerte['general']['access_denied_message']);
					return false;
				}

				// No protected users editing
				if ($this->pagenow == 'user-edit.php') {
					if (isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id'])) {
						$user_info = get_userdata($_REQUEST['user_id']);

						if (in_array(strtolower($user_info->user_email), $this->wpfuerte['super_users'])) {
							wp_die($this->wpfuerte['general']['access_denied_message']);
							return false;
						}
					}
				}

				// No protected users deletion
				if ( $this->pagenow == 'users.php' ) {
					if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' ) {

						if ( isset( $_REQUEST['users'] ) ) {
							// Single user
							foreach ( $_REQUEST['users'] as $user ) {
								$user_info = get_userdata( $user );

								if ( in_array( strtolower( $user_info->user_email ), $this->wpfuerte['super_users'] ) ) {
									wp_die( $this->wpfuerte['general']['access_denied_message'] );
									return false;
								}
							}
						} elseif ( isset( $_REQUEST['user'] ) ) {
							// Batch deletion
							$user_info = get_userdata( $_REQUEST['user'] );

							if ( in_array( strtolower( $user_info->user_email ), $this->wpfuerte['super_users'] ) ) {
								wp_die( $this->wpfuerte['general']['access_denied_message'] );
								return false;
							}
						}
					}
				}

				// No ACF editor menu
				add_filter( 'acf/settings/show_admin', '__return_false', 9999 );
			} // is_admin()

			// Outside wp-admin tweaks
			if ( ! is_admin() ) {
				// Disable admin bar for certain roles
				if ( ! empty( $this->wpfuerte['general']['disable_admin_bar_roles'] ) ) {
					$roles_array = [];

					// Create and array of roles
					if ( stripos( $this->wpfuerte['general']['disable_admin_bar_roles'], ',' ) !== false ) {
						$roles_array = explode( ',', $this->wpfuerte['general']['disable_admin_bar_roles'] );
					} else {
						$roles_array[] = $this->wpfuerte['general']['disable_admin_bar_roles'];
					}

					// Loop and disable if user has a defined role
					foreach ( $roles_array as $role ) {
						if ( true === $this->has_role( $role ) ) {
							add_filter( 'show_admin_bar', '__return_false', 9999 );
						}
					}
				}
			} // !is_admin()
		} // user affected by WP FUERTE
	}

	/**
	 * Set WP sender email address
	 *
	 * @return string   email adress
	 */
	static function sender_email_address()
	{
		global $wpfuerte;

		if ( empty( $wpfuerte['general']['sender_email'] ) ) {
			$sender_email_address = 'no-reply@' . parse_url( home_url() )['host'];
		} else {
			$sender_email_address = $wpfuerte['general']['sender_email'];
		}

		return $sender_email_address;
	}

	/**
	 * Remove wp-admin menus
	 */
	static function remove_menus()
	{
		global $wpfuerte;

		if ( isset( $wpfuerte['restricted_scripts']) && ! empty( $wpfuerte['restricted_scripts'] ) ) {
			foreach ( $wpfuerte['restricted_scripts'] as $item ) {
				remove_menu_page( $item );
			}
		}

		if ( isset( $wpfuerte['removed_menus'] ) && ! empty( $wpfuerte['removed_menus'] ) ) {
			foreach ( $wpfuerte['removed_menus'] as $slug ) {
				remove_menu_page( $slug );
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
	static function recovery_email_address()
	{
		global $wpfuerte;

		if ( empty( $wpfuerte['general']['recovery_email'] ) ) {
			$recovery_email = 'dev@' . parse_url( home_url() )['host'];
		} else {
			$recovery_email = $wpfuerte['general']['recovery_email'];
		}

		$email_data['to'] = $recovery_email;

		return $email_data;
	}

	/**
	 * Check if a role can be created/edited
	 *
	 * @return array    Roles array
	 */
	static function create_edit_role_check( $roles )
	{
		unset( $roles['administrator'] );

		return $roles;
	}

	/**
	 * Check current user role
	 * https://wordpress.org/support/article/roles-and-capabilities/
	 *
	 * @return bool    True if it has the role
	 */
	static function has_role( $role = 'subscriber' )
	{
		$user = wp_get_current_user();

		if ( in_array( $role, (array) $user->roles ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Custom Javascript at footer
	 */
	static function custom_javascript()
	{
		global $wpfuerte;
	?>
		<script type="text/javascript">
			document.addEventListener("DOMContentLoaded", function() {
			<?php
			// Disable typing a custom password (new user, profile edit, lost password).
			// Needed outside wp-admin, because reset password screen
			if ( true === $wpfuerte['general']['force_strong_passwords'] ) :
			?>
				if (document.body.classList.contains('user-new-php') ||
					document.body.classList.contains('user-edit-php') ||
					document.body.classList.contains('login') ||
					document.body.classList.contains('profile-php')) {
					document.getElementById('pass1').setAttribute('readonly', 'readonly');
				}
			<?php
			endif;
			?>
			});
		</script>
	<?php
	}

	/**
	 * Custom CSS at header
	 */
	static function custom_css()
	{
		global $wpfuerte;
	?>
		<style type="text/css">
		<?php
		// Hides "Confirm use of weak password" checkbox on weak password, forcing a medium one at the very minimum.
		// Needed outside wp-admin, because reset password screen
		if ( true === $wpfuerte['general']['disable_weak_passwords'] ) :
		?>
			.pw-weak { display: none !important; }
		<?php
		endif;
		?>
		</style>
	<?php
	}

	static function recommended_plugins()
	{
		global $wpfuerte, $pagenow;

		$show_notice            = false;
		$plugin_recommendations = [];

		if ( ! isset( $wpfuerte['recommended_plugins'] ) || empty( $wpfuerte['recommended_plugins'] ) ) {
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

		if ( true === $show_notice && ( $pagenow == 'plugins.php' || ( isset($_REQUEST['page'] ) && $_REQUEST['page'] == 'wc-settings' ) || $pagenow == 'options-general.php' ) ) {
			//add_action( 'admin_notices', 'wpfuerte_recommended_plugins_notice' );
		}
	}
} // Class WPFuerte

global $wpfuerte;

if ( isset( $wpfuerte ) && ! empty( $wpfuerte ) ) {
	add_action( 'plugins_loaded', array( WPFuerte::get_instance(), 'init' ) );
}
