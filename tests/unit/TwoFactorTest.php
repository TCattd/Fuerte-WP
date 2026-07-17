<?php

/**
 * Two-Factor integration tests.
 *
 * Guards against regressions in the bundled Two-Factor library wrapper:
 *   - Provider policy (DISABLED_PROVIDERS strips Dummy even under WP_DEBUG)
 *   - Boot gate precedence (FUERTEWP_DISABLE_2FA constant > two_factor_enable config)
 *   - Crash safety (class_exists guard skips require when standalone already loaded)
 *   - Config wiring (two_factor_enable default true, normalized from DB + sample)
 *   - Settings page hidden (filter strips Dummy regardless of input shape)
 *
 * Note: WP_DEBUG is defined as true in bootstrap.php, so these tests exercise
 * the harder branch: enable_dummy_method_for_debug re-adds Dummy at priority 10,
 * and our filter at priority 20 must still remove it.
 *
 * @since 1.9.7
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\when;

beforeEach(function () {
    setUp();

    // Load the wrapper class (not autoloaded in bootstrap).
    if (!class_exists('Fuerte_Wp_TwoFactor')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-two-factor.php';
    }

    // Fresh config cache per test.
    Fuerte_Wp_Config::invalidate_cache();
    global $wp_tests_options;
    $wp_tests_options = [];
});

afterEach(function () {
    tearDown();
    Fuerte_Wp_Config::invalidate_cache();
    global $wp_tests_options;
    $wp_tests_options = [];
});

/**
 * Provider policy: DISABLED_PROVIDERS constant.
 */
test('two-factor - DISABLED_PROVIDERS strips Two_Factor_Dummy only', function () {
    $providers = [
        'Two_Factor_Email'        => 'email.php',
        'Two_Factor_Totp'         => 'totp.php',
        'Two_Factor_Backup_Codes' => 'backup.php',
        'Two_Factor_Dummy'        => 'dummy.php',
    ];

    $filtered = Fuerte_Wp_TwoFactor::get_instance()->filter_disabled_providers($providers);

    expect($filtered)->not->toHaveKey('Two_Factor_Dummy');
    expect($filtered)->toHaveCount(3);
    expect($filtered)->toHaveKeys(['Two_Factor_Email', 'Two_Factor_Totp', 'Two_Factor_Backup_Codes']);
});

test('two-factor - filter_disabled_providers leaves a Dummy-less set untouched', function () {
    $providers = [
        'Two_Factor_Email' => 'email.php',
        'Two_Factor_Totp'  => 'totp.php',
    ];

    $filtered = Fuerte_Wp_TwoFactor::get_instance()->filter_disabled_providers($providers);

    expect($filtered)->toBe($providers);
    expect($filtered)->toHaveCount(2);
});

test('two-factor - filter_disabled_providers tolerates non-array input', function () {
    // Defensive: hook callbacks must never fatal on unexpected filter input.
    $instance = Fuerte_Wp_TwoFactor::get_instance();
    expect($instance->filter_disabled_providers(null))->toBeNull();
    expect($instance->filter_disabled_providers(false))->toBeFalse();
});

/**
 * Config wiring: two_factor_enable key.
 */
test('config - two_factor_enable defaults to true when key absent', function () {
    // Empty settings → all defaults kick in.
    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['login_security'])->toHaveKey('two_factor_enable');
    expect($config['login_security']['two_factor_enable'])->toBeTrue();
});

test('config - two_factor_enable read from HyperFields fuertewp_settings', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_two_factor_enable' => false,
    ];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['login_security']['two_factor_enable'])->toBeFalse();
});

test('config - two_factor_enable read from legacy _fuertewp_ option', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_two_factor_enable'] = false;

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['login_security']['two_factor_enable'])->toBeFalse();
});

test('config - sample wp-config-fuerte.php ships two_factor_enable true', function () {
    $sample = file_get_contents(FUERTEWP_PATH . 'config-sample/wp-config-fuerte.php');

    expect($sample)->toContain("'two_factor_enable' => true");
    // Guard against accidental duplicate under a different key.
    expect($sample)->not->toContain("'two_factor_enabled'");
});

/**
 * Boot gate: config_enabled() truthiness logic.
 *
 * The contract: only strict false / '0' / 'no' / 'off' / '' / 0 turn the lib off.
 * Everything else (true, '1', 'yes', 'on', null) loads it. This protects against
 * HyperFields persisting an unchecked checkbox as '0' while still loading the
 * lib when the key is simply missing.
 */
