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
    if (!class_exists('Fuerte_Wp_Enforcer')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-enforcer.php';
    }
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
function runHookManagerMethod(string $method, array $config)
{
    $manager = new ReflectionClass('Fuerte_Wp_Hook_Manager');

    $configProp = $manager->getProperty('config');
    $configProp->setAccessible(true);
    $configProp->setValue(null, $config);

    $methodRef = $manager->getMethod($method);
    $methodRef->setAccessible(true);
    return $methodRef->invoke(null);
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

test('application password email filter is registered when the email is disabled', function () {
    // Simulate core's own default-filters.php registration (WP 7.2+).
    add_action('wp_create_application_password', 'wp_application_password_created_notification', 10, 2);
    // WP 7.2+ real core filter; registered only when the toggle is false.
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['application_password_created' => false],
    ]);

    $hooks = registeredHooksFor('wp_send_application_password_created_email');
    expect($hooks)->toHaveCount(1);
    expect($hooks[0]['priority'])->toBe(10);
    expect($hooks[0]['accepted_args'])->toBe(1);
});

test('application password email filter is not registered when the email is enabled', function () {
    // Default is send: no filter registration, core default applies.
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['application_password_created' => true],
    ]);

    expect(registeredHooksFor('wp_send_application_password_created_email'))->toBeEmpty();
});

test('application password email filter is not registered for other email toggles', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['fatal_error' => false, 'new_user_created' => false],
    ]);

    expect(registeredHooksFor('wp_send_application_password_created_email'))->toBeEmpty();
});

test('automatic_updates toggle wires the three real core auto-update email filters', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['automatic_updates' => false],
    ]);

    expect(registeredHooksFor('auto_core_update_send_email'))->toHaveCount(1);
    expect(registeredHooksFor('auto_plugin_update_send_email'))->toHaveCount(1);
    expect(registeredHooksFor('auto_theme_update_send_email'))->toHaveCount(1);
});

test('comment toggles wire notify_moderator and notify_post_author', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => [
            'comment_awaiting_moderation' => false,
            'comment_has_been_published' => false,
        ],
    ]);

    expect(registeredHooksFor('notify_moderator'))->toHaveCount(1);
    expect(registeredHooksFor('notify_post_author'))->toHaveCount(1);
});

test('new user, password and privacy toggles wire the real core notification filters', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => [
            'new_user_created' => false,
            'user_reset_their_password' => false,
            'user_confirm_personal_data_export_request' => false,
        ],
    ]);

    expect(registeredHooksFor('wp_new_user_notification_email_admin'))->toHaveCount(1);
    expect(registeredHooksFor('wp_password_change_notification_email'))->toHaveCount(1);
    expect(registeredHooksFor('user_request_confirmed_email_to'))->toHaveCount(1);
});

test('network toggles wire send_new_site_email and wpmu_welcome_notification', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => [
            'network_new_site_created' => false,
            'network_new_site_activated' => false,
        ],
    ]);

    expect(registeredHooksFor('send_new_site_email'))->toHaveCount(1);
    expect(registeredHooksFor('wpmu_welcome_notification'))->toHaveCount(1);
});

test('network user registration toggle short-circuits the registrationnotification option', function () {
    // No core bool filter exists for newuser_notify_siteadmin(); the
    // option gate is core's own switch.
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['network_new_user_site_registered' => false],
    ]);
    expect(registeredHooksFor('pre_site_option_registrationnotification'))->toHaveCount(1);

    resetHookManagerState();
    $GLOBALS['wp_tests_hooks'] = [];

    runHookManagerMethod('register_email_hooks', [
        'emails' => ['network_new_user_site_registered' => true],
    ]);
    expect(registeredHooksFor('pre_site_option_registrationnotification'))->toBeEmpty();
});

test('fatal error toggle switches recovery redirect for suppression', function () {
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['fatal_error' => false],
    ]);
    $hooks = registeredHooksFor('recovery_mode_email');
    expect($hooks)->toHaveCount(1);
    expect($hooks[0]['function'])->toBe(['Fuerte_Wp_Enforcer', 'suppress_email_notification']);

    resetHookManagerState();
    $GLOBALS['wp_tests_hooks'] = [];

    runHookManagerMethod('register_email_hooks', [
        'emails' => ['fatal_error' => true],
    ]);
    $hooks = registeredHooksFor('recovery_mode_email');
    expect($hooks)->toHaveCount(1);
    expect($hooks[0]['function'])->toBe(['Fuerte_Wp_Enforcer', 'recovery_email_address']);
});

test('email hooks gate passes when every email toggle is disabled', function () {
    // Regression: the old gate only returned true when a toggle was ON,
    // so an all-disabled config never registered its suppression filters.
    $result = runHookManagerMethod('should_register_email_hooks', [
        'emails' => [
            'fatal_error' => false,
            'automatic_updates' => false,
            'new_user_created' => false,
        ],
    ]);
    expect($result)->toBeTrue();
});

test('application password wiring stays inert until core registers the notifier', function () {
    // No add_action here: simulates WP < 7.2 or a reshipped feature.
    // Without core's own registration we must not wire the disable
    // filter, even when the toggle asks for suppression.
    runHookManagerMethod('register_email_hooks', [
        'emails' => ['application_password_created' => false],
    ]);

    expect(registeredHooksFor('wp_send_application_password_created_email'))->toBeEmpty();
});

test('email hooks gate passes for sender rewrite without an emails section', function () {
    // Peer-review regression: file configs are not merged with defaults,
    // so general.sender_email_enable must pass the gate on its own.
    $result = runHookManagerMethod('should_register_email_hooks', [
        'general' => ['sender_email_enable' => true],
    ]);

    expect($result)->toBeTrue();
});
