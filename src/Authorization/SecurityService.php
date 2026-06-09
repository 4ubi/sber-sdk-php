<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Authorization;

/**
 * PKCE helper for the OAuth authorization-code flow.
 *
 * Ported from lib/authorization/securityService.js of the Node.js SDK.
 */
final class SecurityService
{
    /**
     * Generate a PKCE code verifier (32 random bytes, base64url encoded).
     */
    public function generatePkceCodeVerifier(): string
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    /**
     * Derive the PKCE code challenge from a verifier using SHA-256.
     */
    public function generatePkceCodeChallenge(string $codeVerifier): string
    {
        return $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
    }

    /**
     * Encode bytes as base64url without padding.
     */
    public function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
