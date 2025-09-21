<?php

use SigmaSignet\Settings;
use SigmaSignet\TokenExchange;

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

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array
    {
        // Mock successful token response
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'access_token' => 'mock-access-token',
                'refresh_token' => 'mock-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ])
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array
    {
        // Mock successful userinfo response
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'sub' => 'user-123',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ])
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return $response['response']['code'] ?? 500;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return false;
    }
}

beforeEach(function () {
    global $mock_options;
    $mock_options = [];
});

test('token exchange requires configured settings', function () {
    $settings = new Settings();
    $exchange = new TokenExchange($settings);

    expect($exchange->exchangeCodeForTokens('test-code'))->toBeNull();
});

test('token exchange works with configured settings', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $exchange = new TokenExchange($settings);
    $tokens = $exchange->exchangeCodeForTokens('test-auth-code');

    expect($tokens)->toBeArray();
    expect($tokens['access_token'])->toBe('mock-access-token');
    expect($tokens['refresh_token'])->toBe('mock-refresh-token');
});

test('token exchange can get user info', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $exchange = new TokenExchange($settings);
    $userInfo = $exchange->getUserInfo('mock-access-token');

    expect($userInfo)->toBeArray();
    expect($userInfo['sub'])->toBe('user-123');
    expect($userInfo['name'])->toBe('Test User');
    expect($userInfo['email'])->toBe('test@example.com');
});
