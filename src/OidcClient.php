<?php

namespace SigmaSignet;

/**
 * OIDC Client for SIGMA authentication
 */
class OidcClient
{
    private Settings $settings;
    private TokenGenerator $tokenGenerator;

    public function __construct(Settings $settings, TokenGenerator $tokenGenerator)
    {
        $this->settings = $settings;
        $this->tokenGenerator = $tokenGenerator;
    }

    /**
     * Build authorization URL for OIDC authentication
     *
     * @param string $ipAddress User's IP address
     * @param string|null $referrerUrl Optional referrer URL
     * @return string|null Authorization URL or null if not configured
     */
    public function buildAuthorizationUrl(string $ipAddress, ?string $referrerUrl = null): ?string
    {
        if (!$this->settings->isConfigured()) {
            error_log('Cannot build authorization URL: settings not configured');
            return null;
        }

        $authToken = $this->tokenGenerator->generateAuthToken();
        if (!$authToken) {
            error_log('Cannot build authorization URL: failed to generate auth token');
            return null;
        }

        $params = [
            'auth_token' => $authToken,
            'client_id' => $this->settings->get('client_id'),
            'ip_address' => $ipAddress,
            'prompt' => 'login',
            'redirect_uri' => $this->settings->get('redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid profile email license license_lite profile_extended offline_access',
            'view_name' => 'fullscreen',
        ];

        // Add optional referrer URL if provided
        if ($referrerUrl) {
            $params['referrer_url'] = $referrerUrl;
        }

        $baseUrl = rtrim($this->settings->get('idp_url'), '/') . '/authorize';
        $url = $baseUrl . '?' . http_build_query($params);

        $this->settings->debugLog("Built authorization URL for client_id: " . $this->settings->get('client_id'));

        return $url;
    }

    /**
     * Build authorization URL for IP-based authentication (transparent/implicit)
     * Uses prompt=none to attempt automatic authentication without showing login screen
     *
     * @param string $ipAddress User's IP address
     * @param string|null $referrerUrl Optional referrer URL
     * @return string|null Authorization URL or null if not configured
     */
    public function buildIpAuthUrl(string $ipAddress, ?string $referrerUrl = null): ?string
    {
        if (!$this->settings->isConfigured()) {
            error_log('Cannot build IP auth URL: settings not configured');
            return null;
        }

        $authToken = $this->tokenGenerator->generateAuthToken();
        if (!$authToken) {
            error_log('Cannot build IP auth URL: failed to generate auth token');
            return null;
        }

        $params = [
            'auth_token' => $authToken,
            'client_id' => $this->settings->get('client_id'),
            'ip_address' => $ipAddress,
            'prompt' => 'none', // This is the key difference - no login screen
            'redirect_uri' => $this->settings->get('redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid profile email license license_lite profile_extended offline_access',
            'view_name' => 'fullscreen',
        ];

        // Add optional referrer URL if provided
        if ($referrerUrl) {
            $params['referrer_url'] = $referrerUrl;
        }

        $baseUrl = rtrim($this->settings->get('idp_url'), '/') . '/authorize';
        $url = $baseUrl . '?' . http_build_query($params);

        $this->settings->debugLog("Built IP auth URL (prompt=none) for client_id: " . $this->settings->get('client_id'));

        return $url;
    }

    /**
     * Check if client can build authorization URLs
     */
    public function isReady(): bool
    {
        return $this->settings->isConfigured() && $this->tokenGenerator->canGenerateToken();
    }
}
