<?php
/**
 * Author: Esteban Cuevas
 * https://github.com/TCattd/WP-Fuerte
 */

// No access outside WP
defined( 'ABSPATH' ) || die();

/**
 * To debug or test WP-Fuerte, set to true
 */
define( 'WPFUERTE_FORCE', false );

/**
 * WP-Fuerte configuration.
 * Version: 1.2.0
 *
 * Edit and set up as you like.
 */
$wpfuerte = [
	/*
	Super Users accounts, by email address.
	This users will not be affected by WP-Fuerte's restrictions.
	Add one email per item inside the array.
	*/
	'super_users' => [
		'esteban@attitude.cl',
		'esteban@actitud.xyz',
	],
	/*
	General configuration.
	*/
	'general' => [
		'access_denied_message'         => 'Access denied.', // Default access denied message.
		'recovery_email'                => '', // Recovery email. If empty, dev@wpdomain.tld will be used
		'sender_email'                  => '', // Default sender email. If empty, no-reply@wpdomain.tld will be used.
		'autoupdate_core'               => true, // Auto update WP core.
		'autoupdate_plugins'            => true, // Auto update plugins.
		'autoupdate_themes'             => true, // Auto update themes.
		'autoupdate_translations'       => true, // Auto update translations.
		'use_site_logo_login'           => true, // Use customizer logo as WP login logo.
		'disable_admin_create_edit'     => true, // Disable creation of new admin accounts by non super admins.
		'disable_app_passwords'         => true, // Disable WP application passwords.
		'force_strong_passwords'        => false, // Force strong passwords usage, make password field read-only. Users must use WP provided strong password.
		'disable_weak_passwords'        => true, // Disable ability to use a weak passwords. User can't uncheck "Confirm use of weak password". Let users type their own password, but must be somewhat secure (following WP built in recommendation).
		'disable_admin_bar_roles'       => 'subscriber,customer', // Disable admin bar for some user roles. Comma separated list of WP/WC roles. Empty string to not use this feature.
		'disable_xmlrpc'                => true, // Disable the old XML-RPC API
		'restrict_acf'                  => true, // Restrict ACF editing access
		'restrict_permalinks'           => true, // Restrict Permalink config access
	],
	/*
	Controls several WordPress notification emails, mainly targeted to site/network admin email address.
	True to keep an email enabled. False to disable an email.
	*/
	'emails' => [
		'comment_awaiting_moderation'               => false, // Site admin
		'comment_has_been_published'                => false, // Post author
		'user_reset_their_password'                 => false, // Site admin
		'user_confirm_personal_data_export_request' => false, // Site admin
		'automatic_updates'                         => false, // Site admin
		'new_user_created'                          => true,  // Site admin
		'network_new_site_created'                  => false, // Network admin
		'network_new_user_site_registered'          => false, // Network admin
		'network_new_site_activated'                => false, // Network admin
		'fatal_error'                               => true,  // Site admin OR recovery_email address
	],
	/*
	Restricted scripts by file name.
	These file names will be check against $pagenow.
	These file names will be thrown into remove_menu_page.
	*/
	'restricted_scripts' => [
		'export.php',
		'theme-editor.php',
		//'plugins.php',
		'plugin-install.php',
		'theme-install.php',
		'update.php',
	],
	/*
	Restricted pages by page URL variable.
	In wp-admin, check for admin.php?page=
	*/
	'restricted_pages' => [
		'wp_stream_settings', // Stream configuration
		'envato-market', // Envato market plugin configuration
	],
	/*
	Menus to be removed. Use menu's slug.
	These slugs will be thrown into removed_menus.
	*/
	'removed_menus' => [
		'backwpup', // BackWPup
		'check-email-status', // Check & Log Email
		'limit-login-attempts', // Limit Logins Attempts Reloaded
		'envato-market',  // Envato market plugin configuration
	],
	/*
	Submenus to be removed. Use parent-menu-slug => submenu-slug.
	These will be thrown into remove_submenu_page.
	*/
	'removed_submenus' => [
		'options-general.php' => 'mainwp_child_tab', // MainWP Child
		'tools.php'           => 'export.php', // WP export tool
	],
	/*
	Recommeded plugins.
	Format: plugin-slug-name/plugin-main-file.php
	*/
	'recommended_plugins' => [
		'imsanity/imsanity.php', // Imsanity
		'safe-svg/safe-svg.php', // Save SVG
		'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', // Limit Login Attempts Reloaded
	],
	/*
	Discouraged plugins.
	Format: check the included examples
	*/
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
