<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Organizer-facing event (ics link) create/edit for the mobile app.
 *
 * The web equivalent is {@see \App\Modules\User\Controllers\IcsLinkController}
 * — that controller supports the full editor (recurrence, RSVP questions,
 * calendar sync, agenda, documents, badges, …). This API surface deliberately
 * exposes only the ESSENTIALS the native form collects:
 *   title, start/end date-time, timezone, location, description, capacity,
 *   RSVP on/off.
 * All the "advanced" settings remain web-only; the mobile edit screen shows a
 * read-only summary of them with a "edit on the web" note. Nothing here ever
 * clobbers those advanced keys — they are read-modify-merged into settings.
 *
 * Workspace scoping: the Sanctum API path never runs SetActiveWorkspace, so a
 * bare Link::create() lands workspace_id = null and the row is hidden from the
 * workspace-scoped web list. We resolve the workspace explicitly (mirroring the
 * Api LinkController create path + .agents/memory/api-workspace-scope.md) and
 * assign it directly since workspace_id is NOT mass-assignable on Link.
 */
class EventApiController extends Controller
{
    use ApiResponses;

    /** Create an event (an `ics` link + companion IcsData row). */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        // Enforce the same plan caps the web route enforces via middleware:
        // links-ics store carries CheckPlanLimit:links + CheckPlanLimit:events.
        // The Sanctum API path has no middleware, so we replicate them here.
        if ($limit = $this->planLimitError($user)) {
            return $limit;
        }

        // Per-plan alias floor enforced on create, matching web + Api LinkController.
        $aliasLimits = $user->getAliasLengthLimits();

        $data = $request->validate($this->rules($user, $aliasLimits, null));

        $alias = ($data['alias'] ?? null) ?: Link::generateAlias();
        while (Link::where('alias', $alias)->exists()) {
            $alias = Link::generateAlias();
        }

        $workspaceId = $this->resolveWorkspaceId($user, $request->input('workspace_id'));

        $settings = $this->applyRsvp([], $request);

        $link = new Link([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => $alias,
            'title'      => $data['title'],
            'is_active'  => true,
            'visibility' => $data['visibility'] ?? 'public',
            'settings'   => $settings ?: null,
        ]);
        // workspace_id is not mass-assignable — assign directly (see memory note).
        if ($workspaceId !== null && Schema::hasColumn('links', 'workspace_id')) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();

        IcsData::create($this->icsPayload($data, $link->id));

