<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary Password Settings
    |--------------------------------------------------------------------------
    |
    | These values support the admin-created account lifecycle. Temporary
    | passwords should expire and require administrator action when expired
    | until the password reset flow is implemented.
    |
    */

    'temporary_password' => [
        'expires_hours' => (int) env('TEMP_PASSWORD_EXPIRES_HOURS', 24),
    ],


    /*
    |--------------------------------------------------------------------------
    | Owner Contact Identity
    |--------------------------------------------------------------------------
    |
    | Owner identity and contact details used in security guidance messages and
    | account lifecycle communication paths.
    |
    */

    'owner' => [
        'email' => env('OWNER_EMAIL'),
        'name' => env('OWNER_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Centralized password policy settings for forced password change and
    | future password reset flows.
    |
    */

    'password_policy' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => filter_var(env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true), FILTER_VALIDATE_BOOLEAN),
        'require_lowercase' => filter_var(env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true), FILTER_VALIDATE_BOOLEAN),
        'require_numbers' => filter_var(env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true), FILTER_VALIDATE_BOOLEAN),
        'require_symbols' => filter_var(env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
