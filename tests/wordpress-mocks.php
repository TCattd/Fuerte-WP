<?php
/**
 * WordPress Mock Functions for Testing
 *
 * @since 1.8.0
 */

// WordPress time constants
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);

// Global state simulation
global $wp_tests_options, $wp_tests_transients, $wp_tests_hooks;

$wp_tests_options = [];
$wp_tests_transients = [];
$wp_tests_hooks = [];

// WordPress option functions
function get_option($option, $default = false) {
    global $wp_tests_options;
    return $wp_tests_options[$option] ?? $default;
}

function update_option($option, $value) {
    global $wp_tests_options;
    $wp_tests_options[$option] = $value;
    return true;
}

function delete_option($option) {
    global $wp_tests_options;
    unset($wp_tests_options[$option]);
    return true;
}

function add_option($option, $value, $deprecated = '', $autoload = 'yes') {
    return update_option($option, $value);
}

// WordPress transient functions
function get_transient($transient) {
    global $wp_tests_transients;
    return $wp_tests_transients[$transient] ?? false;
}

function set_transient($transient, $value, $expiration = 0) {
    global $wp_tests_transients;
    $wp_tests_transients[$transient] = $value;
    return true;
}

function delete_transient($transient) {
    global $wp_tests_transients;
    unset($wp_tests_transients[$transient]);
    return true;
}

// WordPress hook functions
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
    global $wp_tests_hooks;
    if (!isset($wp_tests_hooks[$tag])) {
        $wp_tests_hooks[$tag] = [];
    }
    $wp_tests_hooks[$tag][] = [
        'function' => $function_to_add,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
    return true;
}
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
    return add_filter($tag, $function_to_add, $priority, $accepted_args);
}

function has_action($tag, $callback = false) {
    global $wp_tests_hooks;
    if (!isset($wp_tests_hooks[$tag])) {
        return false;
    }
    if (false === $callback) {
        return !empty($wp_tests_hooks[$tag]);
    }
    foreach ($wp_tests_hooks[$tag] as $hook) {
        if ($hook['function'] === $callback) {
            return $hook['priority'];
        }
    }
    return false;
}

function apply_filters($tag, $value) {
    return $value;
}

function do_action($tag, $arg = '') {
    global $wp_tests_hooks;
    if (isset($wp_tests_hooks[$tag])) {
        foreach ($wp_tests_hooks[$tag] as $hook) {
            if (is_callable($hook['function'])) {
                call_user_func($hook['function'], $arg);
            }
        }
    }
}

// WordPress user functions
function wp_get_current_user() {
    $user = new stdClass();
    $user->ID = 1;
    $user->user_email = 'test@example.com';
    $user->user_login = 'testuser';
    $user->roles = ['administrator'];
    return $user;
}

function current_user_can($capability) {
    return true;
}

function is_admin() {
    return true;
}

// WordPress plugin functions
function plugin_basename($path) {
    return basename(dirname($path)) . '/' . basename($path);
}

// WordPress URL functions
function admin_url($path = '') {
    return 'http://example.com/wp-admin/' . ltrim($path, '/');
}

function home_url($path = '') {
    return 'http://example.com/' . ltrim($path, '/');
}

function get_bloginfo($show = '') {
    $info = [
        'name' => 'Test Site',
        'description' => 'Test Site Description',
        'url' => 'http://example.com',
    ];
    return $info[$show] ?? '';
}

// WordPress i18n functions
function __($text, $domain = 'default') {
    return $text;
}

function esc_html__($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function wp_strip_all_tags($string, $remove_breaks = false) {
    return strip_tags($string);
}

// WordPress plugin/theme functions
function get_plugins() {
    return [
        'hello-dolly/hello.php' => [
            'Name' => 'Hello Dolly',
            'PluginURI' => 'https://wordpress.org/plugins/hello-dolly/',
            'Version' => '1.7.2',
            'Description' => 'This plugin displays hello dolly',
            'Author' => 'Matt Mullenweg',
        ],
        'akismet/akismet.php' => [
            'Name' => 'Akismet',
            'PluginURI' => 'https://akismet.com/',
            'Version' => '5.3',
            'Description' => 'Spam protection',
            'Author' => 'Automattic',
        ],
    ];
}

function wp_get_themes() {
    return [
        'twentytwentythree' => new class {
            public function get($key) {
                $data = [
                    'Name' => 'Twenty Twenty-Three',
                    'Slug' => 'twentytwentythree',
                ];
                return $data[$key] ?? null;
            }
        },
        'twentytwentyfour' => new class {
            public function get($key) {
                $data = [
                    'Name' => 'Twenty Twenty-Four',
                    'Slug' => 'twentytwentyfour',
                ];
                return $data[$key] ?? null;
            }
        },
    ];
}

// Note: file_exists() and is_dir() are native PHP functions
// We don't override them to avoid breaking PHP's internal filesystem operations
// Tests should use actual file operations or mock specific filesystem calls as needed

// WordPress database mock
class wpdb {
    public $prefix = 'wp_';
    public $options = 'wp_options';

    public function prepare($query, ...$args) {
        // Simple placeholder replacement for testing
        // Replace %s, %d, %f with actual args
        foreach ($args as $arg) {
            $pos = strpos($query, '%');
            if ($pos !== false) {
                $type = substr($query, $pos, 2);
                $replacement = '';

                switch ($type) {
                    case '%s':
                        $replacement = "'" . $arg . "'";
                        break;
                    case '%d':
                        $replacement = intval($arg);
                        break;
                    case '%f':
                        $replacement = floatval($arg);
                        break;
                    default:
                        $replacement = $arg;
                }

                $query = substr_replace($query, $replacement, $pos, 2);
            }
        }

        return $query;
    }

    public function get_results($query) {
        global $wp_tests_options;

        // Handle multiselect field queries for Carbon Fields
        // Pattern: SELECT option_name, option_value FROM wp_options WHERE option_name LIKE '_fieldname|||%'
        if (strpos($query, "LIKE '") !== false && strpos($query, "|||%") !== false) {
            // Extract the field name prefix from the query
            if (preg_match("/LIKE '_([^|]+)\|\|\|%'/", $query, $matches)) {
                $field_prefix = $matches[1];
                $results = [];

                // Find all matching options in the test options array
                $prefix = "_{$field_prefix}|||";
                foreach ($wp_tests_options as $option_name => $option_value) {
                    if (strpos($option_name, $prefix) === 0 && strpos($option_name, '|value') !== false) {
                        // Skip empty values
                        if ($option_value === '' || $option_value === null) {
                            continue;
                        }
                        $result = new stdClass();
                        $result->option_name = $option_name;
                        $result->option_value = $option_value;
                        $results[] = $result;
                    }
                }

                // Sort results by option name (for consistent ordering)
                usort($results, function ($a, $b) {
                    return strcmp($a->option_name, $b->option_name);
                });

                return $results;
            }
        }

        return [];
    }

    public function get_var($query) {
        return null;
    }

    public function query($query) {
        return true;
    }

    public function delete($table, $where, $where_format = null) {
        return true;
    }
}

global $wpdb;
$wpdb = new wpdb();
