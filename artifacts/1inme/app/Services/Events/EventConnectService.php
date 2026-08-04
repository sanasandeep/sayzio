<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventQrConnect;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Connect QR one-shot "RSVP & Connect" (Task #6685, shared with the
 * mobile API in Task #6687): badge gate → RSVP ("yes", idempotent,
 * honoring capacity/waitlist) → auto-follow of the host → attribution row.
 *
 * Extracted from EventConnectQrController so the web (session) flow and the
 * token-authenticated mobile API flow run the exact same business rules.
 * Session writes are guarded — API requests have no session.
 */
class EventConnectService
{
    /**
     * Run the connect for an already-authenticated user. Returns the JSON
     * response payload (success or error) plus an HTTP status code.
     *
     * @return array{0: array<string,mixed>, 1: int}
     */
    public function connect(Request $request, Link $link, User $user, bool $wasNew): array
    {
        $link->loadMissing(['icsData', 'user']);
        $s            = (array) ($link->settings ?? []);
        $rsvpSettings = (array) ($s['rsvp_settings'] ?? []);

        // Badge-gated events refuse the auto-RSVP for accounts without the
        // required badge — same rule as the manual RSVP form. The visitor is
        // signed in at this point, but nothing else is recorded for them.
        $requiredBadgeId = $link->icsData?->required_badge_id;
        if ($requiredBadgeId) {
            $hasBadge = $user->accountBadges()->where('account_badges.id', $requiredBadgeId)->exists();
            if (!$hasBadge) {
                return [[
                    'success' => false,
                    'code'    => 'badge_required',
                    'message' => 'This event requires an invite badge you don\'t have yet.',
                ], 403];
            }
        }

        // RSVP deadline — re-checked server-side like rsvpSubmit().
        if (!empty($rsvpSettings['deadline'])) {
            try {
                if (new \DateTime($rsvpSettings['deadline']) < new \DateTime()) {
                    return [['success' => false, 'message' => 'RSVPs are closed for this event.'], 422];
                }
            } catch (\Throwable $e) {}
        }

        $email = $user->email;

        // Idempotent RSVP lookup. Primary key is the (link, user) attribution
        // row's rsvp_id — immune to identifier quirks. Fallback matches by
        // email or phone ONLY when that identifier is non-empty: email can be
        // NULL for mobile-only signups, and a bare `where('email', null)`
        // would match (and then mutate) some OTHER attendee's email-less RSVP.
        $priorConnect = EventQrConnect::where('link_id', $link->id)
            ->where('user_id', $user->id)
            ->first();
        $rsvp = null;
        if ($priorConnect?->rsvp_id) {
            $rsvp = Rsvp::where('link_id', $link->id)
                ->where('id', $priorConnect->rsvp_id)
                ->where('status', '!=', 'cancelled')
                ->first();
        }
        if (!$rsvp && $email) {
            $rsvp = Rsvp::where('link_id', $link->id)
                ->where('email', $email)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('id')
                ->first();
        }
        if (!$rsvp && !$email && $user->mobile) {
            $rsvp = Rsvp::where('link_id', $link->id)
                ->where('phone', $user->mobile)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('id')
                ->first();
        }

        $rsvpStatus = $rsvp?->status;
        if (!$rsvp || $rsvp->response !== 'yes') {
            // Capacity / waitlist enforcement — identical math to rsvpSubmit().
            // Also runs when converting a prior "no"/"maybe" RSVP to "yes",
            // since that RSVP was not occupying a seat before.
            $neededSeats = $rsvp ? ((int) $rsvp->plus_ones + 1) : 1;
            $status = 'confirmed';
            $cap    = isset($rsvpSettings['capacity']) ? (int) $rsvpSettings['capacity'] : 0;
            if ($cap > 0) {
                $usedSeats = (int) Rsvp::query()
                    ->where('link_id', $link->id)
                    ->where('response', 'yes')
                    ->where('status', 'confirmed')
                    ->sum(\DB::raw('plus_ones + 1'));
                if (($usedSeats + $neededSeats) > $cap) {
                    if (empty($rsvpSettings['waitlist_enabled'])) {
                        return [['success' => false, 'message' => 'This event is full.'], 422];
                    }
                    $status = 'waitlist';
                }
            }

            if ($rsvp) {
                // Convert the visitor's prior "no"/"maybe" answer to "yes" —
                // completing RSVP & Connect always means "I'm going".
                $rsvp->update(['response' => 'yes', 'status' => $status, 'source' => 'connect_qr']);
                $rsvpStatus = $status;
                \App\Services\Events\RsvpTicketService::sync($rsvp);
                if ($request->hasSession()) {
                    $request->session()->put('rsvp_submitted_' . $link->id, true);
                }
            } else {
                $rsvp = Rsvp::create([
                    'link_id'    => $link->id,
                    'name'       => $user->name ?: ($email ? Str::before($email, '@') : 'Guest'),
                    'email'      => $email,
                    'phone'      => $user->mobile,
                    'response'   => 'yes',
                    'status'     => $status,
                    'plus_ones'  => 0,
                    'source'     => 'connect_qr',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 250),
                ]);
                $rsvpStatus = $status;

                // Confirmed "yes" RSVPs get the same tier-less check-in ticket.
                $ticket = \App\Services\Events\RsvpTicketService::sync($rsvp);

                // Confirmation + organizer notify — best-effort, like the form.
                try {
                    if ($rsvp->email && ($rsvpSettings['send_confirmation'] ?? true)) {
                        \App\Modules\Common\Services\Emailer::sendMailable('events.rsvp_confirmation', $rsvp->email, new \App\Mail\EventRsvpConfirmationMail($link, $rsvp, $ticket), ['title' => $link->title], ['related' => $link, 'user' => $link->user_id]);
                    }
                } catch (\Throwable $e) {
                    logger()->warning('Connect QR RSVP confirmation email failed: ' . $e->getMessage());
                }
                try {
                    if (($rsvpSettings['notify_owner'] ?? true) && ($ownerEmail = $link->user?->email)) {
                        \App\Modules\Common\Services\Emailer::sendMailable('events.rsvp_notify_owner', $ownerEmail, new \App\Mail\EventRsvpNotifyOwnerMail($link, $rsvp), ['title' => $link->title, 'name' => $rsvp->name], ['related' => $link, 'user' => $link->user_id]);
                    }
                } catch (\Throwable $e) {
                    logger()->warning('Connect QR RSVP notify-owner email failed: ' . $e->getMessage());
                }

                if ($request->hasSession()) {
                    $request->session()->put('rsvp_submitted_' . $link->id, true);
                }
            }
        }

