<?php

/**
 * Fuerte-WP Helpers
 *
 * @link       https://actitud.xyz
 * @since      1.3.0
 *
 * @package    Fuerte_Wp
 * @subpackage Fuerte_Wp/includes
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Get WordPress admin users
 */
function fuertewp_get_admin_users()
{
	$users  = get_users(array('role__in' => array('administrator')));
	$admins = [];

	foreach ($users as $user) {
		$admins[$user->user_email] = $user->user_login . '[' . $user->user_email . ']';
	}

	return $admins;
}

/**
 * Get a list of WordPress roles
 */
function fuertewp_get_wp_roles()
{
	global $wp_roles;

	$roles          = $wp_roles->roles;
	// https://developer.wordpress.org/reference/hooks/editable_roles/
	$editable_roles = apply_filters('editable_roles', $roles);

	// We only need the role slug (id) and name
	$returned_roles = [];

	foreach ($editable_roles as $id => $role) {
		$returned_roles[$id] = $role['name'];
	}

	return $returned_roles;
}

/**
 * Check if an option exists
 *
 * https://core.trac.wordpress.org/ticket/51699
 */
function fuertewp_option_exists($option_name, $site_wide = false)
{
	global $wpdb;

	return $wpdb->query($wpdb->prepare("SELECT * FROM " . ($site_wide ? $wpdb->base_prefix : $wpdb->prefix) . "options WHERE option_name ='%s' LIMIT 1", $option_name));
}

/**
 * Customizer disable Additional CSS editor.
 */
function fuertewp_customizer_remove_css_editor($wp_customize)
{
	$wp_customize->remove_section('custom_css');
}

/**
 * REST API restrict access to logged in users only.
 * https://developer.wordpress.org/rest-api/frequently-asked-questions/#require-authentication-for-all-requests
 */
function fuertewp_restapi_loggedin_only($result)
{
	// If a previous authentication check was applied,
	// pass that result along without modification.
	if (true === $result || is_wp_error($result)) {
		return $result;
	}

	// Exclude JWT auth token endpoints URLs
	if (false !== stripos($_SERVER['REQUEST_URI'], 'jwt-auth')) {
		return $result;
	}

	// No authentication has been performed yet.
	// Return an error if user is not logged in.
	if (!is_user_logged_in()) {
		return new WP_Error(
			'rest_not_logged_in',
			__('You are not currently logged in.'),
			array('status' => 401)
		);
	}

	// Our custom authentication check should have no effect
	// on logged-in requests
	return $result;
}

// Define the new admin URL
define('FUERTEWP_ADMIN_DIR', 'admin');

// Change the login URL
function fuertewp_custom_login_url($login_url)
{
	return str_replace('wp-login.php', FUERTEWP_ADMIN_DIR . '.php', $login_url);
}
//add_filter('login_url', 'fuertewp_custom_login_url');

// Hide the wp-admin directory
function fuertewp_hide_wp_admin()
{
	// If url contains /wp-admin/
	if (!is_user_logged_in() && str_contains($_SERVER['REQUEST_URI'], '/wp-admin/')) {
		// Throw a 404 error
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
		include get_query_template('404');
		//wp_safe_redirect('/');
		//exit();
	}
}
//add_action('init', 'fuertewp_hide_wp_admin');

// Replace the old admin URL with the new one
function fuertewp_change_wp_admin_dashboard_url_site_url($url, $path, $orig_scheme)
{
	//$old = array("/(wp-admin)/");
	//$admin_dir = FUERTEWP_ADMIN_DIR;
	//$new = array($admin_dir);
	//return preg_replace($old, $new, $url, 1);

	// In $url, replace wp-admin with the value of FUERTEWP_ADMIN_DIR
	return str_ireplace('wp-admin', FUERTEWP_ADMIN_DIR, $url);
}
//add_filter('site_url', 'fuertewp_change_wp_admin_dashboard_url_site_url', 10, 3);

// Add a rewrite rule to redirect the new admin URL to the original one
function fuertewp_change_wp_admin_dashboard_url_mod_rewrite_rules($rules)
{
	$new_rules = "RewriteRule ^" . FUERTEWP_ADMIN_DIR . "/(.*) wp-admin/$1?%{QUERY_STRING} [L]\n";

	return $new_rules . $rules;
}
//add_filter('mod_rewrite_rules', 'fuertewp_change_wp_admin_dashboard_url_mod_rewrite_rules');

add_filter('site_url',  'wpadmin_filter', 10, 3);

function wpadmin_filter($url, $path, $orig_scheme)
{
	$request_url = $_SERVER['REQUEST_URI'];

	$check_wp_admin = stristr($request_url, 'wp-admin');
	if ($check_wp_admin) {
		wp_redirect(home_url('404'), 302);
		exit();
	}

	$old  = array("/(wp-admin)/");
	$admin_dir = FUERTEWP_ADMIN_DIR;
	$new  = array($admin_dir);

	return preg_replace($old, $new, $url, 1);
}

add_rewrite_rule('^' . FUERTEWP_ADMIN_DIR . '/(.*)', 'wp-admin/$1?%{QUERY_STRING}');

// Flush the rewrite rules
flush_rewrite_rules();
