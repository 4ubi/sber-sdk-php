<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Authorization;

/**
 * Masks sensitive data before it reaches the logs.
 *
 * Ported from lib/authorization/maskingInterceptor.js of the Node.js SDK.
 */
final class MaskingInterceptor
{
    /**
     * Endpoints whose traffic must never be logged.
     *
     * @var list<string>
     */
    public const array EXCLUDES_LOG_URI = [
        '/v1/client-info',
        '/fintech/api/v1/dicts',
    ];

    /**
     * Maps a field name to a masking strategy.
     *
     * @var array<string, string>
     */
    private const array FIELD_MASKING_TYPES = [
        'Authorization' => 'default',
        'authorization' => 'default',
        'X-Auth-Token' => 'default',
        'x-auth-token' => 'default',
        'client_secret' => 'default',
        'password' => 'default',
        'access_token' => 'default',
        'refresh_token' => 'default',
        'token' => 'default',
        'cert' => 'default',
        'new_client_secret' => 'default',
        'authPersonName' => 'default',
        'lastName' => 'default',
        'middleName' => 'default',
        'purpose' => 'default',
        'paymentPurpose' => 'default',
        'archive' => 'default',
        'payerName' => 'default',
        'payeeName' => 'default',
        'inn' => 'default',
        'payerInn' => 'default',
        'payeeInn' => 'default',
        'INN' => 'default',
        'orgTaxNumber' => 'default',
        'authPersonTelfax' => 'default',
        'id_token' => 'default',
        'corrAccountNumber' => 'account',
        'accountNumber' => 'account',
        'payerAccount' => 'account',
        'payeeAccount' => 'account',
        'payeeBankCorrAccount' => 'account',
        'payerBankCorrAccount' => 'account',
        'account' => 'account',
        'email' => 'email',
        'phone_number' => 'phone',
        'amount' => 'number',
        'serialNumber' => 'default',
    ];

    public static function shouldExcludeLogging(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!\is_string($path)) {
            $path = $url;
        }
        return array_any(self::EXCLUDES_LOG_URI, fn ($excluded): bool => str_contains($path, (string) $excluded));
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public static function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $key => $value) {
            $masked[$key] = isset(self::FIELD_MASKING_TYPES[$key])
                ? self::applyRule(self::FIELD_MASKING_TYPES[$key], self::stringify($value))
                : $value;
        }

        return $masked;
    }

    public static function maskUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        foreach ($query as $key => $value) {
            if (isset(self::FIELD_MASKING_TYPES[$key]) && \is_string($value)) {
                $query[$key] = self::applyRule(self::FIELD_MASKING_TYPES[$key], $value);
            }
        }

        $rebuilt = '';
        if (isset($parts['scheme'], $parts['host'])) {
            $rebuilt .= $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $rebuilt .= ':' . $parts['port'];
            }
        }
        $rebuilt .= $parts['path'] ?? '';

        return $rebuilt . ('?' . http_build_query($query));
    }

    /**
     * @param mixed $body raw request/response body (string or array)
     */
    public static function maskBody(mixed $body, ?string $contentType = null): mixed
    {
        if (\in_array($body, [null, '', []], true)) {
            return $body;
        }

        if (\is_string($body) && self::isJwt($body)) {
            return '*****';
        }

        $type = strtolower($contentType ?? '');

        if (str_contains($type, 'application/json')) {
            return self::maskJsonBody($body);
        }
        if (str_contains($type, 'application/x-www-form-urlencoded')) {
            return self::maskUrlEncodedBody(self::stringify($body));
        }

        if (\is_array($body)) {
            return json_encode(self::deepMaskObject($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (\is_string($body)) {
            $trimmed = ltrim($body);
            if (str_starts_with($trimmed, '{')) {
                return self::maskJsonBody($body);
            }
            if (str_contains($body, '=') && str_contains($body, '&')) {
                return self::maskUrlEncodedBody($body);
            }
        }

        return $body;
    }

    private static function maskJsonBody(mixed $body): string
    {
        $decoded = \is_string($body) ? json_decode($body, true) : $body;

        if (!\is_array($decoded)) {
            return self::stringify($body);
        }

        return (string) json_encode(self::deepMaskObject($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private static function deepMaskObject(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (\is_string($key) && isset(self::FIELD_MASKING_TYPES[$key])) {
                $result[$key] = self::applyRule(self::FIELD_MASKING_TYPES[$key], self::stringify($value));
            } elseif (\is_array($value)) {
                $result[$key] = self::deepMaskObject($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function maskUrlEncodedBody(string $body): string
    {
        $pairs = explode('&', $body);
        $result = array_map(static function (string $pair): string {
            $segments = explode('=', $pair, 2);
            if (\count($segments) !== 2) {
                return $pair;
            }
            [$key, $value] = $segments;
            $decodedKey = urldecode($key);
            if (isset(self::FIELD_MASKING_TYPES[$decodedKey])) {
                $masked = self::applyRule(self::FIELD_MASKING_TYPES[$decodedKey], urldecode($value));

                // Match JS encodeURIComponent, which leaves "*" unescaped.
                return $key . '=' . str_replace('%2A', '*', rawurlencode($masked));
            }

            return $pair;
        }, $pairs);

        return implode('&', $result);
    }

    public static function isJwt(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        $parts = explode('.', $value);
        if (\count($parts) !== 3) {
            return false;
        }
        return array_all($parts, fn ($part): bool => preg_match('/^[A-Za-z0-9_-]+$/', (string) $part) === 1);
    }

    private static function applyRule(string $type, string $value): string
    {
        return match ($type) {
            'email' => self::maskEmail($value),
            'account' => self::maskAccount($value),
            'phone' => self::maskPhone($value),
            'number' => '****',
            default => '****',
        };
    }

    private static function maskEmail(string $value): string
    {
        if (mb_strlen($value) <= 4) {
            return '****';
        }

        return '****' . mb_substr($value, 4);
    }

    private static function maskAccount(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';
        if (\strlen($digits) <= 6) {
            return '****';
        }

        return substr($digits, 0, 8) . '*******' . substr($digits, -4);
    }

    private static function maskPhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';
        if (\strlen($digits) <= 4) {
            return '****';
        }

        return $digits[0] . '****' . substr($digits, -2);
    }

    private static function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
