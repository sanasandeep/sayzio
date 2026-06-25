<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BiolinkTheme;
use App\Modules\User\Models\BiolinkThemeSchedule;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BiolinkThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Mobile-app surface for the biolink saved-theme + scheduling feature.
 * Mirrors `BiolinkThemeController` (web) but always returns JSON and
 * is auth-gated by the api token middleware in `routes/api.php`.
 */
class BiolinkThemeApiController extends Controller
{
    use ApiResponses;

    public function __construct(protected BiolinkThemeResolver $resolver) {}

    protected function ownedLink(Request $request, int $id): ?Link
    {
        $link = Link::where('id', $id)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->first();
        if (!$link) return null;
        $user = $request->user();
        if (!$user || (int) $link->user_id !== (int) $user->id) return null;
        return $link;
    }

    public function index(Request $request, int $id)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $themes = BiolinkTheme::where('link_id', $link->id)->orderByDesc('id')->get();
        $schedules = BiolinkThemeSchedule::where('link_id', $link->id)
            ->whereIn('status', [BiolinkThemeSchedule::STATUS_PENDING, BiolinkThemeSchedule::STATUS_ACTIVE])
            ->orderBy('starts_at')->with('theme')->get();

        return $this->ok([
            'themes' => $themes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'created_at' => optional($t->created_at)->toIso8601String(),
            ])->values(),
            'schedules' => $schedules->map(fn ($s) => [
                'id' => $s->id,
                'theme_id' => $s->theme_id,
                'theme_name' => $s->theme?->name,
                'starts_at' => optional($s->starts_at)->toIso8601String(),
                'ends_at' => optional($s->ends_at)->toIso8601String(),
                'timezone' => $s->timezone,
                'status' => $s->status,
                'is_live' => $s->isLive(),
            ])->values(),
            'active_id' => optional($this->resolver->currentScheduleFor($link))->id,
        ]);
    }

    public function storeTheme(Request $request, int $id)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $data = $request->validate(['name' => 'required|string|max:120']);
        $theme = BiolinkTheme::create([
            'link_id' => $link->id,
            'name' => trim($data['name']),
            'settings' => $this->resolver->snapshotFromLink($link),
        ]);
        return $this->ok(['theme' => ['id' => $theme->id, 'name' => $theme->name]]);
    }

    public function destroyTheme(Request $request, int $id, int $themeId)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $theme = BiolinkTheme::where('link_id', $link->id)->where('id', $themeId)->first();
        if (!$theme) return $this->notFound('Theme not found');

        // Same revert-then-cancel safety net as the web destroyTheme:
        // never let an FK cascade drop an active schedule without
        // restoring the live page first.
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
        return $this->ok(['deleted' => true]);
    }

    public function updateSchedule(Request $request, int $id, int $scheduleId)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $schedule = BiolinkThemeSchedule::where('link_id', $link->id)->where('id', $scheduleId)->first();
        if (!$schedule) return $this->notFound('Schedule not found');
        if ($schedule->status !== BiolinkThemeSchedule::STATUS_PENDING) {
            return $this->fail('Only pending schedules can be edited.', 409, 'invalid_state');
        }

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
            'timezone'  => 'nullable|string|max:64',
        ]);
        $tz = $data['timezone'] ?? $schedule->timezone ?? 'UTC';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = 'UTC';
        $startsAt = \Carbon\Carbon::parse($data['starts_at'], $tz)->utc();
        $endsAt   = \Carbon\Carbon::parse($data['ends_at'], $tz)->utc();
        if ($endsAt->isPast()) {
            return $this->fail('Schedule end time is already in the past.', 422, 'invalid_window');
        }
        if (\App\Modules\User\Controllers\BiolinkThemeController::hasOverlap($link, $startsAt, $endsAt, $schedule->id)) {
            return $this->fail('Another scheduled theme already covers part of that window.', 422, 'overlap');
        }
        $schedule->starts_at = $startsAt;
        $schedule->ends_at   = $endsAt;
        $schedule->timezone  = $tz;
        $schedule->save();
        return $this->ok(['schedule' => ['id' => $schedule->id]]);
    }

    public function storeSchedule(Request $request, int $id)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $data = $request->validate([
            'theme_id'  => 'required|integer|exists:biolink_themes,id',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
            'timezone'  => 'nullable|string|max:64',
        ]);
        $theme = BiolinkTheme::where('link_id', $link->id)->find($data['theme_id']);
        if (!$theme) return $this->notFound('Theme not found');

        $tz = $data['timezone'] ?? 'UTC';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = 'UTC';

        $startsAt = \Carbon\Carbon::parse($data['starts_at'], $tz)->utc();
        $endsAt   = \Carbon\Carbon::parse($data['ends_at'], $tz)->utc();
        if ($endsAt->isPast()) {
            return $this->fail('Schedule end time is already in the past.', 422, 'invalid_window');
        }
        if (\App\Modules\User\Controllers\BiolinkThemeController::hasOverlap($link, $startsAt, $endsAt, null)) {
            return $this->fail('Another scheduled theme already covers part of that window.', 422, 'overlap');
        }

        $sched = BiolinkThemeSchedule::create([
            'link_id'   => $link->id,
            'theme_id'  => $theme->id,
            'starts_at' => $startsAt,
            'ends_at'   => $endsAt,
            'timezone'  => $tz,
            'status'    => BiolinkThemeSchedule::STATUS_PENDING,
        ]);
        return $this->ok(['schedule' => ['id' => $sched->id]]);
    }

    public function cancelSchedule(Request $request, int $id, int $scheduleId)
    {
        $link = $this->ownedLink($request, $id);
        if (!$link) return $this->notFound('Link in Bio not found');

        $schedule = BiolinkThemeSchedule::where('link_id', $link->id)->where('id', $scheduleId)->first();
        if (!$schedule) return $this->notFound('Schedule not found');

        DB::transaction(function () use ($schedule) {
            // Same revert-then-cancel logic as the web controller — do
            // NOT skip the revert when status=active or the live page
            // would stay frozen on the scheduled theme.
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

        return $this->ok(['cancelled' => true]);
    }
}
