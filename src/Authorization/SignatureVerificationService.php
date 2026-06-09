<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Authorization;

use Nomokonov\SberSdk\Exception\SignatureVerificationException;

/**
 * Verifies JWT signatures natively with the OpenSSL extension.
 *
 * Sber/SberCA tokens (`id_token`, user-info) are signed with RSA keys
 * (RS256/RS384/RS512), so no external Java/Bouncy Castle process is required —
 * verification is done entirely in PHP.
 */
final readonly class SignatureVerificationService
{
    /**
     * Supported JWS algorithms mapped to OpenSSL digest constants.
     *
     * @var array<string, int>
     */
    private const array ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
    ];

    /** PEM-encoded public key extracted from the certificate. */
    private string $publicKey;

    /**
     * @param string $certificatePath path to the signing certificate (.cer/.crt in PEM or DER),
     *                                 or a PEM public key file
     */
    public function __construct(string $certificatePath)
    {
        if ($certificatePath === '') {
            throw new SignatureVerificationException('Certificate path is required');
        }

        if (!is_readable($certificatePath)) {
            throw new SignatureVerificationException(
                \sprintf('Certificate file not found or not readable: %s', $certificatePath),
            );
        }

        $raw = (string) file_get_contents($certificatePath);
        $this->publicKey = $this->extractPublicKey($raw);
    }

    /**
     * Verify a JWT signature.
     *
     * @return true when the signature is valid
     *
     * @throws SignatureVerificationException when the signature is invalid or cannot be checked
     */
    public function verifyJwt(string $jwt): bool
    {
        if ($jwt === '') {
            throw new SignatureVerificationException('JWT must be a non-empty string');
        }

        $parts = explode('.', $jwt);
        if (\count($parts) !== 3) {
            throw new SignatureVerificationException(
                'Invalid JWT format: expected 3 parts (header.payload.signature)',
            );
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode($this->base64UrlDecode($header64), true);
        if (!\is_array($header) || !isset($header['alg']) || !\is_string($header['alg'])) {
            throw new SignatureVerificationException('Invalid JWT header: missing "alg"');
        }

        $alg = $header['alg'];
        if (!isset(self::ALGORITHMS[$alg])) {
            throw new SignatureVerificationException(\sprintf(
                'Unsupported JWT algorithm "%s". Supported: %s',
                $alg,
                implode(', ', array_keys(self::ALGORITHMS)),
            ));
        }

        $signingInput = $header64 . '.' . $payload64;
        $signature = $this->base64UrlDecode($signature64);

        $result = openssl_verify($signingInput, $signature, $this->publicKey, self::ALGORITHMS[$alg]);

        if ($result === 1) {
            return true;
        }

        if ($result === 0) {
            throw new SignatureVerificationException(
                'Signature verification failed: invalid signature or certificate',
            );
        }

        throw new SignatureVerificationException(
            'Signature verification error: ' . (openssl_error_string() ?: 'unknown OpenSSL error'),
        );
    }

    private function extractPublicKey(string $raw): string
    {
        $pem = str_contains($raw, '-----BEGIN ') ? $raw : $this->derToPem($raw);

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new SignatureVerificationException(
                'Unable to extract public key from certificate: ' . (openssl_error_string() ?: 'unknown error'),
            );
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['key'])) {
            throw new SignatureVerificationException('Unable to read public key details from certificate');
        }

        return (string) $details['key'];
    }

    private function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $pad = (4 - (\strlen($base64) % 4)) % 4;
        $base64 .= str_repeat('=', $pad);

        return (string) base64_decode($base64, true);
    }
}
