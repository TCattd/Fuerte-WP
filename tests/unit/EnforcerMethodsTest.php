<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\when;

beforeEach(function () {
    if (!class_exists('Fuerte_Wp_Enforcer')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-enforcer.php';
    }
    setUp();
});

afterEach(function () {
    tearDown();
});

test('enforcer methods - restrict_rest_api method exists and works', function () {
    // Mock the helper function using Brain\Monkey
    when('fuertewp_restapi_loggedin_only')->returnArg(1);

    // Act & Assert (using Pest's global expect)
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_rest_api'))->toBeTrue();
    
    $result = Fuerte_Wp_Enforcer::restrict_rest_api('original');
    expect($result)->toBe('original');
});

test('enforcer methods - restrict_plugin_installation method exists', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_plugin_installation'))->toBeTrue();
});

test('enforcer methods - restrict_theme_installation method exists', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_theme_installation'))->toBeTrue();
});

test('enforcer methods - suppress_email_notification kills bool, array and string emails', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'suppress_email_notification'))->toBeTrue();

    // Bool send filter: false = do not send.
    expect(Fuerte_Wp_Enforcer::suppress_email_notification(true))->toBeFalse();

    // Content-array filter: recipient emptied, payload kept.
    $email = ['to' => 'user@example.com', 'subject' => 'S', 'message' => 'M'];
    $result = Fuerte_Wp_Enforcer::suppress_email_notification($email);
    expect($result['to'])->toBe('');
    expect($result['subject'])->toBe('S');

    // Recipient-string filter.
    expect(Fuerte_Wp_Enforcer::suppress_email_notification('admin@example.com'))->toBe('');
});

test('enforcer methods - disable_site_registration_notifications returns non-false gate value', function () {
    // pre_site_option_* short-circuits only on a non-false value.
    expect(Fuerte_Wp_Enforcer::disable_site_registration_notifications())->toBe('no');
});

test('enforcer methods - methods are callable as static', function () {
    // This is how Hook_Manager calls them
    $callback = ['Fuerte_Wp_Enforcer', 'restrict_rest_api'];
    expect(is_callable($callback))->toBeTrue();
    
    $callback = ['Fuerte_Wp_Enforcer', 'suppress_email_notification'];
    expect(is_callable($callback))->toBeTrue();

    $callback = ['Fuerte_Wp_Enforcer', 'disable_site_registration_notifications'];
    expect(is_callable($callback))->toBeTrue();
});
