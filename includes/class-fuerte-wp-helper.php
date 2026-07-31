<?php

/**
 * Helper Utilities for Fuerte-WP Login Security.
 *
 * Provides static methods for IP validation, CIDR calculations,
 * and range matching operations. Can be extended with other utility methods.
 *
 * @link       https://actitud.xyz
 * @since      1.7.0
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Helper class with static utility methods.
 *
 * @since 1.7.0
 */
class Fuerte_Wp_Helper
{
    /**
     * WordPress core admin-menu slugs. Used to tag discovered items Core vs foreign.
     *
     * Sourced from wp-admin/menu.php top-level entries. Query-string variants
     * such as edit.php?post_type=page are matched by their strtok() base.
     *
     * @since 1.11.0
     */
    public const CORE_MENU_SLUGS = [
        'index.php',
        'edit.php',
        'upload.php',
        'edit.php?post_type=page',
        'edit-comments.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'profile.php',
        'link-manager.php',
    ];

    /**
     * Single-purpose core scripts safe to block by bare $pagenow.
     *
     * EXCLUDES the edit.php family (Posts / Pages / CPTs share the script, so
     * a $pagenow block hits siblings) AND index.php (the dashboard redirect
     * target; blocking it strands non-super users at /wp-admin) AND profile.php
     * (every user needs their own profile). Hide-only for those. See
     * docs/MENU_VISIBILITY_PLAN.md defect D3.
     *
     * @since 1.11.0
     */
    public const AUTO_BLOCKABLE_CORE_SLUGS = [
        'plugins.php',
        'users.php',
        'themes.php',
        'tools.php',
        'edit-comments.php',
        'options-general.php',
        'upload.php',
        'link-manager.php',
    ];

    /**
     * Check if the current user (or a given user) is a super user.
     *
     * Case-insensitive email matching against the configured super_users list.
     * Optionally respects the FUERTEWP_FORCE constant.
     *
     * @since 1.7.2
     *
     * @param WP_User|null $user User to check. Defaults to current user.
     * @param bool $respect_force If true, returns false when FUERTEWP_FORCE is active.
     *
     * @return bool True if super user (and not forced when respect_force is true)
     */
    public static function is_super_user($user = null, $respect_force = false)
    {
        if ($respect_force && defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE) {
            return false;
        }

        if (!$user && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
        }

        if (!$user || !isset($user->user_email) || empty($user->user_email)) {
            return false;
        }

        $config = Fuerte_Wp_Config::get_config();
        $super_users = $config['super_users'] ?? [];

        return in_array(strtolower($user->user_email), array_map('strtolower', $super_users));
    }

    /**
     * Check if the current user (or a given user) is a super user
     * AND restrictions should be bypassed (not forced).
     *
     * Convenience shortcut for the most common check pattern.
     *
     * @since 1.7.2
     *
     * @param WP_User|null $user User to check. Defaults to current user.
     *
     * @return bool True if user should bypass restrictions
     */
    public static function bypasses_restrictions($user = null)
    {
        if (defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE) {
            return false;
        }

        return self::is_super_user($user);
    }

    /**
     * Check if IP is within a CIDR range.
     *
     * Efficient IPv4 CIDR matching with IPv6 support.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     * @param string $cidr CIDR notation (e.g., 192.168.1.0/24)
     *
     * @return bool True if in range, false otherwise
     */
    public static function ip_in_cidr($ip, $cidr)
    {
        if (empty($ip) || empty($cidr) || strpos($cidr, '/') === false) {
            return false;
        }

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $parts = explode('/', $cidr, 2);
        $subnet = trim($parts[0]);
        $mask = isset($parts[1]) ? trim($parts[1]) : '32';

        // Validate subnet
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        // IPv6 handling
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::ipv6_in_cidr($ip, $cidr);
        }

