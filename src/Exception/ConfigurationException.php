<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Exception;

/**
 * Thrown when the client is misconfigured (missing host, certificate, etc.).
 */
final class ConfigurationException extends SberApiException
{
}