test('two-factor - config_enabled returns true for default and truthy values', function () {
    global $wp_tests_options;

    foreach ([true, '1', 'yes', 'on', null] as $value) {
        $wp_tests_options = [];
        $wp_tests_options['_fuertewp_two_factor_enable'] = $value;
        Fuerte_Wp_Config::invalidate_cache();

        expect(Fuerte_Wp_TwoFactor::config_enabled())->toBeTrue(
            "Expected true for value: " . var_export($value, true)
        );
    }
});

test('two-factor - config_enabled returns false only for explicit off values', function () {
    global $wp_tests_options;

    foreach ([false, 0, '0', 'no', 'off', ''] as $value) {
        $wp_tests_options = [];
        $wp_tests_options['_fuertewp_two_factor_enable'] = $value;
        Fuerte_Wp_Config::invalidate_cache();

        expect(Fuerte_Wp_TwoFactor::config_enabled())->toBeFalse(
            "Expected false for value: " . var_export($value, true)
        );
    }
});

/**
 * Boot gate precedence: constant wins over config.
 */
/**
 * Boot gate precedence: constant wins over config.
 *
 * The "unset" test MUST run before the "truthy" test because PHP constants
 * are immutable once defined — defining FUERTEWP_DISABLE_2FA in one test
 * makes it visible to every later test in the same process.
 */
test('two-factor - is_disabled false when FUERTEWP_DISABLE_2FA unset', function () {
    // Bootstrap.php does not define this constant, so here it is absent.
    expect(defined('FUERTEWP_DISABLE_2FA'))->toBeFalse();
    expect(Fuerte_Wp_TwoFactor::is_disabled())->toBeFalse();
});

/**
 * DISABLED_PROVIDERS constant integrity.
 *
 * Guards against accidental widening (e.g. adding Two_Factor_Email would silently
 * break login) or narrowing (removing Two_Factor_Dummy would leak the debug provider).
 */
test('two-factor - DISABLED_PROVIDERS contains only Two_Factor_Dummy', function () {
    $reflection = new ReflectionClass('Fuerte_Wp_TwoFactor');
    $const = $reflection->getConstant('DISABLED_PROVIDERS');

    expect($const)->toBe(['Two_Factor_Dummy']);
});

/**
 * Public API surface (regression guard for visibility changes).
 *
 * The admin UI and collision-protection layer rely on these being public.
 */
test('two-factor - standalone_is_active is public static', function () {
    $reflection = new ReflectionMethod('Fuerte_Wp_TwoFactor', 'standalone_is_active');

    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->isStatic())->toBeTrue();
});

test('two-factor - filter_disabled_providers is public', function () {
    $reflection = new ReflectionMethod('Fuerte_Wp_TwoFactor', 'filter_disabled_providers');

    expect($reflection->isPublic())->toBeTrue();
});

/**
 * Settings page hidden: the Dummy strip is shape-agnostic.
 *
 * The filter must remove Dummy regardless of whether providers values are
 * paths (pre-instantiation) or class instances (post-instantiation), since
 * Two_Factor_Core::get_providers() transforms values mid-pipeline.
 */
test('two-factor - filter strips Dummy when values are instances not paths', function () {
    $dummy = new stdClass();
    $providers = [
        'Two_Factor_Email' => new stdClass(),
        'Two_Factor_Dummy' => $dummy,
    ];

    $filtered = Fuerte_Wp_TwoFactor::get_instance()->filter_disabled_providers($providers);

    expect($filtered)->not->toHaveKey('Two_Factor_Dummy');
    expect($filtered)->toHaveKey('Two_Factor_Email');
});

/**
 * Enforcement layer (Path B): Enforce 2FA for Admins.
 *
 * Default ON. When on, admins/super-admins get Email injected into their
 * enabled-provider list and are challenged at login. The filter is the
 * load-bearing piece; the seed is UX-only.
 */
test('two-factor - enforcement defaults to ON when key absent', function () {
    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['login_security'])->toHaveKey('two_factor_enforce');
    expect($config['login_security']['two_factor_enforce'])->toBeTrue();
});

test('two-factor - enforcement_is_enabled true on fresh install', function () {
    // No config saved → defaults kick in → enforcement ON.
    expect(Fuerte_Wp_TwoFactor::enforcement_is_enabled())->toBeTrue();
});

test('two-factor - enforcement disabled when two_factor_enable off', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_two_factor_enable'] = false;
    Fuerte_Wp_Config::invalidate_cache();

    // Lib not loaded → enforcement can't run. Guards the boot-gate precedence.
    expect(Fuerte_Wp_TwoFactor::enforcement_is_enabled())->toBeFalse();
});

