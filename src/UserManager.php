<?php

namespace SigmaSignet;

/**
 * Handles WordPress user creation and login for SIGMA users
 */
class UserManager
{
    /**
     * Find or create WordPress user from SIGMA user info
     *
     * @param array $userInfo User information from SIGMA
     * @return \WP_User|null WordPress user object or null on failure
     */
    public function findOrCreateUser(array $userInfo): ?\WP_User
    {
        // Determine authentication type
        $authType = $userInfo['authentication_type'] ?? 'unknown';

        // Extract profile information based on authentication type
        $profileData = $this->extractProfileData($userInfo, $authType);

        if (!$profileData) {
            error_log('SIGMA OIDC: Cannot create user - failed to extract profile data');
            return null;
        }

        $profileId = $profileData['profileId'];
        $profileName = $profileData['profileName'];
        $username = 'profile_' . $profileId;

        // Extract identifier type from organization profiles (USER_PASS takes priority)
        $identifierType = $this->extractIdentifierType($userInfo);

        // Extract OpenURL resolver if available
        $openUrl = $this->extractOpenUrl($userInfo);

        // Find user by username (which is profile_{profileId})
        $existingUser = get_user_by('login', $username);
        if ($existingUser) {
            // Update display name and first_name if different
            if ($profileName && $existingUser->display_name !== $profileName) {
                wp_update_user([
                    'ID' => $existingUser->ID,
                    'display_name' => $profileName,
                    'first_name' => $profileName,
                ]);
            }

            // Update SIGMA meta on every login (these can change)
            update_user_meta($existingUser->ID, 'sigma_auth_type', $authType);
            update_user_meta($existingUser->ID, 'sigma_identifier_type', $identifierType);
            update_user_meta($existingUser->ID, 'sigma_user_info', wp_json_encode($userInfo));

            // Update OpenURL resolver (or clear if no longer present)
            $this->updateOpenUrlMeta($existingUser->ID, $openUrl);

            return $existingUser;
        }

        // User doesn't exist, create new one
        return $this->createNewUser($profileId, $profileName, $authType, $identifierType, $openUrl, $userInfo);
    }

    /**
     * Extract the identifier type using the same logic as profile selection
     * This should match the profile that was actually selected by extractProfileData
     *
     * @param array $userInfo User information from SIGMA
     * @return string|null The identifier type or null if not found
     */
    private function extractIdentifierType(array $userInfo): ?string
    {
        $authenticatedProfiles = $userInfo['authenticated_profiles'] ?? [];

        // Get individual profile if it exists and has allowed identifier type
        $individualProfile = null;
        if (isset($authenticatedProfiles['individualProfile'])) {
            $profile = $authenticatedProfiles['individualProfile'];
            $identifierType = $profile['identifierType'] ?? null;
            if ($this->isAllowedIdentifierType($identifierType)) {
                $individualProfile = $profile;
            }
        }

        // Get valid organization profiles
        $validOrgProfiles = $this->getValidOrganizationProfiles($authenticatedProfiles);

        // Apply same prioritization logic as extractProfileData
        if ($individualProfile && $individualProfile['identifierType'] === 'USER_PASS') {
            return 'user_pass';
        }

        // Look for USER_PASS org profiles first
        foreach ($validOrgProfiles as $profile) {
            if ($profile['identifierType'] === 'USER_PASS') {
                return 'user_pass';
            }
        }

        // Look for IP_RANGE org profiles
        foreach ($validOrgProfiles as $profile) {
            if ($profile['identifierType'] === 'IP_RANGE') {
                return 'ip_range';
            }
        }

        // Fallback to individual profile
        if ($individualProfile) {
            return strtolower($individualProfile['identifierType']);
        }

        return null;
    }

