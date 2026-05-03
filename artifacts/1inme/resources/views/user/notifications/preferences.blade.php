@extends('user.layouts.app')
@section('title', 'Notification preferences')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Notification preferences</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Choose which alerts reach you, and where.</p>
        </div>
        <a href="{{ route('user.notifications.index') }}" class="text-sm text-violet-500 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Back to feed
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('user.notifications.preferences.update') }}"
          class="rounded-2xl"
          style="background: var(--bg-card); border:1px solid var(--border-soft);">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-12 px-4 py-3 text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-faint); border-bottom:1px solid var(--border-soft);">
            <div class="col-span-7">Notification</div>
            <div class="col-span-2 text-center">In-app</div>
            <div class="col-span-2 text-center">Email</div>
            <div class="col-span-1 text-center">Push</div>
        </div>

        <div class="divide-y" style="border-color: var(--border-soft);">
            @foreach($catalog as $type => $meta)
                @php $row = $prefs[$type] ?? null; @endphp
                <label class="grid grid-cols-12 items-center gap-2 px-4 py-4 cursor-default">
                    <div class="col-span-7">
                        <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $meta['label'] }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted);">{{ $meta['description'] }}</div>
                    </div>
                    <div class="col-span-2 text-center">
                        <input type="hidden" name="prefs[{{ $type }}][in_app]" value="0"/>
                        <input type="checkbox" name="prefs[{{ $type }}][in_app]" value="1"
                               class="h-4 w-4 accent-violet-600"
                               @checked($row['in_app'] ?? $meta['default_in_app'])/>
                    </div>
                    <div class="col-span-2 text-center">
                        <input type="hidden" name="prefs[{{ $type }}][email]" value="0"/>
                        <input type="checkbox" name="prefs[{{ $type }}][email]" value="1"
                               class="h-4 w-4 accent-violet-600"
                               @checked($row['email'] ?? $meta['default_email'])/>
                    </div>
                    <div class="col-span-1 text-center">
                        <input type="hidden" name="prefs[{{ $type }}][push]" value="0"/>
                        <input type="checkbox" name="prefs[{{ $type }}][push]" value="1"
                               class="h-4 w-4 accent-violet-600"
                               @checked($row['push'] ?? $meta['default_push'])/>
                    </div>
                </label>
            @endforeach
        </div>

        @php
            $blWeekday = (int) old('backlink_digest_preferred_weekday', $user->backlink_digest_preferred_weekday ?? 1);
            $blHour    = (int) old('backlink_digest_preferred_hour', $user->backlink_digest_preferred_hour ?? 9);
            $weekdays = [
                1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
            ];
        @endphp
        <div class="px-4 py-4" style="border-top:1px solid var(--border-soft);">
            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Weekly backlink digest delivery</div>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Pick when you'd like the weekly backlink digest email to arrive. Only applies if "Backlink digest" email is on above.</p>
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Send on</label>
                <select name="backlink_digest_preferred_weekday" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, var(--bg-card)); border:1px solid var(--border-soft); color: var(--text-primary);">
                    @foreach($weekdays as $val => $label)
                        <option value="{{ $val }}" {{ $blWeekday === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="text-xs" style="color: var(--text-muted);">at</label>
                <select name="backlink_digest_preferred_hour" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, var(--bg-card)); border:1px solid var(--border-soft); color: var(--text-primary);">
                    @for($h = 0; $h < 24; $h++)
                        @php
                            $suffix = $h < 12 ? 'am' : 'pm';
                            $disp = $h % 12; if ($disp === 0) $disp = 12;
                        @endphp
                        <option value="{{ $h }}" {{ $blHour === $h ? 'selected' : '' }}>{{ $disp }}:00 {{ $suffix }}</option>
                    @endfor
                </select>
                <span class="text-xs" style="color: var(--text-faint);">in your timezone ({{ $user->timezone ?: 'UTC' }})</span>
            </div>
            <p class="text-xs mt-3" style="color: var(--text-muted);">
                <i class="fas fa-clock mr-1" style="color: var(--text-faint);"></i>
                Next digest:
                <span id="bl-digest-next-preview" data-tz="{{ $user->timezone ?: 'UTC' }}"
                      style="color: var(--text-primary); font-weight: 600;">…</span>
            </p>
        </div>

        <script>
        (function () {
            // Mirrors the slot-matching logic in
            // app/Console/Commands/SendWeeklyBacklinkDigest.php: the
            // hourly job sends to a user when the current local
            // weekday+hour in *their* timezone equals their preferred
            // weekday+hour. So the next send is the soonest upcoming
            // hour boundary (in their timezone) that satisfies that
            // match. We iterate hour by hour (cap 8 days) using
            // Intl.DateTimeFormat to read weekday/hour in the saved
            // server-side timezone — that way the preview is correct
            // even when the browser is in a different zone.
            const weekdaySel = document.querySelector('select[name="backlink_digest_preferred_weekday"]');
            const hourSel    = document.querySelector('select[name="backlink_digest_preferred_hour"]');
            const out        = document.getElementById('bl-digest-next-preview');
            if (!weekdaySel || !hourSel || !out) return;
            const tz = out.dataset.tz || 'UTC';
            const wMap = { Mon:1, Tue:2, Wed:3, Thu:4, Fri:5, Sat:6, Sun:7 };

            function partsIn(date, opts) {
                try {
                    return new Intl.DateTimeFormat('en-US', Object.assign({ timeZone: tz }, opts)).formatToParts(date);
                } catch (e) {
                    return new Intl.DateTimeFormat('en-US', opts).formatToParts(date);
                }
            }

            function recompute() {
                const wantWeekday = parseInt(weekdaySel.value, 10);
                const wantHour    = parseInt(hourSel.value, 10);
                // Round UP to the next top-of-hour boundary in UTC. The
                // hourly cron only fires at HH:00, so:
                //   * The preview must never carry over the current
                //     minute/second offset (we'd otherwise display
                //     "5:47 pm" instead of "5:00 pm" mid-hour).
                //   * If the user's preferred slot matches the *current*
                //     hour, that hour's send has already fired (or is
                //     firing right now), so the truly next send is a
                //     week away — rounding up skips the current hour
                //     and the loop naturally lands on next week's slot.
                const now = new Date();
                const base = new Date(now);
                base.setUTCMinutes(0, 0, 0);
                if (base.getTime() <= now.getTime()) {
                    base.setTime(base.getTime() + 3600 * 1000);
                }
                for (let i = 0; i < 24 * 8; i++) {
                    const t = new Date(base.getTime() + i * 3600 * 1000);
                    const p = partsIn(t, { weekday: 'short', hour: 'numeric', hour12: false });
                    const w = wMap[(p.find(x => x.type === 'weekday') || {}).value];
                    let h = parseInt((p.find(x => x.type === 'hour') || {}).value, 10);
                    if (h === 24) h = 0;
                    if (w === wantWeekday && h === wantHour) {
                        const f = partsIn(t, {
                            weekday: 'long', month: 'long', day: 'numeric',
                            hour: 'numeric', minute: '2-digit', hour12: true,
                        });
                        const map = {};
                        f.forEach(x => { map[x.type] = x.value; });
                        const label = `${map.weekday}, ${map.month} ${map.day} at ${map.hour}:${map.minute} ${(map.dayPeriod || '').toLowerCase()} in ${tz}`;
                        out.textContent = label;
                        return;
                    }
                }
                out.textContent = 'unable to compute';
            }

            weekdaySel.addEventListener('change', recompute);
            hourSel.addEventListener('change', recompute);
            recompute();
        })();
        </script>

        <div class="px-4 py-4 flex items-center justify-between" style="border-top:1px solid var(--border-soft);">
            <p class="text-xs" style="color: var(--text-faint);">Push delivery rolls out with the next mobile release.</p>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-violet-600 hover:bg-violet-700 text-white">
                Save preferences
            </button>
        </div>
    </form>
</div>
@endsection
