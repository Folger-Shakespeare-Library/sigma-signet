<?php

namespace SigmaSignet;

/**
 * Data Transfer Object for SIGMA user payload
 */
class SigmaUserData
{
    private const PROFILE_TYPE_INDIVIDUAL = 'INDIVIDUAL';
    private const PROFILE_TYPE_ORGANIZATION = 'ORGANIZATION';
    private const UNKNOWN_AUTH_TYPE = 'UNKNOWN';

    private array $payload;
    private ?int $licenseeProfileId = null;

    public function __construct(array $sigmaPayload)
    {
        $this->payload = $sigmaPayload;
        $this->licenseeProfileId = $this->extractLicenseeProfileId();
    }

    public function getAuthType(): string
    {
        return $this->payload['authentication_type'] ?? self::UNKNOWN_AUTH_TYPE;
    }

    public function getLicenseeProfile(): ?array
    {
        if (!$this->licenseeProfileId) {
            return null;
        }

        $authenticatedProfiles = $this->payload['authenticated_profiles'] ?? [];

        // Check individual profile
        $individualProfile = $authenticatedProfiles['individualProfile'] ?? null;
        if ($individualProfile && ($individualProfile['profileId'] ?? null) == $this->licenseeProfileId) {
            return $this->buildProfileData($individualProfile);
        }

        // Check organization profiles
        $organizationProfiles = $authenticatedProfiles['organizationProfiles'] ?? [];
        foreach ($organizationProfiles as $profile) {
            if (($profile['profileId'] ?? null) == $this->licenseeProfileId) {
                return $this->buildProfileData($profile);
            }
        }

        return null;
    }

    public function getIdentifierType(): ?string
    {
        $profile = $this->getLicenseeProfile();
        if (!$profile || !isset($profile['identifierType'])) {
            return null;
        }

        return strtolower($profile['identifierType']);
    }

    public function getOpenUrl(): ?array
    {
        if (!$this->licenseeProfileId) {
            return null;
        }

        $profileExtended = $this->payload['profile_extended'] ?? [];

        if (isset($profileExtended[$this->licenseeProfileId])) {
            $profileData = $profileExtended[$this->licenseeProfileId];
            $openUrl = $profileData['openUrl'] ?? [];

            if (!empty($openUrl['resolverUrl'])) {
                return [
                    'resolverUrl' => $openUrl['resolverUrl'],
                ];
            }
        }

        return null;
    }

    public function hasValidLicenseeProfile(): bool
    {
        return $this->licenseeProfileId !== null && $this->getLicenseeProfile() !== null;
    }

    public function getRawPayload(): array
    {
        return $this->payload;
    }

    /**
     * Extract licensee profile ID, prioritizing individual over organization.
     * 
     * Business rule: Individual licensees take precedence over organization licensees
     * because personal licenses are more specific and restrictive than institutional access.
     * This ensures users with both personal and organizational licenses use their personal
     * profile for authentication and access control.
     */
    private function extractLicenseeProfileId(): ?int
    {
        $licenseAgreements = $this->payload['license_agreements'] ?? [];

        $individualLicenseeId = null;
        $organizationLicenseeId = null;

        foreach ($licenseAgreements as $agreement) {
            $licenseAgreement = $agreement['license_agreement'] ?? [];
            $licenseeProfile = $licenseAgreement['licenseeProfile'] ?? [];

            if (!isset($licenseeProfile['id'], $licenseeProfile['profileType'])) {
                continue;
            }

            $profileId = (int) $licenseeProfile['id'];
            $profileType = $licenseeProfile['profileType'];

            if ($profileType === self::PROFILE_TYPE_INDIVIDUAL) {
                $individualLicenseeId = $profileId;
            } elseif ($profileType === self::PROFILE_TYPE_ORGANIZATION) {
                $organizationLicenseeId = $profileId;
            }
        }

        // Prioritize individual profile ID
        return $individualLicenseeId ?? $organizationLicenseeId;
    }

    private function buildProfileData(array $profile): array
    {
        return [
            'profileId' => $profile['profileId'] ?? null,
            'profileName' => $profile['profileName'] ?? null,
            'identifierType' => $profile['identifierType'] ?? null,
        ];
    }
}
