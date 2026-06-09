<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Authorization;

use Nomokonov\SberSdk\Authorization\MaskingInterceptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaskingInterceptor::class)]
final class MaskingInterceptorTest extends TestCase
{
    public function testMaskHeadersRedactsAuthorization(): void
    {
        $masked = MaskingInterceptor::maskHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Trace' => 'keep-me',
        ]);

        self::assertSame('****', $masked['Authorization']);
        self::assertSame('keep-me', $masked['X-Trace']);
    }

    public function testMaskJsonBodyMasksSensitiveFields(): void
    {
        $json = json_encode([
            'payerName' => 'Ivan Ivanov',
            'payerAccount' => '40702810400000012345',
            'amount' => 1000,
            'note' => 'public',
        ]);

        $masked = MaskingInterceptor::maskBody($json, 'application/json');
        self::assertIsString($masked);
        $decoded = json_decode($masked, true);

        self::assertSame('****', $decoded['payerName']);
        self::assertSame('****', $decoded['amount']);
        self::assertSame('40702810*******2345', $decoded['payerAccount']);
        self::assertSame('public', $decoded['note']);
    }

    public function testMaskUrlEncodedBody(): void
    {
        $masked = MaskingInterceptor::maskBody('client_secret=abc&grant_type=refresh_token', 'application/x-www-form-urlencoded');

        self::assertStringContainsString('client_secret=****', $masked);
        self::assertStringContainsString('grant_type=refresh_token', $masked);
    }

    public function testJwtBodyIsFullyMasked(): void
    {
        $jwt = 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjMifQ.c2lnbmF0dXJl';

        self::assertTrue(MaskingInterceptor::isJwt($jwt));
        self::assertSame('*****', MaskingInterceptor::maskBody($jwt, 'application/jwt'));
    }

    public function testShouldExcludeLogging(): void
    {
        self::assertTrue(MaskingInterceptor::shouldExcludeLogging('https://h/fintech/api/v1/dicts?name=banks'));
        self::assertTrue(MaskingInterceptor::shouldExcludeLogging('https://h/v1/client-info'));
        self::assertFalse(MaskingInterceptor::shouldExcludeLogging('https://h/fintech/api/v1/payments'));
    }

    public function testMaskUrlMasksQueryParams(): void
    {
        $masked = MaskingInterceptor::maskUrl('https://h/api?accountNumber=40702810400000012345&page=1');

        self::assertStringContainsString('accountNumber=', $masked);
        self::assertStringNotContainsString('40702810400000012345', $masked);
        self::assertStringContainsString('page=1', $masked);
    }
}
