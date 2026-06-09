<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Authorization;

use Nomokonov\SberSdk\Authorization\SecurityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityService::class)]
final class SecurityServiceTest extends TestCase
{
    public function testCodeVerifierIsBase64UrlAnd43Chars(): void
    {
        $verifier = new SecurityService()->generatePkceCodeVerifier();

        // 32 bytes base64url (no padding) -> 43 characters
        self::assertSame(43, \strlen($verifier));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
    }

    public function testCodeChallengeMatchesRfc7636Vector(): void
    {
        // RFC 7636 Appendix B reference vector.
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $challenge = new SecurityService()->generatePkceCodeChallenge($verifier);

        self::assertSame($expectedChallenge, $challenge);
    }

    public function testBase64UrlEncodeStripsPadding(): void
    {
        $service = new SecurityService();

        self::assertSame('Zm9vYmFy', $service->base64UrlEncode('foobar'));
        self::assertSame('Zm9vYmE', $service->base64UrlEncode('fooba'));
    }
}
