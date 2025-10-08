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

            return $existingUser;
        }

        // User doesn't exist, create new one
        return $this->createNewUser($profileId, $profileName, $authType, $userInfo);
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

        error_log("Could not extract profile data for auth type: {$authType}");
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

            error_log("User logged in successfully: {$user->ID}");
            return true;
        } catch (\Exception $e) {
            error_log("Failed to log in user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new WordPress user
     */
    private function createNewUser(int $profileId, string $profileName, string $authType, array $userInfo): ?\WP_User
    {
        $username = 'profile_' . $profileId;
        $userEmail = $profileId . '@sigma.local';

        error_log("Creating user with profileName: '{$profileName}'");

        $userData = [
            'user_login' => $username,
            'user_email' => $userEmail,
            'user_pass' => wp_generate_password(32, true, true), // Random password
            'display_name' => $profileName,
            'first_name' => $profileName, // Shows in admin user list
            'role' => 'subscriber', // Default role
        ];

        error_log("UserData being sent to wp_insert_user: " . print_r($userData, true));

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            error_log('Failed to create user: ' . $userId->get_error_message());
            return null;
        }

        // Store SIGMA profile ID in user meta
        update_user_meta($userId, 'sigma_profile_id', $profileId);

        // Store authentication type
        update_user_meta($userId, 'sigma_auth_type', $authType);

        // Store full SIGMA user info for reference
        update_user_meta($userId, 'sigma_user_info', wp_json_encode($userInfo));

        // Mark as SIGMA user
        update_user_meta($userId, 'sigma_user', true);

        $user = get_user_by('ID', $userId);
        error_log("Created new SIGMA user: {$userId} (ProfileId: {$profileId}, Type: {$authType})");
        error_log("User first_name after creation: '{$user->first_name}'");

        return $user;
    }
}
