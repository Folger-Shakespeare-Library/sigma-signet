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

            return $existingUser;
        }

        // User doesn't exist, create new one
        return $this->createNewUser($profileId, $profileName, $authType, $identifierType, $userInfo);
    }

    /**
     * Extract the identifier type from organization profiles
     * Prioritizes USER_PASS over IP_RANGE
     *
     * @param array $userInfo User information from SIGMA
     * @return string|null The identifier type or null if not found
     */
    private function extractIdentifierType(array $userInfo): ?string
    {
        $authenticatedProfiles = $userInfo['authenticated_profiles'] ?? [];
        $organizationProfiles = $authenticatedProfiles['organizationProfiles'] ?? [];

        if (empty($organizationProfiles)) {
            return null;
        }

        $foundTypes = [];
        foreach ($organizationProfiles as $profile) {
            if (isset($profile['identifierType'])) {
                $foundTypes[] = $profile['identifierType'];
            }
        }

        if (empty($foundTypes)) {
            return null;
        }

        // USER_PASS takes priority over IP_RANGE
        if (in_array('USER_PASS', $foundTypes, true)) {
            return 'user_pass';
        }

        // Return first found type (likely IP_RANGE)
        return strtolower($foundTypes[0]);
    }

    /**
     * Extract profile data based on authentication type
     */
    private function extractProfileData(array $userInfo, string $authType): ?array
    {
        $authenticatedProfiles = $userInfo['authenticated_profiles'] ?? [];

        // For named authentication, use individual profile
        if ($authType === 'named' && isset($authenticatedProfiles['individualProfile'])) {
            $profile = $authenticatedProfiles['individualProfile'];
            return [
                'profileId' => $profile['profileId'] ?? null,
                'profileName' => $profile['profileName'] ?? null,
            ];
        }

        // For anonymous authentication, use first organization profile
        if ($authType === 'anonymous' && isset($authenticatedProfiles['organizationProfiles'][0])) {
            $profile = $authenticatedProfiles['organizationProfiles'][0];
            return [
                'profileId' => $profile['profileId'] ?? null,
                'profileName' => $profile['profileName'] ?? null,
            ];
        }

        error_log("SIGMA OIDC: Could not extract profile data for auth type: {$authType}");
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
    private function createNewUser(int $profileId, string $profileName, string $authType, ?string $identifierType, array $userInfo): ?\WP_User
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

        // Store full SIGMA user info for reference
        update_user_meta($userId, 'sigma_user_info', wp_json_encode($userInfo));

        // Mark as SIGMA user
        update_user_meta($userId, 'sigma_user', true);

        $user = get_user_by('ID', $userId);
        error_log("SIGMA OIDC: Created new user: {$userId} (ProfileId: {$profileId}, AuthType: {$authType}, IdentifierType: {$identifierType})");

        return $user;
    }
}
