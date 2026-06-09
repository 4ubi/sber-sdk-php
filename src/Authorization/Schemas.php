<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Authorization;

use Nomokonov\SberSdk\Validation\Rule;
use Nomokonov\SberSdk\Validation\Schema;

/**
 * Request schemas for the authorization module.
 *
 * Ported from lib/authorization/validators.js of the Node.js SDK.
 */
final class Schemas
{
    private const string URI_PATTERN = '#^(https?://)?(www\.)?([a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,})(:\d{1,5})?/?.*$#';

    public static function authorizationRequest(): Schema
    {
        return Schema::make([
            'grant_type' => Rule::string()->pattern('/^(authorization_code)$/')->required(),
            'code' => Rule::string()->pattern('/^[a-zA-Z0-9]{38}$/')->required(),
            'client_id' => Rule::string()->pattern('/^[a-zA-Z0-9]+$/')->required(),
            'redirect_uri' => Rule::string()->pattern(self::URI_PATTERN)->required(),
            'client_secret' => Rule::string()->required(),
            'code_verifier' => Rule::string(),
            'refresh_token' => Rule::string(),
        ]);
    }

    public static function refreshTokenRequest(): Schema
    {
        return Schema::make([
            'grant_type' => Rule::string()->pattern('/^(refresh_token)$/')->required(),
            'client_id' => Rule::string()->pattern('/^[a-zA-Z0-9]+$/')->required(),
            'redirect_uri' => Rule::string()->pattern(self::URI_PATTERN)->required(),
            'client_secret' => Rule::string()->required(),
            'code_verifier' => Rule::string(),
            'refresh_token' => Rule::string(),
        ]);
    }

    public static function revokeTokenRequest(): Schema
    {
        return Schema::make([
            'client_id' => Rule::string()->pattern('/^[a-zA-Z0-9]+$/')->required(),
            'client_secret' => Rule::string()->required(),
            'token' => Rule::string()->pattern('/^([a-zA-Z0-9]){38}$/')->required(),
            'token_type_hint' => Rule::string()->pattern('/^(access_token|refresh_token)$/')->required(),
        ]);
    }

    public static function refreshClientSecretRequest(): Schema
    {
        return Schema::make([
            'client_id' => Rule::string()->pattern('/^[a-zA-Z0-9]+$/')->required(),
            'client_secret' => Rule::string()->required(),
            'new_client_secret' => Rule::string()->required(),
        ]);
    }

    public static function changeClientSecretRequest(): Schema
    {
        return Schema::make([
            'access_token' => Rule::string()->required(),
            'client_id' => Rule::string()->pattern('/^[a-zA-Z0-9]+$/')->required(),
            'client_secret' => Rule::string()->required(),
            'new_client_secret' => Rule::string(),
        ]);
    }
}