test('two-factor - enforcement disabled when admin unchecks enforce', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_two_factor_enforce'] = false;
    Fuerte_Wp_Config::invalidate_cache();

    expect(Fuerte_Wp_TwoFactor::enforcement_is_enabled())->toBeFalse();
});

test('two-factor - is_enforced_user returns true for administrator role', function () {
    // Brain Monkey doesn't materialize a real WP_User; test the role-list
    // branch directly with a stub object.
    $user = new stdClass();
    $user->ID = 999;
    $user->roles = ['administrator'];
    $user->user_email = 'admin@example.com';

    expect(Fuerte_Wp_TwoFactor::is_enforced_user($user))->toBeTrue();
});

test('two-factor - is_enforced_user returns false for editor', function () {
    $user = new stdClass();
    $user->ID = 999;
    $user->roles = ['editor'];
    $user->user_email = 'editor@example.com';

    expect(Fuerte_Wp_TwoFactor::is_enforced_user($user))->toBeFalse();
});

test('two-factor - is_enforced_user returns false for empty user', function () {
    expect(Fuerte_Wp_TwoFactor::is_enforced_user(null))->toBeFalse();
    expect(Fuerte_Wp_TwoFactor::is_enforced_user(0))->toBeFalse();
});

test('two-factor - is_enforced_user returns false for Fuerte super user', function () {
    // Super users bypass enforcement — they are the recovery lever.
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_super_users' => ['boss@example.com'],
    ];
    Fuerte_Wp_Config::invalidate_cache();

    $user = new stdClass();
    $user->ID = 1;
    $user->roles = ['administrator']; // would normally be enforced...
    $user->user_email = 'boss@example.com'; // ...but is a super user

    expect(Fuerte_Wp_TwoFactor::is_enforced_user($user))->toBeFalse();
});

test('two-factor - enforce_email_for_admins injects Email for a real admin', function () {
    // Stub get_userdata so user 42 resolves to an administrator. This
    // exercises the actual filter path that fires on wp_login (int user_id
    // → is_enforced_user → get_userdata → role check).
    when('get_userdata')->alias(function ($id) {
        $u = new stdClass();
        $u->ID = $id;
        $u->roles = ['administrator'];
        $u->user_email = 'admin' . $id . '@example.com';
        return $u;
    });

    // Fresh install → enforcement ON. Admin with no providers → gets Email.
    $result = Fuerte_Wp_TwoFactor::get_instance()->enforce_email_for_admins([], 42);

    expect($result)->toContain('Two_Factor_Email');
});

test('two-factor - disabling enforcement releases the admin (no lingering injection)', function () {
    // Regression for the recovery path: once Enforce is unchecked, the
    // filter must no longer inject Email, even if the admin previously
    // had it enforced. The filter is the sole source of truth — no meta
    // persistence to clean up.
    when('get_userdata')->alias(function ($id) {
        $u = new stdClass();
        $u->ID = $id;
        $u->roles = ['administrator'];
        $u->user_email = 'admin' . $id . '@example.com';
        return $u;
    });

    global $wp_tests_options;
    $wp_tests_options['_fuertewp_two_factor_enforce'] = false;
    Fuerte_Wp_Config::invalidate_cache();

    $result = Fuerte_Wp_TwoFactor::get_instance()->enforce_email_for_admins(['Two_Factor_Email'], 42);

    // With enforcement off, the filter passes the input through untouched —
    // it neither adds nor removes. Cleanup is the admin's choice via profile.
    expect($result)->toBe(['Two_Factor_Email']);
    expect(Fuerte_Wp_TwoFactor::enforcement_is_enabled())->toBeFalse();
});

/**
 * Boot gate: constant escape hatch (MUST run last).
 *
 * This test defines the FUERTEWP_DISABLE_2FA constant, which is immutable
 * for the rest of the PHP process. Every test above depends on the constant
 * being unset (is_disabled() returns false), so this one runs last to avoid
 * polluting them. If you add tests below this, they will see the constant
 * as defined — reorder accordingly.
 */
test('two-factor - is_disabled true when FUERTEWP_DISABLE_2FA constant truthy', function () {
    if (!defined('FUERTEWP_DISABLE_2FA')) {
        define('FUERTEWP_DISABLE_2FA', true);
    }

    expect(Fuerte_Wp_TwoFactor::is_disabled())->toBeTrue();
});
