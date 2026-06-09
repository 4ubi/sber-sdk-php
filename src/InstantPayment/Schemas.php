<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\InstantPayment;

use Nomokonov\SberSdk\Validation\Rule;
use Nomokonov\SberSdk\Validation\Schema;

/**
 * Request schemas for the instant payment module.
 *
 * Ported from lib/instantpayment/validators.js of the Node.js SDK.
 */
final class Schemas
{
    public static function vat(): Schema
    {
        return Schema::make([
            'amount' => Rule::number()->positive(),
            'rate' => Rule::string()->pattern('/^\d{0,2}$/'),
            'type' => Rule::string()->valid('INCLUDED', 'NO_VAT', 'MANUAL')->required(),
        ]);
    }

    public static function linkedDoc(): Schema
    {
        return Schema::make([
            'docExtId' => Rule::string()->guid()->required(),
            'type' => Rule::string()->valid('ExportContractInsure'),
        ]);
    }

    public static function departmentalInfo(): Schema
    {
        return Schema::make([
            'uip' => Rule::string()->min(1),
            'drawerStatus101' => Rule::string()->min(1),
            'kbk' => Rule::string()->min(1),
            'oktmo' => Rule::string()->min(1),
            'docNumber108' => Rule::string()->min(1),
            'reasonCode106' => Rule::string()->min(1),
            'taxPeriod107' => Rule::string()->min(1),
            'paymentKind110' => Rule::string()->min(1),
        ]);
    }

    public static function paymentInvoiceRequest(): Schema
    {
        return Schema::make([
            'externalId' => Rule::string()->min(1)->required(),
            'amount' => Rule::number()->positive()->required(),
            'date' => Rule::string()->pattern('/^\d{4}-\d{2}-\d{2}$/')->required(),
            'purpose' => Rule::string()->max(240),
            'payeeAccount' => Rule::string()->min(1)->required(),
            'urgencyCode' => Rule::string()->valid('INTERTAL', 'INTERNAL_NOTIF', 'OFFHOURS', 'BESP', 'NORMAL'),
            'paymentNumber' => Rule::string()->pattern('/^\d{1,6}$/'),
            'deliveryKind' => Rule::string()->valid('электронно', 'срочно', '0'),
            'expirationDate' => Rule::string()->pattern('/^\d{4}-\d{2}-\d{2}$/'),
            'operationCode' => Rule::string()->pattern('/^01$/'),
            'linkedDocs' => Rule::array(Rule::object(self::linkedDoc())),
            'orderNumber' => Rule::string(),
            'priority' => Rule::string()->pattern('/^[4-5]$/'),
            'vat' => Rule::object(self::vat()),
            'payeeOrgIdHash' => Rule::string()->pattern('/^[0-9a-f]{64}$/i'),
        ]);
    }

    public static function paymentInvoiceBudgetRequest(): Schema
    {
        return self::paymentInvoiceRequest()->keys([
            'departmentalInfo' => Rule::object(self::departmentalInfo())->required(),
            'payeeBankBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
            'payeeBankCorrAccount' => Rule::string()->pattern('/^\d{20}$/'),
            'payeeInn' => Rule::string()->pattern('/^(\d{5}|\d{10}|\d{12}|0)$/')->required(),
            'payeeKpp' => Rule::string()->pattern('/^(\d{9}|0)$/'),
            'payeeName' => Rule::string()->min(1)->required(),
        ]);
    }

    public static function paymentInvoiceFromAnyRequest(): Schema
    {
        return self::paymentInvoiceRequest()->keys([
            'creditContractNumber' => Rule::string(),
            'isPaidByCredit' => Rule::boolean()->required(),
            'payeeBankBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
            'payeeBankCorrAccount' => Rule::string()->pattern('/^\d{20}$/'),
            'payeeInn' => Rule::string()->pattern('/^(\d{5}|\d{10}|\d{12}|0)$/')->required(),
            'payeeKpp' => Rule::string()->pattern('/^(\d{9}|0)$/'),
            'payeeName' => Rule::string()->min(1)->required(),
        ]);
    }
}
