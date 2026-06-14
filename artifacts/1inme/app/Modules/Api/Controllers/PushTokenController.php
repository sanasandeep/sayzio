<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\ExpoPushNotifier;
use App\Modules\User\Models\DevicePushToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Expo push-token registration for the 1inme-mobile app (task #1403).
 *
 * The app obtains a token from expo-notifications after sign-in and POSTs
 * it here so the backend can fan push notifications out (e.g. the
 * `api.usage_warning` alerts). Tokens are unique per install, so a token
 * already owned by another user (device handed over / reinstalled) is
 * re-pointed at the caller rather than rejected. The matching DELETE is
 * called on sign-out so a shared device stops receiving the previous
 * user's notifications.
 */
class PushTokenController extends Controller
{
    use ApiResponses;

    public function store(Request $request)
    {
        $data = $request->validate([
            'token'       => 'required|string|max:255',
            'platform'    => 'nullable|string|max:16',
            'device_name' => 'nullable|string|max:255',
        ]);

        $token = trim($data['token']);
        if (!ExpoPushNotifier::looksLikeExpoToken($token)) {
            return $this->fail(
                'That does not look like a valid Expo push token.',
                422,
                'invalid_push_token',
            );
        }

        $row = DevicePushToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $data['platform']    ?? null,
                'device_name'  => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return $this->ok([
            'registered' => true,
            'id'         => $row->id,
        ]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
        ]);

        DevicePushToken::where('user_id', $request->user()->id)
            ->where('token', trim($data['token']))
            ->delete();

        return $this->ok(['unregistered' => true]);
    }
}
