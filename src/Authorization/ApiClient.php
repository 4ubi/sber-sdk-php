<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Authorization;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Nomokonov\SberSdk\Exception\ConfigurationException;
use Nomokonov\SberSdk\Exception\SberApiException;
use Nomokonov\SberSdk\Validation\Schema;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Core HTTP client for SberAPI: authorization, TLS with a client certificate,
 * retries with exponential backoff, masked logging and the OAuth token methods.
 *
 * Ported from lib/authorization/client.js of the Node.js SDK.
 */
class ApiClient
{
    public const string USER_AGENT = 'SberApiSDK_PHP';

    private const int DEFAULT_TIMEOUT_MS = 60_000;
    private const int DEFAULT_MAX_RETRIES = 3;
    private const int DEFAULT_RETRY_DELAY_MS = 1000;
    private const string ALLOWED_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    private const string TOKEN_ENDPOINT = '/ic/sso/api/oauth/token';

    private readonly string $host;
    private readonly int $connectTimeoutMs;
    private readonly int $readTimeoutMs;
    private readonly bool $enableLogs;
    private readonly int $maxRetries;
    private readonly int $retryDelayMs;

    private readonly ClientInterface $httpClient;

    /** @var list<string> temporary files to remove on destruction */
    private array $tempFiles = [];

    /**
     * @param array<string, mixed>  $config     host, p12Path, p12Password, caPath, connectTimeout, readTimeout, enableLogs, maxRetries, retryDelay
     * @param ClientInterface|null   $httpClient inject a pre-built client (e.g. for tests); when null one is built from the certificate config
     */
    public function __construct(
        array $config,
        ?ClientInterface $httpClient = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $host = $config['host'] ?? null;
        if (!\is_string($host) || $host === '') {
            throw new ConfigurationException('Host is required');
        }

        $this->host = $host;
        $this->connectTimeoutMs = (int) ($config['connectTimeout'] ?? self::DEFAULT_TIMEOUT_MS);
        $this->readTimeoutMs = (int) ($config['readTimeout'] ?? self::DEFAULT_TIMEOUT_MS);
        $this->enableLogs = (bool) ($config['enableLogs'] ?? false);
        $this->maxRetries = isset($config['maxRetries']) ? (int) $config['maxRetries'] : self::DEFAULT_MAX_RETRIES;
        $this->retryDelayMs = (int) ($config['retryDelay'] ?? self::DEFAULT_RETRY_DELAY_MS);

        $this->httpClient = $httpClient ?? $this->createHttpClient($config);
    }

    public function __destruct()
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    /**
     * Send a request, validating it against an optional schema and retrying
     * transient failures. Returns the decoded body (array for JSON, string otherwise).
     *
     * @param array<string, mixed>|null $data
     * @param array<string, string>     $headers
     */
    public function sendRequest(
        string $endpoint,
        ?array $data = null,
        ?Schema $schema = null,
        array $headers = [],
        string $method = 'POST',
    ): mixed {
        $response = $this->dispatch($endpoint, $data, $schema, $headers, $method);

        return $this->parseBody($response);
    }

    /**
     * Like {@see sendRequest()} but returns the raw response body (e.g. a PDF
     * print form). Re-throws the original transport exception on failure.
     *
     * @param array<string, mixed>|null $data
     * @param array<string, string>     $headers
     */
    public function sendRequestPrint(
        string $endpoint,
        ?array $data = null,
        ?Schema $schema = null,
        array $headers = [],
        string $method = 'POST',
    ): string {
        unset($headers['X-Response-Type']);
        $response = $this->dispatch($endpoint, $data, $schema, $headers, $method);

        return (string) $response->getBody();
    }

    public function getAccessToken(array $authorizationReq): mixed
    {
        $authorizationReq['grant_type'] = 'authorization_code';

        return $this->sendRequest(
            self::TOKEN_ENDPOINT,
            $authorizationReq,
            Schemas::authorizationRequest(),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
        );
    }

    public function getRefreshToken(array $refreshTokenReq): mixed
    {
        $refreshTokenReq['grant_type'] = 'refresh_token';

        return $this->sendRequest(
            self::TOKEN_ENDPOINT,
            $refreshTokenReq,
            Schemas::refreshTokenRequest(),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
        );
    }

