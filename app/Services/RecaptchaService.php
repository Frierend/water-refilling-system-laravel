<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! (bool) config('services.recaptcha.enabled', false)) {
            return true;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        $secret = (string) config('services.recaptcha.secret_key', '');
        if ($secret === '') {
            return false;
        }

        $response = Http::asForm()->post((string) config('services.recaptcha.verify_url'), [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) data_get($response->json(), 'success', false);
    }
}
