<?php

namespace SigmaSignet;

/**
 * Handles exchanging authorization codes for access tokens
 */
class TokenExchange
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Exchange authorization code for access tokens
     *
     * @param string $code The authorization code from SIGMA
     * @return array|null Token data or null on failure
     */
    public function exchangeCodeForTokens(string $code): ?array
    {
        if (!$this->settings->isConfigured()) {
            error_log('Cannot exchange code: settings not configured');
            return null;
        }

        try {
            $tokenUrl = rtrim($this->settings->get('idp_url'), '/') . '/token';

            $params = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->settings->get('redirect_uri'),
                'client_id' => $this->settings->get('client_id'),
            ];

            $response = wp_remote_post($tokenUrl, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->settings->get('client_id') . ':' . $this->settings->get('client_secret')
                    ),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($params),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                error_log('Token exchange failed: ' . $response->get_error_message());
                return null;
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($statusCode !== 200) {
                error_log("Token exchange failed with status {$statusCode}: {$body}");
                return null;
            }

            $tokenData = json_decode($body, true);
            if (!$tokenData || !isset($tokenData['access_token'])) {
                error_log('Invalid token response: missing access_token');
                return null;
            }

            error_log('Token exchange successful');
            return $tokenData;
        } catch (\Exception $e) {
            error_log('Token exchange exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user info from SIGMA using access token
     *
     * @param string $accessToken The access token
     * @return array|null User info or null on failure
     */
    public function getUserInfo(string $accessToken): ?array
    {
        try {
            $userInfoUrl = rtrim($this->settings->get('idp_url'), '/') . '/userinfo';

            $response = wp_remote_get($userInfoUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                error_log('UserInfo request failed: ' . $response->get_error_message());
                return null;
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($statusCode !== 200) {
                error_log("UserInfo request failed with status {$statusCode}: {$body}");
                return null;
            }

            $userInfo = json_decode($body, true);
            if (!$userInfo) {
                error_log('Invalid userinfo response: invalid JSON');
                return null;
            }

            error_log('UserInfo retrieved successfully');
            return $userInfo;
        } catch (\Exception $e) {
            error_log('UserInfo exception: ' . $e->getMessage());
            return null;
        }
    }
}
