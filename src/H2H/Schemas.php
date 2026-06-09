<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\H2H;

use Nomokonov\SberSdk\Validation\Rule;
use Nomokonov\SberSdk\Validation\Schema;

/**
 * Request schemas for the H2H direct integration module.
 *
 * Ported from lib/h2h/validators.js of the Node.js SDK.
 */
final class Schemas
{
    public const string VAT_INCLUDED = 'INCLUDED';
    public const string VAT_ONTOP = 'ONTOP';
    public const string VAT_NO_VAT = 'NO_VAT';
    public const string VAT_MANUAL = 'MANUAL';

    public const string LINK_TYPE_ONE_TIME = 'oneTime';
    public const string LINK_TYPE_REUSABLE = 'reusable';

    public static function certRequest(): Schema
    {
        return Schema::make([
            'email' => Rule::string()->pattern('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+.[A-Za-z]{2,}$/')->required(),
            'externalId' => Rule::string()->required(),
            'number' => Rule::string()->required(),
            'orgName' => Rule::string(),
            'pkcs10' => Rule::object(Schema::make([
                'bicryptId' => Rule::string()->required(),
                'cms' => Rule::string()->required(),
            ])),
            'userName' => Rule::string()->required(),
            'userPosition' => Rule::string()->required(),
            'bankStatus' => Rule::string(),
            'bankComment' => Rule::string(),
        ]);
    }

    public static function certRequestEio(): Schema
    {
        return Schema::make([
            'email' => Rule::string()->pattern('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+.[A-Za-z]{2,}$/')->required(),
            'externalId' => Rule::string()->required(),
            'login' => Rule::string()->required(),
            'number' => Rule::string()->required(),
            'orgName' => Rule::string(),
            'pkcs10' => Rule::object(Schema::make([
                'bicryptId' => Rule::string()->required(),
                'cms' => Rule::string()->required(),
            ])),
            'userName' => Rule::string()->required(),
            'userPosition' => Rule::string()->required(),
            'bankStatus' => Rule::string(),
            'bankComment' => Rule::string(),
        ]);
    }

    public static function payment(): Schema
    {
        return Schema::make([
            'number' => Rule::string()->pattern('/^\d{1,6}$/'),
            'date' => Rule::date()->iso()->required(),
            'digestSignatures' => Rule::array(Rule::object(Schema::make([
                'base64Encoded' => Rule::string()->required(),
                'certificateUuid' => Rule::string()->guid()->required(),
            ]))),
            'bankStatus' => Rule::string(),
            'bankComment' => Rule::string(),
            'externalId' => Rule::string()->guid()->required(),
            'amount' => Rule::number()->required(),
            'operationCode' => Rule::string()->pattern('/^01$/')->required(),
            'deliveryKind' => Rule::string()->pattern('/^(электронно|срочно|0)$/iu'),
            'priority' => Rule::string()->pattern('/^(1|2|3|4|5)$/')->required(),
            'urgencyCode' => Rule::string()->valid('INTERTAL', 'INTERNAL_NOTIF', 'OFFHOURS', 'BESP', 'NORMAL'),
            'voCode' => Rule::string()->pattern('/^\d{5}$/'),
            'purpose' => Rule::string()->max(210)->required(),
            'departmentalInfo' => Rule::object(Schema::make([
                'uip' => Rule::string()->pattern('/^[A-Za-z0-9]{1,25}$/')->required(),
                'drawerStatus101' => Rule::string()->pattern('/^(01|02|08|13)$/')->required(),
                'kbk' => Rule::string()->pattern('/^[A-Za-z0-9]{1,20}$/')->required(),
                'oktmo' => Rule::string()->pattern('/^[A-Za-z0-9]{1,11}$/')->required(),
                'reasonCode106' => Rule::string()->pattern('/^\d{1,2}$/')->required(),
                'taxPeriod107' => Rule::string()->pattern('/^(0|[0-9]{8}|([0-9]{2}|МС|КВ|ПЛ|ГД)\.[0-9]{2}\.[0-9]{4})$/u')->required(),
                'docNumber108' => Rule::string()->pattern('/^\d{1,15}$/')->required(),
                'docDate109' => Rule::string()->pattern('/^(0|00|\d{2}\.\d{2}\.\d{4})$/')->required(),
                'paymentKind110' => Rule::string()->pattern('/^\d{1,2}$/'),
            ])),
            'payerName' => Rule::string()->max(254)->required(),
            'payerInn' => Rule::string()->pattern('/^(\d{5}|\d{10}|\d{12}|0)$/')->required(),
            'payerKpp' => Rule::string()->pattern('/^(\d{9}|0)$/'),
            'payerAccount' => Rule::string()->pattern('/^\d{20}$/')->required(),
            'payerBankBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
            'payerBankCorrAccount' => Rule::string()->pattern('/^\d{20}$/')->required(),
            'payeeName' => Rule::string()->max(254)->required(),
            'payeeInn' => Rule::string()->pattern('/^(\d{5}|\d{10}|\d{12}|0)$/'),
            'payeeKpp' => Rule::string()->pattern('/^(\d{9}|0)$/'),
            'payeeAccount' => Rule::string()->pattern('/^\d{20}$/'),
            'payeeBankBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
            'payeeBankCorrAccount' => Rule::string()->pattern('/^\d{20}$/'),
            'crucialFieldsHash' => Rule::string(),
            'vat' => Rule::object(Schema::make([
                'type' => Rule::string()->valid(self::VAT_INCLUDED, self::VAT_ONTOP, self::VAT_NO_VAT, self::VAT_MANUAL)->required(),
                'rate' => Rule::string()->pattern('/^\d{0,2}$/'),
                'amount' => Rule::number(),
            ])),
            'incomeTypeCode' => Rule::string()->pattern('/^[1-9]{1,6}$/'),
            'isPaidByCredit' => Rule::boolean(),
            'creditContractNumber' => Rule::string(),
        ]);
    }

