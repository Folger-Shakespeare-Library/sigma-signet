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
    private Authorizer $authorizer;

    public function __construct(Settings $settings, OidcClient $oidcClient, TokenExchange $tokenExchange, UserManager $userManager, Authorizer $authorizer)
    {
        $this->settings = $settings;
        $this->oidcClient = $oidcClient;
        $this->tokenExchange = $tokenExchange;
        $this->userManager = $userManager;
        $this->authorizer = $authorizer;
    }

    /**
     * Initialize WordPress hooks
     */
    public function init(): void
    {
        add_action('template_redirect', [$this, 'processFlashMessages'], 1);
        add_action('template_redirect', [$this, 'handleLoginRoute'], 1);
        add_action('template_redirect', [$this, 'handleIpAuthentication'], 5);
        add_action('template_redirect', [$this, 'handleCallbackRoute']);
        add_action('template_redirect', [$this, 'handleLogoutRoute']);

        // Allow OIDC callback parameters in WordPress query vars
        add_filter('query_vars', [$this, 'addCallbackQueryVars']);
    }

    /**
     * Add OIDC callback parameters to allowed query vars
     */
    public function addCallbackQueryVars(array $vars): array
    {
        $vars[] = 'code';
        $vars[] = 'error';
        $vars[] = 'state';
        return $vars;
    }

    /**
     * Attempt IP-based authentication if not already attempted this session
     */
    public function handleIpAuthentication(): void
    {
        // Skip if IP authentication is disabled
        if (!$this->settings->get('ip_auth_enabled')) {
            return;
        }

        // Skip if user is already logged in
        if (is_user_logged_in()) {
            return;
        }

        // Skip if we're on the callback route
        $redirectUri = $this->settings->get('redirect_uri');
        if ($redirectUri) {
            $callbackPath = parse_url($redirectUri, PHP_URL_PATH);
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            if ($callbackPath === $currentPath) {
                return; // Don't try IP auth on the callback route
            }
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

        // Generate state for CSRF protection
        $state = wp_generate_uuid4();
        set_transient('sigma_oidc_state_' . $state, true, 300); // 5 minute expiry

        // Build authorization URL with state
        $authUrl = $this->oidcClient->buildAuthorizationUrl($ipAddress, $referrer, $state);

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

        // Parse query string directly since WordPress may strip query vars in some cases
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        parse_str($queryString, $params);

        // Verify state parameter for CSRF protection (only for login flow, not logout)
        if (isset($params['code']) && isset($params['state'])) {
            $state = sanitize_text_field($params['state']);
            $transient = get_transient('sigma_oidc_state_' . $state);

            if (!$transient) {
                $this->settings->debugLog("Invalid or expired state parameter: {$state}");
                wp_die('Invalid state parameter. Please try logging in again.');
            }

            // Delete transient - state is single-use
            delete_transient('sigma_oidc_state_' . $state);
            $this->settings->debugLog("State verified and consumed: {$state}");
        }

        // Check for error parameter
        if (isset($params['error'])) {
            $error = sanitize_text_field($params['error']);
            $this->settings->debugLog("SIGMA authentication error: {$error}");

            $state = $params['state'] ?? '';

            // If this is login_required, it means IP auth failed (user not recognized by IP)
            // OR the user successfully logged out from SIGMA
            // This is expected behavior, so just redirect to home
            if ($error === 'login_required') {
                $this->settings->debugLog("Login required (IP auth failed or logout complete) - redirecting to home");
                wp_redirect(home_url());
                exit;
            }

            // For other errors, show error page
            error_log("SIGMA OIDC: Authentication error - {$error}");
            wp_die('Authentication failed: ' . esc_html($error));
        }

        // Check for authorization code
        if (isset($params['code'])) {
            $code = sanitize_text_field($params['code']);
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
            $this->settings->debugLog("User sub ID: " . ($userInfo['sub'] ?? 'MISSING'));
            $this->settings->debugLog("Authentication type: " . ($userInfo['authentication_type'] ?? 'MISSING'));

            // Check authorization
            if (!$this->authorizer->isAuthorized($userInfo)) {
                $this->settings->debugLog("User not authorized for WSB access");
                set_transient('sigma_signet_flash_error', 'This account has no valid subscription for this site.', 60);
                wp_safe_redirect(home_url('/'));
                exit;
            }

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
            // wp_redirect(home_url());
            wp_safe_redirect(add_query_arg('welcome', '1', home_url('/')));
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

    /**
     * Handle the logout route
     */
    public function handleLogoutRoute(): void
    {
        // Check if this is our logout route
        if (!isset($_GET['sigma_logout'])) {
            return;
        }

        if (!$this->oidcClient->isReady()) {
            // If SIGMA not configured, just do WordPress logout
            wp_logout();
            wp_redirect(home_url());
            exit;
        }

        $this->settings->debugLog('SIGMA logout initiated');

        // Log out of WordPress first
        wp_logout();

        // Get user's IP address
        $ipAddress = $this->getUserIP();

        // Build SIGMA logout URL (uses prompt=logout for automatic logout)
        $logoutUrl = $this->oidcClient->buildLogoutUrl($ipAddress);

        if (!$logoutUrl) {
            // If we can't build the URL, just redirect home
            wp_redirect(home_url());
            exit;
        }

        $this->settings->debugLog("Redirecting to SIGMA for logout: {$logoutUrl}");

        // Redirect to SIGMA - will auto-logout and redirect back to our callback
        wp_redirect($logoutUrl);
        exit;
    }

    /**
     * Process flash messages early so themes can use them.
     */
    public function processFlashMessages(): void
    {
        $error = get_transient('sigma_signet_flash_error');
        if ($error) {
            delete_transient('sigma_signet_flash_error');
            do_action('sigma_signet_flash_error', $error);
        }
    }
}
