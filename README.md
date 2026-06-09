# Sber API SDK (PHP)

A lightweight PHP SDK for integrating with Sberbank's SberAPI: authorization,
H2H direct integration, instant payments and payroll projects. A PHP port of the
official [Node.js SDK](https://github.com/GreenBankTeamRu/SDK_Node.js).

The SDK contains three modules:

- **Authorization module** (`Nomokonov\SberSdk\Authorization`) — obtaining,
  refreshing and revoking tokens, rotating the client secret, retrieving user
  info, PKCE and JWT signature verification.
- **H2H direct integration module** (`Nomokonov\SberSdk\H2H`) — dictionaries,
  client info, crypto operations and certificates, payments, statements, payroll
  sheets, SBP B2B links.
- **Instant payments module** (`Nomokonov\SberSdk\InstantPayment`) — creating
  payment order drafts from an invoice and building the payment URL.

## Requirements

- PHP 8.5+
- Extensions: `ext-openssl`, `ext-zip`, `ext-json`
- [Guzzle](https://docs.guzzlephp.org/) 7.9+ (HTTP client)

## Installation

```bash
composer require nomokonov/sber-sdk-php
```

## Client configuration

The client uses mTLS: a client certificate in PKCS#12 format (`.p12`) and trusted
root certificates (CA). Production certificates are located in the `certs/`
directory.

```php
use Nomokonov\SberSdk\Authorization\ApiClient;

$client = new ApiClient([
    'host'            => 'https://iftfintech.testsbi.sberbank.ru:9443',
    'p12Path'         => '/path/to/SBBAPI_xxx.p12',
    'p12Password'     => 'certpass',
    // For production, pass the full certificate chain:
    // 'caPath'       => [__DIR__ . '/certs/sberca-ext.crt', __DIR__ . '/certs/sberca-root-ext.crt'],
    'caPath'          => '/path/to/russiantrustedca2024.pem',
    'connectTimeout'  => 60000, // ms, default 60000
    'readTimeout'     => 60000, // ms, default 60000
    'enableLogs'      => true,  // default false
    'maxRetries'      => 3,     // default 3
    'retryDelay'      => 1000,  // ms, default 1000
]);
```

### Logging

With `enableLogs => true`, pass a PSR-3 logger as the third argument. Sensitive
data (tokens, accounts, INN, amounts, etc.) is masked automatically
([`MaskingInterceptor`](src/Authorization/MaskingInterceptor.php)):

```php
$client = new ApiClient($config, httpClient: null, logger: $psr3Logger);
```

### Custom HTTP client

For tests or fine-tuning you can pass your own Guzzle instance — in that case
certificates are not required:

```php
$client = new ApiClient(['host' => 'https://...'], $guzzleClient);
```

## Usage

### Authorization

```php
// Obtain an access token
$token = $client->getAccessToken([
    'code'          => $authorizationCode,
    'client_id'     => $clientId,
    'redirect_uri'  => 'https://example.com/callback',
    'client_secret' => $clientSecret,
]);
$accessToken = $token['access_token'];

// Refresh the token
$client->getRefreshToken([
    'refresh_token' => $refreshToken,
    'client_id'     => $clientId,
    'redirect_uri'  => 'https://example.com/callback',
    'client_secret' => $clientSecret,
]);

// Revoke the token
$client->getRevokeToken($accessToken, [
    'client_id'       => $clientId,
    'client_secret'   => $clientSecret,
    'token'           => $accessToken,
    'token_type_hint' => 'access_token',
]);

// Rotate the client secret (a new secret is generated automatically)
$result = $client->getChangeClientSecret($accessToken, [
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
]);
// $result['new_client_secret'], $result['clientSecretExpiration']

// User info (with JWT decoding)
$info = $client->getUserInfo($accessToken);
// $info['userInfoBodyResponse'], $info['jwt']
```

### PKCE

```php
use Nomokonov\SberSdk\Authorization\SecurityService;

$security = new SecurityService();
$verifier  = $security->generatePkceCodeVerifier();
$challenge = $security->generatePkceCodeChallenge($verifier);
```

### JWT signature verification

The signature of `id_token`/user-info is verified natively via the OpenSSL
extension — no Java or external processes are required. Sber tokens are signed
with RSA (RS256/RS384/RS512), which is fully supported by PHP.

```php
use Nomokonov\SberSdk\Authorization\SignatureVerificationService;

$verifier = new SignatureVerificationService('/path/to/sber-signing-cert.cer');
$verifier->verifyJwt($token['id_token']); // true or SignatureVerificationException
```

The certificate may be in PEM or DER format (`.cer`/`.crt`).

### H2H — direct integration

```php
use Nomokonov\SberSdk\H2H\H2hClient;

$h2h = new H2hClient($client);

$dict       = $h2h->getDictionary($accessToken, 'banks'); // ['name' => ..., 'content' => ...]
$clientInfo = $h2h->getClientInfo($accessToken);
$crypto     = $h2h->getCrypto($accessToken);

// Certificates
$h2h->certificateRequest($accessToken, $certRequest);
$h2h->activateCert($accessToken, $externalId);
$pdf = $h2h->printCert($accessToken, $externalId); // raw PDF bytes
$h2h->getCertState($accessToken, $externalId);

// Payments
$h2h->createPayment($accessToken, $paymentRequest);
$h2h->getPayment($accessToken, $externalId);
$h2h->getPaymentDocState($accessToken, $externalId);

// Statements
$h2h->getStatementSummary($accessToken, $accountNumber, $statementDate);
$h2h->getStatementTransactions($accessToken, $accountNumber, $statementDate, 1);

// Payroll sheets
$h2h->createPayroll($accessToken, $payrollRequest);

// SBP B2B payment links
$h2h->createPaymentLink($accessToken, $linkRequest);
$h2h->getPaymentLinkList($accessToken, '550e8400-e29b-41d4-a716-446655440000');
```

### Instant payments

```php
use Nomokonov\SberSdk\InstantPayment\InstantPaymentClient;
use Nomokonov\SberSdk\InstantPayment\CryptoprofileType;

$instant = new InstantPaymentClient($client);

$instant->getPaymentInvoice($accessToken, $invoiceRequest);        // fixed requisites
$instant->getPaymentInvoiceBudget($accessToken, $budgetRequest);   // budget payment
$instant->getPaymentInvoiceAny($accessToken, $anyRequest);         // free requisites
$instant->getPaymentState($accessToken, $externalId);

$url = $instant->buildPaymentUrl(
    externalId: $externalId,
    backUrl: 'https://shop.example/return',
    cryptoprofileType: CryptoprofileType::SMS,
    isProd: false,
);
```

## Validation

Requests are validated before being sent using
[schemas](src/Validation/Schema.php) that mirror the Joi schemas of the Node.js
SDK. On failure a `Nomokonov\SberSdk\Exception\ValidationException` is thrown with
the list of fields and messages (`getErrors()`), without any network call.

## Error handling

All exceptions extend `Nomokonov\SberSdk\Exception\SberApiException`:

- `ConfigurationException` — invalid configuration (missing host/certificate).
- `ValidationException` — the request failed validation.
- `SignatureVerificationException` — JWT signature verification error.
- `SberApiException` — network and response errors (after retries).

Transient failures (connection errors and 5xx responses) are retried
automatically with exponential backoff.

## Development

```bash
composer install
composer test        # PHPUnit
composer cs          # PHP CS Fixer (check)
composer cs:fix      # PHP CS Fixer (fix)
composer rector      # Rector (check)
composer rector:fix  # Rector (apply)
composer ci          # cs + rector + test
```

### CI

[`.gitlab-ci.yml`](.gitlab-ci.yml) runs PHP CS Fixer, Rector and PHPUnit on every
merge request and on pushes to the default branch.

## License

[MIT](LICENSE)
