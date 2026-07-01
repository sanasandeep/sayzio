{{--
    Reusable "Soon" badge for sidebar nav items.

    Usage: @include('common.partials.soon-badge', ['feature' => 'connected_apps'])

    Renders a small pill only when the given catalogue feature currently
    resolves to "coming soon" (admin-forced or its integration/config isn't
    connected yet). Renders nothing otherwise, so it is safe to drop next to
    any nav label.
--}}
@if(\App\Modules\Common\Support\FeatureStates\FeatureAvailability::isComingSoon($feature ?? ''))
    <span class="ml-auto inline-flex items-center rounded-full border border-amber-400/30 bg-amber-400/15 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-amber-300"
          title="Coming soon">Soon</span>
@endif
