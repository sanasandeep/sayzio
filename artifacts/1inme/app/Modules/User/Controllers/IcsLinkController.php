<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IcsLinkController extends Controller
{
    private const TIMEZONES = [
        'UTC', 'America/New_York', 'America/Chicago', 'America/Denver',
        'America/Los_Angeles', 'America/Sao_Paulo',
        'Europe/London', 'Europe/Paris', 'Europe/Berlin',
        'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Asia/Dubai',
        'Asia/Singapore', 'Australia/Sydney', 'Pacific/Auckland',
        'Africa/Lagos', 'Africa/Cairo', 'Africa/Johannesburg',
    ];

    public function create(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();

        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = workspace_owner()->getAliasLengthLimits();
        $timezones    = self::TIMEZONES;
        $calAccounts  = $this->ownerCalendarAccounts();
        return view('user.links.create-ics', compact('projects', 'prefillAlias', 'aliasLimits', 'timezones', 'calAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, null);

        $alias = $validated['alias'] ?: Link::generateAlias();

        $settings = $this->initialSettings($request);

        $link = Link::create([
            'user_id'    => workspace_owner_id(),
            'type'       => 'ics',
            'alias'      => $alias,
            'title'      => $validated['event_name'],
            'project_id' => $validated['project_id'] ?? null,
            'is_active'  => true,
            'visibility' => $validated['visibility'] ?? 'public',
            'settings'   => $settings ?: null,
        ]);

        IcsData::create($this->icsPayload($validated, $link->id));

        $this->syncToCalendar($link, 'created');

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Event created successfully.');
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $link->load('icsData');
        $ics = $link->icsData ?? new IcsData();

        $projects   = workspace_owner()->projects()->orderBy('name')->get();
        $timezones  = self::TIMEZONES;
        $calAccounts = $this->ownerCalendarAccounts();
        $domains    = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.edit-ics', compact('link', 'ics', 'projects', 'timezones', 'calAccounts', 'domains'));
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $validated = $this->validateRequest($request, $link);

        $newSettings = array_merge((array) $link->settings, [
            'show_preview_page'    => $request->boolean('show_preview_page'),
            'rsvp_enabled'         => $request->boolean('rsvp_enabled'),
            'rsvp_allow_plus_ones' => $request->boolean('rsvp_allow_plus_ones'),
            'rsvp_collect_phone'   => $request->boolean('rsvp_collect_phone'),
            'rsvp_settings'        => $this->parseRsvpSettings($request),
            'calendar_sync_mode'   => $this->parseCalendarSyncMode($request),
        ]);

        // Calendar account binding (visible to the workspace owner only — members
        // see their owner's accounts in the dropdown but writes still gate by ownership).
        if ($request->has('push_calendar_account_id')) {
            $accountId = $request->input('push_calendar_account_id');
            if ($accountId) {
                $owns = CalendarAccount::where('id', $accountId)
                    ->where('user_id', workspace_owner_id())->exists();
                if ($owns) {
                    $newSettings['push_calendar_account_id'] = (int) $accountId;
                }
            } else {
                unset($newSettings['push_calendar_account_id']);
            }
        }

        if ($request->has('smart_rules_json')) {
            $rules = LinkController::sanitizeSmartRules($request->input('smart_rules_json'));
            if (!empty($rules)) {
                $newSettings['smart_rules'] = $rules;
            } else {
                unset($newSettings['smart_rules']);
            }
        }

        $ps = LinkController::applyProtectionScheduling($request);
        $newSettings = LinkController::mergeProtectionScheduling($newSettings, $ps['settings']);

        $link->update([
            'alias'      => ($validated['alias'] ?? null) ?: $link->alias,
            'title'      => $validated['event_name'],
            'project_id' => $validated['project_id'] ?? null,
            'expires_at' => $ps['expires_at'],
            'visibility' => $validated['visibility'] ?? 'public',
            'settings'   => $newSettings,
        ]);

        $link->loadMissing('icsData');
        $payload = $this->icsPayload($validated, $link->id);
        if ($link->icsData) {
            $link->icsData->update($payload);
        } else {
            IcsData::create($payload);
        }

        $this->syncToCalendar($link->fresh('icsData'), 'updated');

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Event updated successfully.');
    }

    private function validateRequest(Request $request, ?Link $link): array
    {
        $aliasLimits = workspace_owner()->getAliasLengthLimits();
        $aliasRule = ['nullable', 'string', new \App\Modules\User\Rules\AliasFormat(),
            'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max']];
        $aliasRule[] = new \App\Modules\User\Rules\UniqueAliasCi($link?->id);
        $aliasRule[] = new \App\Modules\Admin\Rules\NotBannedName();

        // Cross-midnight aware end-after-start rule. Same-day "9pm → 1am"
        // really means "next day 1am", so we accept any end whose offset
        // from start is between 0 and 36 hours.
        $endRule = function ($attribute, $value, $fail) use ($request) {
            try {
                $s = new \DateTime((string) $request->input('start_date'));
                $e = new \DateTime((string) $value);
            } catch (\Throwable $err) { return; }
            $diff = $e->getTimestamp() - $s->getTimestamp();
            if ($diff < 0) {
                // Cross-midnight — silently roll forward.
                $diff += 86400;
            }
            if ($diff < 0 || $diff > 36 * 3600) {
                $fail('End must be within 36 hours of the start.');
            }
        };

        return $request->validate([
            'alias' => $aliasRule,
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)
                    ->where('user_id', workspace_owner_id())->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'event_name'      => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'location'        => 'nullable|string|max:500',
            'organizer'       => 'nullable|string|max:255',
            'organizer_email' => 'nullable|email|max:255',
            'start_date'      => 'required|date',
            'end_date'        => ['required', 'date', $endRule],
            'timezone'        => 'required|string|max:100',
            'url'             => 'nullable|url|max:2048',
            'all_day'         => 'sometimes|boolean',

            'recurrence_freq'      => 'nullable|in:daily,weekly,monthly,yearly,weekdays',
            'recurrence_interval'  => 'nullable|integer|min:1|max:365',
            'recurrence_count'     => 'nullable|integer|min:1|max:999',
            'recurrence_until'     => 'nullable|date',
            'recurrence_byday'     => 'nullable|array',
            'recurrence_byday.*'   => 'string|in:MO,TU,WE,TH,FR,SA,SU',
            'monthly_mode'         => 'nullable|in:day_of_month,weekday_ordinal',
            'monthly_weekday_ordinal' => 'nullable|in:1,2,3,4,-1',
            'yearly_month'         => 'nullable|integer|min:1|max:12',

            'slots'                => 'nullable|array|max:50',
            'slots.*.start'        => 'nullable|date',
            'slots.*.end'          => 'nullable|date',
            'slots.*.label'        => 'nullable|string|max:255',
            'slots.*.location'     => 'nullable|string|max:500',
            'visibility'           => 'nullable|in:public,registered,followers,subscribers',
        ] + LinkController::protectionSchedulingRules());
    }

    private function icsPayload(array $v, int $linkId): array
    {
        $slots = collect($v['slots'] ?? [])
            ->filter(fn ($x) => !empty($x['start']) && !empty($x['end']))
            ->map(fn ($x) => [
                'start'    => $x['start'],
                'end'      => $x['end'],
                'label'    => $x['label']    ?? null,
                'location' => $x['location'] ?? null,
            ])
            ->values()
            ->all();

        // Translate the "every weekday" quick-pick into a real WEEKLY rule
        // with BYDAY=MO,TU,WE,TH,FR so .ics readers get a standard RRULE.
        $freq  = $v['recurrence_freq'] ?? null;
        $byday = $v['recurrence_byday'] ?? null;
        if ($freq === 'weekdays') {
            $freq  = 'weekly';
            $byday = ['MO', 'TU', 'WE', 'TH', 'FR'];
        }

        return [
            'link_id'         => $linkId,
            'event_name'      => $v['event_name'],
            'description'     => $v['description'] ?? null,
            'location'        => $v['location'] ?? null,
            'organizer'       => $v['organizer'] ?? null,
            'organizer_email' => $v['organizer_email'] ?? null,
            'start_date'      => $v['start_date'],
            'end_date'        => $v['end_date'],
            'timezone'        => $v['timezone'],
            'url'             => $v['url'] ?? null,
            'all_day'         => (bool) ($v['all_day'] ?? false),
            'recurrence_freq'     => $freq,
            'recurrence_interval' => max(1, (int) ($v['recurrence_interval'] ?? 1)),
            'recurrence_count'    => $v['recurrence_count'] ?? null,
            'recurrence_until'    => $v['recurrence_until'] ?? null,
            'recurrence_byday'    => !empty($byday) ? implode(',', $byday) : null,
            'monthly_mode'        => $freq === 'monthly' ? ($v['monthly_mode'] ?? 'day_of_month') : null,
            'monthly_weekday_ordinal' => $freq === 'monthly' && ($v['monthly_mode'] ?? null) === 'weekday_ordinal'
                ? ($v['monthly_weekday_ordinal'] ?? '1') : null,
            'yearly_month'        => $freq === 'yearly' ? ($v['yearly_month'] ?? null) : null,
            'slots'               => $slots,
            // Keep extra_schedules in sync for back-compat readers.
            'extra_schedules'     => array_slice($slots, 1),
        ];
    }

    private function parseRsvpSettings(Request $request): array
    {
        $deadline = $request->input('rsvp_deadline');
        $capacity = $request->input('rsvp_capacity');
        $reminder = $request->input('rsvp_reminder_hours_before');

        $questions = [];
        foreach ((array) $request->input('rsvp_questions', []) as $q) {
            $label = trim((string) ($q['label'] ?? ''));
            if ($label === '') continue;
            $type = in_array(($q['type'] ?? 'text'), ['text','select','checkbox'], true) ? $q['type'] : 'text';
            $opts = array_values(array_filter(array_map('trim',
                explode("\n", (string) ($q['options'] ?? '')))));
            $questions[] = [
                'label'    => mb_substr($label, 0, 191),
                'type'     => $type,
                'required' => !empty($q['required']),
                'options'  => $opts,
            ];
            if (count($questions) >= 10) break;
        }

        return array_filter([
            'capacity'              => $capacity !== null && $capacity !== '' ? max(0, (int) $capacity) : null,
            'waitlist_enabled'      => $request->boolean('rsvp_waitlist_enabled'),
            'deadline'              => $deadline ?: null,
            'send_confirmation'     => $request->boolean('rsvp_send_confirmation', true),
            'notify_owner'          => $request->boolean('rsvp_notify_owner', true),
            'reminder_hours_before' => $reminder !== null && $reminder !== '' ? max(0, (int) $reminder) : 24,
            'collect_company'       => $request->boolean('rsvp_collect_company'),
            'collect_role'          => $request->boolean('rsvp_collect_role'),
            'per_occurrence'        => $request->boolean('rsvp_per_occurrence'),
            'questions'             => $questions,
        ], fn ($v) => $v !== null);
    }

    private function parseCalendarSyncMode(Request $request): string
    {
        $mode = (string) $request->input('calendar_sync_mode', 'off');
        return in_array($mode, ['off', 'one_time', 'keep_in_sync'], true) ? $mode : 'off';
    }

    private function initialSettings(Request $request): array
    {
        $owner = workspace_owner();
        $defaultAccountId = $owner?->auto_sync_calendar_account_id;
        $settings = [];

        if ($request->boolean('show_preview_page')) {
            $settings['show_preview_page'] = true;
        }
        if ($request->boolean('rsvp_enabled')) {
            $settings['rsvp_enabled'] = true;
            $settings['rsvp_allow_plus_ones'] = $request->boolean('rsvp_allow_plus_ones');
            $settings['rsvp_collect_phone']   = $request->boolean('rsvp_collect_phone');
            $settings['rsvp_settings']        = $this->parseRsvpSettings($request);
        }

        // If the user has chosen a default sync target, auto-attach + auto-sync new events.
        if ($defaultAccountId && CalendarAccount::where('id', $defaultAccountId)
            ->where('user_id', workspace_owner_id())->exists()) {
            $settings['push_calendar_account_id'] = (int) $defaultAccountId;
            $settings['calendar_sync_mode']       = 'keep_in_sync';
        } else {
            $settings['calendar_sync_mode'] = $this->parseCalendarSyncMode($request);
            if ($request->filled('push_calendar_account_id')) {
                $accountId = (int) $request->input('push_calendar_account_id');
                if (CalendarAccount::where('id', $accountId)
                    ->where('user_id', workspace_owner_id())->exists()) {
                    $settings['push_calendar_account_id'] = $accountId;
                }
            }
        }

        return $settings;
    }

    private function ownerCalendarAccounts()
    {
        return CalendarAccount::where('user_id', workspace_owner_id())
            ->where('push_enabled', true)
            ->orderBy('provider')->get();
    }

    /**
     * Push or update the link in the bound calendar account when the
     * sync mode is keep_in_sync (or one_time on first save).
     */
    private function syncToCalendar(?Link $link, string $event): void
    {
        if (!$link) return;
        $s = (array) ($link->settings ?? []);
        $mode = $s['calendar_sync_mode'] ?? 'off';
        if ($mode === 'off') return;

        $accountId = $s['push_calendar_account_id'] ?? null;
        if (!$accountId) return;
        $account = CalendarAccount::where('id', $accountId)
            ->where('user_id', $link->user_id)->first();
        if (!$account) return;

        try {
            app(CalendarSyncService::class)->pushLink($account, $link);
        } catch (\Throwable $e) {
            Log::warning('Calendar push from IcsLinkController failed', [
                'link' => $link->id, 'event' => $event, 'err' => $e->getMessage(),
            ]);
        }
    }
}
