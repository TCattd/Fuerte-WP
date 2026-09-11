<?php

/**
 * Conditional Hook Manager for Fuerte-WP.
 *
 * This class provides intelligent hook registration based on configuration
 * and context to minimize unnecessary hook overhead.
 *
 * @since 1.7.0
 */
if (!defined('ABSPATH')) {
    exit;
}

class Fuerte_Wp_Hook_Manager
{
    /**
     * Registered hooks tracking.
     *
     * @var array
     */
    private static $registered_hooks = [];

    /**
     * Configuration cache.
     *
     * @var array|null
     */
    private static $config = null;

    /**
     * Current request context.
     *
     * @var array
     */
    private static $context = [];

    /**
     * Initialize the hook manager.
     *
     * @since 1.7.0
     */
    public static function init()
    {
        self::determine_context();
        self::load_config();

        // Register core hooks that are always needed
        self::register_core_hooks();

        // Register conditional hooks based on context and config
        self::register_conditional_hooks();
    }

    /**
     * Determine current request context.
     *
     * @since 1.7.0
     */
    private static function determine_context()
    {
        self::$context = [
            'is_admin' => is_admin(),
            'is_login' => self::is_login_page(),
            'is_register' => self::is_registration_page(),
            'is_ajax' => wp_doing_ajax(),
            'is_rest' => self::is_rest_request(),
            'is_cron' => wp_doing_cron(),
            'is_frontend' => !is_admin() && !self::is_login_page() && !self::is_registration_page(),
            'user_can_manage' => current_user_can('manage_options'),
            'user_logged_in' => is_user_logged_in(),
        ];
    }

    /**
     * Load configuration for hook decisions.
     *
     * @since 1.7.0
     */
    private static function load_config()
    {
        if (self::$config === null) {
            // Load from simple configuration
            if (class_exists('Fuerte_Wp_Config')) {
                self::$config = Fuerte_Wp_Config::get_config();
            } else {
                // Fallback to basic defaults
                self::$config = [
                    'restrictions' => [],
                    'emails' => [],
                    'tweaks' => [],
                    'login_security' => [],
                ];
            }
        }
    }

    /**
     * Register core hooks that are always needed.
     *
     * @since 1.7.0
     */
    private static function register_core_hooks()
    {
        // Plugin initialization hook
        self::add_hook('init', 'Fuerte_Wp_Enforcer::get_instance', 'run', 10, false);

        // Note: Carbon Fields theme options saved hook is handled by main plugin class
        // with proper instance management to avoid static method call errors.
    }

    /**
     * Register conditional hooks based on context and configuration.
     *
     * @since 1.7.0
     */
    private static function register_conditional_hooks()
    {
        // Login/Registration context hooks
        if (self::$context['is_login'] || self::$context['is_register']) {
            self::register_login_hooks();
        }

        // Admin context hooks
        if (self::$context['is_admin']) {
            self::register_admin_hooks();
        }

        // Frontend context hooks
        if (self::$context['is_frontend']) {
            self::register_frontend_hooks();
        }

        // AJAX context hooks
        if (self::$context['is_ajax']) {
            self::register_ajax_hooks();
        }

        // REST API context hooks
        if (self::$context['is_rest']) {
            self::register_rest_hooks();
        }

        // Security-related hooks (always needed if enabled)
        self::register_security_hooks();

        // Email hooks (only if email features are enabled)
        if (self::should_register_email_hooks()) {
            self::register_email_hooks();
        }

        // Auto-update hooks (only if auto-updates are enabled)
        if (self::should_register_autoupdate_hooks()) {
            self::register_autoupdate_hooks();
        }
    }

