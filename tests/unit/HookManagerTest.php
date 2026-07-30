<?php

/**
 * Hook Manager tests.
 *
 * Covers the fixes from the 2026-07-17 session:
 *   - App passwords now read the correct config key
 *     `restrictions.restapi_disable_app_passwords` (was a no-op reading the
 *     non-existent `rest_api.disable_app_passwords`).
 *   - App passwords + XML-RPC hooks registered with the correct add_hook()
 *     argument shape (was passing `10` into `$method` and `true` into
 *     `$priority`, landing at priority 1 instead of 10).
 *   - Plugin is the source of truth: the legacy `rest_api` file-config key is
 *     intentionally NOT supported.
 *
 * @since 1.10.0
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    if (!class_exists('Fuerte_Wp_Hook_Manager')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-hook-manager.php';
    }
    setUp();

    // Hook_Manager is static with private state; reset it between tests so the
    // dedup map and cached config do not leak across cases.
    resetHookManagerState();
    $GLOBALS['wp_tests_hooks'] = [];
});

afterEach(function () {
    tearDown();
});

/**
 * Reset the Hook_Manager private static properties to a pristine state.
 */
function resetHookManagerState(): void
{
    $manager = new ReflectionClass('Fuerte_Wp_Hook_Manager');

    foreach (['registered_hooks', 'config', 'context'] as $property) {
        $prop = $manager->getProperty($property);
        $prop->setAccessible(true);
        $default = ($property === 'config') ? null : [];
        $prop->setValue(null, $default);
    }
}

/**
 * Set the Hook_Manager config and invoke a private registration method.
 */
function runHookManagerMethod(string $method, array $config): void
{
    $manager = new ReflectionClass('Fuerte_Wp_Hook_Manager');

    $configProp = $manager->getProperty('config');
    $configProp->setAccessible(true);
    $configProp->setValue(null, $config);

    $methodRef = $manager->getMethod($method);
    $methodRef->setAccessible(true);
    $methodRef->invoke(null);
}

/**
 * Read every hook registered for a tag during the test.
 */
function registeredHooksFor(string $tag): array
{
    return $GLOBALS['wp_tests_hooks'][$tag] ?? [];
}

test('app passwords filter is registered when enabled via the correct key', function () {
    runHookManagerMethod('register_frontend_hooks', [
        'restrictions' => ['restapi_disable_app_passwords' => true],
    ]);

    $hooks = registeredHooksFor('wp_is_application_passwords_available');
    expect($hooks)->toHaveCount(1);
    expect($hooks[0]['function'])->toBe('__return_false');
});

test('app passwords filter is registered at default priority 10, not 1', function () {
    // Regression guard for the add_hook() arg-shape bug: the old
    // `add_hook($hook, '__return_false', 10, true)` passed `true` as the
    // priority (coerced to 1) because `10` landed in the `$method` slot.
    runHookManagerMethod('register_frontend_hooks', [
        'restrictions' => ['restapi_disable_app_passwords' => true],
    ]);

    $hooks = registeredHooksFor('wp_is_application_passwords_available');
    expect($hooks[0]['priority'])->toBeInt();
    expect($hooks[0]['priority'])->toBe(10);
});

test('app passwords filter is not registered when disabled', function () {
    runHookManagerMethod('register_frontend_hooks', [
        'restrictions' => ['restapi_disable_app_passwords' => false],
    ]);

    expect(registeredHooksFor('wp_is_application_passwords_available'))->toBeEmpty();
});

test('app passwords legacy rest_api file-config key does not register the filter', function () {
    // Documents the source-of-truth decision: file configs were migrated off
    // the raw `rest_api` namespace. The plugin reads only the normalized key.
    runHookManagerMethod('register_frontend_hooks', [
        'rest_api' => ['disable_app_passwords' => true],
    ]);

    expect(registeredHooksFor('wp_is_application_passwords_available'))->toBeEmpty();
});

test('xmlrpc filter is registered at default priority 10 when enabled', function () {
    // Regression guard for the same add_hook() arg-shape bug on the XML-RPC
    // hook. The key path was already correct; only the priority was wrong.
    runHookManagerMethod('register_frontend_hooks', [
        'restrictions' => ['disable_xmlrpc' => true],
    ]);

    $hooks = registeredHooksFor('xmlrpc_enabled');
    expect($hooks)->toHaveCount(1);
    expect($hooks[0]['function'])->toBe('__return_false');
    expect($hooks[0]['priority'])->toBeInt();
    expect($hooks[0]['priority'])->toBe(10);
});

test('xmlrpc filter is not registered when disabled', function () {
    runHookManagerMethod('register_frontend_hooks', [
        'restrictions' => ['disable_xmlrpc' => false],
    ]);

    expect(registeredHooksFor('xmlrpc_enabled'))->toBeEmpty();
});
