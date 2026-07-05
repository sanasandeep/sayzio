<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventInterest;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * One-tap Interested / Not-interested signal on an `ics` event page
 * (Task #3593). Deliberately separate from {@see \App\Modules\User\Models\Rsvp}
 * — a visitor can mark interest without answering any RSVP questions, and an
 * RSVP does not imply interest bookkeeping here. Signed-in users are keyed by
 * user_id; anonymous visitors are keyed by a client-supplied fingerprint
 * (cookie) so a repeat tap flips the row instead of stacking duplicates.
 */
class EventInterestController extends Controller
{
    public function toggle(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);

        $data = $request->validate([
            'status'      => ['required', 'in:interested,not_interested'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
            'email'       => ['nullable', 'email', 'max:160'],
        ]);

        $user = $request->user();

        $query = EventInterest::where('link_id', $link->id);
        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $fingerprint = $data['fingerprint'] ?? $request->cookie('event_interest_fp');
            if (!$fingerprint) {
                $fingerprint = (string) \Illuminate\Support\Str::uuid();
            }
            $query->whereNull('user_id')->where('guest_fingerprint', $fingerprint);
        }

        $existing = $query->first();
        if ($existing) {
            $existing->update([
                'status'      => $data['status'],
                'guest_email' => $data['email'] ?? $existing->guest_email,
            ]);
            $interest = $existing;
        } else {
            $interest = EventInterest::create([
                'link_id'           => $link->id,
                'user_id'           => $user?->id,
                'guest_email'       => $user ? null : ($data['email'] ?? null),
                'guest_fingerprint' => $user ? null : ($fingerprint ?? null),
                'status'            => $data['status'],
            ]);
        }

        $counts = $this->counts($link);

        $response = response()->json([
            'success' => true,
            'status'  => $interest->status,
            'counts'  => $counts,
        ]);

        if (!$user && !empty($fingerprint)) {
            $response->cookie('event_interest_fp', $fingerprint, 60 * 24 * 365);
        }

        return $response;
    }

    public function counts(Link $link): array
    {
        return [
            'interested'     => $link->eventInterests()->where('status', EventInterest::INTERESTED)->count(),
            'not_interested' => $link->eventInterests()->where('status', EventInterest::NOT_INTERESTED)->count(),
        ];
    }
}
