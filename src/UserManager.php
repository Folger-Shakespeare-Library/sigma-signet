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
        $sigmaId = $userInfo['sub'] ?? null;
        $email = $userInfo['email'] ?? null;
        $name = $userInfo['name'] ?? null;

        if (!$sigmaId) {
            error_log('Cannot create user: missing sub (SIGMA ID)');
            return null;
        }

        // Find user by username (which is the SIGMA ID)
        $existingUser = get_user_by('login', $sigmaId);
        if ($existingUser) {
            error_log("Found existing user: {$existingUser->ID}");

            // Update display name if it's different (user info might have changed in SIGMA)
            if ($name && $existingUser->display_name !== $name) {
                wp_update_user([
                    'ID' => $existingUser->ID,
                    'display_name' => $name,
                    'first_name' => $this->extractFirstName($name),
                    'last_name' => $this->extractLastName($name),
                ]);
                error_log("Updated user display name for user {$existingUser->ID}");
            }

            return $existingUser;
        }

        // Create new user
        return $this->createNewUser($sigmaId, $email, $name);
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
     * Find user by SIGMA ID
     */
    private function findUserBySigmaId(string $sigmaId): ?\WP_User
    {
        $users = get_users([
            'meta_key' => 'sigma_id',
            'meta_value' => $sigmaId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Create new WordPress user
     */
    private function createNewUser(string $sigmaId, ?string $email, ?string $name): ?\WP_User
    {
        // Use SIGMA ID directly as username (guaranteed unique by SIGMA)
        $username = $sigmaId;
        $userEmail = $sigmaId . '@sigma.local';

        $userData = [
            'user_login' => $username,
            'user_email' => $userEmail,
            'user_pass' => wp_generate_password(32, true, true), // Random password
            'display_name' => $name ?: $username,
            'first_name' => $this->extractFirstName($name),
            'last_name' => $this->extractLastName($name),
            'role' => 'subscriber', // Default role
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            error_log('Failed to create user: ' . $userId->get_error_message());
            return null;
        }

        // Store SIGMA ID in user meta (this is the authoritative identifier)
        update_user_meta($userId, 'sigma_id', $sigmaId);

        // Store original SIGMA email for reference (if provided)
        if ($email) {
            update_user_meta($userId, 'sigma_email', $email);
        }

        // Mark as SIGMA user
        update_user_meta($userId, 'sigma_user', true);

        $user = get_user_by('ID', $userId);
        error_log("Created new SIGMA user: {$userId} (SIGMA ID: {$sigmaId})");

        return $user;
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(?string $fullName): string
    {
        if (!$fullName) return '';

        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(?string $fullName): string
    {
        if (!$fullName) return '';

        $parts = explode(' ', trim($fullName));
        if (count($parts) <= 1) return '';

        return implode(' ', array_slice($parts, 1));
    }
}
