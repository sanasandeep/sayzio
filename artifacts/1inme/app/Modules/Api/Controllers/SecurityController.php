<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile-parity for the recent-logins page + revoke flow.
 */
class SecurityController extends Controller
{
    use ApiResponses;

    public function logins(Request $request)
    {
        $events = LoginEvent::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (LoginEvent $e) => [
                'id'           => $e->id,
                'channel'      => $e->channel,
                'country_code' => $e->country_code,
                'platform'     => $e->platform,
                'browser'      => $e->browser,
                'device_label' => $e->device_label,
                'is_new'       => (bool) $e->is_new,
                'new_reasons'  => $e->new_reasons,
                'revoked_at'   => optional($e->revoked_at)->toIso8601String(),
                'created_at'   => optional($e->created_at)->toIso8601String(),
                'status'       => $e->revoked_at
                    ? 'revoked'
                    : ($e->is_new ? 'new' : 'recognized'),
            ])
            ->values();
        return $this->ok(['events' => $events]);
    }

    public function revoke(Request $request, int $id, LoginAlertService $service)
    {
        $event = LoginEvent::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();
        if (!$event) return $this->notFound('Login not found');
        $service->revokeFromEmail($event);
        return $this->ok([
            'revoked'       => true,
            'message'       => 'Every device has been signed out and your password has been cleared. Use "Forgot password" to set a new one.',
        ]);
    }
}
