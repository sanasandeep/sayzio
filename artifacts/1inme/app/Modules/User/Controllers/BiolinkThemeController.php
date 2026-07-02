<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkTheme;
use App\Modules\User\Models\BiolinkThemeSchedule;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BiolinkThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Themes & schedules CRUD for a single biolink. Visible from the
 * link settings tab as "Themes" and exposed to the mobile app via
 * the BiolinkApi controllers.
 */
class BiolinkThemeController extends Controller
{
    public function __construct(protected BiolinkThemeResolver $resolver) {}

    protected function authorize(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
    }

    public function settingsIndex(Link $link)
    {
        $this->authorize($link);
        [$themes, $schedules] = $this->loadFor($link);
        return view('user.links.settings.themes', [
            'link'       => $link,
            'themes'     => $themes,
            'schedules'  => $schedules,
            'activeId'   => optional($this->resolver->currentScheduleFor($link))->id,
            'tzList'     => $this->commonTimezones(),
        ]);
    }

    public function jsonIndex(Link $link)
    {
        $this->authorize($link);
        [$themes, $schedules] = $this->loadFor($link);
        return response()->json([
            'themes'    => $themes->map(fn ($t) => $this->themeToArray($t))->values(),
            'schedules' => $schedules->map(fn ($s) => $this->scheduleToArray($s))->values(),
            'active_id' => optional($this->resolver->currentScheduleFor($link))->id,
        ]);
    }

