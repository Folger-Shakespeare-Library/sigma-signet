<?php

use SigmaSignet\Settings;
use SigmaSignet\TokenGenerator;
use SigmaSignet\OidcClient;

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

if (!function_exists('error_log')) {
    function error_log(string $message): bool
    {
        return true;
    }
}

beforeEach(function () {
    global $mock_options;
    $mock_options = [];
});

test('oidc client requires configured settings', function () {
    $settings = new Settings();
    $tokenGenerator = new TokenGenerator($settings, true); // Test mode
    $client = new OidcClient($settings, $tokenGenerator);

    expect($client->isReady())->toBeFalse();
    expect($client->buildAuthorizationUrl('192.168.1.1'))->toBeNull();
});

test('oidc client builds authorization url when configured', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $tokenGenerator = new TokenGenerator($settings, true); // Test mode
    $client = new OidcClient($settings, $tokenGenerator);

    expect($client->isReady())->toBeTrue();

    $url = $client->buildAuthorizationUrl('192.168.1.1');
    expect($url)->toBeString();
    expect($url)->toContain('https://uat-idp.sams-sigma.com/authorize');
    expect($url)->toContain('client_id=test-client');
    expect($url)->toContain('ip_address=192.168.1.1');
    expect($url)->toContain('response_type=code');
    expect($url)->toContain('scope=openid');
});

test('oidc client includes referrer url when provided', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $tokenGenerator = new TokenGenerator($settings, true); // Test mode
    $client = new OidcClient($settings, $tokenGenerator);

    $url = $client->buildAuthorizationUrl('192.168.1.1', 'https://referrer.com');
    expect($url)->toContain('referrer_url=https%3A%2F%2Freferrer.com');
});
