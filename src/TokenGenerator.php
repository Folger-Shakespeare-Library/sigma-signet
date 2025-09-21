<?php

namespace SigmaSignet;

use Jose\Component\Core\JWK;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128GCM;
use Jose\Component\Encryption\Serializer\CompactSerializer;

/**
 * JWT Token Generator for SIGMA OIDC authentication
 */
class TokenGenerator
{
    private Settings $settings;
    private bool $testMode;

    public function __construct(Settings $settings, bool $testMode = false)
    {
        $this->settings = $settings;
        $this->testMode = $testMode;
    }

    /**
     * Generate JWT auth token for iframe security
     *
     * @return string|null The encrypted JWT token, or null if settings not configured
     */
    public function generateAuthToken(): ?string
    {
        if (!$this->settings->isConfigured()) {
            error_log('Cannot generate auth token: settings not configured');
            return null;
        }

        // Return placeholder in test mode
        if ($this->testMode) {
            $this->settings->debugLog("Test mode - generating placeholder JWT for client_id: " . $this->settings->get('client_id'));
            return 'test-jwt-token';
        }

        try {
            // Get the public key from SIGMA's JWKS endpoint
            $publicKey = $this->getSigmaPublicKey();
            if (!$publicKey) {
                error_log('Cannot generate auth token: failed to get public key');
                return null;
            }

            // Build JWT claims
            $claims = [
                'iss' => $this->settings->get('client_id'),
                'secret' => $this->settings->get('client_secret'),
                'exp' => time() + 60, // 60 seconds expiry
                'jti' => wp_generate_uuid4(),
            ];

            // Create JWE with proper algorithm managers
            $keyEncryptionAlgorithmManager = new AlgorithmManager([
                new RSAOAEP256()
            ]);

            $contentEncryptionAlgorithmManager = new AlgorithmManager([
                new A128GCM()
            ]);

            $jweBuilder = new JWEBuilder(
                $keyEncryptionAlgorithmManager,
                $contentEncryptionAlgorithmManager,
                null // No compression algorithms needed
            );

            $jwe = $jweBuilder
                ->create()
                ->withPayload(json_encode($claims))
                ->withSharedProtectedHeader([
                    'alg' => 'RSA-OAEP-256',
                    'enc' => 'A128GCM'
                ])
                ->addRecipient($publicKey)
                ->build();

            $serializer = new CompactSerializer();
            $token = $serializer->serialize($jwe, 0);

            $this->settings->debugLog('JWT auth token generated successfully');
            return $token;
        } catch (\Exception $e) {
            error_log('JWT generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get SIGMA's public encryption key
     */
    private function getSigmaPublicKey(): ?JWK
    {
        try {
            $idpUrl = $this->settings->get('idp_url');
            $configUrl = rtrim($idpUrl, '/') . '/.well-known/openid-configuration';

            // Get OpenID configuration
            $configResponse = wp_remote_get($configUrl);
            if (is_wp_error($configResponse)) {
                error_log('Failed to get OpenID configuration: ' . $configResponse->get_error_message());
                return null;
            }

            $config = json_decode(wp_remote_retrieve_body($configResponse), true);
            if (!isset($config['jwks_uri'])) {
                error_log('No jwks_uri found in OpenID configuration');
                return null;
            }

            // Get JWKS
            $jwksResponse = wp_remote_get($config['jwks_uri']);
            if (is_wp_error($jwksResponse)) {
                error_log('Failed to get JWKS: ' . $jwksResponse->get_error_message());
                return null;
            }

            $jwks = json_decode(wp_remote_retrieve_body($jwksResponse), true);
            if (!isset($jwks['keys'])) {
                error_log('No keys found in JWKS');
                return null;
            }

            // Find the encryption key (use="enc")
            foreach ($jwks['keys'] as $keyData) {
                if (isset($keyData['use']) && $keyData['use'] === 'enc') {
                    // Remove the algorithm restriction to allow encryption use
                    unset($keyData['alg']);
                    $this->settings->debugLog('Found encryption key, removed alg restriction: ' . $keyData['kid']);
                    return JWK::createFromJson(json_encode($keyData));
                }
            }

            // Try looking for keys by key ID if no 'use=enc' found
            foreach ($jwks['keys'] as $keyData) {
                if (isset($keyData['kid']) && str_contains($keyData['kid'], 'enc')) {
                    unset($keyData['alg']);
                    $this->settings->debugLog('Found encryption key by kid: ' . $keyData['kid']);
                    return JWK::createFromJson(json_encode($keyData));
                }
            }

            error_log('No encryption key found in JWKS. Available keys: ' . json_encode($jwks['keys']));
            return null;
        } catch (\Exception $e) {
            error_log('Failed to get SIGMA public key: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if token generation is possible
     */
    public function canGenerateToken(): bool
    {
        return $this->settings->isConfigured();
    }
}
