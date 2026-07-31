<?php

/**
 * Regression test: the five Advanced Restrictions keys must reach the enforcer.
 *
 * Before 1.11.0 these were nested under 'advanced_restrictions' in
 * normalize_settings(), while every enforcer reader reads the FLAT top-level
 * key. On installs without legacy Carbon Fields rows the flat key was always
 * empty, so remove_menu_page / remove_submenu_page / remove_node / page
 * restrictions never ran with the admin's saved values. This test exercises
 * the full get_config() path (not get_field(), which bypasses the bug) and
 * asserts each key is populated at the flat key.
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    global $wp_tests_options;
    $wp_tests_options = [];
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    global $wp_tests_options;
    $wp_tests_options = [];
});

it('maps fuertewp_removed_menus to the flat removed_menus key the enforcer reads', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => "plugins.php\nthemes.php",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_menus'])->toBeArray()
        ->and($config['removed_menus'])->toHaveCount(2)
        ->and($config['removed_menus'])->toContain('plugins.php')
        ->and($config['removed_menus'])->toContain('themes.php');
});

it('maps fuertewp_removed_submenus to the flat removed_submenus key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_submenus' => "tools.php|export.php\noptions-general.php|updraftplus",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_submenus'])->toBeArray()
        ->and($config['removed_submenus'])->toHaveCount(2)
        ->and($config['removed_submenus'])->toContain('tools.php|export.php');
});

it('maps fuertewp_removed_adminbar_menus to the flat removed_adminbar_menus key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_adminbar_menus' => "wp-logo\nupdraft_admin_node",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_adminbar_menus'])->toBeArray()
        ->and($config['removed_adminbar_menus'])->toHaveCount(2)
        ->and($config['removed_adminbar_menus'])->toContain('wp-logo');
});

it('maps fuertewp_restricted_scripts to the flat restricted_scripts key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restricted_scripts' => "export.php\nupdate-core.php",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['restricted_scripts'])->toBeArray()
        ->and($config['restricted_scripts'])->toHaveCount(2)
        ->and($config['restricted_scripts'])->toContain('export.php');
});

it('maps fuertewp_restricted_pages to the flat restricted_pages key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restricted_pages' => "wprocket\nupdraftplus",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['restricted_pages'])->toBeArray()
        ->and($config['restricted_pages'])->toHaveCount(2)
        ->and($config['restricted_pages'])->toContain('wprocket');
});

it('does not emit the dead nested advanced_restrictions key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => 'plugins.php',
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config)->not->toHaveKey('advanced_restrictions');
});

it('keeps the five keys empty (no-op) when nothing meaningful is saved', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_super_users' => ['admin@example.com'],
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    // The legacy loader emits empty arrays for these keys; normalize then
    // filters its own empty values. Either way the enforcer guards every
    // read with isset() && !empty(), so an unset/empty value is a no-op.
    // This is the safe baseline the regression fix must not disturb.
    expect($config['removed_menus'] ?? [])->toBeEmpty()
        ->and($config['removed_submenus'] ?? [])->toBeEmpty()
        ->and($config['removed_adminbar_menus'] ?? [])->toBeEmpty()
        ->and($config['restricted_scripts'] ?? [])->toBeEmpty()
        ->and($config['restricted_pages'] ?? [])->toBeEmpty();
});