    public static function amount(): Schema
    {
        return Schema::make([
            'amount' => Rule::number()->min(0.01)->required(),
            'currencyCode' => Rule::string()->pattern('/^[A-Z\d]\d{2}$/i')->required(),
            'currencyName' => Rule::string()->pattern('/^[A-Z]{3}$/i')->required(),
        ]);
    }

    public static function rate(): Rule
    {
        return Rule::string()->pattern('/^([1-9]\d*|0)(\.\d+)?$/');
    }

    public static function payroll(): Schema
    {
        return Schema::make([
            'bankComment' => Rule::string(),
            'bankStatus' => Rule::string(),
            'date' => Rule::date()->iso()->required(),
            'digestSignatures' => Rule::array(Rule::object(Schema::make([
                'base64Encoded' => Rule::string()->required(),
                'certificateUuid' => Rule::string()->guid()->required(),
            ]))),
            'number' => Rule::string(),
            'account' => Rule::string()->pattern('/^\d{20}$/'),
            'admissionValue' => Rule::string()->pattern('/^\d{2}$/')->required(),
            'amount' => Rule::object(self::amount())->required(),
            'authPersonName' => Rule::string()->max(60),
            'authPersonTelfax' => Rule::string()->max(40),
            'bic' => Rule::string()->pattern('/^\d{9}$/')->required(),
            'commissionInfo' => Rule::object(Schema::make([
                'actualRate' => self::rate(),
                'actualSum' => self::rate(),
                'estimatedRate' => self::rate(),
                'estimatedSum' => self::rate(),
                'invoiceDate' => Rule::date()->iso(),
            ])),
            'contractDate' => Rule::date()->iso()->required(),
            'contractNumber' => Rule::string()->min(1)->required(),
            'employeeSalaries' => Rule::array(Rule::object(Schema::make([
                'account' => Rule::string()->pattern('/^\d{20}$/')->required(),
                'amount' => Rule::object(self::amount()),
                'bankMessage' => Rule::string(),
                'bic' => Rule::string()->pattern('/^\d{9}$/'),
                'firstName' => Rule::string()->min(1)->required(),
                'lastName' => Rule::string()->min(1)->required(),
                'middleName' => Rule::string(),
                'receiptResult' => Rule::string(),
                'receiptStatus' => Rule::string(),
                'result' => Rule::string(),
                'withheldAmount' => Rule::number()->positive(),
            ])))->min(1)->required(),
            'employeesNumber' => Rule::number()->integer()->min(1)->required(),
            'externalId' => Rule::string()->guid()->required(),
            'incomeTypeCode' => Rule::string()->pattern('/^([1-9]|[1-9]\d)$/'),
            'loanAmount' => Rule::object(self::amount()),
            'loanDate' => Rule::date()->iso(),
            'loanNumber' => Rule::string()->max(50),
            'month' => Rule::string()->min(1)->max(50)->required(),
            'orgName' => Rule::string()->min(1)->max(160)->required(),
            'orgTaxNumber' => Rule::string()->pattern('/^(\d{5}|\d{10}|\d{12}|0)$/')->required(),
            'payDocs' => Rule::array(Rule::object(Schema::make([
                'amount' => Rule::object(self::amount()),
                'docDate' => Rule::date()->iso()->required(),
                'number' => Rule::string()->min(1)->required(),
                'payeeAccount' => Rule::string()->pattern('/^\d{20}$/')->required(),
                'payeeBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
                'payerAccount' => Rule::string()->pattern('/^\d{20}$/')->required(),
                'payerBic' => Rule::string()->pattern('/^\d{9}$/')->required(),
                'purpose' => Rule::string()->min(1)->required(),
            ]))),
            'year' => Rule::string()->length(4)->required(),
        ]);
    }

    public static function sbpB2BLinkCreateRequest(): Schema
    {
        $linkData = Schema::make([
            'linkType' => Rule::string()->valid(self::LINK_TYPE_ONE_TIME, self::LINK_TYPE_REUSABLE),
            'account' => Rule::string()->pattern('/^\d{20}$/')->max(20)->required(),
            'amount' => Rule::string()->pattern('/^\d{1,12}$/')->max(12),
            'takeTax' => Rule::boolean(),
            'totalTaxAmount' => Rule::string()->pattern('/^\d{1,12}$/')->max(12),
            'paymentPurpose' => Rule::string()->max(210),
            'dayLife' => Rule::string()->pattern('/^\d{4}-(0[1-9]|1[012])-(0[1-9]|[12]\d|3[01])$/'),
            'linkName' => Rule::string()->pattern('/^[\x{0020}-\x{007E}\x{0410}-\x{044F}\x{2116}]{0,100}$/u')->max(100),
            'redirectUrl' => Rule::string()->pattern('#^https?://.*#')->max(1024),
            'brandName' => Rule::string()->max(35),
            'payerInn' => Rule::string()->pattern('/^\d{10,12}$/')->max(50),
        ]);

        return Schema::make([
            'linkData' => Rule::object($linkData)->required(),
            'email' => Rule::array(Rule::string()->email())->max(3),
        ]);
    }
}
