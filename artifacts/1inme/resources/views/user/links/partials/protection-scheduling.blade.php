{{--
    Shared "Protection & Scheduling" editor.
    Drops into any link-editor <form>. Self-contained Alpine component
    so it can live alongside other partials without state collisions.

    Usage:  @include('user.links.partials.protection-scheduling', ['link' => $link])

    Posts these inputs (LinkController::applyProtectionScheduling parses them):
      tz                       Timezone identifier (e.g. "America/New_York")
      start_at                 datetime-local (naive wall-clock in the chosen tz)
      expires_at               datetime-local (naive wall-clock in the chosen tz)
      _exp_mode                none|date|clicks|first_click
      max_clicks               integer
      expire_on_first_click    0|1 (derived from _exp_mode)
      expiry_url               URL
      active_window_enabled    0|1
      active_window_starts[]   HH:MM (one per slot — supports multiple slots/day with breaks)
      active_window_ends[]     HH:MM (parallel array, paired by index with active_window_starts)
      active_window_days[]     mon|tue|wed|thu|fri|sat|sun
      country_blocklist        comma-separated ISO codes (e.g. "RU,KP")
--}}
@auth
    @include('user.partials._plan_lock', ['feature' => 'link_expiry', 'kind' => 'flag', 'label' => 'Link expiry'])
    @include('user.partials._plan_lock', ['feature' => 'link_active_window', 'kind' => 'flag', 'label' => 'Active-window scheduling'])
    @include('user.partials._plan_lock', ['feature' => 'link_geo_targeting', 'kind' => 'flag', 'label' => 'Geo targeting'])
@endauth
@php
    $s_ps          = (array) ($link->settings ?? []);
    $tz_ps         = $s_ps['timezone'] ?? 'UTC';
    $startLocal    = '';
    $expiresLocal  = '';
    try {
        if (!empty($s_ps['start_at'])) {
            $startLocal = \Carbon\Carbon::parse($s_ps['start_at'])->setTimezone($tz_ps)->format('Y-m-d\TH:i');
        }
        if ($link->expires_at) {
            $expiresLocal = $link->expires_at->copy()->setTimezone($tz_ps)->format('Y-m-d\TH:i');
        }
    } catch (\Throwable $e) { /* fall through with empty defaults */ }

    $expMode_ps = !empty($s_ps['expire_on_first_click']) ? 'first_click'
        : ($link->expires_at ? 'date' : 'none');
    $maxClicks_ps   = (int) ($s_ps['max_clicks'] ?? 0);
    $clicksLimitOn  = $maxClicks_ps > 0;
    $totalClicks_ps = (int) ($link->total_clicks ?? 0);

    $aw         = (array) ($s_ps['active_window'] ?? []);
    $awEnabled  = !empty($aw['enabled']);
    $awDays     = (array) ($aw['days'] ?? ['mon','tue','wed','thu','fri']);
    // Multi-slot support, with backward compat for the old single-window shape.
    $awSlots = [];
    if (!empty($aw['slots']) && is_array($aw['slots'])) {
        foreach ($aw['slots'] as $sl) {
            if (!empty($sl['start']) && !empty($sl['end'])) {
                $awSlots[] = ['start' => $sl['start'], 'end' => $sl['end']];
            }
        }
    } elseif (!empty($aw['start']) && !empty($aw['end'])) {
        $awSlots[] = ['start' => $aw['start'], 'end' => $aw['end']];
    }
    if (empty($awSlots)) {
        $awSlots[] = ['start' => '09:00', 'end' => '17:00'];
    }

    $blocklist  = implode(',', (array) ($s_ps['country_blocklist'] ?? []));

    $tzList = [
        'UTC',
        'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
        'America/Toronto', 'America/Mexico_City', 'America/Sao_Paulo',
        'Europe/London', 'Europe/Dublin', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid',
        'Europe/Rome', 'Europe/Amsterdam', 'Europe/Stockholm', 'Europe/Moscow',
        'Africa/Lagos', 'Africa/Cairo', 'Africa/Johannesburg', 'Africa/Nairobi',
        'Asia/Dubai', 'Asia/Tehran', 'Asia/Karachi', 'Asia/Kolkata', 'Asia/Dhaka',
        'Asia/Bangkok', 'Asia/Singapore', 'Asia/Hong_Kong', 'Asia/Shanghai',
        'Asia/Tokyo', 'Asia/Seoul',
        'Australia/Perth', 'Australia/Sydney', 'Pacific/Auckland', 'Pacific/Honolulu',
    ];