    public function getRevokeToken(string $accessToken, array $revokeTokenReq): mixed
    {
        if ($accessToken === '') {
            throw new SberApiException('AccessToken is not provided or is empty');
        }

        return $this->sendRequest(
            '/ic/sso/api/v2/oauth/revoke',
            $revokeTokenReq,
            Schemas::revokeTokenRequest(),
            [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        );
    }

    /**
     * @return array{clientSecretExpiration: mixed, new_client_secret: string}
     */
    public function getChangeClientSecret(string $accessToken, array $refreshClientSecretRq): array
    {
        $refreshClientSecretRq['access_token'] = $accessToken;
        $refreshClientSecretRq['new_client_secret'] = $this->generateClientSecret();

        $response = $this->sendRequest(
            '/ic/sso/api/v1/change-client-secret',
            $refreshClientSecretRq,
            Schemas::changeClientSecretRequest(),
            [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        );

        return [
            'clientSecretExpiration' => \is_array($response) ? ($response['clientSecretExpiration'] ?? null) : null,
            'new_client_secret' => $refreshClientSecretRq['new_client_secret'],
        ];
    }

    /**
     * @return array{userInfoBodyResponse: array<string, mixed>, jwt: string}
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $encodedResponse = $this->sendRequest(
                '/ic/sso/api/v2/oauth/user-info',
                null,
                null,
                ['Authorization' => 'Bearer ' . $accessToken],
                'GET',
            );

            if (!\is_string($encodedResponse)) {
                throw new SberApiException('Unexpected user-info response: expected a JWT string');
            }

            $payload = $this->splitJwt($encodedResponse)[1];
            $decodedPayload = $this->decodeBase64Url($payload);
            $body = json_decode($decodedPayload, true);

            return [
                'userInfoBodyResponse' => \is_array($body) ? $body : [],
                'jwt' => $encodedResponse,
            ];
        } catch (SberApiException $error) {
            throw new SberApiException('Failed to get user info: ' . $error->getMessage(), 0, $error);
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string>     $headers
     */
    private function dispatch(
        string $endpoint,
        ?array $data,
        ?Schema $schema,
        array $headers,
        string $method,
    ): ResponseInterface {
        $schema?->assert($data ?? []);

        $method = strtoupper($method);
        $lastError = null;

        for ($attempt = 1; $attempt <= $this->maxRetries + 1; ++$attempt) {
            try {
                $url = $this->buildUrl($endpoint, $data, $method);
                $body = $method === 'GET' ? null : $this->serializeBody($data, $headers);
                $requestHeaders = $this->buildHeaders($headers, $body);

                $request = new Request($method, $url, $requestHeaders, $body);
                $this->logRequest($request);

                $response = $this->httpClient->send($request);

                $this->logResponse($url, $response);

                return $response;
            } catch (ConnectException|RequestException $error) {
                $lastError = $error;
                if (!$this->shouldRetry($error, $attempt - 1)) {
                    break;
                }
            } catch (GuzzleException $error) {
                $lastError = $error;
                break;
            }

            $this->backoff($attempt);
        }

        throw $this->wrapError($lastError);
    }

    private function shouldRetry(Throwable $error, int $retryCount): bool
    {
        if ($retryCount >= $this->maxRetries) {
            return false;
        }

        if ($error instanceof ConnectException) {
            return true;
        }

        if ($error instanceof RequestException) {
            $response = $error->getResponse();

            return $response instanceof ResponseInterface && $response->getStatusCode() >= 500;
        }

        return false;
    }

    private function backoff(int $attempt): void
    {
        $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
        $this->logger?->warning(\sprintf('Retry %d/%d after %dms', $attempt, $this->maxRetries, $delayMs));
        usleep($delayMs * 1000);
    }

    private function wrapError(?Throwable $error): SberApiException
    {
        if ($error instanceof ConnectException) {
            $message = $error->getMessage();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
                return new SberApiException('Request timed out after retries', 0, $error);
            }

            return new SberApiException('Network error after retries: ' . $message, 0, $error);
        }

        if ($error instanceof RequestException && $error->getResponse() instanceof ResponseInterface) {
            $response = $error->getResponse();

            return new SberApiException(\sprintf(
                'Request failed after retries: %d - %s',
                $response->getStatusCode(),
                (string) $response->getBody(),
            ), 0, $error);
        }

        return new SberApiException(
            'Network error after retries: ' . ($error?->getMessage() ?? 'unknown error'),
            0,
            $error,
        );
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function buildUrl(string $endpoint, ?array $data, string $method): string
    {
        if ($method !== 'GET' || $data === null || $data === []) {
            return $endpoint;
        }

        $query = http_build_query($data);

        return $query === '' ? $endpoint : $endpoint . '?' . $query;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string>     $headers
     */
    private function serializeBody(?array $data, array $headers): ?string
    {
        if ($data === null) {
            return null;
        }

        $contentType = $this->findContentType($headers);

        return match ($contentType) {
            'application/x-www-form-urlencoded' => http_build_query($data),
            default => (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildHeaders(array $headers, ?string $body): array
    {
        if ($this->findContentType($headers) === null && $body !== null) {
            $headers['Content-Type'] = str_starts_with(ltrim($body), '{')
                ? 'application/json'
                : 'application/x-www-form-urlencoded';
        }

        $headers['User-Agent'] = self::USER_AGENT;

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function findContentType(array $headers): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'content-type') {
                return $value;
            }
        }

        return null;
    }

    private function parseBody(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        $looksJson = str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[');

        if (str_contains($contentType, 'application/json') || $looksJson) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $body;
    }

    private function generateClientSecret(): string
    {
        $result = '';
        $max = \strlen(self::ALLOWED_CHARACTERS) - 1;
        for ($i = 0; $i < 40; ++$i) {
            $result .= self::ALLOWED_CHARACTERS[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitJwt(string $jwt): array
    {
        $blocks = explode('.', $jwt);
        if (\count($blocks) !== 3) {
            throw new SberApiException('Invalid format. Expected three parts separated by dots.');
        }

        return [$blocks[0], $blocks[1], $blocks[2]];
    }

    private function decodeBase64Url(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $pad = (4 - (\strlen($base64) % 4)) % 4;
        $base64 .= str_repeat('=', $pad);

        return (string) base64_decode($base64, true);
    }

    private function logRequest(Request $request): void
    {
        if (!$this->logger instanceof LoggerInterface || !$this->enableLogs) {
            return;
        }

        $url = (string) $request->getUri();
        if (MaskingInterceptor::shouldExcludeLogging($url)) {
            return;
        }

        $this->logger->info(\sprintf('Outgoing Request: %s %s', $request->getMethod(), MaskingInterceptor::maskUrl($url)));
        $this->logger->info('Headers', MaskingInterceptor::maskHeaders($this->flattenHeaders($request->getHeaders())));

        $body = (string) $request->getBody();
        if ($body !== '') {
            $masked = MaskingInterceptor::maskBody($body, $request->getHeaderLine('Content-Type'));
            $this->logger->info('Request Body', ['body' => $masked]);
        }
    }

    private function logResponse(string $url, ResponseInterface $response): void
    {
        if (!$this->logger instanceof LoggerInterface || !$this->enableLogs) {
            return;
        }

        if (MaskingInterceptor::shouldExcludeLogging($url)) {
            return;
        }

        $this->logger->info(\sprintf('Response: %d', $response->getStatusCode()));
        $this->logger->info('Headers', MaskingInterceptor::maskHeaders($this->flattenHeaders($response->getHeaders())));

        $body = (string) $response->getBody();
        $response->getBody()->rewind();
        if ($body !== '') {
            $masked = MaskingInterceptor::maskBody($body, $response->getHeaderLine('Content-Type'));
            $this->logger->info('Response Body', ['body' => $masked]);
        }
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createHttpClient(array $config): ClientInterface
    {
        $p12Path = $config['p12Path'] ?? null;
        if (!\is_string($p12Path) || $p12Path === '') {
            throw new ConfigurationException('Path to p12 certificate is required');
        }

        [$certFile, $keyFile] = $this->loadClientCertificate($p12Path, (string) ($config['p12Password'] ?? ''));
        $verify = $this->buildTrustStore($config['caPath'] ?? null);

        return new Client([
            'base_uri' => $this->host,
            'timeout' => $this->readTimeoutMs / 1000,
            'connect_timeout' => $this->connectTimeoutMs / 1000,
            'cert' => $certFile,
            'ssl_key' => $keyFile,
            'verify' => $verify,
            'http_errors' => true,
        ]);
    }

    /**
     * Extract the client certificate and private key from a PKCS#12 bundle into
     * temporary PEM files usable by cURL.
     *
     * @return array{0: string, 1: string} [certFile, keyFile]
     */
    private function loadClientCertificate(string $p12Path, string $password): array
    {
        $raw = @file_get_contents($p12Path);
        if ($raw === false) {
            throw new ConfigurationException(\sprintf('Unable to read p12 certificate: %s', $p12Path));
        }

        $certs = [];
        if (!openssl_pkcs12_read($raw, $certs, $password)) {
            throw new ConfigurationException(
                'Failed to read PKCS#12 bundle (wrong password or unsupported legacy algorithm): '
                . (openssl_error_string() ?: 'unknown error'),
            );
        }

        $certPem = $certs['cert'] ?? '';
        if (isset($certs['extracerts']) && \is_array($certs['extracerts'])) {
            $certPem .= implode('', $certs['extracerts']);
        }

        $certFile = $this->writeTempFile('sber-cert-', $certPem);
        $keyFile = $this->writeTempFile('sber-key-', (string) ($certs['pkey'] ?? ''));

        return [$certFile, $keyFile];
    }

    /**
     * Build a CA bundle file from one or more PEM/DER certificate paths.
     *
     * @param string|list<string>|null $caPath
     * @return string|true path to the CA bundle, or true to use the system store
     */
    private function buildTrustStore(string|array|null $caPath): string|bool
    {
        if ($caPath === null || $caPath === []) {
            return true;
        }

        $paths = \is_array($caPath) ? $caPath : [$caPath];
        $bundle = '';

        foreach ($paths as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                throw new ConfigurationException(\sprintf('Unable to read CA certificate: %s', $path));
            }

            $bundle .= str_contains($content, '-----BEGIN CERTIFICATE-----')
                ? $content
                : $this->derToPem($content);
            $bundle .= "\n";
        }

        return $this->writeTempFile('sber-ca-', $bundle);
    }

    private function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function writeTempFile(string $prefix, string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), $prefix);
        if ($file === false) {
            throw new ConfigurationException('Unable to create temporary certificate file');
        }

        file_put_contents($file, $content);
        chmod($file, 0o600);
        $this->tempFiles[] = $file;

        return $file;
    }
}
