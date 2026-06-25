{{-- Reusable plan-gate lock banner.

  Props:
    $feature  string          required — the plan feature key.
    $kind     string          'flag' (boolean) or 'limit' (numeric); default 'flag'.
    $current  int|null        for 'limit' kind, the user's current count.
    $label    string          human-readable feature name.

  Renders nothing when the user already has access. Otherwise renders an
  inline upgrade banner that names the cheapest plan that unlocks it
  (centralized via User::planThatUnlocks).
--}}
@php
    $__lockUser = auth()->user();
    $__lockShow = false;
    if ($__lockUser) {
        if (($kind ?? 'flag') === 'limit') {
            $__lockShow = !$__lockUser->planUnderLimit($feature, (int) ($current ?? 0));
        } else {
            $__lockShow = !$__lockUser->planFeatureEnabled($feature);
        }
    }
@endphp
@if($__lockShow)
    @php
        $__lockTarget = $__lockUser->planThatUnlocks($feature, $current ?? null);
        $__lockTargetName = $__lockTarget?->name;
    @endphp
    <div data-plan-lock="{{ $feature }}"
         class="rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 mb-4 flex items-start gap-3">
        <i class="fas fa-lock text-blue-300 mt-0.5"></i>
        <div class="flex-1 text-sm text-blue-100">
            <div class="font-semibold">{{ $label ?? str_replace('_', ' ', $feature) }} is locked on your current plan.</div>
            <div class="text-blue-200/80 mt-0.5">
                @if($__lockTargetName)
                    Upgrade to the <strong>{{ $__lockTargetName }}</strong> plan to unlock this feature.
                @else
                    Upgrade your plan to unlock this feature.
                @endif
            </div>
        </div>
        <a href="{{ route('user.upgrade') }}"
           class="text-xs font-semibold uppercase tracking-wider px-3 py-1.5 rounded-lg bg-blue-500 hover:bg-blue-400 text-white">
            Upgrade
        </a>
    </div>
@endif
