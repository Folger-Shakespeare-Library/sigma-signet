<?php

namespace SigmaSignet;

/**
 * Settings manager for SIGMA OIDC configuration
 */
class Settings
{
    private const OPTION_NAME = 'sigma_signet_settings';

    private array $settings = [];

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Load settings from WordPress options
     */
    private function loadSettings(): void
    {
        $this->settings = get_option(self::OPTION_NAME, []);
    }

    /**
     * Get a setting value
     */
    public function get(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Save settings to WordPress options
     */
    public function save(): bool
    {
        return update_option(self::OPTION_NAME, $this->settings);
    }

    /**
     * Check if all required settings are configured
     */
    public function isConfigured(): bool
    {
        $required = ['idp_url', 'client_id', 'client_secret', 'redirect_uri'];

        foreach ($required as $key) {
            if (empty($this->settings[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all settings
     */
    public function getAll(): array
    {
        return $this->settings;
    }

    /**
     * Log debug message if debug is enabled
     */
    public function debugLog(string $message): void
    {
        if ($this->get('debug_enabled')) {
            error_log('[SIGMA OIDC Debug] ' . $message);
        }
    }

    /**
     * Check if IP authentication has been attempted this session
     */
    public function hasAttemptedIpAuth(): bool
    {
        return isset($_COOKIE['sigma_ip_auth_checked']);
    }

    /**
     * Mark that IP authentication has been attempted this session
     */
    public function markIpAuthAttempted(): void
    {
        // Set cookie for 1 hour
        setcookie('sigma_ip_auth_checked', '1', time() + 3600, '/', '', is_ssl(), true);
    }
}
