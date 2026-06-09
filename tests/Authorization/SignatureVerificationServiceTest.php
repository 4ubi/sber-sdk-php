<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Authorization;

use Nomokonov\SberSdk\Authorization\SignatureVerificationService;
use Nomokonov\SberSdk\Exception\SignatureVerificationException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignatureVerificationService::class)]
final class SignatureVerificationServiceTest extends TestCase
{
    private string $certFile;

    private OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        self::assertNotFalse($res, 'Unable to generate RSA key');
        $this->privateKey = $res;

        // Self-signed certificate carrying the RSA public key.
        $csr = openssl_csr_new(['commonName' => 'test'], $this->privateKey, $config);
        self::assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $this->privateKey, 1, $config);
        self::assertNotFalse($cert);
        openssl_x509_export($cert, $certPem);

        $this->certFile = (string) tempnam(sys_get_temp_dir(), 'sigtest-');
        file_put_contents($this->certFile, $certPem);
    }

    protected function tearDown(): void
    {
        @unlink($this->certFile);
    }

    public function testVerifiesValidRs256Signature(): void
    {
        $jwt = $this->makeJwt(['sub' => '123'], 'RS256', OPENSSL_ALGO_SHA256);

        $service = new SignatureVerificationService($this->certFile);

        self::assertTrue($service->verifyJwt($jwt));
    }

    public function testRejectsTamperedSignature(): void
    {
        $jwt = $this->makeJwt(['sub' => '123'], 'RS256', OPENSSL_ALGO_SHA256);
        // Flip the payload, keep the original signature.
        [$h, , $s] = explode('.', $jwt);
        $tampered = $h . '.' . $this->b64(json_encode(['sub' => 'evil'])) . '.' . $s;

        $service = new SignatureVerificationService($this->certFile);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('invalid signature');
        $service->verifyJwt($tampered);
    }

    public function testRejectsUnsupportedAlgorithm(): void
    {
        $jwt = $this->b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']))
            . '.' . $this->b64(json_encode(['sub' => '1']))
            . '.' . $this->b64('signature');

        $service = new SignatureVerificationService($this->certFile);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm');
        $service->verifyJwt($jwt);
    }

    public function testRejectsMalformedJwt(): void
    {
        $service = new SignatureVerificationService($this->certFile);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('expected 3 parts');
        $service->verifyJwt('only.two');
    }

    public function testConstructorRejectsMissingFile(): void
    {
        $this->expectException(SignatureVerificationException::class);
        new SignatureVerificationService('/nonexistent/path.cer');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function makeJwt(array $payload, string $alg, int $opensslAlg): string
    {
        $header64 = $this->b64(json_encode(['alg' => $alg, 'typ' => 'JWT']));
        $payload64 = $this->b64(json_encode($payload));
        $signingInput = $header64 . '.' . $payload64;

        openssl_sign($signingInput, $signature, $this->privateKey, $opensslAlg);

        return $signingInput . '.' . $this->b64($signature);
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