    /**
     * Register login-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_login_hooks()
    {
        // Login security hooks are handled by Fuerte_Wp_Login_Manager directly
        // to avoid conflicts and ensure proper instance method calls

        // Login URL hiding hooks
        if (self::is_login_url_hiding_enabled()) {
            // Use singleton instance for Login URL Hider
            $login_url_hider = Fuerte_Wp_Login_URL_Hider::get_instance();

            if ($login_url_hider) {
                self::add_hook('parse_request', [$login_url_hider, 'handle_parse_request'], '', 1, true);
                self::add_hook('login_init', [$login_url_hider, 'handle_login_init'], '', 10, true);
            }
        }

        // UI tweaks for login page
        if (isset(self::$config['tweaks']['use_site_logo_login']) && self::$config['tweaks']['use_site_logo_login']) {
            self::add_hook('login_head', 'Fuerte_Wp_Enforcer', 'custom_login_logo', 10, true);
            self::add_hook('login_headerurl', 'Fuerte_Wp_Enforcer', 'custom_login_url', 10, true);
            self::add_hook('login_headertext', 'Fuerte_Wp_Enforcer', 'custom_login_title', 10, true);
        }
    }

    /**
     * Register admin-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_admin_hooks()
    {
        // Capture the full admin-bar node set BEFORE removal so the settings
        // multiselect can list removable nodes. Priority 900 < removal 999:
        // WP fires ascending, so capture precedes removal. Always registered
        // (the callback self-gates to the settings page for super users).
        // See docs/MENU_VISIBILITY_PLAN.md defect D1.
        self::add_hook('admin_bar_menu', 'Fuerte_Wp_Enforcer', 'capture_adminbar_nodes', 900, true);

        // Menu and admin bar restrictions - skip for super users
        if (!Fuerte_Wp_Helper::bypasses_restrictions() && self::should_register_restriction_hooks()) {
            self::add_hook('admin_menu', 'Fuerte_Wp_Enforcer', 'remove_menus', 999, true);
            self::add_hook('admin_bar_menu', 'Fuerte_Wp_Enforcer', 'remove_adminbar_menus', 999, true);
        }

        // Plugin/theme editor restrictions are handled by early exit conditions in the enforcer
        // No need for separate hook registrations to remove edit links

        // Admin UI tweaks
        if (isset(self::$config['tweaks']['use_site_logo_login']) && self::$config['tweaks']['use_site_logo_login']) {
            self::add_hook('admin_footer', 'Fuerte_Wp_Enforcer', 'custom_javascript', 10, true);
        }

        // Disable comments admin hooks
        if (isset(self::$config['restrictions']['disable_comments']) && self::$config['restrictions']['disable_comments']) {
            self::register_disable_comments_admin_hooks();
        }
    }

    /**
     * Register frontend-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_frontend_hooks()
    {
        // REST API restrictions
        if (isset(self::$config['restrictions']['restapi_loggedin_only']) && self::$config['restrictions']['restapi_loggedin_only']) {
            self::add_hook('rest_authentication_errors', 'Fuerte_Wp_Enforcer', 'restrict_rest_api', 10, true);
        }

        // XML-RPC restrictions
        if (isset(self::$config['restrictions']['disable_xmlrpc']) && self::$config['restrictions']['disable_xmlrpc']) {
            self::add_hook('xmlrpc_enabled', '__return_false');
        }

        // Application passwords
        if (isset(self::$config['restrictions']['restapi_disable_app_passwords']) && self::$config['restrictions']['restapi_disable_app_passwords']) {
            self::add_hook('wp_is_application_passwords_available', '__return_false');
        }

        // Disable comments frontend hooks
        if (isset(self::$config['restrictions']['disable_comments']) && self::$config['restrictions']['disable_comments']) {
            self::register_disable_comments_frontend_hooks();
        }
    }

    /**
     * Register AJAX-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_ajax_hooks()
    {
        if (self::$context['user_can_manage']) {
            $ajax_hooks = [
                'fuertewp_clear_login_logs' => 'ajax_clear_login_logs',
                'fuertewp_reset_lockouts' => 'ajax_reset_lockouts',
                'fuertewp_export_attempts' => 'ajax_export_attempts',
                'fuertewp_export_ips' => 'ajax_export_ips',
                'fuertewp_add_ip' => 'ajax_add_ip',
                'fuertewp_remove_ip' => 'ajax_remove_ip',
                'fuertewp_get_login_logs' => 'ajax_get_login_logs',
                'fuertewp_unlock_ip' => 'ajax_unlock_ip',
                'fuertewp_unblock_single' => 'ajax_unblock_single',
            ];

            foreach ($ajax_hooks as $action => $method) {
                self::add_hook("wp_ajax_{$action}", 'Fuerte_Wp_Enforcer', $method, 10, true);
            }
        }
    }

    /**
     * Register REST API related hooks.
     *
     * @since 1.7.0
     */
    private static function register_rest_hooks()
    {
        if (isset(self::$config['restrictions']['restapi_loggedin_only']) && self::$config['restrictions']['restapi_loggedin_only']) {
            self::add_hook('rest_authentication_errors', 'Fuerte_Wp_Enforcer', 'restrict_rest_api', 10, true);
        }

        // Block REST API comment endpoints
        if (isset(self::$config['restrictions']['disable_comments']) && self::$config['restrictions']['disable_comments']) {
            self::add_hook('rest_pre_dispatch', 'Fuerte_Wp_Enforcer', 'disable_rest_comments', 10, true, 3);
        }
    }

