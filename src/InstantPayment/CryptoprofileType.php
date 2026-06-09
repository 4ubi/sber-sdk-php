<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\InstantPayment;

/**
 * Authentication (crypto profile) type used when building a payment URL.
 */
enum CryptoprofileType: string
{
    case SMS = 'SMS';
    case TOKEN = 'TOKEN';
}
