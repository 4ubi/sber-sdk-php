<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Authorization;

use Nomokonov\SberSdk\Authorization\Schemas;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schemas::class)]
final class SchemasTest extends TestCase
{
    public function testValidAuthorizationRequestPasses(): void
    {
        $errors = Schemas::authorizationRequest()->validate([
            'grant_type' => 'authorization_code',
            'code' => str_repeat('a', 38),
            'client_id' => '1111',
            'redirect_uri' => 'http://ya.ru',
            'client_secret' => 'secret',
        ]);

        self::assertSame([], $errors);
    }

    public function testAuthorizationRequestRejectsBadCode(): void
    {
        $errors = Schemas::authorizationRequest()->validate([
            'grant_type' => 'authorization_code',
            'code' => 'too-short',
            'client_id' => '1111',
            'redirect_uri' => 'http://ya.ru',
            'client_secret' => 'secret',
        ]);

        self::assertNotSame([], $errors);
    }

    public function testRevokeTokenRequiresValidHint(): void
    {
        $valid = Schemas::revokeTokenRequest()->validate([
            'client_id' => '1111',
            'client_secret' => 'secret',
            'token' => str_repeat('b', 38),
            'token_type_hint' => 'refresh_token',
        ]);
        self::assertSame([], $valid);

        $invalid = Schemas::revokeTokenRequest()->validate([
            'client_id' => '1111',
            'client_secret' => 'secret',
            'token' => str_repeat('b', 38),
            'token_type_hint' => 'bogus',
        ]);
        self::assertNotSame([], $invalid);
    }
}
