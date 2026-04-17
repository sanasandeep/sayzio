<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\IcsData;
use Illuminate\Http\Request;
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
        $projects = $request->user()->projects()->orderBy('name')->get();

        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = $request->user()->getAliasLengthLimits();
        $timezones    = self::TIMEZONES;
        return view('user.links.create-ics', compact('projects', 'prefillAlias', 'aliasLimits', 'timezones'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, null);

        $alias = $validated['alias'] ?: Link::generateAlias();

        $link = Link::create([
            'user_id'    => $request->user()->id,
            'type'       => 'ics',
            'alias'      => $alias,
            'title'      => $validated['event_name'],
            'project_id' => $validated['project_id'] ?? null,
            'is_active'  => true,
            'settings'   => $request->boolean('show_preview_page') ? ['show_preview_page' => true] : null,
        ]);

        IcsData::create($this->icsPayload($validated, $link->id));

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Event created successfully.');
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'ics', 404);

        $link->load('icsData');
        $ics = $link->icsData ?? new IcsData();

        $projects   = $request->user()->projects()->orderBy('name')->get();
        $timezones  = self::TIMEZONES;

        return view('user.links.edit-ics', compact('link', 'ics', 'projects', 'timezones'));
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'ics', 404);

        $validated = $this->validateRequest($request, $link);

        $newSettings = array_merge((array) $link->settings, [
            'show_preview_page' => $request->boolean('show_preview_page'),
            'rsvp_enabled'      => $request->boolean('rsvp_enabled'),
            'rsvp_allow_plus_ones' => $request->boolean('rsvp_allow_plus_ones'),
            'rsvp_collect_phone'   => $request->boolean('rsvp_collect_phone'),
        ]);

        // Push-to-calendar: store the chosen calendar account id (or null to detach)
        if ($request->has('push_calendar_account_id')) {
            $accountId = $request->input('push_calendar_account_id');
            if ($accountId) {
                $owns = \App\Modules\User\Models\CalendarAccount::where('id', $accountId)
                    ->where('user_id', $request->user()->id)->exists();
                if ($owns) {
                    $newSettings['push_calendar_account_id'] = (int) $accountId;
                }
            } else {
                unset($newSettings['push_calendar_account_id']);
            }
        }

        // Smart redirect rules — supported on every link type. A matched
        // rule overrides the .ics download with the rule's destination URL.
        if ($request->has('smart_rules_json')) {
            $rules = \App\Modules\User\Controllers\LinkController::sanitizeSmartRules(
                $request->input('smart_rules_json')
            );
            if (!empty($rules)) {
                $newSettings['smart_rules'] = $rules;
            } else {
                unset($newSettings['smart_rules']);
            }
        }

        // Protection & Scheduling (timezone, schedule, expiry, daily window,
        // banned countries) — driven by the shared partial and parsed by the
        // central LinkController helper so behavior stays identical across
        // every link-type editor.
        $ps = \App\Modules\User\Controllers\LinkController::applyProtectionScheduling($request);
        $newSettings = \App\Modules\User\Controllers\LinkController::mergeProtectionScheduling($newSettings, $ps['settings']);

        $link->update([
            'alias'      => $validated['alias'] ?: $link->alias,
            'title'      => $validated['event_name'],
            'project_id' => $validated['project_id'] ?? null,
            'expires_at' => $ps['expires_at'],
            'settings'   => $newSettings,
        ]);

        $link->loadMissing('icsData');
        $payload = $this->icsPayload($validated, $link->id);
        if ($link->icsData) {
            $link->icsData->update($payload);
        } else {
            IcsData::create($payload);
        }

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Event updated successfully.');
    }

    private function validateRequest(Request $request, ?Link $link): array
    {
        $aliasLimits = $request->user()->getAliasLengthLimits();
        $aliasRule = ['nullable', 'string', 'alpha_dash',
            'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max']];
        $aliasRule[] = $link
            ? Rule::unique('links', 'alias')->ignore($link->id)
            : Rule::unique('links', 'alias');

        return $request->validate([
            'alias' => $aliasRule,
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', $request->user()->id)->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'event_name'      => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'location'        => 'nullable|string|max:500',
            'organizer'       => 'nullable|string|max:255',
            'organizer_email' => 'nullable|email|max:255',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'timezone'        => 'required|string|max:100',
            'url'             => 'nullable|url|max:2048',
            'all_day'         => 'sometimes|boolean',

            'recurrence_freq'     => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_interval' => 'nullable|integer|min:1|max:365',
            'recurrence_count'    => 'nullable|integer|min:1|max:999',
            'recurrence_until'    => 'nullable|date',
            'recurrence_byday'    => 'nullable|array',
            'recurrence_byday.*'  => 'string|in:MO,TU,WE,TH,FR,SA,SU',

            'extra_schedules'             => 'nullable|array|max:50',
            'extra_schedules.*.start'     => 'nullable|date',
            'extra_schedules.*.end'       => 'nullable|date|after_or_equal:extra_schedules.*.start',
            'extra_schedules.*.label'    => 'nullable|string|max:255',
            'extra_schedules.*.location' => 'nullable|string|max:500',
        ]);
    }

    private function icsPayload(array $v, int $linkId): array
    {
        $extras = collect($v['extra_schedules'] ?? [])
            ->filter(fn ($x) => !empty($x['start']) && !empty($x['end']))
            ->map(fn ($x) => [
                'start'    => $x['start'],
                'end'      => $x['end'],
                'label'    => $x['label']    ?? null,
                'location' => $x['location'] ?? null,
            ])
            ->values()
            ->all();

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
            'recurrence_freq'     => $v['recurrence_freq'] ?? null,
            'recurrence_interval' => max(1, (int) ($v['recurrence_interval'] ?? 1)),
            'recurrence_count'    => $v['recurrence_count'] ?? null,
            'recurrence_until'    => $v['recurrence_until'] ?? null,
            'recurrence_byday'    => !empty($v['recurrence_byday']) ? implode(',', $v['recurrence_byday']) : null,
            'extra_schedules'     => $extras,
        ];
    }
}
