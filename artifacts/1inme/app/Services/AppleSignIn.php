<?php

namespace App\Services;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AppleSignIn
{
    public static function enabled(): bool
    {
        return (bool) AppSetting::get('apple_login_enabled', false);
    }

    public static function bundleId(): string
    {
        return (string) AppSetting::get('apple_bundle_id', config('services.apple.bundle_id', env('APPLE_BUNDLE_ID', 'com.oneinme.app')));
    }

    public static function serviceId(): string
    {
        return (string) AppSetting::get('apple_service_id', '');
    }

    public static function nonce(): string
    {
        $nonce = Str::random(64);
        Cache::put('apple_nonce:'.hash('sha256', $nonce), true, now()->addMinutes(5));

        return $nonce;
    }

    public static function consumeNonce(string $nonce): bool
    {
        return strlen($nonce) === 64 && Cache::pull('apple_nonce:'.hash('sha256', $nonce)) === true;
    }
}