    /**
     * Register security-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_security_hooks()
    {
        // Super users bypass file editing and installation restrictions
        if (Fuerte_Wp_Helper::bypasses_restrictions()) {
            return;
        }

        // File editing restrictions (DISALLOW_FILE_EDIT)
        $disable_theme_editor = isset(self::$config['restrictions']['disable_theme_editor']) && self::$config['restrictions']['disable_theme_editor'];
        $disable_plugin_editor = isset(self::$config['restrictions']['disable_plugin_editor']) && self::$config['restrictions']['disable_plugin_editor'];

        if (($disable_theme_editor || $disable_plugin_editor) && !defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // Plugin/theme installation restrictions
        if (isset(self::$config['restrictions']['disable_plugin_install']) && self::$config['restrictions']['disable_plugin_install']) {
            self::add_hook('install_plugins', 'Fuerte_Wp_Enforcer', 'restrict_plugin_installation', 10, true);
        }

        if (isset(self::$config['restrictions']['disable_theme_install']) && self::$config['restrictions']['disable_theme_install']) {
            self::add_hook('install_themes', 'Fuerte_Wp_Enforcer', 'restrict_theme_installation', 10, true);
        }
    }

    /**
     * Register email-related hooks.
     *
     * @since 1.7.0
     */
    private static function register_email_hooks()
    {
        $email_settings = self::$config['emails'] ?? [];

        // Fatal error (recovery mode) email: redirect to the configured
        // address, or suppress entirely when the toggle is off.
        if (!isset($email_settings['fatal_error']) || $email_settings['fatal_error']) {
            self::add_hook('recovery_mode_email', 'Fuerte_Wp_Enforcer', 'recovery_email_address', 10, true, 1);
        } else {
            self::add_hook('recovery_mode_email', 'Fuerte_Wp_Enforcer', 'suppress_email_notification', 10, true, 1);
        }

        // Sender email customization
        if (isset(self::$config['general']['sender_email_enable']) && self::$config['general']['sender_email_enable']) {
            self::add_hook('wp_mail_from', 'Fuerte_Wp_Enforcer', 'sender_email_address', 10, true);
            self::add_hook('wp_mail_from_name', 'Fuerte_Wp_Enforcer', 'sender_email_name', 10, true);
        }

        // Email notification filters. Keys = emails.* toggles; values = real
        // WordPress core hooks, verified against vendored core 7.0.
        // Unchecked toggle = suppress via the hook; checked = send (no hook).
        $email_hooks = [
            'automatic_updates' => [
                'auto_core_update_send_email',
                'auto_plugin_update_send_email',
                'auto_theme_update_send_email',
            ],
            'comment_awaiting_moderation' => ['notify_moderator'],
            'comment_has_been_published' => ['notify_post_author'],
            'user_reset_their_password' => ['wp_password_change_notification_email'],
            'user_confirm_personal_data_export_request' => ['user_request_confirmed_email_to'],
            'new_user_created' => ['wp_new_user_notification_email_admin'],
            'network_new_site_created' => ['send_new_site_email'],
            'network_new_site_activated' => ['wpmu_welcome_notification'],
        ];

        // Application password created (ships in WP 7.2; core.trac changeset
        // 63582, ticket #63927). Critical security notice. Wire only when
        // core has registered the notifier on wp_create_application_password
        // in default-filters.php: has_action() probes core's own wiring, so
        // a renamed or reshipped feature keeps us inert instead of wiring a
        // disable filter nobody applies.
        if (has_action('wp_create_application_password', 'wp_application_password_created_notification')) {
            $email_hooks['application_password_created'] = ['wp_send_application_password_created_email'];
        }

        foreach ($email_hooks as $setting => $filters) {
            if (isset($email_settings[$setting]) && !$email_settings[$setting]) {
                foreach ($filters as $filter) {
                    self::add_hook($filter, 'Fuerte_Wp_Enforcer', 'suppress_email_notification', 10, true, 1);
                }
            }
        }

        // Network user registration has no dedicated core disable filter:
        // newuser_notify_siteadmin() gates on the registrationnotification
        // site option. Short-circuit that option read instead. Core ties
        // newblog_notify_siteadmin() (self-service signup notice) to the
        // same option, so this toggle also silences that email.
        if (isset($email_settings['network_new_user_site_registered']) && !$email_settings['network_new_user_site_registered']) {
            self::add_hook('pre_site_option_registrationnotification', 'Fuerte_Wp_Enforcer', 'disable_site_registration_notifications', 10, true, 0);
        }
    }