        // IPv4 handling
        return self::ipv4_cidr_match($ip, $subnet, (int) $mask);
    }

    /**
     * Efficient IPv4 CIDR matching.
     *
     * Based on the Limit Login Attempts Reloaded approach.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     * @param string $subnet Subnet address
     * @param int $mask Subnet mask (0-32)
     *
     * @return bool True if matches, false otherwise
     */
    public static function ipv4_cidr_match($ip, $subnet, $mask)
    {
        // Validate inputs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
            !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
            $mask === null || $mask === '' || $mask < 0 || $mask > 32) {
            return false;
        }

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);

        if ($ip_long === false || $subnet_long === false) {
            return false;
        }

        $mask_long = -1 << (32 - $mask);
        $subnet_long &= $mask_long; // Ensure subnet is correctly aligned

        return ($ip_long & $mask_long) == $subnet_long;
    }

    /**
     * Check if IPv6 is in CIDR range.
     *
     * @since 1.7.0
     *
     * @param string $ip IPv6 address
     * @param string $cidr IPv6 CIDR notation
     *
     * @return bool True if in range, false otherwise
     */
    public static function ipv6_in_cidr($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);
        $subnet = trim($parts[0]);
        $mask = (int) trim($parts[1]);

        if ($mask < 0 || $mask > 128) {
            return false;
        }

        $ip_binary = self::ipv6_to_binary($ip);
        $subnet_binary = self::ipv6_to_binary($subnet);

        // Compare first $mask bits
        return substr($ip_binary, 0, $mask) === substr($subnet_binary, 0, $mask);
    }

    /**
     * Convert IPv6 address to binary string.
     *
     * @since 1.7.0
     *
     * @param string $ipv6 IPv6 address
     *
     * @return string Binary representation (128 bits)
     */
    public static function ipv6_to_binary($ipv6)
    {
        // Expand IPv6 notation (handle :: compression)
        $ipv6 = self::expand_ipv6($ipv6);
        $parts = explode(':', $ipv6);
        $binary = '';

        foreach ($parts as $part) {
            // Convert each 16-bit hextet to binary
            $hex_val = hexdec($part);
            $binary .= str_pad(decbin($hex_val), 16, '0', STR_PAD_LEFT);
        }

        return $binary;
    }

    /**
     * Expand compressed IPv6 notation.
     *
     * @since 1.7.0
     *
     * @param string $ipv6 IPv6 address
     *
     * @return string Expanded IPv6 address
     */
    public static function expand_ipv6($ipv6)
    {
        // Handle :: compression
        if (strpos($ipv6, '::') !== false) {
            $parts = explode('::', $ipv6);
            $left = explode(':', $parts[0]);
            $right = isset($parts[1]) ? explode(':', $parts[1]) : [];

            $missing = 8 - (count($left) + count($right));
            $middle = array_fill(0, $missing, '0');

            $parts = array_merge($left, $middle, $right);
        } else {
            $parts = explode(':', $ipv6);
        }

        // Ensure we have exactly 8 parts
        while (count($parts) < 8) {
            $parts[] = '0';
        }

        // Pad each part to 4 hex digits
        foreach ($parts as &$part) {
            $part = str_pad($part, 4, '0', STR_PAD_LEFT);
        }

        return implode(':', $parts);
    }

    /**
     * Validate CIDR notation.
     *
     * @since 1.7.0
     *
     * @param string $range CIDR range (e.g., 192.168.1.0/24 or 2001:db8::/32)
     *
     * @return bool True if valid, false otherwise
     */
    public static function validate_cidr($range)
    {
        if (empty($range) || strpos($range, '/') === false) {
            return false;
        }

        $parts = explode('/', $range, 2);
        $ip = trim($parts[0]);
        $mask = trim($parts[1]);

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Validate mask
        if (!is_numeric($mask)) {
            return false;
        }

        $mask = (int) $mask;

        // IPv4 validation
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mask >= 0 && $mask <= 32;
        }

        // IPv6 validation
        return $mask >= 0 && $mask <= 128;
    }

    /**
     * Check if IP matches wildcard pattern.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     * @param string $pattern Wildcard pattern (e.g., 192.168.1.*)
     *
     * @return bool True if matches, false otherwise
     */
    public static function ip_matches_wildcard($ip, $pattern)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 wildcard matching would be more complex
            return false;
        }

        // Convert wildcard to regex pattern
        $regex = str_replace('*', '\d+', $pattern);
        $regex = '/^' . str_replace('.', '\.', $regex) . '$/';

        return preg_match($regex, $ip) === 1;
    }

    /**
     * Check if IP is in dash-separated range.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     * @param string $range Range (e.g., 192.168.1.1-192.168.1.10)
     *
     * @return bool True if in range, false otherwise
     */
    public static function ip_in_dash_range($ip, $range)
    {
        if (strpos($range, '-') === false) {
            return false;
        }

        $parts = explode('-', $range, 2);
        $start = trim($parts[0]);
        $end = trim($parts[1]);

        if (!filter_var($start, FILTER_VALIDATE_IP) || !filter_var($end, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // For IPv6, this would require more complex comparison
            return false;
        }

        // IPv4 comparison
        $ip_long = ip2long($ip);
        $start_long = ip2long($start);
        $end_long = ip2long($end);

        if ($ip_long === false || $start_long === false || $end_long === false) {
            return false;
        }

        return $ip_long >= $start_long && $ip_long <= $end_long;
    }

    /**
     * Check if IP is in reserved/private range.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     *
     * @return bool True if reserved, false otherwise
     */
    public static function is_reserved_ip($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false; // Only check IPv4 for now
        }

        $reserved_ranges = [
            '10.0.0.0/8',        // Private Class A
            '172.16.0.0/12',     // Private Class B
            '192.168.0.0/16',    // Private Class C
            '127.0.0.0/8',       // Loopback
            '169.254.0.0/16',    // Link-local
            '224.0.0.0/4',       // Multicast
            '240.0.0.0/4',       // Reserved
            '0.0.0.0/8',         // This network
        ];

        foreach ($reserved_ranges as $range) {
            if (self::ip_in_cidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize IP address by removing port and cleaning whitespace.
     *
     * @since 1.7.0
     *
     * @param string $ip Raw IP address
     *
     * @return string Normalized IP address
     */
    public static function normalize_ip($ip)
    {
        if (empty($ip)) {
            return '';
        }

        $ip = trim($ip);

        // Remove port if present for IPv4
        if (strpos($ip, ':') !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $ip = $parts[0];
        }

        // Validate final IP
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Get network address for IP and mask.
     *
     * @since 1.7.0
     *
     * @param string $ip IP address
     * @param int $mask CIDR mask
     *
     * @return string|false Network address or false on failure
     */
    public static function get_network_address($ip, $mask)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::get_ipv6_network($ip, $mask);
        }

        // IPv4 calculation
        $ip_long = ip2long($ip);

        if ($ip_long === false) {
            return false;
        }

        $mask_long = -1 << (32 - $mask);
        $network_long = $ip_long & $mask_long;

        return long2ip($network_long);
    }

    /**
     * Get IPv6 network address.
     *
     * @since 1.7.0
     *
     * @param string $ip IPv6 address
     * @param int $mask CIDR mask
     *
     * @return string IPv6 network address
     */
    private static function get_ipv6_network($ip, $mask)
    {
        $binary = self::ipv6_to_binary($ip);
        $network_binary = substr($binary, 0, $mask) . str_repeat('0', 128 - $mask);

        // Convert back to IPv6 notation
        $hextets = str_split($network_binary, 16);
        $ipv6_parts = [];

        foreach ($hextets as $hextet) {
            $ipv6_parts[] = dechex(bindec($hextet));
        }

        return implode(':', $ipv6_parts);
    }

    /**
     * Is the given slug a WordPress core admin-menu slug?
     *
     * Matches the full slug and the strtok($slug, '?') base so query-string
     * variants (edit.php?post_type=page) classify correctly.
     *
     * @since 1.11.0
     *
     * @param string $slug Menu slug from $GLOBALS['menu'][n][2].
     *
     * @return bool
     */
    public static function is_core_menu_slug($slug)
    {
        $slug = (string) $slug;
        $base = strtok($slug, '?');

        return in_array($slug, self::CORE_MENU_SLUGS, true)
            || in_array($base, self::CORE_MENU_SLUGS, true);
    }

    /**
     * Is the given slug a single-purpose core script safe to block by $pagenow?
     *
     * False for the edit.php family, index.php, and profile.php (hide-only).
     *
     * @since 1.11.0
     *
     * @param string $slug Menu slug.
     *
     * @return bool
     */
    public static function is_auto_blockable_core_slug($slug)
    {
        $slug = (string) $slug;
        $base = strtok($slug, '?');

        return in_array($slug, self::AUTO_BLOCKABLE_CORE_SLUGS, true)
            || in_array($base, self::AUTO_BLOCKABLE_CORE_SLUGS, true);
    }

    /**
     * Discover registered top-level admin menus from $GLOBALS['menu'].
     *
     * Returns [slug => "[Core|Plugin] Title (capability)"]. Safe to call at
     * settings-page render time, where the viewing super user sees the full
     * universe (core plus every plugin/theme menu they can access).
     *
     * @since 1.11.0
     *
     * @return array<string,string>
     */
    public static function discover_admin_menus()
    {
        $menus = [];

        if (!isset($GLOBALS['menu']) || !is_array($GLOBALS['menu'])) {
            return $menus;
        }

        foreach ($GLOBALS['menu'] as $item) {
            if (!is_array($item) || !isset($item[2])) {
                continue;
            }

            $slug = (string) $item[2];
            $title = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : $slug;
            $cap = isset($item[1]) ? (string) $item[1] : '';
            $tag = self::is_core_menu_slug($slug) ? __('Core', 'fuerte-wp') : __('Plugin', 'fuerte-wp');
            $menus[$slug] = $cap !== ''
                ? sprintf('[%s] %s (%s)', $tag, $title, $cap)
                : sprintf('[%s] %s', $tag, $title);
        }

        return $menus;
    }

    /**
     * Discover registered admin submenus from $GLOBALS['submenu'].
     *
     * Returns ["parent|child" => "Parent > Child (capability)"].
     *
     * @since 1.11.0
     *
     * @return array<string,string>
     */
    public static function discover_admin_submenus()
    {
        $submenus = [];

        if (!isset($GLOBALS['submenu']) || !is_array($GLOBALS['submenu'])) {
            return $submenus;
        }

        foreach ($GLOBALS['submenu'] as $parent_slug => $children) {
            if (!is_array($children)) {
                continue;
            }

            foreach ($children as $item) {
                if (!is_array($item) || !isset($item[2])) {
                    continue;
                }

                $parent = (string) $parent_slug;
                $child = (string) $item[2];
                $title = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : $child;
                $cap = isset($item[1]) ? (string) $item[1] : '';
                $key = $parent . '|' . $child;
                $submenus[$key] = $cap !== ''
                    ? sprintf('%s > %s (%s)', $parent, $title, $cap)
                    : sprintf('%s > %s', $parent, $title);
            }
        }

        return $submenus;
    }

    /**
     * Discover admin-bar node IDs from a populated WP_Admin_Bar.
     *
     * Must be called during admin_bar_menu, before Fuerte removes nodes.
     * Returns [node_id => "Title"]. Root nodes and their children are listed.
     *
     * @since 1.11.0
     *
     * @param \WP_Admin_Bar|null $wp_admin_bar Admin bar instance.
     *
     * @return array<string,string>
     */
    public static function discover_adminbar_nodes($wp_admin_bar)
    {
        $nodes = [];

        if (!$wp_admin_bar || !method_exists($wp_admin_bar, 'get_nodes')) {
            return $nodes;
        }

        $all = $wp_admin_bar->get_nodes();

        if (!is_array($all)) {
            return $nodes;
        }

        foreach ($all as $id => $node) {
            if (!is_object($node)) {
                continue;
            }

            $title = isset($node->title) ? wp_strip_all_tags((string) $node->title) : (string) $id;

            if ($title === '') {
                $title = (string) $id;
            }

            $nodes[(string) $id] = $title;
        }

        return $nodes;
    }

    /**
     * Merge discovered menu options with currently-saved values.
     *
     * HyperFields renders only the options it is given, so a saved slug that
     * is no longer discovered (e.g. a plugin was uninstalled) would silently
     * vanish on the next save. This adds a [Missing] entry for every saved
     * slug absent from the discovered set, so nothing is silently dropped and
     * the admin can deselect it. Used at field-build time.
     *
     * @since 1.11.0
     *
     * @param array<string,string> $discovered Slug => label from discovery.
     * @param array<int,string> $saved Currently saved slugs.
     *
     * @return array<string,string>
     */
    public static function merge_menu_options(array $discovered, array $saved)
    {
        foreach ($saved as $slug) {
            $slug = trim((string) $slug);

            if ($slug === '' || array_key_exists($slug, $discovered)) {
                continue;
            }

            $discovered[$slug] = sprintf('[%s] %s', __('Missing', 'fuerte-wp'), $slug);
        }

        return $discovered;
    }

    /**
     * Derive block vectors from hide selections plus explicit restricted_* lists.
     *
     * One selection both hides and blocks, routed by slug type to avoid
     * over-blocking:
     * - Single-purpose core scripts (themes.php, tools.php, ...) -> block by
     *   bare $pagenow.
     * - The edit.php family, index.php, profile.php -> hide-only (defect D3).
     *   Blocking them by $pagenow would hit siblings or strand non-super users.
     * - Foreign plugin/theme pages -> block by ?page=.
     * - Submenus: a core child (.php file under a core parent, e.g.
     *   tools.php|export.php) loads as $pagenow; a foreign child (e.g.
     *   options-general.php|wprocket) loads as ?page= (defect D2).
     *
     * Explicit restricted_scripts / restricted_pages are always honored as-is.
     *
     * @since 1.11.0
     *
     * @param array $removed_menus Hidden top-level menus.
     * @param array $removed_submenus Hidden submenus (parent|child).
     * @param array $restricted_scripts Explicit $pagenow blocks.
     * @param array $restricted_pages Explicit ?page= blocks.
     *
     * @return array{scripts:array<int,string>,pages:array<int,string>}
     */
    public static function derive_block_vectors(array $removed_menus, array $removed_submenus, array $restricted_scripts, array $restricted_pages)
    {
        $scripts = array_values($restricted_scripts);
        $pages = array_values($restricted_pages);

        foreach ($removed_menus as $slug) {
            $slug = trim((string) $slug);

            if ($slug === '' || strpos($slug, '//') === 0) {
                continue;
            }

            if (self::is_auto_blockable_core_slug($slug)) {
                $scripts[] = strtok($slug, '?');
            } elseif (self::is_core_menu_slug($slug)) {
                // edit.php family, index.php, profile.php: hide-only.
                continue;
            } else {
                $pages[] = $slug;
            }
        }

        foreach ($removed_submenus as $item) {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $item));
            $child = $parts[1] ?? '';
            $parent = $parts[0] ?? '';

            if ($child === '') {
                continue;
            }

            // Core admin children are .php files under a core parent and load
            // directly via $pagenow. Foreign children load as ?page=child.
            $is_core_child = self::is_core_menu_slug($parent) && substr($child, -4) === '.php';

            if ($is_core_child) {
                $scripts[] = $child;
            } else {
                $pages[] = $child;
            }
        }

        return [
            'scripts' => array_values(array_unique($scripts)),
            'pages' => array_values(array_unique($pages)),
        ];
    }
}