    public function storeTheme(Request $request, Link $link)
    {
        $this->authorize($link);
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);
        $theme = BiolinkTheme::create([
            'link_id'  => $link->id,
            'name'     => trim($data['name']),
            'settings' => $this->resolver->snapshotFromLink($link),
        ]);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['theme' => $this->themeToArray($theme)]);
        }
        return back()->with('success', 'Theme saved.');
    }

    public function destroyTheme(Request $request, Link $link, BiolinkTheme $theme)
    {
        $this->authorize($link);
        abort_if($theme->link_id !== $link->id, 404);

        // CRITICAL: deleting a theme must NOT bypass revert. If a
        // schedule for this theme is currently active, we have to put
        // the live page back to its prev_settings snapshot first —
        // otherwise FK cascade will drop the schedule row and the
        // biolink stays frozen on the scheduled look forever.
        DB::transaction(function () use ($theme) {
            $schedules = BiolinkThemeSchedule::where('theme_id', $theme->id)
                ->whereIn('status', [BiolinkThemeSchedule::STATUS_ACTIVE, BiolinkThemeSchedule::STATUS_PENDING])
                ->lockForUpdate()
                ->get();
            foreach ($schedules as $sched) {
                if ($sched->status === BiolinkThemeSchedule::STATUS_ACTIVE) {
                    $link = Link::query()->lockForUpdate()->find($sched->link_id);
                    if ($link) {
                        $settings = $link->settings ?? [];
                        $prev     = (array) ($sched->prev_settings ?? []);
                        $current  = (array) ($settings['biolink'] ?? []);
                        foreach (BiolinkThemeResolver::THEMABLE_KEYS as $k) {
                            if (array_key_exists($k, $prev)) $current[$k] = $prev[$k];
                            else unset($current[$k]);
                        }
                        $settings['biolink'] = $current;
                        $link->settings = $settings;
                        $link->save();
                    }
                    $sched->reverted_at = now();
                }
                $sched->status = BiolinkThemeSchedule::STATUS_CANCELLED;
                $sched->save();
            }
            $theme->delete();
        });

        if ($request->wantsJson() || $request->ajax()) return response()->json(['ok' => true]);
        return back()->with('success', 'Theme deleted.');
    }

    public function storeSchedule(Request $request, Link $link)
    {
        $this->authorize($link);
        $data = $request->validate([
            'theme_id'  => 'required|integer|exists:biolink_themes,id',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
            'timezone'  => 'nullable|string|max:64',
        ]);
        $theme = BiolinkTheme::where('link_id', $link->id)->findOrFail($data['theme_id']);

        $tz = $data['timezone'] ?? \App\Support\PlatformTimezone::platformDefault();
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = \App\Support\PlatformTimezone::platformDefault();

        // Treat the form's local datetime strings as wall-clock in `$tz`.
        $startsAt = \Carbon\Carbon::parse($data['starts_at'], $tz)->utc();
        $endsAt   = \Carbon\Carbon::parse($data['ends_at'], $tz)->utc();

        // Block obviously bogus windows (already-ended) up-front so the
        // creator gets immediate feedback rather than the cron silently
        // marking the schedule completed on its next tick.
        abort_if($endsAt->isPast(), 422, 'Schedule end time is already in the past.');

        // Reject overlapping windows on the same link. Allowing overlaps
        // breaks revert-to-baseline: if A is active and B starts while
        // A is still running, B's prev_settings would snapshot A's
        // themed look and restoring it on B's end would re-apply A
        // instead of the true baseline. Forcing non-overlap keeps each
        // schedule's prev_settings rooted in the unthemed page state.
        abort_if(
            $this->hasOverlap($link, $startsAt, $endsAt, null),
            422,
            'Another scheduled theme already covers part of that window. Cancel it first or pick a different range.'
        );

        $sched = BiolinkThemeSchedule::create([
            'link_id'   => $link->id,
            'theme_id'  => $theme->id,
            'starts_at' => $startsAt,
            'ends_at'   => $endsAt,
            'timezone'  => $tz,
            'status'    => BiolinkThemeSchedule::STATUS_PENDING,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['schedule' => $this->scheduleToArray($sched->fresh('theme'))]);
        }
        return back()->with('success', 'Theme scheduled.');
    }

    public function updateSchedule(Request $request, Link $link, BiolinkThemeSchedule $schedule)
    {
        $this->authorize($link);
        abort_if($schedule->link_id !== $link->id, 404);
        // Active and completed schedules can't be re-timed (they're past
        // the activation snapshot point); cancel + re-create instead.
        abort_if(!in_array($schedule->status, [BiolinkThemeSchedule::STATUS_PENDING], true), 409,
            'Only pending schedules can be edited.');

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
            'timezone'  => 'nullable|string|max:64',
        ]);
        $tz = $data['timezone'] ?? $schedule->timezone ?? \App\Support\PlatformTimezone::platformDefault();
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = \App\Support\PlatformTimezone::platformDefault();
        $startsAt = \Carbon\Carbon::parse($data['starts_at'], $tz)->utc();
        $endsAt   = \Carbon\Carbon::parse($data['ends_at'], $tz)->utc();

        // Same already-ended guard as create — moving a pending
        // schedule entirely into the past would otherwise be silently
        // marked completed by the cron's next tick.
        abort_if($endsAt->isPast(), 422, 'Schedule end time is already in the past.');

        abort_if(
            $this->hasOverlap($link, $startsAt, $endsAt, $schedule->id),
            422,
            'Another scheduled theme already covers part of that window. Cancel it first or pick a different range.'
        );

        $schedule->starts_at = $startsAt;
        $schedule->ends_at   = $endsAt;
        $schedule->timezone  = $tz;
        $schedule->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['schedule' => $this->scheduleToArray($schedule->fresh('theme'))]);
        }
        return back()->with('success', 'Schedule updated.');
    }

    public function cancelSchedule(Request $request, Link $link, BiolinkThemeSchedule $schedule)
    {
        $this->authorize($link);
        abort_if($schedule->link_id !== $link->id, 404);

        DB::transaction(function () use ($schedule) {
            // If the schedule is currently active we MUST revert the
            // page first — otherwise cancellation would leave the
            // scheduled theme stuck on the live biolink forever.
            if ($schedule->status === BiolinkThemeSchedule::STATUS_ACTIVE) {
                $link = Link::query()->lockForUpdate()->find($schedule->link_id);
                if ($link) {
                    $settings = $link->settings ?? [];
                    $prev     = (array) ($schedule->prev_settings ?? []);
                    $current  = (array) ($settings['biolink'] ?? []);
                    foreach (BiolinkThemeResolver::THEMABLE_KEYS as $k) {
                        if (array_key_exists($k, $prev)) $current[$k] = $prev[$k];
                        else unset($current[$k]);
                    }
                    $settings['biolink'] = $current;
                    $link->settings = $settings;
                    $link->save();
                }
                $schedule->reverted_at = now();
            }
            $schedule->status = BiolinkThemeSchedule::STATUS_CANCELLED;
            $schedule->save();
        });

        if ($request->wantsJson() || $request->ajax()) return response()->json(['ok' => true]);
        return back()->with('success', 'Schedule cancelled.');
    }

    /**
     * True if the given UTC interval intersects any pending/active
     * schedule on the link (excluding $ignoreId, used for the edit
     * path so a schedule doesn't conflict with itself). Half-open
     * `[start, end)` so back-to-back schedules are allowed.
     */
    public static function hasOverlap(Link $link, \DateTimeInterface $start, \DateTimeInterface $end, ?int $ignoreId): bool
    {
        $q = BiolinkThemeSchedule::where('link_id', $link->id)
            ->whereIn('status', [BiolinkThemeSchedule::STATUS_PENDING, BiolinkThemeSchedule::STATUS_ACTIVE])
            ->where('starts_at', '<', $end)
            ->where('ends_at',   '>', $start);
        if ($ignoreId) $q->where('id', '!=', $ignoreId);
        return $q->exists();
    }

    /** @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Database\Eloquent\Collection} */
    protected function loadFor(Link $link): array
    {
        $themes = BiolinkTheme::where('link_id', $link->id)->orderByDesc('id')->get();
        $schedules = BiolinkThemeSchedule::where('link_id', $link->id)
            ->whereIn('status', [BiolinkThemeSchedule::STATUS_PENDING, BiolinkThemeSchedule::STATUS_ACTIVE])
            ->orderBy('starts_at')
            ->with('theme')
            ->get();
        return [$themes, $schedules];
    }

    protected function themeToArray(BiolinkTheme $t): array
    {
        return [
            'id'         => $t->id,
            'name'       => $t->name,
            'settings'   => $t->settings,
            'created_at' => optional($t->created_at)->toIso8601String(),
        ];
    }

    protected function scheduleToArray(BiolinkThemeSchedule $s): array
    {
        return [
            'id'          => $s->id,
            'theme_id'    => $s->theme_id,
            'theme_name'  => $s->theme?->name,
            'starts_at'   => optional($s->starts_at)->toIso8601String(),
            'ends_at'     => optional($s->ends_at)->toIso8601String(),
            'timezone'    => $s->timezone,
            'status'      => $s->status,
            'is_live'     => $s->isLive(),
            'applied_at'  => optional($s->applied_at)->toIso8601String(),
            'reverted_at' => optional($s->reverted_at)->toIso8601String(),
        ];
    }

    /** A short, friendly list of timezones for the schedule picker. */
    protected function commonTimezones(): array
    {
        return [
            'UTC',
            'America/Los_Angeles', 'America/Denver', 'America/Chicago', 'America/New_York', 'America/Sao_Paulo',
            'Europe/London', 'Europe/Berlin', 'Europe/Madrid', 'Europe/Athens',
            'Africa/Cairo', 'Africa/Johannesburg',
            'Asia/Dubai', 'Asia/Kolkata', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Seoul',
            'Australia/Sydney', 'Pacific/Auckland',
        ];
    }
}
