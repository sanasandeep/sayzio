<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\ExpoPushNotifier;
use App\Modules\User\Models\DevicePushToken;
use App\Modules\User\Models\DialerCallEvent;
use App\Modules\User\Models\DialerDevice;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Desktop ⇄ phone call handoff for the Zio Browser Dialer pane (task #5780).
 *
 * Three small surfaces, all owner-scoped:
 *  - requestCall(): the browser pushes a "call this number" request to the
 *    user's Zio Dialer phone app via Expo push (`dialer.call_request`).
 *  - reportCallEvent(): the phone reports incoming-call events (ringing /
 *    answered / ended) so the desktop pane can mirror them. Best-effort
 *    telemetry with a per-user retention cap.
 *  - callEvents(): short-poll read of recent events with a `since` id
 *    cursor, only while the pane is open.
 */
class DialerHandoffController extends Controller
{
    use ApiResponses;

    /** Keep at most this many mirrored call events per user. */
    private const MAX_EVENTS_PER_USER = 100;

    /** Events older than this are never returned (stale rings are noise). */
    private const READ_WINDOW_MINUTES = 60;

    /**
     * GET /dialer/handoff/status — lightweight linked-device check so the
     * desktop pane can offer the Zio Dialer app download proactively
     * instead of after a failed call attempt.
     *
     * `device_linked` means a Zio Dialer install signed in with this
     * account (a `dialer_devices` row) OR a push token exists (legacy
     * installs that never registered a device record). `push_available`
     * separately reports whether click-to-call pushes can be delivered,
     * so the desktop can show "enable notifications" instead of the
     * download promo when the app is installed but push is unavailable.
     */
    public function status(Request $request)
    {
        $userId = $request->user()->id;

        $pushAvailable = DevicePushToken::where('user_id', $userId)->exists();
        $deviceLinked  = $pushAvailable
            || DialerDevice::where('user_id', $userId)->exists();

        return $this->ok([
            'device_linked'  => $deviceLinked,
            'push_available' => $pushAvailable,
        ]);
    }

    /**
     * POST /dialer/device — the Zio Dialer app records/heartbeats this
     * install at sign-in/unlock, independent of push-token registration,
     * so "device linked" no longer requires notification permission.
     */
    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'device_key'  => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'platform'    => ['nullable', 'string', 'max:16'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $row = DialerDevice::updateOrCreate(
            [
                'user_id'    => $request->user()->id,
                'device_key' => $data['device_key'],
            ],
            [
                'platform'     => $data['platform']    ?? null,
                'device_name'  => $data['device_name'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return $this->ok([
            'registered' => true,
            'id'         => $row->id,
        ]);
    }

    /**
     * DELETE /dialer/device — detach this install from the user (called
     * best-effort on sign-out, mirroring push-token unregistration).
     */
    public function unregisterDevice(Request $request)
    {
        $data = $request->validate([
            'device_key' => ['required', 'string', 'max:64'],
        ]);

        DialerDevice::where('user_id', $request->user()->id)
            ->where('device_key', $data['device_key'])
            ->delete();

        return $this->ok(['unregistered' => true]);
    }

    /**
     * POST /dialer/handoff/call — push a click-to-call request to the
     * user's phone. 404s with a clear code when no phone is registered.
     */
    public function requestCall(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 \-\(\)\.]{2,30}$/'],
            'name'   => ['nullable', 'string', 'max:191'],
        ]);

        $user = $request->user();

        if (!DevicePushToken::where('user_id', $user->id)->exists()) {
            // Distinguish "no app installed at all" from "app installed but
            // push unavailable" (notifications denied / no Expo token), so
            // the desktop can show the right guidance instead of the
            // download promo.
            if (DialerDevice::where('user_id', $user->id)->exists()) {
                return $this->fail(
                    'Your phone is linked, but it can\'t receive call requests — enable notifications for the Zio Dialer app on your phone.',
                    409,
                    'no_push_token',
                );
            }

            return $this->fail(
                'No phone with the Zio Dialer app is linked to this account. Sign in to the Zio Dialer app on your phone first.',
                404,
                'no_dialer_device',
            );
        }

        $number = trim($data['number']);
        $name   = isset($data['name']) ? trim((string) $data['name']) : '';

        $sent = app(ExpoPushNotifier::class)->sendToUser(
            $user->id,
            'Call from Zio Browser',
            'Tap to call ' . ($name !== '' ? "{$name} ({$number})" : $number),
            [
                'type'   => 'dialer.call_request',
                'number' => $number,
                'name'   => $name !== '' ? $name : null,
            ],
        );

        return $this->ok([
            'requested' => true,
            'sent'      => $sent,
        ]);
    }

    /**
     * POST /dialer/call-events — the phone mirrors an incoming-call event.
     * Best-effort: retention-capped per user, invalid rows rejected loudly.
     */
    public function reportCallEvent(Request $request)
    {
        $data = $request->validate([
            'status'      => ['required', 'string', 'in:ringing,answered,ended'],
            'number'      => ['required', 'string', 'max:32'],
            'caller_name' => ['nullable', 'string', 'max:191'],
            // Epoch millis of the event on the phone; defaults to now.
            'occurred_at_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        $occurredAt = isset($data['occurred_at_ms'])
            ? Carbon::createFromTimestampMs((int) $data['occurred_at_ms'])
            : now();
        // Clamp obviously-bogus phone clocks into a sane window.
        if ($occurredAt->lt(now()->subDay()) || $occurredAt->gt(now()->addMinutes(5))) {
            $occurredAt = now();
        }

        $event = DialerCallEvent::create([
            'user_id'     => $user->id,
            'status'      => $data['status'],
            'number'      => trim($data['number']),
            'caller_name' => isset($data['caller_name']) && trim((string) $data['caller_name']) !== ''
                ? trim((string) $data['caller_name'])
                : null,
            'occurred_at' => $occurredAt,
        ]);

        // Retention cap: prune the oldest rows beyond the per-user ceiling.
        $cutoffId = DialerCallEvent::where('user_id', $user->id)
            ->orderByDesc('id')
            ->skip(self::MAX_EVENTS_PER_USER)
            ->value('id');
        if ($cutoffId !== null) {
            DialerCallEvent::where('user_id', $user->id)
                ->where('id', '<=', $cutoffId)
                ->delete();
        }

        return $this->ok([
            'recorded' => true,
            'id'       => $event->id,
        ]);
    }

    /**
     * GET /dialer/call-events?since={id} — recent events after the cursor,
     * oldest-first, limited to a short freshness window.
     */
    public function callEvents(Request $request)
    {
        $user  = $request->user();
        $since = max(0, (int) $request->query('since', 0));

        $events = DialerCallEvent::where('user_id', $user->id)
            ->where('id', '>', $since)
            ->where('occurred_at', '>=', now()->subMinutes(self::READ_WINDOW_MINUTES))
            ->orderBy('id')
            ->limit(50)
            ->get();

        return $this->ok([
            'events' => $events->map(fn (DialerCallEvent $e) => [
                'id'          => $e->id,
                'status'      => $e->status,
                'number'      => $e->number,
                'caller_name' => $e->caller_name,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values(),
            'cursor' => $events->isNotEmpty() ? $events->last()->id : $since,
        ]);
    }
}
