<?php

/**
 * Block-vector routing tests for Fuerte_Wp_Helper::derive_block_vectors.
 *
 * One selection both hides and blocks, but routing must avoid over-blocking:
 * - edit.php family, index.php, profile.php are hide-only (defect D3)
 * - single-purpose core scripts block by $pagenow
 * - foreign pages block by ?page=
 * - core submenu children (.php under core parent) block by $pagenow,
 *   foreign submenu children block by ?page= (defect D2)
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
});

afterEach(function () {
    tearDown();
});

it('routes a foreign menu slug to the ?page= block vector', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(['wprocket'], [], [], []);

    expect($v['pages'])->toContain('wprocket')
        ->and($v['scripts'])->toBeEmpty();
});

it('routes a single-purpose core slug to the $pagenow block vector', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(['themes.php'], [], [], []);

    expect($v['scripts'])->toContain('themes.php')
        ->and($v['pages'])->toBeEmpty();
});

it('strips the query string when routing a core script', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(['tools.php'], [], [], []);

    expect($v['scripts'])->toContain('tools.php')
        ->and($v['scripts'])->toHaveCount(1);
});

it('keeps the edit.php family hide-only (no auto-block, defect D3)', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(
        ['edit.php', 'edit.php?post_type=page'],
        [],
        [],
        []
    );

    expect($v['scripts'])->toBeEmpty()
        ->and($v['pages'])->toBeEmpty();
});

it('keeps index.php and profile.php hide-only (defect D3)', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(['index.php', 'profile.php'], [], [], []);

    expect($v['scripts'])->toBeEmpty()
        ->and($v['pages'])->toBeEmpty();
});

it('routes a core submenu child (.php) to the $pagenow vector (defect D2)', function () {
    // tools.php|export.php -> export.php loads directly via $pagenow.
    $v = Fuerte_Wp_Helper::derive_block_vectors([], ['tools.php|export.php'], [], []);

    expect($v['scripts'])->toContain('export.php')
        ->and($v['pages'])->toBeEmpty();
});

it('routes a foreign submenu child to the ?page= vector (defect D2)', function () {
    // options-general.php|wprocket -> loads as ?page=wprocket, not as a file.
    $v = Fuerte_Wp_Helper::derive_block_vectors([], ['options-general.php|wprocket'], [], []);

    expect($v['pages'])->toContain('wprocket')
        ->and($v['scripts'])->toBeEmpty();
});

it('routes a foreign child of a foreign parent to the ?page= vector', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors([], ['wprocket|settings'], [], []);

    expect($v['pages'])->toContain('settings')
        ->and($v['scripts'])->toBeEmpty();
});

it('always honors explicit restricted_scripts and restricted_pages', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors([], [], ['export.php'], ['custom-page']);

    expect($v['scripts'])->toContain('export.php')
        ->and($v['pages'])->toContain('custom-page');
});

it('de-duplicates across explicit and derived entries', function () {
    // themes.php both explicitly blocked and a hidden menu -> single entry.
    $v = Fuerte_Wp_Helper::derive_block_vectors(['themes.php'], [], ['themes.php'], []);

    expect($v['scripts'])->toHaveCount(1)
        ->and($v['scripts'])->toContain('themes.php');
});

it('skips commented and blank lines in removed_menus', function () {
    $v = Fuerte_Wp_Helper::derive_block_vectors(['//wprocket', '  ', 'real-plugin'], [], [], []);

    expect($v['pages'])->toHaveCount(1)
        ->and($v['pages'])->toContain('real-plugin');
});
