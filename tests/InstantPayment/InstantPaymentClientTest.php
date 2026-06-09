<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\InstantPayment;

use GuzzleHttp\Psr7\Response;
use Nomokonov\SberSdk\Exception\ValidationException;
use Nomokonov\SberSdk\InstantPayment\CryptoprofileType;
use Nomokonov\SberSdk\InstantPayment\InstantPaymentClient;
use Nomokonov\SberSdk\Tests\MockClientTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstantPaymentClient::class)]
final class InstantPaymentClientTest extends TestCase
{
    use MockClientTrait;

    public function testBuildPaymentUrlTestEnvironment(): void
    {
        $client = new InstantPaymentClient($this->makeClient([]));

        $url = $client->buildPaymentUrl('ext 1', 'https://shop.test/back', CryptoprofileType::SMS);

        self::assertStringStartsWith('https://efs-sbbol-ift-web.testsbi.sberbank.ru:9443', $url);
        self::assertStringContainsString('/payment-creator/ext%201', $url);
        self::assertStringContainsString('backUrl=https%3A%2F%2Fshop.test%2Fback', $url);
    }

    public function testBuildPaymentUrlProductionSms(): void
    {
        $client = new InstantPaymentClient($this->makeClient([]));

        $url = $client->buildPaymentUrl('ext1', 'https://b', CryptoprofileType::SMS, null, true);

        self::assertStringStartsWith('https://sbi.sberbank.ru:9443', $url);
    }

    public function testBuildPaymentUrlProductionToken(): void
    {
        $client = new InstantPaymentClient($this->makeClient([]));

        $url = $client->buildPaymentUrl('ext1', 'https://b', CryptoprofileType::TOKEN, null, true);

        self::assertStringStartsWith('http://localhost:28016', $url);
    }

    public function testBuildPaymentUrlCustomHost(): void
    {
        $client = new InstantPaymentClient($this->makeClient([]));

        $url = $client->buildPaymentUrl('ext1', 'https://b', CryptoprofileType::SMS, 'https://custom.host');

        self::assertStringStartsWith('https://custom.host/ic/ufs', $url);
    }

    public function testGetPaymentInvoicePostsToCorrectEndpoint(): void
    {
        $apiClient = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['externalId' => 'ext1'])),
        ]);
        $client = new InstantPaymentClient($apiClient);

        $client->getPaymentInvoice('access-token', [
            'externalId' => 'ext1',
            'amount' => 100.0,
            'date' => '2026-01-01',
            'payeeAccount' => '40702810400000012345',
        ]);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/fintech/api/v1/payments/from-invoice', $request->getUri()->getPath());
    }

    public function testGetPaymentInvoiceRejectsInvalidPayload(): void
    {
        $client = new InstantPaymentClient($this->makeClient([]));

        $this->expectException(ValidationException::class);
        $client->getPaymentInvoice('access-token', ['amount' => -5]);
    }
}
