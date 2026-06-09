<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Authorization;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nomokonov\SberSdk\Authorization\ApiClient;
use Nomokonov\SberSdk\Exception\ConfigurationException;
use Nomokonov\SberSdk\Exception\SberApiException;
use Nomokonov\SberSdk\Exception\ValidationException;
use Nomokonov\SberSdk\Tests\MockClientTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiClient::class)]
final class ApiClientTest extends TestCase
{
    use MockClientTrait;

    private const array VALID_AUTH = [
        'code' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'client_id' => '1111',
        'redirect_uri' => 'http://ya.ru',
        'client_secret' => 'secret',
    ];

    public function testMissingHostThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        new ApiClient([]);
    }

    public function testGetAccessTokenPostsFormUrlEncoded(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'tok', 'id_token' => 'x'])),
        ]);

        $result = $client->getAccessToken(self::VALID_AUTH);

        self::assertSame('tok', $result['access_token']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/ic/sso/api/oauth/token', $request->getUri()->getPath());
        self::assertStringContainsString('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('grant_type=authorization_code', (string) $request->getBody());
        self::assertSame(ApiClient::USER_AGENT, $request->getHeaderLine('User-Agent'));
    }

    public function testInvalidRequestRaisesValidationExceptionBeforeHttp(): void
    {
        $client = $this->makeClient([]); // no responses queued; must fail before any call

        $this->expectException(ValidationException::class);
        $client->getAccessToken(['client_id' => '1111']);
    }

    public function testGetUserInfoDecodesJwtPayload(): void
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode(['name' => 'Ivan'])), '+/', '-_'), '=');
        $jwt = 'eyJhbGciOiJSUzI1NiJ9.' . $payload . '.signature';

        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/jwt'], $jwt),
        ]);

        $result = $client->getUserInfo('access-token');

        self::assertSame('Ivan', $result['userInfoBodyResponse']['name']);
        self::assertSame($jwt, $result['jwt']);
        self::assertStringContainsString('Bearer access-token', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testRetriesOnServerErrorThenSucceeds(): void
    {
        $client = $this->makeClient([
            new Response(500, [], 'boom'),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true])),
        ], ['maxRetries' => 2]);

        $result = $client->getAccessToken(self::VALID_AUTH);

        self::assertTrue($result['ok']);
        self::assertCount(2, $this->history);
    }

    public function testFailsAfterExhaustingRetries(): void
    {
        $client = $this->makeClient([
            new Response(500, [], 'e1'),
            new Response(500, [], 'e2'),
            new Response(500, [], 'e3'),
        ], ['maxRetries' => 2]);

        try {
            $client->getAccessToken(self::VALID_AUTH);
            self::fail('Expected SberApiException');
        } catch (SberApiException $e) {
            self::assertStringContainsString('Request failed after retries: 500', $e->getMessage());
        }

        self::assertCount(3, $this->history);
    }

    public function testConnectTimeoutWrappedAsTimeout(): void
    {
        $request = new Request('POST', '/ic/sso/api/oauth/token');
        $client = $this->makeClient([
            new ConnectException('cURL error 28: Connection timed out', $request),
            new ConnectException('cURL error 28: Connection timed out', $request),
        ], ['maxRetries' => 1]);

        $this->expectException(SberApiException::class);
        $this->expectExceptionMessage('Request timed out after retries');
        $client->getAccessToken(self::VALID_AUTH);
    }

    public function testClientErrorIsNotRetried(): void
    {
        $client = $this->makeClient([
            new Response(400, [], 'bad request'),
        ], ['maxRetries' => 3]);

        try {
            $client->getAccessToken(self::VALID_AUTH);
            self::fail('Expected SberApiException');
        } catch (SberApiException $e) {
            self::assertStringContainsString('Request failed after retries: 400', $e->getMessage());
        }

        self::assertCount(1, $this->history);
    }

    public function testGetChangeClientSecretGeneratesNewSecret(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['clientSecretExpiration' => 123456])),
        ]);

        $result = $client->getChangeClientSecret('access-token', [
            'client_id' => '1111',
            'client_secret' => 'old-secret',
        ]);

        self::assertSame(123456, $result['clientSecretExpiration']);
        self::assertSame(40, \strlen($result['new_client_secret']));
    }
}
