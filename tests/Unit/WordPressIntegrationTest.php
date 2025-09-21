<?php

use SigmaSignet\Settings;
use SigmaSignet\TokenGenerator;
use SigmaSignet\OidcClient;
use SigmaSignet\TokenExchange;
use SigmaSignet\UserManager;
use SigmaSignet\WordPressIntegration;

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

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback): void
    {
        // Mock - do nothing
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array
    {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array
    {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return 200;
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

test('wordpress integration can be created', function () {
    $settings = new Settings();
    $tokenGenerator = new TokenGenerator($settings, true);
    $oidcClient = new OidcClient($settings, $tokenGenerator);
    $tokenExchange = new TokenExchange($settings);
    $userManager = new UserManager();
    $wpIntegration = new WordPressIntegration($settings, $oidcClient, $tokenExchange, $userManager);

    expect($wpIntegration)->toBeInstanceOf(WordPressIntegration::class);
});

test('wordpress integration can initialize hooks', function () {
    $settings = new Settings();
    $settings->updateSettings([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'idp_url' => 'https://uat-idp.sams-sigma.com',
        'redirect_uri' => 'https://example.com/callback',
    ]);

    $tokenGenerator = new TokenGenerator($settings, true);
    $oidcClient = new OidcClient($settings, $tokenGenerator);
    $tokenExchange = new TokenExchange($settings);
    $userManager = new UserManager();
    $wpIntegration = new WordPressIntegration($settings, $oidcClient, $tokenExchange, $userManager);

    // Should not throw any errors
    $wpIntegration->init();

    expect(true)->toBeTrue();
});
