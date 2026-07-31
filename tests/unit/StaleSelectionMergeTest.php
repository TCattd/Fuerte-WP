<?php

/**
 * Stale-selection merge tests for Fuerte_Wp_Helper::merge_menu_options.
 *
 * Guarantees a saved slug that is no longer discovered (plugin uninstalled,
 * menu registers only on a sub-screen) stays visible as [Missing] instead of
 * being silently dropped on the next save.
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
});

afterEach(function () {
    tearDown();
});

it('keeps discovered labels unchanged when every saved slug is still discovered', function () {
    $discovered = [
        'wprocket' => '[Plugin] WP Rocket (manage_options)',
        'tools.php' => '[Core] Tools (manage_options)',
    ];

    $merged = Fuerte_Wp_Helper::merge_menu_options($discovered, ['wprocket', 'tools.php']);

    expect($merged)->toBe($discovered)
        ->and($merged)->toHaveCount(2);
});

it('adds a [Missing] entry for a saved slug no longer discovered', function () {
    $discovered = [
        'wprocket' => '[Plugin] WP Rocket (manage_options)',
    ];

    $merged = Fuerte_Wp_Helper::merge_menu_options($discovered, ['wprocket', 'uninstalled-plugin']);

    expect($merged)->toHaveCount(2)
        ->and($merged)->toHaveKey('uninstalled-plugin')
        ->and($merged['uninstalled-plugin'])->toBe('[Missing] uninstalled-plugin');
});

it('does not duplicate a [Missing] entry when the slug reappears in discovery', function () {
    $discovered = [
        'wprocket' => '[Plugin] WP Rocket (manage_options)',
    ];

    // 'wprocket' is both saved and discovered -> single entry, not tagged Missing.
    $merged = Fuerte_Wp_Helper::merge_menu_options($discovered, ['wprocket']);

    expect($merged)->toHaveCount(1)
        ->and($merged['wprocket'])->toBe('[Plugin] WP Rocket (manage_options)');
});

it('ignores blank saved slugs', function () {
    $discovered = ['wprocket' => '[Plugin] WP Rocket (manage_options)'];

    $merged = Fuerte_Wp_Helper::merge_menu_options($discovered, ['', '  ', 'wprocket']);

    expect($merged)->toHaveCount(1);
});

it('handles multiple missing saved slugs', function () {
    $merged = Fuerte_Wp_Helper::merge_menu_options([], ['a', 'b', 'c']);

    expect($merged)->toHaveCount(3)
        ->and($merged['a'])->toBe('[Missing] a')
        ->and($merged['c'])->toBe('[Missing] c');
});