    /**
     * Register auto-update related hooks.
     *
     * @since 1.7.0
     */
    private static function register_autoupdate_hooks()
    {
        // Schedule auto-update cron
        if (isset(self::$config['general']['autoupdate_core']) && self::$config['general']['autoupdate_core']) {
            self::add_hook('init', 'Fuerte_Wp_Auto_Update_Manager', 'schedule_updates', 10, true);
        }
    }

    /**
     * Register admin hooks for disabling comments.
     *
     * @since 1.9.5
     */
    private static function register_disable_comments_admin_hooks()
    {
        // Remove comments column from post/page list tables
        self::add_hook('manage_posts_columns', 'Fuerte_Wp_Enforcer', 'disable_comments_list_column', 999, true);
        self::add_hook('manage_pages_columns', 'Fuerte_Wp_Enforcer', 'disable_comments_list_column', 999, true);

        // Remove comments menu page
        self::add_hook('admin_menu', 'Fuerte_Wp_Enforcer', 'disable_comments_remove_menu', 999, true);

        // Remove comments admin bar node (admin context)
        self::add_hook('admin_bar_menu', 'Fuerte_Wp_Enforcer', 'disable_comments_admin_bar', 999, true);

        // Remove dashboard recent comments widget
        self::add_hook('wp_dashboard_setup', 'Fuerte_Wp_Enforcer', 'disable_comments_remove_dashboard', 10, true);

        // Remove post type support for comments and trackbacks
        self::add_hook('admin_init', 'Fuerte_Wp_Enforcer', 'disable_comments_post_type_support', 999, true);
    }

