<?php

use SigmaSignet\Settings;
use SigmaSignet\TokenGenerator;

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

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url): array
    {
        // Mock response for testing
        if (str_contains($url, 'openid-configuration')) {
            return [
                'body' => json_encode(['jwks_uri' => 'https://example.com/jwks']),
                'response' => ['code' => 200]
            ];
        }
        if (str_contains($url, 'jwks')) {
            // Mock JWKS with an encryption key
            $mockJwks = [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'use' => 'enc',
                        'kid' => 'test-enc-key',
                        'n' => 'mock-modulus',
                        'e' => 'AQAB'
                    ]
                ]
            ];
            return [
                'body' => json_encode($mockJwks),
                'response' => ['code' => 200]
            ];
        }
        return ['body' => '', 'response' => ['code' => 404]];
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

test('token generator requires configured settings', function () {
    $settings = new Settings();
    $generator = new TokenGenerator($settings, true); // Test mode

    expect($generator->canGenerateToken())->toBeFalse();
    expect($generator->generateAuthToken())->toBeNull();
});

test('token generator works with configured settings', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $generator = new TokenGenerator($settings, true); // Test mode

    expect($generator->canGenerateToken())->toBeTrue();
    expect($generator->generateAuthToken())->toBeString();
    expect($generator->generateAuthToken())->toBe('test-jwt-token');
});