@endphp

<div class="glass rounded-2xl p-6 mb-6"
     x-data="protectionScheduling({
        tz: @js($tz_ps),
        expMode: @js($expMode_ps),
        clicksLimitOn: @js($clicksLimitOn),
        awEnabled: @js($awEnabled),
        awDays: @js($awDays),
        awSlots: @js($awSlots),
     })">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-white">Protection &amp; Scheduling</h2>
            <p class="text-xs text-white/40 mt-1">Control exactly when this link works, how many clicks it accepts, and which countries can reach it. All times use your selected timezone.</p>
        </div>
    </div>

    {{-- Timezone — applies to start, expiry, and the daily active window. --}}
    <div class="mb-4">
        <label class="block text-sm text-white/60 mb-1">Timezone</label>
        <select name="tz" x-model="tz"
                class="w-full max-w-xs border border-white/10 rounded-xl px-3 py-2 text-sm bg-white/[0.03] focus:ring-2 focus:ring-blue-500/40">
            @foreach($tzList as $tz)
                <option value="{{ $tz }}" {{ $tz_ps === $tz ? 'selected' : '' }}>{{ $tz }}</option>
            @endforeach
        </select>
        <p class="text-xs text-white/30 mt-1">All schedule times below are interpreted in this zone.</p>
    </div>

    {{-- Schedule: goes-live + expiry mode --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-white/60 mb-1">Goes live at <span class="text-white/30">(optional)</span></label>
            <input type="datetime-local" name="start_at"
                   value="{{ old('start_at', $startLocal) }}"
                   class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
            <p class="text-xs text-white/30 mt-1">Visitors before this time see "not yet available".</p>
        </div>

        <div>
            <label class="block text-sm text-white/60 mb-1">Expiry rule</label>
            <select name="_exp_mode" x-model="expMode"
                    class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <option value="none">Never expires</option>
                <option value="date">Expires on a specific date</option>
                <option value="first_click">One-time use (expires after first click)</option>
            </select>
        </div>

        <div x-show="expMode === 'date'" x-cloak>
            <label class="block text-sm text-white/60 mb-1">Expiration date</label>
            <input type="datetime-local" name="expires_at"
                   value="{{ old('expires_at', $expiresLocal) }}"
                   class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
        </div>
    </div>

    {{-- Click limit — independent of the expiry rule above, so it can be
         combined with date-based expiry (link stops at whichever happens
         first) or used on its own. --}}
    <div class="border-t border-white/5 pt-4 mt-4 mb-4">
        <input type="hidden" name="click_limit_enabled" :value="clicksLimitOn ? '1' : '0'">
        <label class="flex items-center gap-3 cursor-pointer mb-3">
            <input type="checkbox" x-model="clicksLimitOn"
                   class="rounded text-blue-400 focus:ring-blue-500/40">
            <div>
                <div class="text-sm font-medium text-white">Limit total clicks</div>
                <p class="text-xs text-white/40 mt-0.5">Stop accepting visits once this link has been opened a set number of times. Works alongside any expiry rule above.</p>
            </div>
        </label>

        <div x-show="clicksLimitOn" x-cloak class="ml-7">
            <label class="block text-sm text-white/60 mb-1">Maximum clicks</label>
            <input type="number" min="1" max="1000000000" name="max_clicks"
                   value="{{ old('max_clicks', $maxClicks_ps ?: '') }}"
                   placeholder="e.g. 100"
                   class="w-full max-w-xs border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
            <p class="text-xs text-white/30 mt-1">Used so far: <span class="font-mono text-white/50">{{ number_format($totalClicks_ps) }}</span> click{{ $totalClicks_ps === 1 ? '' : 's' }}.</p>
        </div>
    </div>

    <div x-show="expMode !== 'none' || clicksLimitOn" x-cloak class="mb-4">
        <label class="block text-sm text-white/60 mb-1">After expiry, redirect to <span class="text-white/30">(optional)</span></label>
        <input type="url" name="expiry_url"
               value="{{ old('expiry_url', $s_ps['expiry_url'] ?? '') }}"
               placeholder="https://example.com/expired"
               class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
        <p class="text-xs text-white/30 mt-1">Leave empty to show the default "link expired" page.</p>
    </div>

    {{-- Daily active window --}}
    <div class="border-t border-white/5 pt-4 mt-4">
        <input type="hidden" name="active_window_enabled" :value="awEnabled ? '1' : '0'">
        <label class="flex items-center gap-3 cursor-pointer mb-3">
            <input type="checkbox" x-model="awEnabled"
                   class="rounded text-blue-400 focus:ring-blue-500/40">
            <div>
                <div class="text-sm font-medium text-white">Only active during specific hours each day</div>
                <p class="text-xs text-white/40 mt-0.5">Outside this window the link behaves as expired.</p>
            </div>
        </label>

        <div x-show="awEnabled" x-cloak class="ml-7 space-y-3">
            <div class="space-y-2">
                <template x-for="(slot, i) in awSlots" :key="i">
                    <div class="flex items-end gap-2 max-w-md">
                        <div class="flex-1">
                            <label class="block text-xs text-white/60 mb-1" x-text="i === 0 ? 'From' : ''">&nbsp;</label>
                            <input type="time" :name="`active_window_starts[${i}]`" x-model="slot.start"
                                   class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-white/60 mb-1" x-text="i === 0 ? 'Until' : ''">&nbsp;</label>
                            <input type="time" :name="`active_window_ends[${i}]`" x-model="slot.end"
                                   class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                        </div>
                        <button type="button" @click="awSlots.splice(i, 1)" x-show="awSlots.length > 1"
                                class="h-9 w-9 mb-0.5 flex items-center justify-center rounded-lg border border-white/10 text-white/50 hover:text-rose-300 hover:border-rose-400/40"
                                title="Remove this slot">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </template>
                <button type="button" @click="awSlots.push({ start: '18:00', end: '22:00' })"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-300 hover:text-blue-200">
                    <i class="fas fa-plus"></i> Add another time slot
                </button>
            </div>
            <p class="text-xs text-white/30">Tip: add multiple slots to leave a break in the middle of the day (e.g. 09:00–12:00 and 14:00–18:00). Within a single slot, if "Until" is earlier than "From" the window wraps past midnight (e.g. 22:00–02:00).</p>

            <div>
                <label class="block text-xs text-white/60 mb-2">Active days</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $val => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="active_window_days[]" value="{{ $val }}"
                                   x-model="awDays" class="sr-only peer">
                            <span class="inline-flex items-center justify-center w-12 h-9 text-xs font-medium rounded-lg border border-white/10 bg-white/[0.03] text-white/60 peer-checked:bg-blue-500/20 peer-checked:border-blue-500/50 peer-checked:text-white">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Banned countries --}}
    <div class="border-t border-white/5 pt-4 mt-4">
        <label class="block text-sm font-medium text-white mb-1">Banned locations</label>
        <p class="text-xs text-white/40 mb-2">Visitors from these countries are blocked. Use ISO 2-letter codes separated by commas. Leave empty to allow everywhere.</p>
        <input type="text" name="country_blocklist"
               value="{{ old('country_blocklist', $blocklist) }}"
               placeholder="e.g. RU,KP,IR"
               class="w-full max-w-md border border-white/10 rounded-xl px-3 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-blue-500/40">
    </div>
</div>

@once
<script>
    function protectionScheduling(initial) {
        return {
            tz: initial.tz || 'UTC',
            expMode: initial.expMode || 'none',
            clicksLimitOn: !!initial.clicksLimitOn,
            awEnabled: !!initial.awEnabled,
            awDays: Array.isArray(initial.awDays) ? initial.awDays : [],
            awSlots: Array.isArray(initial.awSlots) && initial.awSlots.length
                ? initial.awSlots.map(s => ({ start: s.start || '09:00', end: s.end || '17:00' }))
                : [{ start: '09:00', end: '17:00' }],
        };
    }
</script>
@endonce
