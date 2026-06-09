<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\InstantPayment;

use Nomokonov\SberSdk\Authorization\ApiClient;

/**
 * Instant payment module: create payment drafts from an invoice (fixed, budget
 * or free requisites), track their status and build the payment confirmation URL.
 *
 * Ported from lib/instantpayment/instantpaymentClient.js of the Node.js SDK.
 */
final readonly class InstantPaymentClient
{
    private const string TEST_HOST = 'https://efs-sbbol-ift-web.testsbi.sberbank.ru:9443';
    private const string PROM_SMS_HOST = 'https://sbi.sberbank.ru:9443';
    private const string PROM_TOKEN_HOST = 'http://localhost:28016';

    public function __construct(private ApiClient $apiClient)
    {
    }

    /**
     * @param array<string, mixed> $paymentInvoiceReq
     */
    public function getPaymentInvoice(string $accessToken, array $paymentInvoiceReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v1/payments/from-invoice',
            $paymentInvoiceReq,
            Schemas::paymentInvoiceRequest(),
            $this->auth($accessToken),
            'POST',
        );
    }

    /**
     * @param array<string, mixed> $paymentInvoiceBudgetReq
     */
    public function getPaymentInvoiceBudget(string $accessToken, array $paymentInvoiceBudgetReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v1/payments/from-invoice-budget',
            $paymentInvoiceBudgetReq,
            Schemas::paymentInvoiceBudgetRequest(),
            $this->auth($accessToken),
            'POST',
        );
    }

    /**
     * @param array<string, mixed> $paymentInvoiceAnyReq
     */
    public function getPaymentInvoiceAny(string $accessToken, array $paymentInvoiceAnyReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v1/payments/from-invoice-any',
            $paymentInvoiceAnyReq,
            Schemas::paymentInvoiceFromAnyRequest(),
            $this->auth($accessToken),
            'POST',
        );
    }

    public function getPaymentState(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/payments/%s/state', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getPayment(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/payments/%s', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    /**
     * Build the URL where the client confirms (signs) the payment.
     *
     * @param string|null $host optional custom host; falls back to the standard
     *                          production/test hosts based on $isProd and the profile type
     */
    public function buildPaymentUrl(
        string $externalId,
        string $backUrl,
        CryptoprofileType $cryptoprofileType,
        ?string $host = null,
        bool $isProd = false,
    ): string {
        if ($host === null || trim($host) === '') {
            $konturBankUrl = $isProd
                ? ($cryptoprofileType === CryptoprofileType::SMS ? self::PROM_SMS_HOST : self::PROM_TOKEN_HOST)
                : self::TEST_HOST;
        } else {
            $konturBankUrl = $host;
        }

        return \sprintf(
            '%s/ic/ufs/rpp-light/index.html#/payment-creator/%s?backUrl=%s',
            $konturBankUrl,
            rawurlencode($externalId),
            rawurlencode($backUrl),
        );
    }

    /**
     * @return array<string, string>
     */
    private function auth(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
    }
}
