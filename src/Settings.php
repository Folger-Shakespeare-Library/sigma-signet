<?php

namespace SigmaSignet;

/**
 * Settings management for OIDC configuration
 */
class Settings
{
    private const OPTION_NAME = 'sigma_signet_settings';

    /**
     * Get all settings
     */
    public function getSettings(): array
    {
        return get_option(self::OPTION_NAME, [
            'client_id' => '',
            'client_secret' => '',
            'idp_url' => '',
            'redirect_uri' => '',
            'debug_enabled' => false,
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): bool
    {
        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * Get a specific setting
     */
    public function get(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Check if basic OIDC settings are configured
     */
    public function isConfigured(): bool
    {
        $settings = $this->getSettings();
        return !empty($settings['client_id']) &&
            !empty($settings['client_secret']) &&
            !empty($settings['idp_url']) &&
            !empty($settings['redirect_uri']);
    }

    /**
     * Log debug message if debug is enabled
     */
    public function debugLog(string $message): void
    {
        if ($this->get('debug_enabled')) {
            error_log('[SIGMA Debug] ' . $message);
        }
    }
}