    /**
     * Register frontend hooks for disabling comments.
     *
     * @since 1.9.5
     */
    private static function register_disable_comments_frontend_hooks()
    {
        // Close comments and pings
        self::add_hook('comments_open', 'Fuerte_Wp_Enforcer', 'disable_comments_status', 20, true, 2);
        self::add_hook('pings_open', 'Fuerte_Wp_Enforcer', 'disable_pings_status', 20, true, 2);

        // Empty existing comments and zero the count
        self::add_hook('comments_array', 'Fuerte_Wp_Enforcer', 'disable_comments_array', 20, true, 2);
        self::add_hook('get_comments_number', 'Fuerte_Wp_Enforcer', 'disable_comments_number', 20, true, 2);

        // Remove X-Pingback header
        self::add_hook('wp_headers', 'Fuerte_Wp_Enforcer', 'remove_pingback_header', 10, true);

        // Block comment feeds
        self::add_hook('template_redirect', 'Fuerte_Wp_Enforcer', 'disable_comment_feed', 9, true);

        // Remove comment-reply script, comment feed links, blank template
        self::add_hook('template_redirect', 'Fuerte_Wp_Enforcer', 'disable_comments_template_hooks', 10, true);

        // Remove post type support on frontend too (themes check this)
        self::add_hook('init', 'Fuerte_Wp_Enforcer', 'disable_comments_post_type_support', 999, true);

        // Remove comments admin bar node (visible on frontend for logged-in users)
        self::add_hook('admin_bar_menu', 'Fuerte_Wp_Enforcer', 'disable_comments_admin_bar', 999, true);

        // Block REST API comment endpoints
        self::add_hook('rest_pre_dispatch', 'Fuerte_Wp_Enforcer', 'disable_rest_comments', 10, true, 3);

        // Block XML-RPC comments
        self::add_hook('xmlrpc_methods', 'Fuerte_Wp_Enforcer', 'disable_xmlrpc_comments', 10, true);
    }

    /**
     * Add hook with tracking.
     *
     * @since 1.7.0
     *
     * @param string $hook Hook name
     * @param string $callback Class or function name
     * @param string $method Method name (if callback is a class)
     * @param int $priority Priority
     * @param bool $conditional Whether this is a conditional hook
     * @param int $accepted_args Number of accepted arguments
     */
    private static function add_hook($hook, $callback, $method = '', $priority = 10, $conditional = false, $accepted_args = 1)
    {
        // Create a unique hook key that works with array callbacks
        if (is_array($callback)) {
            $callback_key = get_class($callback[0]) . '::' . $callback[1];
        } else {
            $callback_key = $callback;
        }
        $hook_key = "{$hook}:{$callback_key}:{$method}:{$priority}";

        // Avoid duplicate hook registration
        if (isset(self::$registered_hooks[$hook_key])) {
            return;
        }

        // Prepare callback
        if (is_array($callback)) {
            // Already an instance callback array [object, method]
            $final_callback = $callback;
        } elseif (str_contains($callback, '::')) {
            // Static method call
            $final_callback = $callback;
        } elseif (class_exists($callback)) {
            // Instance method call
            $final_callback = [$callback, $method];
        } else {
            // Function call
            $final_callback = $callback;
        }

        // Register the hook
        if (str_starts_with($hook, 'filter_') ||
            str_starts_with($hook, 'wp_') ||
            in_array($hook, [
            'authenticate', 'wp_authenticate_user', 'registration_errors',
            'rest_authentication_errors', 'xmlrpc_enabled', 'xmlrpc_methods',
            'wp_is_application_passwords_available', 'editable_roles',
            'recovery_mode_email', 'wp_mail_from', 'wp_mail_from_name',
            'comments_open', 'pings_open', 'comments_array',
            'get_comments_number', 'wp_headers', 'rest_pre_dispatch',
            'manage_posts_columns', 'manage_pages_columns',
            'auto_core_update_send_email', 'auto_plugin_update_send_email',
            'auto_theme_update_send_email', 'notify_moderator', 'notify_post_author',
            'send_new_site_email', 'wpmu_welcome_notification',
            'user_request_confirmed_email_to', 'pre_site_option_registrationnotification',
        ])) {
            add_filter($hook, $final_callback, $priority, $accepted_args);
        } else {
            add_action($hook, $final_callback, $priority, $accepted_args);
        }

        // Track the hook
        self::$registered_hooks[$hook_key] = [
            'hook' => $hook,
            'callback' => $callback,
            'method' => $method,
            'priority' => $priority,
            'conditional' => $conditional,
            'context' => self::$context,
            'registered_at' => microtime(true),
        ];
    }