    /**
     * Extract OpenURL resolver from valid organization profiles only
     * Returns the first valid organization profile that has an openUrl with a resolverUrl
     *
     * @param array $userInfo User information from SIGMA
     * @return array|null Array with 'resolver_url' and optional 'icon_url', or null if not found
     */
    private function extractOpenUrl(array $userInfo): ?array
    {
        $authenticatedProfiles = $userInfo['authenticated_profiles'] ?? [];

        // Only consider valid organization profiles (USER_PASS or IP_RANGE)
        $validOrgProfiles = $this->getValidOrganizationProfiles($authenticatedProfiles);

        foreach ($validOrgProfiles as $profile) {
            if (!empty($profile['openUrl']['resolverUrl'])) {
                return [
                    'resolver_url' => $profile['openUrl']['resolverUrl'],
                    'icon_url' => $profile['openUrl']['iconUrl'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Update or clear OpenURL meta fields for a user
     *
     * @param int $userId WordPress user ID
     * @param array|null $openUrl OpenURL data or null to clear
     */
    private function updateOpenUrlMeta(int $userId, ?array $openUrl): void
    {
        if ($openUrl) {
            update_user_meta($userId, 'sigma_openurl_resolver', $openUrl['resolver_url']);
            if ($openUrl['icon_url']) {
                update_user_meta($userId, 'sigma_openurl_icon', $openUrl['icon_url']);
            } else {
                delete_user_meta($userId, 'sigma_openurl_icon');
            }
        } else {
            // Clear if no longer present (institution affiliation may have changed)
            delete_user_meta($userId, 'sigma_openurl_resolver');
            delete_user_meta($userId, 'sigma_openurl_icon');
        }
    }

    /**
     * Check if an identifier type is allowed (USER_PASS or IP_RANGE)
     *
     * @param string|null $identifierType The identifier type to check
     * @return bool True if the identifier type is allowed
     */
    private function isAllowedIdentifierType(?string $identifierType): bool
    {
        return in_array($identifierType, ['USER_PASS', 'IP_RANGE'], true);
    }

    /**
     * Get valid organization profiles (filtered by allowed identifier types)
     *
     * @param array $authenticatedProfiles The authenticated profiles array
     * @return array Array of valid organization profiles
     */
    private function getValidOrganizationProfiles(array $authenticatedProfiles): array
    {
        $organizationProfiles = $authenticatedProfiles['organizationProfiles'] ?? [];
        $validProfiles = [];

        foreach ($organizationProfiles as $profile) {
            $identifierType = $profile['identifierType'] ?? null;
            if ($this->isAllowedIdentifierType($identifierType)) {
                $validProfiles[] = $profile;
            }
        }

        return $validProfiles;
    }

    /**
     * Extract profile data based on authentication type
     * Filters profiles to only use USER_PASS or IP_RANGE (no REFERRER_URL)
     * Prioritizes individual profile over organizational when both have USER_PASS
     */
    private function extractProfileData(array $userInfo, string $authType): ?array
    {
        $authenticatedProfiles = $userInfo['authenticated_profiles'] ?? [];

        // Get individual profile if it exists and has allowed identifier type
        $individualProfile = null;
        if (isset($authenticatedProfiles['individualProfile'])) {
            $profile = $authenticatedProfiles['individualProfile'];
            $identifierType = $profile['identifierType'] ?? null;
            if ($this->isAllowedIdentifierType($identifierType)) {
                $individualProfile = $profile;
            }
        }

        // Get valid organization profiles (filtered by allowed identifier types)
        $validOrgProfiles = $this->getValidOrganizationProfiles($authenticatedProfiles);

        // Apply prioritization logic:
        // 1. If individual profile has USER_PASS, use it regardless of org profiles
        // 2. Otherwise, use the first valid org profile with USER_PASS
        // 3. If no USER_PASS profiles, use the first valid org profile with IP_RANGE
        // 4. If no valid org profiles but individual exists, use individual

        if ($individualProfile && $individualProfile['identifierType'] === 'USER_PASS') {
            return [
                'profileId' => $individualProfile['profileId'] ?? null,
                'profileName' => $individualProfile['profileName'] ?? null,
            ];
        }

        // Look for USER_PASS org profiles first
        foreach ($validOrgProfiles as $profile) {
            if ($profile['identifierType'] === 'USER_PASS') {
                return [
                    'profileId' => $profile['profileId'] ?? null,
                    'profileName' => $profile['profileName'] ?? null,
                ];
            }
        }

        // Look for IP_RANGE org profiles
        foreach ($validOrgProfiles as $profile) {
            if ($profile['identifierType'] === 'IP_RANGE') {
                return [
                    'profileId' => $profile['profileId'] ?? null,
                    'profileName' => $profile['profileName'] ?? null,
                ];
            }
        }

        // Fallback to individual profile if it exists (should have IP_RANGE at this point)
        if ($individualProfile) {
            return [
                'profileId' => $individualProfile['profileId'] ?? null,
                'profileName' => $individualProfile['profileName'] ?? null,
            ];
        }

        error_log("SIGMA OIDC: No valid profiles found (USER_PASS/IP_RANGE only) for auth type: {$authType}");
        return null;
    }

    /**
     * Log in a WordPress user
     *
     * @param \WP_User $user WordPress user object
     * @return bool Success status
     */
    public function loginUser(\WP_User $user): bool
    {
        try {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);

            error_log("SIGMA OIDC: User logged in successfully: {$user->ID}");
            return true;
        } catch (\Exception $e) {
            error_log("SIGMA OIDC: Failed to log in user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new WordPress user
     */
    private function createNewUser(int $profileId, string $profileName, string $authType, ?string $identifierType, ?array $openUrl, array $userInfo): ?\WP_User
    {
        $username = 'profile_' . $profileId;
        $userEmail = $profileId . '@sigma.local';

        $userData = [
            'user_login' => $username,
            'user_email' => $userEmail,
            'user_pass' => wp_generate_password(32, true, true),
            'display_name' => $profileName,
            'first_name' => $profileName,
            'role' => 'subscriber',
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            error_log('SIGMA OIDC: Failed to create user: ' . $userId->get_error_message());
            return null;
        }

        // Store SIGMA profile ID in user meta
        update_user_meta($userId, 'sigma_profile_id', $profileId);

        // Store authentication type
        update_user_meta($userId, 'sigma_auth_type', $authType);

        // Store identifier type
        update_user_meta($userId, 'sigma_identifier_type', $identifierType);

        // Store OpenURL resolver if available
        $this->updateOpenUrlMeta($userId, $openUrl);

        // Store full SIGMA user info for reference
        update_user_meta($userId, 'sigma_user_info', wp_json_encode($userInfo));

        // Mark as SIGMA user
        update_user_meta($userId, 'sigma_user', true);

        $user = get_user_by('ID', $userId);
        error_log("SIGMA OIDC: Created new user: {$userId} (ProfileId: {$profileId}, AuthType: {$authType}, IdentifierType: {$identifierType})");

        return $user;
    }
}
