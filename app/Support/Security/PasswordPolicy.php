<?php

namespace App\Support\Security;

use App\Rules\NotCommonPassword;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $requireConfirmation = true, bool $required = true): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'string',
            self::passwordRule(),
            new NotCommonPassword(),
        ];

        if ($requireConfirmation) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    public static function isCompliant(string $password): bool
    {
        return ! Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => self::rules(requireConfirmation: true)]
        )->fails();
    }

    public static function passwordRule(): Password
    {
        $rule = Password::min(max(1, (int) config('security.password_policy.min_length', 8)));

        $requireUppercase = (bool) config('security.password_policy.require_uppercase', true);
        $requireLowercase = (bool) config('security.password_policy.require_lowercase', true);

        if ($requireUppercase && $requireLowercase) {
            $rule = $rule->mixedCase();
        } elseif ($requireUppercase) {
            $rule = $rule->letters()->rules(['regex:/[A-Z]/']);
        } elseif ($requireLowercase) {
            $rule = $rule->letters()->rules(['regex:/[a-z]/']);
        }

        if ((bool) config('security.password_policy.require_numbers', true)) {
            $rule = $rule->numbers();
        }

        if ((bool) config('security.password_policy.require_symbols', true)) {
            $rule = $rule->symbols();
        }

        return $rule;
    }
}