        // Auto-follow the host's creator profile — idempotent, and skipped
        // entirely when the visitor IS the host.
        $followed = $this->followHost($link, $user);

        // Attribution row for the QR Connect stats panel. `was_new_user` is
        // stamped once at creation and never flipped by later repeat scans.
        $connect = EventQrConnect::firstOrCreate(
            ['link_id' => $link->id, 'user_id' => $user->id],
            ['was_new_user' => $wasNew]
        );
        $dirty = false;
        if (!$connect->rsvp_id && $rsvp) { $connect->rsvp_id = $rsvp->id; $dirty = true; }
        if ($followed && !$connect->followed) { $connect->followed = true; $dirty = true; }
        if ($dirty) $connect->save();

        return [[
            'success'     => true,
            'status'      => $rsvpStatus,
            'followed'    => $followed || $connect->followed,
            'manage_url'  => $rsvp?->manageUrl(),
            'message'     => $rsvpStatus === 'waitlist'
                ? "You're on the waitlist — we'll email you the moment a spot opens. You're now connected with the host."
                : "You're going! We've saved your RSVP and connected you with the host.",
        ], 200];
    }

    /**
     * Create the follow of the event host if it doesn't exist yet.
     * Returns true when a NEW follow row was created (for attribution);
     * an already-following visitor stays untouched (idempotent).
     */
    private function followHost(Link $link, User $viewer): bool
    {
        $creatorId = (int) $link->user_id;
        if (!$creatorId || (int) $viewer->id === $creatorId) return false;

        $creator = $link->user ?: User::find($creatorId);
        if (!$creator) return false;
        if (!($creator->allow_followers ?? true)) return false;

        $exists = Follow::where('follower_id', $viewer->id)->where('creator_id', $creatorId)->exists();
        if ($exists) return false;

        // Pin the follow to the creator profile of the event's workspace
        // when one exists; fall back to the host's personal profile —
        // matching the viewer follow endpoint's semantics.
        $profile = null;
        if ($link->workspace_id) {
            $profile = \App\Modules\User\Models\CreatorProfile::where('workspace_id', $link->workspace_id)->first();
        }
        $profile ??= \App\Modules\User\Models\CreatorProfile::personalForUser($creatorId);

        Follow::create([
            'follower_id'        => $viewer->id,
            'creator_id'         => $creatorId,
            'creator_profile_id' => $profile?->id,
            'created_at'         => now(),
        ]);
        $creator->increment('followers_count');
        $profile?->increment('followers_count');

        try {
            app(\App\Services\Dm\DmDispatcher::class)->triggerNewFollower($creator, $viewer);
        } catch (\Throwable $e) { /* welcome rules must never block the connect */ }

        UserNotification::create([
            'user_id' => $creator->id,
            'type'    => 'new_follower',
            'data'    => ['follower_id' => $viewer->id, 'follower_name' => $viewer->name, 'follower_avatar' => \App\Support\PublicStorageUrl::resolve($viewer->avatar)],
            'created_at' => now(),
        ]);
        if ($creator->notify_new_follower) {
            try {
                \App\Modules\Common\Services\Emailer::send('follow.new_follower', $creator->email, [
                    'follower_name' => $viewer->name,
                ], ['user' => $creator->id, 'related' => $viewer]);
            } catch (\Throwable $e) {}
        }

        return true;
    }
}
