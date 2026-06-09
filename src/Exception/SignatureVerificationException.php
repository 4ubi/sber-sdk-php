<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Exception;

/**
 * Thrown when JWT signature verification fails or cannot be performed.
 */
final class SignatureVerificationException extends SberApiException
{
}
