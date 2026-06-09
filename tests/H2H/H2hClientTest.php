<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\H2H;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Nomokonov\SberSdk\Exception\ValidationException;
use Nomokonov\SberSdk\H2H\H2hClient;
use Nomokonov\SberSdk\Tests\MockClientTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(H2hClient::class)]
final class H2hClientTest extends TestCase
{
    use MockClientTrait;

    public function testGetDictionaryDecodesAndUnzipsArchive(): void
    {
        $base64Zip = $this->makeZipBase64('banks.txt', 'SBERBANK;044525225');

        $apiClient = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'name' => 'banks',
                'archive' => $base64Zip,
            ])),
        ]);
        $h2h = new H2hClient($apiClient);

        $result = $h2h->getDictionary('access-token', 'banks');

        self::assertSame('banks', $result['name']);
        self::assertSame('SBERBANK;044525225', $result['content']);
        self::assertSame('/fintech/api/v1/dicts', $this->lastRequest()->getUri()->getPath());
        self::assertStringContainsString('name=banks', $this->lastRequest()->getUri()->getQuery());
    }

    public function testGetPaymentLinkListRejectsInvalidUuid(): void
    {
        $apiClient = $this->makeClient([]);
        $h2h = new H2hClient($apiClient);

        $this->expectException(InvalidArgumentException::class);
        $h2h->getPaymentLinkList('access-token', 'not-a-uuid');
    }

    public function testGetPaymentBuildsCorrectEndpoint(): void
    {
        $apiClient = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['externalId' => 'abc'])),
        ]);
        $h2h = new H2hClient($apiClient);

        $h2h->getPayment('access-token', 'abc-123');

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/fintech/api/v1/payments/abc-123', $request->getUri()->getPath());
        self::assertStringContainsString('Bearer access-token', $request->getHeaderLine('Authorization'));
    }

    public function testCreatePaymentRejectsInvalidPayload(): void
    {
        $apiClient = $this->makeClient([]);
        $h2h = new H2hClient($apiClient);

        $this->expectException(ValidationException::class);
        $h2h->createPayment('access-token', ['amount' => 'oops']);
    }

    public function testPrintCertReturnsRawBytes(): void
    {
        $apiClient = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/pdf'], "%PDF-1.4\nbinary"),
        ]);
        $h2h = new H2hClient($apiClient);

        $bytes = $h2h->printCert('access-token', 'ext-1');

        self::assertStringStartsWith('%PDF-1.4', $bytes);
        self::assertSame('/fintech/api/v2/crypto/cert-requests/ext-1/print', $this->lastRequest()->getUri()->getPath());
        self::assertFalse($this->lastRequest()->hasHeader('X-Response-Type'));
    }

    private function makeZipBase64(string $entryName, string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ziptest-');
        self::assertIsString($tmp);

        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString($entryName, $content);
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return base64_encode($bytes);
    }
}
