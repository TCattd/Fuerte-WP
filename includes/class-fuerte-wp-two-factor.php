<?php

/**
 * Fuerte_Wp_TwoFactor Class.
 *
 * Boots the bundled upstream Two-Factor code (see docs/TWO_FACTOR_BUNDLING.md)
 * only when the standalone two-factor plugin is NOT active, and otherwise defers
 * to it. Exactly one copy ever runs.
 *
 * Also prevents the standalone plugin from being activated while the bundled
 * copy is the active source of 2FA (collision prevention), using the same
 * request-blocking pattern as Fuerte_Wp_Enforcer::self_protect(). Covers
 * single-row activation, bulk activation, and the multisite network admin
 * screen. CLI/REST activation are documented limitations (higher trust tier).
 *
 * The FUERTEWP_DISABLE_2FA constant (defined in wp-config-fuerte.php) is the
 * escape hatch: when true, the bundled copy is NOT loaded, so the standalone
 * plugin can be activated cleanly. Super users can then configure standalone
 * 2FA without a class-redeclare fatal.
 *
 * @link       https://actitud.xyz
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Two-Factor bootstrapper (detect-and-defer) + collision protection.
 */
class Fuerte_Wp_TwoFactor
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     *
     * @var self|null
     */
    protected static $instance = null;

    /**
     * Path to the bundled two-factor main file.
     */
    private const BUNDLED_FILE = 'includes/two-factor/two-factor.php';

    /**
     * Standalone plugin basename to detect and block.
     */
    private const STANDALONE_BASENAME = 'two-factor/two-factor.php';

    /**
     * Two-Factor provider classnames suppressed site-wide.
     *
     * Dummy is an upstream debug-only provider (core itself strips it unless
     * WP_DEBUG is on). We never surface it: not on the settings page, not at
     * login. Filtering here, rather than editing the synced upstream code,
     * keeps the bundled lib pristine for re-syncs.
     */
    private const DISABLED_PROVIDERS = [
        'Two_Factor_Dummy',
    ];

    /**
     * True once the bundled copy has been required (it is the active source).
     *
     * @var bool
     */
    private bool $bundled_active = false;

    /**
     * Access this plugin instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Whether Two-Factor is disabled via the FUERTEWP_DISABLE_2FA constant.
     *
     * When true, the bundled copy is not loaded. This is the escape hatch that
     * lets a super user activate the standalone two-factor plugin without a
     * class-redeclare fatal (the bundled Two_Factor_Core must not be loaded
     * first). Same trust tier as filesystem/CLI recovery.
     *
     * Uses a lenient truthiness check: accepts true, 1, '1', 'true'. This is
     * deliberately more forgiving than the FUERTEWP_DISABLE/FUERTEWP_FORCE
     * strict-true convention because this constant is the documented recovery
     * lever and a silent no-op from a stringy value would trap the operator
     * out of their own site.
     *
     * @return bool
     */
    public static function is_disabled()
    {
        if (!defined('FUERTEWP_DISABLE_2FA')) {
            return false;
        }

        $value = FUERTEWP_DISABLE_2FA;

        // Strict true (matches existing convention) OR lenient truthy for
        // string/int variants an operator might write under stress.
        return true === $value
            || 1 === $value
            || '1' === $value
            || 'true' === $value;
    }

    /**
     * Whether Two-Factor enforcement is on (Path B).
     *
     * When true, admins and super admins are challenged with Email 2FA even if
     * they never configured a provider. Email is zero-config (the code goes to
     * the address on file), so enforcement needs no user setup step.
     * Default ON (opt-out).
     *
     * @return bool
     */
    public static function enforcement_is_enabled()
    {
        if (self::is_disabled() || !self::config_enabled()) {
            return false;
        }

        $value = Fuerte_Wp_Config::get('login_security.two_factor_enforce', true);

        // Strict opt-in: only truthy values enable enforcement. Default off
        // because flipping login behavior for admins is a high-blast-radius
        // change that must be deliberate.
        return in_array($value, [true, '1', 'yes', 'on'], true);
    }

    /**
     * Whether the admin Enable 2FA toggle is on.
     *
     * Reads login_security.two_factor_enable from Fuerte_Wp_Config. Defaults
     * true (lib loads on a fresh install and whenever the key is missing).
     * Accepts native booleans and the stringy variants HyperFields may store.
     *
     * @return bool
     */
    public static function config_enabled()
    {
        $value = Fuerte_Wp_Config::get('login_security.two_factor_enable', true);

        // Strict false is the only real off signal; everything else (true,
        // '1', 'yes', 'on', null) loads the lib. Honors existing truthy
        // conventions in the codebase rather than imposing a new one.
        return false !== $value
            && 0 !== $value
            && '' !== $value
            && '0' !== $value
            && 'no' !== $value
            && 'off' !== $value;
    }

    /**
     * Whether a user is subject to 2FA enforcement.
     *
     * Fuerte super users ALWAYS bypass enforcement (documented in
     * config-sample/wp-config-fuerte.php): they are the recovery lever that
     * can reach admin to disable the policy if a regular admin is locked out.
     * Beyond that, covers the Administrator role (single site and per-blog on
     * multisite) and Super Admins (network level).
     *
     * @param int|WP_User $user User ID or object.
     *
     * @return bool
     */
    public static function is_enforced_user($user)
    {
        if (empty($user)) {
            return false;
        }

        // Fuerte super users bypass enforcement — they are the recovery path.
        if (Fuerte_Wp_Helper::is_super_user($user)) {
            return false;
        }

        $user_id = is_object($user) ? ($user->ID ?? 0) : (int) $user;

        if (!$user_id) {
            return false;
        }

        // Super Admin (multisite network admin) is a capability check, not a
        // role — lives in the user_meta spam/deleted flag set, not wp_roles.
        if (function_exists('is_super_admin') && is_super_admin($user_id)) {
            return true;
        }

        $user_obj = is_object($user) ? $user : null;

        if (!$user_obj && function_exists('get_userdata')) {
            $user_obj = get_userdata($user_id);
        }

        if (!$user_obj) {
            return false;
        }

        $roles = (array) ($user_obj->roles ?? []);

        return in_array('administrator', $roles, true);
    }

    /**
     * Inject Two_Factor_Email into a user's enabled-provider list.
     *
     * The load-bearing enforcement hook. Filters through get_enabled_providers_for_user(),
     * which BOTH the login auth flow and the profile UI read, so Email stays
     * enabled regardless of what the user's meta says. Works even when the
     * meta is empty (unconfigured admin). Read-only by design: Email is never
     * persisted to user meta, so disabling enforcement releases the admin
     * immediately (no lingering meta cleanup needed).
     *
     * @param array $enabled Enabled provider classnames for the user.
     * @param int $user_id User ID.
     *
     * @return array
     */
    public function enforce_email_for_admins($enabled, $user_id)
    {
        if (!self::enforcement_is_enabled()) {
            return $enabled;
        }

        if (!self::is_enforced_user($user_id)) {
            return $enabled;
        }

        $enabled = (array) $enabled;

        if (!in_array('Two_Factor_Email', $enabled, true)) {
            $enabled[] = 'Two_Factor_Email';
        }

        return array_values($enabled);
    }

    /**
     * Register the enforcement hooks.
     *
     * Only fires when the bundled lib is active AND enforcement is on.
     *
     * The filter alone is load-bearing: it injects Email at read time (both
     * login auth and profile UI), never persisting to user meta. This keeps
     * recovery honest — disable enforcement, the filter no-ops, and admins
     * with no prior provider config are released immediately. Super users
     * always bypass via is_enforced_user().
     */
    protected function register_enforcement()
    {
        // Inject Email into the enabled list (the actual enforcement).
        add_filter('two_factor_enabled_providers_for_user', [$this, 'enforce_email_for_admins'], 20, 2);
    }

    /**
     * Boot the bundled Two-Factor code unless the standalone plugin is active,
     * the escape-hatch constant is set, or the admin disabled 2FA in settings.
     *
     * Hooked early on plugins_loaded so providers register before the login
     * flow runs. The bundled file boots itself on include (Two_Factor_Core::add_hooks()
     * runs at include time; settings UI registers on a later init hook).
     *
     * Two off switches, in priority order:
     *   1. FUERTEWP_DISABLE_2FA constant (operator escape hatch, filesystem tier)
     *   2. login_security.two_factor_enable = false (admin toggle, stored in config)
     * Both simply skip the require; collision protection still registers so the
     * standalone plugin remains blocked while we own this space.
     */
    public function boot()
    {
        // Escape hatch: when FUERTEWP_DISABLE_2FA is truthy, do not load the
        // bundled copy at all. This is what makes the standalone plugin safe
        // to activate (no class-redeclare fatal). Collision protection still
        // registers below so the abort messaging stays consistent.
        $disabled = self::is_disabled();

        // Admin toggle: when the Enable 2FA checkbox is off, do not load the
        // bundled copy. Default true so the lib loads on a fresh install.
        // Checked here (after the constant) so the operator's hard switch still
        // wins even if config got flipped back on.
        if (!$disabled) {
            $disabled = !self::config_enabled();
        }

        // Guard: never load if upstream core is already available (standalone
        // plugin active, or this somehow ran twice). Prevents fatal redeclare
        // of global functions defined in the bundled main file.
        if (class_exists('Two_Factor_Core')) {
            $this->register_collision_protection();

            return;
        }

        if (!$disabled && self::standalone_is_active()) {
            // Standalone plugin is the source of 2FA; defer to it and only
            // layer our own protection on top.
            $this->register_collision_protection();

            return;
        }

        if (!$disabled) {
            $file = FUERTEWP_PATH . self::BUNDLED_FILE;

            if (is_readable($file)) {
                require_once $file;
                $this->bundled_active = true;
                $this->restrict_providers();
                $this->hide_settings_page();
                $this->register_enforcement();
            }
        }

        // Collision protection is intentionally inert when the bundled copy is
        // not loaded (constant set or checkbox off): there is nothing to
        // collide with, so the standalone plugin is free to activate.
        $this->register_collision_protection();
    }

    /**
     * Whether the bundled copy is the active source of Two-Factor.
     *
     * @return bool
     */
    public function is_bundled_active()
    {
        return $this->bundled_active;
    }

    /**
     * Suppress provider classnames site-wide (e.g. the Dummy debug method).
     *
     * Hooked at priority 20 to run AFTER core's enable_dummy_method_for_debug
     * (registered at default priority 10 in Two_Factor_Core::add_hooks),
     * which re-adds Dummy when WP_DEBUG is on. Stripping here at 20 means
     * Dummy is removed even in debug builds. Do not lower this priority
     * without re-checking that Dummy stays hidden under WP_DEBUG.
     */
    protected function restrict_providers()
    {
        add_filter('two_factor_providers', [$this, 'filter_disabled_providers'], 20);
    }

    /**
     * Hide the bundled Two-Factor settings page entirely.
     *
     * Provider policy is enforced in code (DISABLED_PROVIDERS) and is not an
     * admin-facing choice, so the upstream Settings -> Two-Factor screen has
     * nothing useful to offer. Removing the submenu at admin_menu priority 99
     * (after two_factor_add_settings_page registers at default 10) drops the
     * link; the admin_init guard closes the direct-URL loophole.
     */
    protected function hide_settings_page()
    {
        add_action('admin_menu', [$this, 'remove_settings_submenu'], 99);
        add_action('admin_init', [$this, 'block_settings_direct_access']);
    }

    /**
     * Drop the Two-Factor entry from the Settings submenu.
     */
    public function remove_settings_submenu()
    {
        remove_submenu_page('options-general.php', 'two-factor-settings');
    }

    /**
     * Refuse direct navigation to ?page=two-factor-settings.
     *
     * remove_submenu_page hides the link but does not unregister the page
     * callback, so the URL still renders without this guard. Redirect to the
     * parent settings screen rather than dying, so stray bookmarks fail soft.
     */
    public function block_settings_direct_access()
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ('two-factor-settings' !== $page) {
            return;
        }

        wp_safe_redirect(admin_url('options-general.php'));
        exit;
    }

    /**
     * Remove suppressed provider classnames from the registered set.
     *
     * @param array $providers Providers keyed by classname (path or instance).
     *
     * @return array
     */
    public function filter_disabled_providers($providers)
    {
        if (!is_array($providers)) {
            return $providers;
        }

        foreach (self::DISABLED_PROVIDERS as $classname) {
            unset($providers[$classname]);
        }

        return $providers;
    }

    /**
     * Register the collision-protection hooks.
     *
     * Runs whether the bundled copy or the standalone plugin is the active
     * source, and even when the escape hatch is set: the goal is always to
     * keep exactly one copy running.
     */
    protected function register_collision_protection()
    {
        // Block activation of the standalone plugin while our copy is running.
        add_action('admin_init', [$this, 'block_standalone_activation']);
        // Hide the Activate link on the regular Plugins screen.
        add_filter('plugin_action_links', [$this, 'hide_standalone_activate_link'], 10, 2);
        // Hide the Activate link on the multisite network Plugins screen.
        add_filter('network_admin_plugin_action_links', [$this, 'hide_standalone_activate_link'], 10, 2);
    }

    /**
     * Prevent the standalone two-factor plugin from being activated.
     *
     * Covers the single-row Activate link (GET action=activate&plugin=...),
     * the bulk Activate action (POST action=activate-selected&checked[]=...),
     * and both action/action2 dropdown positions. Mirrors Fuerte_Wp_Enforcer::self_protect():
     * admin_init fires before plugins.php processes any of these, so
     * intercepting the request here stops activation from completing.
     *
     * Does NOT cover WP-CLI or REST API activation (documented limitation;
     * both require a trust tier that already implies filesystem access).
     */
    public function block_standalone_activation()
    {
        if (!$this->bundled_active) {
            return;
        }

        if (!$this->is_standalone_activation_request()) {
            return;
        }

        // Super users may activate the standalone plugin. For this to be safe
        // (no class-redeclare fatal) they must first set FUERTEWP_DISABLE_2FA
        // so the bundled copy stops loading. The abort message says so.
        if (Fuerte_Wp_Helper::is_super_user()) {
            return;
        }

        $this->abort_standalone_activation();
    }

    /**
     * Detect whether the current request is attempting to activate the
     * standalone two-factor plugin, via any of the supported paths.
     *
     * Uses $_REQUEST (covers GET single-row and POST bulk), checks both the
     * action and action2 fields (WP dropdowns submit to either), and scans
     * the checked[] array for the standalone basename (bulk path).
     *
     * @return bool
     */
    protected function is_standalone_activation_request()
    {
        if (!is_admin()) {
            return false;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : '';
        $action2 = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash((string) $_REQUEST['action2'])) : '';

        $is_activate_action = in_array($action, ['activate', 'activate-selected'], true)
            || in_array($action2, ['activate', 'activate-selected'], true);

        if (!$is_activate_action) {
            return false;
        }

        // Single-row path: ?action=activate&plugin=two-factor/two-factor.php
        $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['plugin'])) : '';

        if (self::STANDALONE_BASENAME === $plugin) {
            return true;
        }

        // Bulk path: action=activate-selected&checked[]=two-factor/two-factor.php
        $checked = isset($_REQUEST['checked']) ? (array) wp_unslash($_REQUEST['checked']) : [];

        if (in_array(self::STANDALONE_BASENAME, $checked, true)) {
            return true;
        }

        return false;
    }

    /**
     * Hide the Activate link for the standalone plugin.
     *
     * Hooked on both plugin_action_links (single site) and
     * network_admin_plugin_action_links (multisite network admin).
     *
     * @param string[] $actions Plugin action links.
     * @param string $plugin_file Plugin basename.
     *
     * @return string[]
     */
    public function hide_standalone_activate_link($actions, $plugin_file)
    {
        if (!$this->bundled_active) {
            return $actions;
        }

        if (self::STANDALONE_BASENAME !== $plugin_file) {
            return $actions;
        }

        // Super users keep the link (escape hatch, after setting the constant).
        if (Fuerte_Wp_Helper::is_super_user()) {
            return $actions;
        }

        unset($actions['activate']);

        return $actions;
    }

    /**
     * Abort the standalone activation with an explanatory error.
     *
     * Sends the user back to the plugins screen. The message is honest about
     * the real prerequisite: the bundled copy must not be loaded, which
     * requires the FUERTEWP_DISABLE_2FA constant (set by an operator with
     * filesystem access, same trust tier as CLI recovery).
     */
    protected function abort_standalone_activation()
    {
        $message = __(
            'Fuerte-WP already provides Two-Factor authentication. To use the standalone Two-Factor plugin instead, an operator with filesystem access must set FUERTEWP_DISABLE_2FA to true in wp-config-fuerte.php (or wp-config.php) so Fuerte-WP stops loading its bundled copy, then activate the standalone plugin.',
            'fuerte-wp',
        );

        wp_die(esc_html($message), '', ['back_link' => true]);
    }

    /**
     * Whether the standalone two-factor plugin is active.
     *
     * Uses is_plugin_active() when available (admin context) and falls back to
     * checking the active_plugins option directly otherwise. Public so the admin
     * UI can query it to hide the bundled-library config when the standalone
     * plugin is the active source.
     */
    public static function standalone_is_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('is_plugin_active')) {
            return is_plugin_active(self::STANDALONE_BASENAME);
        }

        // Multisite-aware fallback.
        if (is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', []);

            if (isset($network[self::STANDALONE_BASENAME])) {
                return true;
            }
        }

        $active = (array) get_option('active_plugins', []);

        return in_array(self::STANDALONE_BASENAME, $active, true);
    }
} // Class end
