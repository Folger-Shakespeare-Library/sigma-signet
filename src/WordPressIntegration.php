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
        add_action('template_redirect', [$this, 'handleLoginRoute']);
        add_action('template_redirect', [$this, 'handleCallbackRoute']);
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

            // Get user info
            $userInfo = $this->tokenExchange->getUserInfo($tokens['access_token']);
            if (!$userInfo) {
                wp_die('Failed to retrieve user information.');
            }

            // Log the user information we received
            $this->settings->debugLog('SIGMA user authenticated: ' . json_encode([
                'sub' => $userInfo['sub'] ?? 'unknown',
                'name' => $userInfo['name'] ?? 'unknown',
                'email' => $userInfo['email'] ?? 'unknown'
            ]));

            // Find or create WordPress user
            $wpUser = $this->userManager->findOrCreateUser($userInfo);
            if (!$wpUser) {
                wp_die('Failed to create or find WordPress user.');
            }

            // Log the user in
            if (!$this->userManager->loginUser($wpUser)) {
                wp_die('Failed to log in user.');
            }

            // Redirect to admin or home page
            $redirectUrl = is_admin() ? admin_url() : home_url();
            wp_redirect($redirectUrl);
            exit;
        }

        // No code or error - something went wrong
        wp_die('Invalid callback - no code or error received.');
    }
    public function addLoginLink(): void
    {
        if (!$this->oidcClient->isReady()) {
            return;
        }

        $loginUrl = add_query_arg('sigma_login', '1', home_url());
        echo '<div style="position: fixed; top: 10px; right: 10px; background: #0073aa; color: white; padding: 10px; z-index: 9999;">';
        echo '<a href="' . esc_url($loginUrl) . '" style="color: white; text-decoration: none;">SIGMA Login</a>';
        echo '</div>';
    }

    /**
     * Get user's IP address
     */
    private function getUserIP(): string
    {
        // Check for IP from various headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
