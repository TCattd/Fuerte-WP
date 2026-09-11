<?php

/**
 * Filesystem kill switch (dot file) tests.
 *
 * Covers .fuertewp-disable (master) and .fuertewp-disable-mfa, checked in
 * ABSPATH and in the docroot parent (wp-config.php style). Uses the real
 * temp filesystem the test bootstrap maps ABSPATH into; markers are cleaned
 * up after every case.
 *
 * @since 1.12.0
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

const MARKER_ALL = '.fuertewp-disable';
const MARKER_MFA = '.fuertewp-disable-mfa';

beforeEach(function () {
    if (!class_exists('Fuerte_Wp_Dot_Files')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-dot-files.php';
    }

    if (!class_exists('Fuerte_Wp_TwoFactor')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-two-factor.php';
    }

    if (!is_dir(ABSPATH)) {
        mkdir(ABSPATH, 0777, true);
    }

    setUp();
    Fuerte_Wp_Dot_Files::reset_cache();
});

afterEach(function () {
    tearDown();

    foreach ([MARKER_ALL, MARKER_MFA] as $marker) {
        $in_abspath = ABSPATH . $marker;
        if (file_exists($in_abspath)) {
            unlink($in_abspath);
        }

        $in_parent = dirname(ABSPATH) . '/' . $marker;
        if (file_exists($in_parent)) {
            unlink($in_parent);
        }
    }

    Fuerte_Wp_Dot_Files::reset_cache();
});

test('no marker files means nothing is disabled', function () {
    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeFalse();
    expect(Fuerte_Wp_Dot_Files::mfa_disabled())->toBeFalse();
});

test('master marker in ABSPATH disables everything including MFA', function () {
    touch(ABSPATH . MARKER_ALL);

    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeTrue();
    expect(Fuerte_Wp_Dot_Files::mfa_disabled())->toBeTrue();
});

test('MFA marker in ABSPATH disables MFA only', function () {
    touch(ABSPATH . MARKER_MFA);

    expect(Fuerte_Wp_Dot_Files::mfa_disabled())->toBeTrue();
    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeFalse();
});

test('markers are detected in the docroot parent (moved wp-config.php style)', function () {
    // Site at /user/public_html/, marker at /user/.
    touch(dirname(ABSPATH) . '/' . MARKER_ALL);

    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeTrue();

    unlink(dirname(ABSPATH) . '/' . MARKER_ALL);
    Fuerte_Wp_Dot_Files::reset_cache();

    touch(dirname(ABSPATH) . '/' . MARKER_MFA);

    expect(Fuerte_Wp_Dot_Files::mfa_disabled())->toBeTrue();
    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeFalse();
});

test('an empty marker file counts: presence only, content never read', function () {
    file_put_contents(ABSPATH . MARKER_ALL, '');

    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeTrue();

    file_put_contents(ABSPATH . MARKER_MFA, 'disabled 2026-07-18 by ops');

    expect(Fuerte_Wp_Dot_Files::mfa_disabled())->toBeTrue();
});

test('cache is per request: reset_cache re-reads the filesystem', function () {
    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeFalse();

    touch(ABSPATH . MARKER_ALL);

    // Still false: the first lookup cached the result.
    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeFalse();

    Fuerte_Wp_Dot_Files::reset_cache();

    expect(Fuerte_Wp_Dot_Files::all_disabled())->toBeTrue();
});

test('two factor gate honors the MFA marker', function () {
    expect(Fuerte_Wp_TwoFactor::is_disabled())->toBeFalse();

    touch(ABSPATH . MARKER_MFA);
    Fuerte_Wp_Dot_Files::reset_cache();

    expect(Fuerte_Wp_TwoFactor::is_disabled())->toBeTrue();
});
