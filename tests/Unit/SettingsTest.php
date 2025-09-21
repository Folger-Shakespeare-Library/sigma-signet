<?php

use SigmaSignet\Settings;

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        global $mock_options;
        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value): bool
    {
        global $mock_options;
        $mock_options[$option] = $value;
        return true;
    }
}

beforeEach(function () {
    // Reset mock options before each test
    global $mock_options;
    $mock_options = [];
});

test('settings has default values', function () {
    $settings = new Settings();
    $config = $settings->getSettings();

    expect($config)->toHaveKey('client_id');
    expect($config)->toHaveKey('client_secret');
    expect($config)->toHaveKey('idp_url');
    expect($config)->toHaveKey('redirect_uri');
    expect($config['idp_url'])->toBe('');
});

test('settings can get individual values', function () {
    $settings = new Settings();

    expect($settings->get('idp_url'))->toBe('');
    expect($settings->get('nonexistent', 'default'))->toBe('default');
});

test('settings knows when not configured', function () {
    $settings = new Settings();

    expect($settings->isConfigured())->toBeFalse();
});

test('settings knows when configured', function () {
    $settings = new Settings();

    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    expect($settings->isConfigured())->toBeTrue();
});
