<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\H2H;

use InvalidArgumentException;
use Nomokonov\SberSdk\Authorization\ApiClient;
use Nomokonov\SberSdk\Exception\SberApiException;
use ZipArchive;

/**
 * H2H (host-to-host) direct integration module: dictionaries, client info,
 * crypto/certificate operations, payments, statements, payrolls and SBP B2B links.
 *
 * Ported from lib/h2h/h2hClient.js of the Node.js SDK.
 */
final readonly class H2hClient
{
    private const string UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(private ApiClient $apiClient)
    {
    }

    /**
     * @return array{name: mixed, content: string}
     */
    public function getDictionary(string $accessToken, string $name): array
    {
        $response = $this->apiClient->sendRequest(
            '/fintech/api/v1/dicts',
            ['name' => $name],
            null,
            $this->auth($accessToken),
            'GET',
        );

        if (!\is_array($response) || !isset($response['archive'])) {
            throw new SberApiException('Invalid server response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return [
            'name' => $response['name'] ?? null,
            'content' => $this->decodeAndUnzip((string) $response['archive']),
        ];
    }

    public function getClientInfo(string $accessToken): mixed
    {
        return $this->apiClient->sendRequest('/fintech/api/v1/client-info', null, null, $this->auth($accessToken), 'GET');
    }

    public function getCrypto(string $accessToken): mixed
    {
        return $this->apiClient->sendRequest('/fintech/api/v1/crypto', null, null, $this->auth($accessToken), 'GET');
    }

    public function getCryptoEio(string $accessToken): mixed
    {
        return $this->apiClient->sendRequest('/fintech/api/v1/crypto/eio', null, null, $this->auth($accessToken), 'GET');
    }

    /**
     * @param array<string, mixed> $certRequestReq
     */
    public function certificateRequest(string $accessToken, array $certRequestReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v2/crypto/cert-requests',
            $certRequestReq,
            Schemas::certRequest(),
            $this->auth($accessToken),
            'POST',
        );
    }

    /**
     * @param array<string, mixed> $certRequestReqEio
     */
    public function certificateRequestEio(string $accessToken, array $certRequestReqEio): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v2/crypto/cert-requests/eio',
            $certRequestReqEio,
            Schemas::certRequestEio(),
            $this->auth($accessToken),
            'POST',
        );
    }

    public function activateCertEio(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/crypto/cert-requests/eio/%s/activate', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'POST',
        );
    }

    public function activateCert(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/crypto/cert-requests/%s/activate', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'POST',
        );
    }

    /**
     * Returns the raw bytes of the certificate request print form (PDF).
     */
    public function printCert(string $accessToken, string $externalId): string
    {
        return $this->apiClient->sendRequestPrint(
            \sprintf('/fintech/api/v2/crypto/cert-requests/%s/print', $externalId),
            null,
            null,
            $this->auth($accessToken) + ['X-Response-Type' => 'arraybuffer'],
            'GET',
        );
    }

    public function getCertState(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/crypto/cert-requests/%s/state', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getCertStateEio(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/crypto/cert-requests/eio/%s/state', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    /**
     * @param array<string, mixed> $paymentReq
     */
    public function createPayment(string $accessToken, array $paymentReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v1/payments',
            $paymentReq,
            Schemas::payment(),
            $this->auth($accessToken),
            'POST',
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

    public function getPaymentDocState(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/payments/%s/state', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getStatementSummary(string $accessToken, string $accountNumber, string $statementDate): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v2/statement/summary',
            ['accountNumber' => $accountNumber, 'statementDate' => $statementDate],
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getStatementTransactionId(
        string $accessToken,
        string $id,
        string $accountNumber,
        string $operationDate,
    ): mixed {
        return $this->apiClient->sendRequest(
            '/fintech/api/v2/statement/transactionId',
            ['id' => $id, 'accountNumber' => $accountNumber, 'operationDate' => $operationDate],
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getStatementTransactions(
        string $accessToken,
        string $accountNumber,
        string $statementDate,
        int|string|null $page = null,
        ?string $curFormat = null,
    ): mixed {
        return $this->apiClient->sendRequest(
            '/fintech/api/v2/statement/transactions',
            [
                'accountNumber' => $accountNumber,
                'statementDate' => $statementDate,
                'page' => $page,
                'curFormat' => $curFormat,
            ],
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    /**
     * @param array<string, mixed> $payrollReq
     */
    public function createPayroll(string $accessToken, array $payrollReq): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/v1/payrolls',
            $payrollReq,
            Schemas::payroll(),
            $this->auth($accessToken),
            'POST',
        );
    }

    public function getPayroll(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/payrolls/%s', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    public function getPayrollState(string $accessToken, string $externalId): mixed
    {
        return $this->apiClient->sendRequest(
            \sprintf('/fintech/api/v1/payrolls/%s', $externalId),
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    /**
     * @param array<string, mixed> $linkCreateRequest
     */
    public function createPaymentLink(string $accessToken, array $linkCreateRequest): mixed
    {
        return $this->apiClient->sendRequest(
            '/fintech/api/sbpb2b/v1/sbp/payment-link/create',
            $linkCreateRequest,
            Schemas::sbpB2BLinkCreateRequest(),
            $this->auth($accessToken),
            'POST',
        );
    }

    public function getPaymentLinkList(string $accessToken, string $linkId): mixed
    {
        if (preg_match(self::UUID_REGEX, $linkId) !== 1) {
            throw new InvalidArgumentException(
                'Invalid linkId: must be a valid UUID (e.g. 550e8400-e29b-41d4-a716-446655440000)',
            );
        }

        return $this->apiClient->sendRequest(
            '/fintech/api/sbpb2b/v1/sbp/payment-link/getTransactionList/' . $linkId,
            null,
            null,
            $this->auth($accessToken),
            'GET',
        );
    }

    /**
     * @return array<string, string>
     */
    private function auth(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
    }

    private function decodeAndUnzip(string $base64EncodedZip): string
    {
        $decoded = base64_decode($base64EncodedZip, true);
        if ($decoded === false) {
            throw new SberApiException('Error decoding archive: invalid base64 content');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'sber-dict-');
        if ($tmpFile === false) {
            throw new SberApiException('Error decoding archive: unable to create temporary file');
        }

        try {
            file_put_contents($tmpFile, $decoded);

            $zip = new ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                throw new SberApiException('Error decoding archive: cannot open ZIP');
            }

            $content = '';
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $data = $zip->getFromIndex($i);
                if ($data !== false) {
                    $content .= $data;
                }
            }
            $zip->close();

            return $content;
        } finally {
            @unlink($tmpFile);
        }
    }
}
