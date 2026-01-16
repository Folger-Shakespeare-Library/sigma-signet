<?php

namespace SigmaSignet;

/**
 * Handles WordPress user creation and login for SIGMA users
 */
class UserManager
{
    private const DEFAULT_USER_ROLE = 'subscriber';
    private const PLACEHOLDER_DOMAIN = '@sigma.local';
    private const USERNAME_PREFIX = 'profile_';
    private const META_PROFILE_ID = 'sigma_profile_id';
    private const META_AUTH_TYPE = 'sigma_auth_type';
    private const META_IDENTIFIER_TYPE = 'sigma_identifier_type';
    private const META_USER_FLAG = 'sigma_user';
    private const META_USER_INFO = 'sigma_user_info';
    private const META_OPENURL_RESOLVER = 'sigma_openurl_resolver';
    private const META_LAST_LOGIN = 'sigma_last_login';

    /**
     * Find or create WordPress user from SIGMA user info.
     */
    public function findOrCreateUser(array $userInfo): ?\WP_User
    {
        $sigmaData = new SigmaUserData($userInfo);

        if (!$sigmaData->hasValidLicenseeProfile()) {
            error_log('SIGMA OIDC: Cannot create user - no valid licensee profile found');
            return null;
        }

        $profileData = $sigmaData->getLicenseeProfile();
        $user = $this->findUser($profileData['profileId']);

        if ($user) {
            return $this->updateUser($user, $profileData, $sigmaData);
        }

        return $this->createUser($profileData, $sigmaData);
    }

    /**
     * Log in a WordPress user.
     */
    public function loginUser(\WP_User $user): bool
    {
        try {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);

            // Track last login date
            $this->updateLastLoginDate($user->ID);

            do_action('wp_login', $user->user_login, $user);

            return true;
        } catch (\Exception $e) {
            error_log("SIGMA OIDC: Failed to log in user: " . $e->getMessage());
            return false;
        }
    }

    private function findUser(int $profileId): ?\WP_User
    {
        $username = self::USERNAME_PREFIX . $profileId;
        $user = get_user_by('login', $username);

        return $user instanceof \WP_User ? $user : null;
    }

    private function updateUser(\WP_User $user, array $profileData, SigmaUserData $sigmaData): \WP_User
    {
        $this->updateUserDisplayName($user, $profileData['profileName'] ?? null);
        $this->updateUserMeta($user->ID, $sigmaData);

        return $user;
    }

    private function updateUserDisplayName(\WP_User $user, ?string $profileName): void
    {
        if ($profileName && $user->display_name !== $profileName) {
            $result = wp_update_user([
                'ID' => $user->ID,
                'display_name' => $profileName,
                'first_name' => $profileName,
            ]);

            if (is_wp_error($result)) {
                error_log("SIGMA OIDC: Failed to update user display name: " . $result->get_error_message());
            }
        }
    }

    private function updateUserMeta(int $userId, SigmaUserData $sigmaData): void
    {
        $openUrl = $sigmaData->getOpenUrl();

        update_user_meta($userId, self::META_AUTH_TYPE, $sigmaData->getAuthType());
        update_user_meta($userId, self::META_IDENTIFIER_TYPE, $sigmaData->getIdentifierType());
        update_user_meta($userId, self::META_USER_INFO, wp_json_encode($sigmaData->getRawPayload()));

        $this->updateOpenUrlMeta($userId, $openUrl);
    }

    private function createUser(array $profileData, SigmaUserData $sigmaData): ?\WP_User
    {
        $userData = $this->buildUserData($profileData);
        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            error_log('SIGMA OIDC: Failed to create user: ' . $userId->get_error_message());
            return null;
        }

        $this->storeUserMeta($userId, $profileData, $sigmaData);

        $user = get_user_by('ID', $userId);

        return $user;
    }

    private function buildUserData(array $profileData): array
    {
        $profileId = $profileData['profileId'] ?? null;
        $profileName = $profileData['profileName'] ?? "User {$profileId}";

        if (!$profileId) {
            throw new \InvalidArgumentException('Profile ID is required for user creation');
        }

        return [
            'user_login' => self::USERNAME_PREFIX . $profileId,
            'user_email' => $profileId . self::PLACEHOLDER_DOMAIN,
            'user_pass' => wp_generate_password(32, true, true),
            'display_name' => $profileName,
            'first_name' => $profileName,
            'role' => self::DEFAULT_USER_ROLE,
        ];
    }

    private function storeUserMeta(int $userId, array $profileData, SigmaUserData $sigmaData): void
    {
        $openUrl = $sigmaData->getOpenUrl();

        update_user_meta($userId, self::META_PROFILE_ID, $profileData['profileId'] ?? null);
        update_user_meta($userId, self::META_AUTH_TYPE, $sigmaData->getAuthType());
        update_user_meta($userId, self::META_IDENTIFIER_TYPE, $sigmaData->getIdentifierType());
        update_user_meta($userId, self::META_USER_FLAG, true);
        update_user_meta($userId, self::META_USER_INFO, wp_json_encode($sigmaData->getRawPayload()));

        $this->updateOpenUrlMeta($userId, $openUrl);
    }

    private function updateOpenUrlMeta(int $userId, ?array $openUrl): void
    {
        if ($openUrl) {
            update_user_meta($userId, self::META_OPENURL_RESOLVER, $openUrl['resolverUrl'] ?? '');
        } else {
            delete_user_meta($userId, self::META_OPENURL_RESOLVER);
        }
    }

    /**
     * Update the last login date for a user.
     */
    private function updateLastLoginDate(int $userId): void
    {
        update_user_meta($userId, self::META_LAST_LOGIN, current_time('mysql'));
    }

    /**
     * Get the last login date for a user.
     */
    public static function getLastLoginDate(int $userId): ?string
    {
        return get_user_meta($userId, self::META_LAST_LOGIN, true) ?: null;
    }
}
