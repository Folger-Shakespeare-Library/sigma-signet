<?php

namespace SigmaSignet;

/**
 * Handles authorization checks for SIGMA users
 */
class Authorizer
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Check if user is authorized for WSB access.
     *
     * @param array $userInfo The decoded userinfo from SIGMA.
     * @return bool True if authorized, false otherwise.
     */
    public function isAuthorized(array $userInfo): bool
    {
        $authorized = $this->hasContentAccess($userInfo, 'WSB');

        $this->settings->debugLog(
            $authorized
                ? 'Authorization check: WSB access granted'
                : 'Authorization check: WSB access denied'
        );

        return $authorized;
    }

    /**
     * Check if user has access to specific content.
     */
    private function hasContentAccess(array $userInfo, string $contentId): bool
    {
        foreach ($this->getSubscriptions($userInfo) as $subscription) {
            if ($this->subscriptionGrantsAccess($subscription, $contentId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's subscriptions from SIGMA response.
     */
    private function getSubscriptions(array $userInfo): array
    {
        return $userInfo['license_agreements'] ?? [];
    }

    /**
     * Check if a subscription grants access to specific content.
     */
    private function subscriptionGrantsAccess(array $subscription, string $contentId): bool
    {
        $bundles = $subscription['license_agreement']['contentBundles'] ?? [];

        foreach ($bundles as $bundle) {
            if ($this->bundleIncludesContent($bundle, $contentId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a bundle includes specific content.
     */
    private function bundleIncludesContent(array $bundle, string $contentId): bool
    {
        $identifiers = $bundle['contentIdentifiers'] ?? [];

        foreach ($identifiers as $identifier) {
            if (($identifier['contentIdentifier'] ?? '') === $contentId) {
                return true;
            }
        }

        return false;
    }
}
