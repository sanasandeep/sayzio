<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\SessionTokenIssuer;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponses;

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'handle'   => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/i', Rule::unique('users', 'handle')],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'password' => $data['password'],
            'handle'   => $data['handle'] ?? null,
            'role'     => 'user',
            'status'   => 'active',
            'allow_followers' => true,
            'discoverable'    => true,
        ]);

        $newToken = SessionTokenIssuer::issue($user, $request, null, 'api', 'mobile');
        // First-ever login is informational only — record it so the
        // "Recent logins" page has a baseline, but no alert email goes
        // out for the registration handshake itself.
        app(LoginAlertService::class)->record($user, $request, 'api_register', [
            'personal_access_token_id' => $newToken->accessToken->id ?? null,
        ]);

        return $this->created([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        // Always run a Hash::check, even when the user does not exist,
        // so an attacker can't tell "unknown email" apart from "known
        // email + wrong password" by timing the response. The dummy
        // hash below is a real bcrypt of an unguessable random string.
        $hashedAttempt = $user ? $user->password : '$2y$12$.invalid.dummy.hash.to.equalize.timing.zzzzzzzzzzzzzz';
        $passwordOk    = Hash::check($data['password'], $hashedAttempt);

        if (!$user || !$passwordOk) {
            return $this->unauthorized('Invalid credentials', 'invalid_credentials');
        }
        if (($user->status ?? 'active') !== 'active') {
            return $this->forbidden('Account is not active');
        }

        // Opportunistic re-hash if Laravel's hasher (e.g. bcrypt cost,
        // argon parameters) has rotated since this password was set.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $newToken = SessionTokenIssuer::issue($user, $request, $data['device'] ?? null, 'api', 'mobile');
        app(LoginAlertService::class)->record($user, $request, 'api_password', [
            'personal_access_token_id' => $newToken->accessToken->id ?? null,
            'device_label'             => $data['device'] ?? null,
        ]);

        return $this->ok([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        return $this->noContent();
    }

    public function me(Request $request)
    {
        return $this->ok(['user' => UserResource::toArray($request->user(), self: true)]);
    }
}
