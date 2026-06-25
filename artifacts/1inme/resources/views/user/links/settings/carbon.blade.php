@extends('user.layouts.app')
@section('title', 'Sustainability - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $activeSettingsTab = 'carbon';
    $eff = $effective ?? [];
    $override = $linkOverride ?? [];
    // Per-link form values fall back to the effective (merged) value
    // so toggling a link from "inherit workspace" to "override" feels
    // continuous — the form isn't suddenly empty.
    $enabled = (bool) ($override['enabled'] ?? $eff['enabled'] ?? false);
    $budget  = (int)  ($override['monthly_budget_minor'] ?? $eff['monthly_budget_minor'] ?? 0);
    $fallback= (string)($override['fallback'] ?? $eff['fallback'] ?? 'pause');
    $badgeOn = (bool) ($override['badge_visible'] ?? $eff['badge_visible'] ?? true);
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7" id="settings-tab-content">
            <form method="POST" action="{{ route('user.links.settings.carbon.update', $link) }}" class="space-y-6">
                @csrf

                @if($hasOverride)
                <div class="card-premium p-5" style="border: 1px solid rgba(144,172,255,0.35);">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-link mt-1" style="color:#90acff;"></i>
                        <div class="flex-1">
                            <h4 class="text-[12px] font-bold mb-1" style="color: var(--text-primary);">This biolink overrides workspace defaults</h4>
                            <p class="text-[11px] mb-3" style="color: var(--text-dimmed);">Workspace owners can change the workspace-wide policy on the sustainability dashboard, but those changes won't reach this biolink while an override is set.</p>
                            <button type="submit" name="inherit" value="1"
                                    onclick="return confirm('Clear this Link in Bio\'s sustainability override and follow workspace defaults?');"
                                    class="text-[11px] font-semibold px-3 py-1.5 rounded-lg"
                                    style="background: rgba(144,172,255,0.15); color:#90acff;">
                                Inherit workspace defaults instead
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.12);">
                            <i class="fas fa-leaf text-[12px]" style="color:#10b981;"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Carbon-neutral biolink</h3>
                            <p class="text-[11px]" style="color: var(--text-dimmed);">Estimate the CO₂ each visit produces and auto-purchase verified offsets monthly. Settings here override your workspace defaults.</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 mt-4 cursor-pointer">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} class="w-4 h-4">
                        <span class="text-sm" style="color: var(--text-primary);">Estimate &amp; offset CO₂ for this biolink</span>
                    </label>

                    <label class="flex items-center gap-3 mt-3 cursor-pointer">
                        <input type="hidden" name="badge_visible" value="0">
                        <input type="checkbox" name="badge_visible" value="1" {{ $badgeOn ? 'checked' : '' }} class="w-4 h-4">
                        <span class="text-sm" style="color: var(--text-primary);">Show the “Carbon Neutral” badge on the public page</span>
                    </label>
                </div>

                <div class="card-premium p-6">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Monthly budget cap</h3>
                    <p class="text-[11px] mb-3" style="color: var(--text-dimmed);">Hard ceiling on what we'll spend on offsets for this biolink each month (USD). Set to 0 for no per-link cap. To inherit your workspace's cap instead, use the "Inherit workspace defaults" button above.</p>

                    <label class="block max-w-xs">
                        <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Cap (USD)</span>
                        <input type="number" name="monthly_budget" min="0" max="1000000" step="0.01"
                               value="{{ old('monthly_budget', number_format($budget / 100, 2, '.', '')) }}"
                               class="mt-1 w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    </label>

                    <fieldset class="mt-4">
                        <legend class="text-[11px] font-semibold mb-2" style="color: var(--text-dimmed);">If a month exceeds the cap</legend>
                        <label class="flex items-center gap-2 mt-1 cursor-pointer">
                            <input type="radio" name="fallback" value="pause" {{ $fallback === 'pause' ? 'checked' : '' }}>
                            <span class="text-sm" style="color: var(--text-primary);">Pause offsets for that month (keep the badge hidden until the next month)</span>
                        </label>
                        <label class="flex items-center gap-2 mt-1 cursor-pointer">
                            <input type="radio" name="fallback" value="partial" {{ $fallback === 'partial' ? 'checked' : '' }}>
                            <span class="text-sm" style="color: var(--text-primary);">Buy a partial offset that fits inside the cap</span>
                        </label>
                    </fieldset>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('user.carbon.index') }}" class="text-[12px]" style="color: var(--text-dimmed);">
                        <i class="fas fa-chart-pie mr-1"></i> Open the sustainability dashboard
                    </a>
                    <button type="submit" class="btn-primary px-5 py-2 text-sm">Save sustainability settings</button>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-5 space-y-4">
            <div class="card-premium p-5">
                <h4 class="text-[12px] font-bold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-1" style="color:#10b981;"></i> Live badge preview
                </h4>
                <div class="rounded-xl p-4 flex items-center justify-center min-h-[88px]" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    @if($badgeOn && $enabled)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[12px] font-semibold"
                              style="background: rgba(16,185,129,0.12); color:#10b981; border: 1px solid rgba(16,185,129,0.25);">
                            <i class="fas fa-leaf"></i>
                            <span>Carbon Neutral</span>
                        </span>
                    @else
                        <span class="text-[11px]" style="color: var(--text-dimmed);">
                            Badge is hidden — enable both toggles above to show it on this page.
                        </span>
                    @endif
                </div>
                <p class="text-[10px] mt-2" style="color: var(--text-dimmed);">
                    Visitors who tap the badge see the methodology and the verified offset project for the most recent month.
                </p>
            </div>

            @if($recentSnapshot)
            <div class="card-premium p-5">
                <h4 class="text-[12px] font-bold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-1" style="color:#90acff;"></i> Most recent snapshot
                </h4>
                <dl class="text-[12px] grid grid-cols-2 gap-y-1.5" style="color: var(--text-primary);">
                    <dt style="color: var(--text-dimmed);">Period</dt>
                    <dd class="text-right">{{ $recentSnapshot->period_start?->format('M Y') }}</dd>

                    <dt style="color: var(--text-dimmed);">Page views</dt>
                    <dd class="text-right">{{ number_format($recentSnapshot->page_views) }}</dd>

                    <dt style="color: var(--text-dimmed);">CO₂ estimate</dt>
                    <dd class="text-right">{{ number_format((float) $recentSnapshot->grams_co2, 2) }} g</dd>

                    <dt style="color: var(--text-dimmed);">Offset</dt>
                    <dd class="text-right">{{ number_format((float) $recentSnapshot->grams_offset, 2) }} g</dd>

                    <dt style="color: var(--text-dimmed);">Status</dt>
                    <dd class="text-right capitalize">{{ str_replace('_', ' ', (string) $recentSnapshot->offset_status) }}</dd>
                </dl>
            </div>
            @else
            <div class="card-premium p-5">
                <p class="text-[12px]" style="color: var(--text-dimmed);">
                    No CO₂ snapshot yet — they're written on the 1st of each month for Link in Bio pages that are opted in.
                </p>
            </div>
            @endif

            @if($hasOverride)
            <div class="card-premium p-5">
                <p class="text-[11px]" style="color: var(--text-dimmed);">
                    <i class="fas fa-info-circle mr-1"></i>
                    This Link in Bio stores its own carbon settings. Use the "Inherit workspace defaults" button at the top of the form to drop the override and follow workspace policy again.
                </p>
            </div>
            @endif
        </aside>
    </div>
</div>
@endsection