    /**
     * Check if login security is enabled.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function is_login_security_enabled()
    {
        return isset(self::$config['login_security']['login_enable']) &&
               self::$config['login_security']['login_enable'] === 'enabled';
    }

    /**
     * Check if login URL hiding is enabled.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function is_login_url_hiding_enabled()
    {
        return isset(self::$config['login_security']['login_url_hiding_enabled']) &&
               self::$config['login_security']['login_url_hiding_enabled'];
    }

    /**
     * Check if email hooks should be registered.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function should_register_email_hooks()
    {
        $email_settings = self::$config['emails'] ?? [];
        $general = self::$config['general'] ?? [];

        // Sender rewrite and the recovery redirect also live in
        // register_email_hooks(). File configs are not merged with defaults
        // (load_from_file() returns the raw $fuertewp array), so an absent
        // emails section must not kill them.
        if (!empty($general['recovery_email']) || !empty($general['sender_email_enable'])) {
            return true;
        }

        // Email hooks register in both directions: redirects when a toggle
        // is on, suppression filters when it is off. Any configured emails
        // section therefore needs its hooks.
        return !empty($email_settings);
    }

    /**
     * Check if restriction hooks should be registered.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function should_register_restriction_hooks()
    {
        $restrictions = self::$config['restrictions'] ?? [];
        $restricted_items = self::$config['restricted_scripts'] ?? [];
        $removed_menus = self::$config['removed_menus'] ?? [];
        $removed_submenus = self::$config['removed_submenus'] ?? [];
        $removed_adminbar_menus = self::$config['removed_adminbar_menus'] ?? [];

        return !empty($restrictions) ||
               !empty($restricted_items) ||
               !empty($removed_menus) ||
               !empty($removed_submenus) ||
               !empty($removed_adminbar_menus);
    }

    /**
     * Check if auto-update hooks should be registered.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function should_register_autoupdate_hooks()
    {
        $general = self::$config['general'] ?? [];

        return isset($general['autoupdate_core']) && $general['autoupdate_core'] ||
               isset($general['autoupdate_plugins']) && $general['autoupdate_plugins'] ||
               isset($general['autoupdate_themes']) && $general['autoupdate_themes'];
    }

    /**
     * Check if current page is login page.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function is_login_page()
    {
        // Use simple context detection
        global $pagenow;

        return in_array($pagenow, ['wp-login.php', 'wp-register.php']) ||
               (isset($_GET['action']) && in_array($_GET['action'], ['login', 'register', 'lostpassword', 'resetpass']));
    }

    /**
     * Check if current page is registration page.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function is_registration_page()
    {
        return $GLOBALS['pagenow'] === 'wp-register.php' ||
               (isset($_GET['action']) && $_GET['action'] === 'register');
    }

    /**
     * Check if current request is REST API request.
     *
     * @since 1.7.0
     *
     * @return bool
     */
    private static function is_rest_request()
    {
        return defined('REST_REQUEST') && REST_REQUEST ||
               (isset($_SERVER['REQUEST_URI']) &&
                str_contains($_SERVER['REQUEST_URI'], rest_get_url_prefix()));
    }

    /**
     * Get hook registration statistics.
     *
     * @since 1.7.0
     *
     * @return array Hook statistics
     */
    public static function get_hook_stats()
    {
        $stats = [
            'total_hooks' => count(self::$registered_hooks),
            'conditional_hooks' => 0,
            'core_hooks' => 0,
            'context_breakdown' => [],
            'hook_types' => ['actions' => 0, 'filters' => 0],
        ];

        foreach (self::$registered_hooks as $hook) {
            if ($hook['conditional']) {
                $stats['conditional_hooks']++;
            } else {
                $stats['core_hooks']++;
            }

            // Context breakdown
            foreach ($hook['context'] as $context => $value) {
                if ($value) {
                    $stats['context_breakdown'][$context] = ($stats['context_breakdown'][$context] ?? 0) + 1;
                }
            }
        }

        return $stats;
    }

    /**
     * Clear hook manager cache.
     *
     * @since 1.7.0
     */
    public static function clear_cache()
    {
        self::$config = null;
        self::$registered_hooks = [];
    }
}
