<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonPassword implements ValidationRule
{
    /**
     * @var array<string, bool>
     */
    private array $denylist;

    public function __construct()
    {
        /** @var array<int, string> $configured */
        $configured = config('security.password_policy.common_password_denylist', []);

        $this->denylist = [];

        foreach ($configured as $password) {
            $normalized = $this->normalize($password);
            if ($normalized === '') {
                continue;
            }

            $this->denylist[$normalized] = true;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (isset($this->denylist[$this->normalize($value)])) {
            $fail('The :attribute is too common. Please choose a more unique value.');
        }
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
