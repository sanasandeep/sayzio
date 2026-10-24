<?php

namespace App\Modules\User\Controllers;

use App\Modules\Api\Controllers\SocialAuthController;
use App\Services\AppleSignIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AppleLoginController
{
    public function nonce()
    {
        abort_unless(AppleSignIn::enabled(), 404);

        return response()->json(['data' => ['nonce' => AppleSignIn::nonce()]])->header('Cache-Control', 'no-store');
    }

    public function page(Request $request)
    {
        abort_unless(AppleSignIn::enabled() && AppleSignIn::serviceId() !== '', 404);
        $nonce = AppleSignIn::nonce();
        $request->session()->put('apple_web_nonce', $nonce);

        return view('user.auth.apple', ['nonce' => $nonce]);
    }

    public function complete(Request $request, SocialAuthController $social)
    {
        abort_unless(AppleSignIn::enabled(), 404);
        $nonce = $request->session()->pull('apple_web_nonce');
        abort_unless(is_string($nonce) && hash_equals($nonce, (string) $request->input('nonce')), 419);
        // A web Services ID token must not be replaced by a native token.
        $claims = json_decode(base64_decode(strtr(explode('.', (string) $request->input('id_token'))[1] ?? '', '-_', '+/')), true) ?? [];
        abort_unless(($claims['aud'] ?? null) === AppleSignIn::serviceId(), 401);
        $request->merge(['provider' => 'apple']);
        $response = $social->exchange($request);
        $payload = $response->getData(true);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $token = PersonalAccessToken::findToken($payload['data']['token']);
        abort_unless($token, 401);
        $user = $token->tokenable;
        $token->delete();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        if (! empty($payload['data']['created'])) {
            $request->session()->put('auth_needs_name', true);
        }

        return response()->json(['redirect' => route('user.dashboard')]);
    }
}
