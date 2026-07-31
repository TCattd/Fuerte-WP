<?php

/**
 * Menu discovery tests for Fuerte_Wp_Helper.
 *
 * Covers the live $GLOBALS['menu'] / $GLOBALS['submenu'] readers, the Core vs
 * Plugin tagging, and the is_core_menu_slug / is_auto_blockable_core_slug
 * classifiers (including defect D3: index.php and profile.php are Core but
 * must NOT be auto-blocked).
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    $GLOBALS['menu'] = [];
    $GLOBALS['submenu'] = [];
});

afterEach(function () {
    unset($GLOBALS['menu'], $GLOBALS['submenu']);
    tearDown();
});

it('discovers top-level admin menus as slug => labeled entry', function () {
    $GLOBALS['menu'] = [
        [2 => 'index.php', 0 => 'Dashboard', 1 => 'read'],
        [2 => 'tools.php', 0 => 'Tools', 1 => 'manage_options'],
        [2 => 'wprocket', 0 => 'WP Rocket', 1 => 'manage_options'],
    ];

    $menus = Fuerte_Wp_Helper::discover_admin_menus();

    expect($menus)->toHaveCount(3)
        ->and($menus)->toHaveKey('index.php')
        ->and($menus['index.php'])->toBe('[Core] Dashboard (read)')
        ->and($menus['wprocket'])->toBe('[Plugin] WP Rocket (manage_options)');
});

it('strips HTML from menu titles but keeps the slug as key', function () {
    $GLOBALS['menu'] = [
        [2 => 'edit-comments.php', 0 => 'Comments <span class="awaiting-mod count-3">3</span>', 1 => 'moderate_comments'],
    ];

    $menus = Fuerte_Wp_Helper::discover_admin_menus();

    expect($menus['edit-comments.php'])->toBe('[Core] Comments 3 (moderate_comments)');
});

it('returns an empty array when no menu is registered', function () {
    expect(Fuerte_Wp_Helper::discover_admin_menus())->toBeEmpty();
});

it('classifies query-string core slugs by their base', function () {
    expect(Fuerte_Wp_Helper::is_core_menu_slug('edit.php?post_type=page'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_core_menu_slug('edit.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_core_menu_slug('wprocket'))->toBeFalse();
});

it('tags edit.php family core slugs as Core but NOT auto-blockable (defect D3)', function () {
    // edit.php and its query-string variants are Core (so they get the [Core] tag)
    // but must stay hide-only: a $pagenow == 'edit.php' block hits Pages and CPTs.
    expect(Fuerte_Wp_Helper::is_core_menu_slug('edit.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('edit.php'))->toBeFalse()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('edit.php?post_type=page'))->toBeFalse();
});

it('never auto-blocks index.php or profile.php (defect D3)', function () {
    // index.php is the /wp-admin redirect target; blocking strands non-super users.
    // profile.php is every user's own profile. Both are Core, both hide-only.
    expect(Fuerte_Wp_Helper::is_core_menu_slug('index.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('index.php'))->toBeFalse()
        ->and(Fuerte_Wp_Helper::is_core_menu_slug('profile.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('profile.php'))->toBeFalse();
});

it('auto-blocks single-purpose core scripts', function () {
    expect(Fuerte_Wp_Helper::is_auto_blockable_core_slug('themes.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('tools.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('options-general.php'))->toBeTrue()
        ->and(Fuerte_Wp_Helper::is_auto_blockable_core_slug('plugins.php'))->toBeTrue();
});

it('discovers submenus as parent|child => labeled entry', function () {
    $GLOBALS['submenu'] = [
        'tools.php' => [
            ['Tools', 'manage_options', 'tools.php'],
            ['Export', 'manage_options', 'export.php'],
            ['Transients Manager', 'manage_options', 'transients-manager'],
        ],
    ];

    $submenus = Fuerte_Wp_Helper::discover_admin_submenus();

    expect($submenus)->toHaveCount(3)
        ->and($submenus)->toHaveKey('tools.php|export.php')
        ->and($submenus['tools.php|export.php'])->toBe('tools.php > Export (manage_options)')
        ->and($submenus['tools.php|transients-manager'])->toBe('tools.php > Transients Manager (manage_options)');
});

it('discovers admin-bar nodes from a populated bar', function () {
    $bar = new class {
        public function get_nodes()
        {
            return [
                'wp-logo' => (object) ['id' => 'wp-logo', 'title' => 'About WordPress'],
                'site-name' => (object) ['id' => 'site-name', 'title' => 'Test Site'],
                'my-account' => (object) ['id' => 'my-account', 'title' => 'Howdy, admin'],
            ];
        }
    };

    $nodes = Fuerte_Wp_Helper::discover_adminbar_nodes($bar);

    expect($nodes)->toHaveCount(3)
        ->and($nodes)->toHaveKey('wp-logo')
        ->and($nodes['wp-logo'])->toBe('About WordPress');
});

it('returns empty admin-bar nodes for a null or unsupported bar', function () {
    expect(Fuerte_Wp_Helper::discover_adminbar_nodes(null))->toBeEmpty();

    $bare = new \stdClass();
    expect(Fuerte_Wp_Helper::discover_adminbar_nodes($bare))->toBeEmpty();
});
