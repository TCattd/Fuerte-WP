<?php
/**
 * Author: Esteban Cuevas
 * https://github.com/TCattd/wp-fuerte
 */
defined( 'ABSPATH' ) || die();

/**
 * WP Fuerte configuration.
 *
 * Set up as you like.
 */
$wpfuerte = [
	// General configuration.
	'config' => [
		'version'                   => '1.1.0', // WP Fuerte's version.
		'access_denied_message'     => 'Access denied.', // Default access denied message.
		'recovery_email'            => '', // Recovery email. If empty, dev@wpdomain.tld will be used
		'sender_email'              => '', // Default sender email. If empty, no-reply@wpdomain.tld will be used.
		'autoupdate_core'           => true, // Auto update WP core.
		'autoupdate_plugins'        => true, // Auto update plugins.
		'autoupdate_themes'         => true, // Auto update themes.
		'autoupdate_translations'   => true, // Auto update translations.
		'disable_update_email'      => true, // Disable email when WP auto update.
		'disable_admin_create_edit' => true, // Disable creation of new admin accounts by non super admins.
		'disable_app_passwords'     => true, // Disable WP application passwords.
		'force_strong_passwords'    => true, // Force strong passwords usage, make password field read-only.
		'disable_weak_passwords'    => true, // Disable ability to use a weak passwords. User can't uncheck "Confirm use of weak password".
	],
	// Super Users accounts by email address. They will become inmune from WP Fuerte's restrictions.
	'super_users'    => [
		'esteban@attitude.cl',
	],
	// Restricted scripts by file name.
	// These file names will be check against $pagenow.
	// These file names will be thrown into remove_menu_page.
	'restricted_scripts' => [
		'export.php',
		'theme-editor.php',
		'plugins.php',
		'plugin-install.php',
		'theme-install.php',
		'update.php',
	],
	// Restricted pages by page URL variable.
	// In wp-admin, check for admin.php?page=
	'restricted_pages' => [
		'wp_stream_settings', // Stream configuration
		'envato-market', // Envato market plugin configuration
	],
	// Menus to be removed. Use menu's slug.
	// These slugs will be thrown into removed_menus.
	'removed_menus' => [
		'backwpup', // BackWPup
		'check-email-status', // Check & Log Email
		'limit-login-attempts', // Limit Logins Attempts Reloaded
		'envato-market',  // Envato market plugin configuration
	],
	// Submenus to be removed. Use parent-menu-slug => submenu-slug.
	// These will be thrown into remove_submenu_page.
	'removed_submenus' => [
		'options-general.php' => 'mainwp_child_tab', // MainWP Child
		'tools.php'           => 'export.php', // WP export tool
	],
	// Recommeded plugins.
	// Format: plugin-slug-name/plugin-main-file.php
	'recommended_plugins' => [
		'imsanity/imsanity.php', // Imsanity
		'safe-svg/safe-svg.php', // Save SVG
		'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', // Limit Login Attempts Reloaded
	],
	// Discouraged plugins.
	// Format: check the included examples
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
