<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

/**
 * Configuration Integrity Tests
 *
 * Tests for configuration loading, validation, and fallbacks
 */
test('config integrity - returns valid config structure', function () {
    // Arrange: Minimal valid config
    global $wp_tests_options;

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should have all required top-level keys
    expect($config)->toBeArray();
    expect($config)->toHaveKey('super_users');
    expect($config)->toHaveKey('general');
    expect($config)->toHaveKey('restrictions');
});

test('config integrity - general settings have defaults', function () {
    // Arrange: No general settings configured
    global $wp_tests_options;

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should have default general settings
    expect($config['general'])->toBeArray();
    expect($config['general'])->toHaveKey('access_denied_message');
    expect($config['general'])->toHaveKey('recovery_email');
    expect($config['general'])->toHaveKey('autoupdate_core');
    expect($config['general'])->toHaveKey('autoupdate_plugins');
});

test('config integrity - application password email is enabled by default', function () {
    // Critical security notice (WP 7.2+): absent option must mean send.
    global $wp_tests_options;
    $wp_tests_options = [];

    $config = Fuerte_Wp_Config::get_config(true);

    expect($config['emails']['application_password_created'])->toBeTrue();
});

test('config integrity - cache invalidation works correctly', function () {
    // Arrange: Initial config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_access_denied_message'] = 'Access denied.';

    // Act: Get config twice with invalidation
    $config1 = Fuerte_Wp_Config::get_config(true);

    // Change a value
    $wp_tests_options['_fuertewp_access_denied_message'] = 'New message.';
    Fuerte_Wp_Config::invalidate_cache();

    $config2 = Fuerte_Wp_Config::get_config();

    // Assert: Configs should be different after invalidation
    expect($config1['general']['access_denied_message'])->toBe('Access denied.');
    expect($config2['general']['access_denied_message'])->toBe('New message.');
});

test('config integrity - multiselect fields return arrays', function () {
    // Arrange: Multiselect field with values
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'admin@example.com';
    $wp_tests_options['_fuertewp_super_users|||1|value'] = 'super@example.com';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should return array, not string
    expect($config['super_users'])->toBeArray();
    expect($config['super_users'])->toHaveCount(2);
    expect($config['super_users'])->toContain('admin@example.com');
    expect($config['super_users'])->toContain('super@example.com');
});

test('config integrity - empty multiselect returns empty array', function () {
    // Arrange: No multiselect values
    global $wp_tests_options;

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should return empty array
    expect($config['super_users'])->toBeArray();
    expect($config['super_users'])->toBeEmpty();
});

test('config integrity - handles missing options gracefully', function () {
    // Arrange: Completely empty config
    global $wp_tests_options;
    $wp_tests_options = [];

    // Act: Get config (should not throw error)
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should return valid config structure with defaults
    expect($config)->toBeArray();
});

test('config integrity - boolean settings are handled correctly', function () {
    // Arrange: Boolean settings stored as strings
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_autoupdate_core'] = '1';
    $wp_tests_options['_fuertewp_autoupdate_plugins'] = '';
    $wp_tests_options['_fuertewp_autoupdate_themes'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should preserve string format for WordPress compatibility
    expect($config['general']['autoupdate_core'])->toBe('1');
    expect($config['general']['autoupdate_plugins'])->toBe('');
    expect($config['general']['autoupdate_themes'])->toBe('1');
});

test('config integrity - bypass cache parameter works', function () {
    // Arrange: Set up config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_access_denied_message'] = 'Original message.';

    // Act: Get with cache bypass
    $config1 = Fuerte_Wp_Config::get_config(true);

    // Invalidate cache to ensure fresh load
    Fuerte_Wp_Config::invalidate_cache();

    // Modify and get without bypass (should load fresh since cache was invalidated)
    $wp_tests_options['_fuertewp_access_denied_message'] = 'Modified message.';
    $config2 = Fuerte_Wp_Config::get_config();

    // Assert: Both should reflect current state after invalidation
    expect($config1['general']['access_denied_message'])->toBe('Original message.');
    expect($config2['general']['access_denied_message'])->toBe('Modified message.');
});

test('config integrity - email validation in super users', function () {
    // Arrange: Super users with various email formats
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'valid@example.com';
    $wp_tests_options['_fuertewp_super_users|||1|value'] = 'another.valid@sub.example.com';
    $wp_tests_options['_fuertewp_super_users|||2|value'] = 'user+tag@example.com';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: All email formats should be preserved
    expect($config['super_users'])->toHaveCount(3);
    expect($config['super_users'])->toContain('valid@example.com');
    expect($config['super_users'])->toContain('another.valid@sub.example.com');
    expect($config['super_users'])->toContain('user+tag@example.com');
});