        return $this->created($this->shape($link->fresh('icsData')));
    }

    /** Prefill payload for the mobile edit screen (essentials + advanced summary). */
    public function show(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

        return $this->ok($this->shape($link->load('icsData')));
    }

    /** Update an event's essentials + RSVP on/off (advanced settings untouched). */
    public function update(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $aliasLimits = $user->getAliasLengthLimits();
        $data = $request->validate($this->rules($user, $aliasLimits, $link));

        // Read-modify-merge so the mobile essentials save never wipes the
        // advanced (recurrence/RSVP-questions/calendar-sync/…) settings that
        // are only editable on the web.
        $settings = $this->applyRsvp((array) ($link->settings ?? []), $request);

        $link->title      = $data['title'];
        $link->visibility = $data['visibility'] ?? $link->visibility;
        if (!empty($data['alias'])) {
            $link->alias = $data['alias'];
        }
        $link->settings = $settings ?: null;
        $link->save();

        $link->loadMissing('icsData');
        $payload = $this->icsPayload($data, $link->id);
        if ($link->icsData) {
            $link->icsData->update($payload);
        } else {
            IcsData::create($payload);
        }

        return $this->ok($this->shape($link->fresh('icsData')));
    }

    /**
     * Cancel an event — mobile mirror of {@see IcsLinkController::cancel}.
     *
     * The settings-state + calendar-sync logic is shared via
     * {@see \App\Modules\User\Services\EventCancellationService} (no
     * copy-paste). When `notify_guests` is set we ALSO fire the cancellation
     * broadcast to `all_rsvps`; if that hits the per-event rate limit the
     * event STAYS cancelled and we surface a `broadcast_skipped` flag +
     * message so the app can point the organizer at the broadcast screen.
     */
    public function cancel(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        // Owner-only for this destructive action — parity with the web
        // IcsLinkController::cancel (link->user_id === workspace_owner_id()).
        // Workspace collaborators with links.edit can PATCH the event but must
        // NOT be able to cancel it.
        if (!$this->isEventOwner($request, $link)) return $this->forbidden();

        app(\App\Modules\User\Services\EventCancellationService::class)->cancel($link);
        $link = $link->fresh('icsData');

        $notified = null;
        $broadcastSkipped = false;
        $broadcastMessage = null;

        if ($request->boolean('notify_guests')) {
            $subject = 'Cancelled: ' . ($link->title ?: 'our event');
            $message = "We're sorry to share that this event has been cancelled. "
                . "We apologise for any inconvenience. If you have any questions, please reply to this email.";
            try {
                $broadcast = app(\App\Modules\User\Services\EventBroadcastService::class)
                    ->send($link, (int) $link->user_id, 'all_rsvps', $subject, $message);
                $notified = (int) $broadcast->recipients_count;
            } catch (\App\Modules\User\Services\EventBroadcastLimitException $e) {
                // The event is already cancelled; only the notice couldn't go
                // out. Surface the limit message so the app can hand off to the
                // broadcast screen.
                $broadcastSkipped = true;
                $broadcastMessage = $e->getMessage();
            }
        }

        return $this->ok($this->shape($link) + [
            'notified_count'    => $notified,
            'broadcast_skipped' => $broadcastSkipped,
            'broadcast_message' => $broadcastMessage,
        ]);
    }

    /** Reactivate a previously-cancelled event (mirror of IcsLinkController::reactivate). */
    public function reactivate(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        // Owner-only — parity with IcsLinkController::reactivate. See cancel().
        if (!$this->isEventOwner($request, $link)) return $this->forbidden();

        app(\App\Modules\User\Services\EventCancellationService::class)->reactivate($link);

        return $this->ok($this->shape($link->fresh('icsData')));
    }

    // ─── Plan limits (mirror CheckPlanLimit middleware) ─────────────

    /**
     * Server-side equivalent of the web `links-ics` store route's
     * `CheckPlanLimit:links` + `CheckPlanLimit:events` middleware chain
     * ({@see \App\Modules\User\Middleware\CheckPlanLimit}). The Sanctum API
     * path runs no middleware, so we replicate the gate here.
     *
     * Returns an API plan-gate error envelope (HTTP 402, includes the
     * recommended upgrade plan the mobile app reads) when the user is over a
     * cap, or null when creation is allowed:
     *  - `user.plan_limits.bypass` holders bypass all gating (identical to the
     *    middleware + User::getPlanFeature contract).
     *  - `max_links` / `max_events` of -1 means unlimited.
     *  - counts use withoutGlobalScope('workspace') so the cap reflects the
     *    whole account (plan caps are account-wide, per memory), not just the
     *    currently-resolved workspace.
     */
    private function planLimitError($user)
    {
        // Bypass holders are never limited (same contract as the middleware).
        if ($user->hasPermission('user.plan_limits.bypass')) {
            return null;
        }

        $plan = $user->plan;
        if (!$plan || !$plan->features) {
            return null;
        }
        $features = $plan->features;

        // 1) Overall link cap.
        $maxLinks = $features['max_links'] ?? 5;
        if ($maxLinks !== -1) {
            $linkCount = Link::withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->count();
            if ($linkCount >= $maxLinks) {
                return $this->planGate(
                    "You've reached your plan's link limit ({$maxLinks}).",
                    'max_links',
                    $user,
                    402,
                    'plan_upgrade_required',
                    $linkCount
                );
            }
        }

        // 2) Events feature must be enabled at all.
        if (empty($features['events'])) {
            return $this->planGate(
                'Events are not available on your current plan.',
                'events',
                $user
            );
        }

        // 3) Per-plan event cap (type=ics links).
        $maxEvents = (int) ($features['max_events'] ?? 0);
        if ($maxEvents !== -1) {
            $eventCount = Link::withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->where('type', 'ics')->count();
            if ($eventCount >= $maxEvents) {
                return $this->planGate(
                    "You've reached your plan's event limit ({$maxEvents}).",
                    'max_events',
                    $user,
                    402,
                    'plan_upgrade_required',
                    $eventCount
                );
            }
        }

        return null;
    }

    // ─── Validation ─────────────────────────────────────────────────

    /**
     * Essential-field rules, mirroring the corresponding subset of
     * IcsLinkController::validateRequest(). `end_date` uses the same
     * cross-midnight-aware "within 36h of start" rule as the web form.
     */
    private function rules($user, array $aliasLimits, ?Link $link): array
    {
        $endRule = function ($attribute, $value, $fail) {
            try {
                $s = new \DateTime((string) request()->input('start_date'));
                $e = new \DateTime((string) $value);
            } catch (\Throwable $err) {
                return;
            }
            $diff = $e->getTimestamp() - $s->getTimestamp();
            if ($diff < 0) {
                $diff += 86400; // cross-midnight roll-forward
            }
            if ($diff < 0 || $diff > 36 * 3600) {
                $fail('End must be within 36 hours of the start.');
            }
        };

        return [
            'alias' => [
                'nullable', 'string',
                'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'],
                new \App\Modules\User\Rules\AliasFormat(),
                new \App\Modules\Admin\Rules\NotBannedName(),
                new \App\Modules\User\Rules\UniqueAliasCi($link?->id),
            ],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location'    => ['nullable', 'string', 'max:500'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', $endRule],
            'timezone'    => ['required', 'string', 'max:100'],
            'capacity'    => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rsvp_enabled' => ['sometimes', 'boolean'],
            'visibility'  => ['nullable', 'in:public,registered,followers,subscribers'],
            'workspace_id' => ['nullable', 'integer'],
        ];
    }

    /** Companion IcsData attributes for the essentials the mobile form owns. */
    private function icsPayload(array $v, int $linkId): array
    {
        return [
            'link_id'     => $linkId,
            'event_name'  => $v['title'],
            'description' => $v['description'] ?? null,
            'location'    => $v['location'] ?? null,
            'start_date'  => $v['start_date'],
            'end_date'    => $v['end_date'],
            'timezone'    => $v['timezone'],
        ];
    }

    /**
     * Merge the RSVP on/off toggle + capacity into the link settings without
     * disturbing any advanced RSVP config. `rsvp_disabled` is the canonical
     * opt-out flag consulted by RedirectController::isRsvpAvailable() (Task
     * #3674: free events accept RSVPs by default). Capacity lives under
     * `rsvp_settings.capacity`, matching the web editor.
     */
    private function applyRsvp(array $settings, Request $request): array
    {
        if ($request->has('rsvp_enabled')) {
            $settings['rsvp_disabled'] = !$request->boolean('rsvp_enabled');
        }

        if ($request->has('capacity')) {
            $cap = $request->input('capacity');
            $rsvp = (array) ($settings['rsvp_settings'] ?? []);
            $rsvp['capacity'] = ($cap !== null && $cap !== '') ? max(0, (int) $cap) : null;
            $settings['rsvp_settings'] = $rsvp;
        }

        return $settings;
    }

    // ─── Serialization ──────────────────────────────────────────────

    /**
     * The mobile edit-screen payload: the essentials the form edits plus a
     * read-only `advanced` summary (recurrence / RSVP questions / calendar
     * sync) rendered as "edit on the web" context.
     */
    private function shape(Link $link): array
    {
        $ics = $link->icsData;
        $s   = (array) ($link->settings ?? []);
        $rsvpSettings = (array) ($s['rsvp_settings'] ?? []);

        $activeTiers = $link->eventTicketTiers()->where('is_active', true)->get();

        return [
            'id'          => $link->id,
            'alias'       => $link->alias,
            'title'       => $link->title,
            'description' => $ics?->description,
            'location'    => $ics?->location,
            'start_date'  => optional($ics?->start_date)->toIso8601String(),
            'end_date'    => optional($ics?->end_date)->toIso8601String(),
            'timezone'    => $ics?->timezone ?? 'UTC',
            'visibility'  => $link->visibility,
            'capacity'    => isset($rsvpSettings['capacity']) ? (int) $rsvpSettings['capacity'] : null,
            'rsvp_enabled' => \App\Modules\Common\Controllers\RedirectController::isRsvpAvailable($link, $activeTiers),
            // Event cancellation state (Sayzio events). Mirrors the flags
            // EventTicketApiController exposes so mobile can render a
            // "Cancelled" state + reactivate affordance.
            'cancelled'    => $link->isEventCancelled(),
            'cancelled_at' => optional($link->eventCancelledAt())->toIso8601String(),
            'web_edit_url' => url('/user/links/' . $link->id . '/edit-ics'),
            // Read-only advanced summary — everything below is web-only to edit.
            'advanced'    => [
                'recurrence'         => $this->recurrenceSummary($ics),
                'rsvp_question_count' => count((array) ($rsvpSettings['questions'] ?? [])),
                'calendar_sync_mode' => (string) ($s['calendar_sync_mode'] ?? 'off'),
                'ticketing_enabled'  => (bool) ($s['ticketing_enabled'] ?? false),
            ],
        ];
    }

    /** Human-readable recurrence label for the advanced summary. */
    private function recurrenceSummary(?IcsData $ics): string
    {
        $freq = $ics?->recurrence_freq;
        if (!$freq) return 'Does not repeat';

        $interval = max(1, (int) ($ics->recurrence_interval ?? 1));
        $labels = [
            'daily'   => 'day',
            'weekly'  => 'week',
            'monthly' => 'month',
            'yearly'  => 'year',
        ];
        $unit = $labels[$freq] ?? $freq;
        return $interval > 1 ? "Every {$interval} {$unit}s" : "Every {$unit}";
    }

    // ─── Workspace-aware access (mirrors EventTicketApiController) ────

    protected function findEventLink(Request $request, int $id): ?Link
    {
        $user = $request->user();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($id);
        if ($link) return $link;

        if (!Schema::hasColumn('links', 'workspace_id')) return null;
        $workspaceIds = $user->accessibleWorkspaces()->pluck('id')->all();
        if (empty($workspaceIds)) return null;

        return Link::where('type', 'ics')->whereIn('workspace_id', $workspaceIds)->find($id);
    }

    /**
     * Owner-only gate for destructive event actions (cancel/reactivate),
     * mirroring the web IcsLinkController's `link->user_id === workspace_owner_id()`.
     *
     * Events (`ics` links) are always created with `user_id = workspace_owner_id`,
     * so the link owner IS the workspace owner. A workspace collaborator (who may
     * hold links.edit and can therefore PATCH the event) is NOT the owner and is
     * refused here — matching the web policy for this destructive action.
     */
    protected function isEventOwner(Request $request, Link $link): bool
    {
        $user = $request->user();

        if ((int) $link->user_id === (int) $user->id) return true;

        // Belt-and-suspenders: if the link is workspace-scoped, the workspace
        // owner is also the owner even if user_id ever diverged.
        if (!empty($link->workspace_id)) {
            $workspace = Workspace::find($link->workspace_id);
            if ($workspace && (int) $workspace->owner_user_id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    protected function canAct(Request $request, Link $link, string $permission): bool
    {
        $user = $request->user();

        if ((int) $link->user_id === (int) $user->id) return true;
        if (empty($link->workspace_id)) return true;

        $workspace = Workspace::find($link->workspace_id);
        if (!$workspace) return true;

        return $user->canInWorkspace($workspace, $permission);
    }
}
