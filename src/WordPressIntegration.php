<?php

namespace SigmaSignet;

/**
 * WordPress Integration - handles hooks and routes
 */
class WordPressIntegration
{
    private Settings $settings;
    private OidcClient $oidcClient;
    private TokenExchange $tokenExchange;
    private UserManager $userManager;

    public function __construct(Settings $settings, OidcClient $oidcClient, TokenExchange $tokenExchange, UserManager $userManager)
    {
        $this->settings = $settings;
        $this->oidcClient = $oidcClient;
        $this->tokenExchange = $tokenExchange;
        $this->userManager = $userManager;
    }

    /**
     * Initialize WordPress hooks
     */
    public function init(): void
    {
        add_action('template_redirect', [$this, 'handleIpAuthentication'], 5);
        add_action('template_redirect', [$this, 'handleLoginRoute']);
        add_action('template_redirect', [$this, 'handleCallbackRoute']);
    }

    /**
     * Attempt IP-based authentication if not already attempted this session
     */
    public function handleIpAuthentication(): void
    {
        // Skip if user is already logged in
        if (is_user_logged_in()) {
            return;
        }

        // Skip if we've already attempted IP auth this session
        if ($this->settings->hasAttemptedIpAuth()) {
            return;
        }

        // Skip if OIDC is not configured
        if (!$this->oidcClient->isReady()) {
            return;
        }

        // Mark that we're attempting IP auth to avoid loops
        $this->settings->markIpAuthAttempted();

        // Get user's IP address
        $ipAddress = $this->getUserIP();

        // Get referrer if available
        $referrer = wp_get_referer();

        // Build IP authentication URL (with prompt=none)
        $authUrl = $this->oidcClient->buildIpAuthUrl($ipAddress, $referrer);

        if (!$authUrl) {
            return;
        }

        $this->settings->debugLog("Attempting IP authentication for IP: {$ipAddress}");

        // Redirect to SIGMA for IP auth
        wp_redirect($authUrl);
        exit;
    }

    /**
     * Handle the OIDC login route
     */
    public function handleLoginRoute(): void
    {
        // Check if this is our login route
        if (!isset($_GET['sigma_login'])) {
            return;
        }

        if (!$this->oidcClient->isReady()) {
            wp_die('SIGMA OIDC not configured properly.');
        }

        // Get user's IP address
        $ipAddress = $this->getUserIP();

        // Get referrer if available
        $referrer = wp_get_referer();

        // Build authorization URL
        $authUrl = $this->oidcClient->buildAuthorizationUrl($ipAddress, $referrer);

        if (!$authUrl) {
            wp_die('Failed to build authorization URL.');
        }

        $this->settings->debugLog("Redirecting to SIGMA OIDC: {$authUrl}");

        // Redirect to SIGMA
        wp_redirect($authUrl);
        exit;
    }

    /**
     * Handle the OIDC callback route
     */
    public function handleCallbackRoute(): void
    {
        // Check if this is our callback route (based on the redirect_uri path)
        $redirectUri = $this->settings->get('redirect_uri');
        if (!$redirectUri) {
            return;
        }

        $callbackPath = parse_url($redirectUri, PHP_URL_PATH);
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($callbackPath !== $currentPath) {
            return;
        }

        $this->settings->debugLog('SIGMA callback route detected');

        // Check for error parameter
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $this->settings->debugLog("SIGMA authentication error: {$error}");

            // If this is login_required, it means IP auth failed (user not recognized by IP)
            // This is expected behavior for prompt=none, so just redirect to home
            if ($error === 'login_required') {
                $this->settings->debugLog("IP authentication failed (login_required) - redirecting to home");
                wp_redirect(home_url());
                exit;
            }

            // For other errors, show error page
            error_log("SIGMA authentication error: {$error}");
            wp_die('Authentication failed: ' . esc_html($error));
        }

        // Check for authorization code
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $this->settings->debugLog("SIGMA authentication code received: {$code}");

            // Exchange code for tokens
            $tokens = $this->tokenExchange->exchangeCodeForTokens($code);
            if (!$tokens) {
                wp_die('Failed to exchange authorization code for tokens.');
            }

            $this->settings->debugLog("Successfully exchanged code for tokens");

            // Get user info using the access token
            $userInfo = $this->tokenExchange->getUserInfo($tokens['access_token']);
            if (!$userInfo) {
                wp_die('Failed to retrieve user information.');
            }

            $this->settings->debugLog("Retrieved user info: " . json_encode($userInfo));

            // Create or update WordPress user
            $user = $this->userManager->findOrCreateUser($userInfo);
            if (!$user) {
                wp_die('Failed to create or update user.');
            }

            $this->settings->debugLog("User created/updated: {$user->user_login}");

            // Log the user in
            wp_set_auth_cookie($user->ID);

            $this->settings->debugLog("User logged in successfully, redirecting to home");

            // Redirect to home page
            wp_redirect(home_url());
            exit;
        }

        // If we get here, something unexpected happened
        $this->settings->debugLog("Callback reached with no code or error");
        wp_die('Invalid callback request.');
    }

    /**
     * Get user's IP address, accounting for proxies
     */
    private function getUserIP(): string
    {
        // Check for proxy headers first
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
