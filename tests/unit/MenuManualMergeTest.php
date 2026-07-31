<?php

/**
 * merge_manual tests: multiselect array + manual textarea lines combine into
 * one de-duplicated array at config load time.
 *
 * Exercises the public path (get_config) because merge_manual is private.
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

it('merges multiselect selections with a manual textarea list', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => ['wprocket', 'updraftplus'],
        'fuertewp_removed_menus_manual' => "some-plugin\nanother-plugin",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_menus'])->toBeArray()
        ->and($config['removed_menus'])->toHaveCount(4)
        ->and($config['removed_menus'])->toContain('wprocket')
        ->and($config['removed_menus'])->toContain('some-plugin')
        ->and($config['removed_menus'])->toContain('another-plugin');
});

it('de-duplicates overlapping multiselect and manual entries', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => ['wprocket', 'akismet'],
        'fuertewp_removed_menus_manual' => "wprocket\nakismet\nnew-one",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_menus'])->toHaveCount(3)
        ->and($config['removed_menus'])->toContain('wprocket')
        ->and($config['removed_menus'])->toContain('akismet')
        ->and($config['removed_menus'])->toContain('new-one');
});

it('accepts a legacy textarea string for the multiselect slot (backward compat)', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => "plugins.php\nthemes.php",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_menus'])->toHaveCount(2)
        ->and($config['removed_menus'])->toContain('plugins.php')
        ->and($config['removed_menus'])->toContain('themes.php');
});

it('drops blank and whitespace-only manual lines', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_menus' => ['wprocket'],
        'fuertewp_removed_menus_manual' => "  \n\nkeep-me\n  ",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_menus'])->toHaveCount(2)
        ->and($config['removed_menus'])->toContain('wprocket')
        ->and($config['removed_menus'])->toContain('keep-me');
});

it('applies the same merge to submenus and adminbar keys', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_removed_submenus' => ['tools.php|export.php'],
        'fuertewp_removed_submenus_manual' => 'tools.php|import.php',
        'fuertewp_removed_adminbar_menus' => ['wp-logo'],
        'fuertewp_removed_adminbar_menus_manual' => "wp-logo\nmy-node",
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['removed_submenus'])->toHaveCount(2)
        ->and($config['removed_submenus'])->toContain('tools.php|import.php')
        ->and($config['removed_adminbar_menus'])->toHaveCount(2)
        ->and($config['removed_adminbar_menus'])->toContain('my-node');
});
