{{-- ============================ AI DASHBOARD (TEASER) ============================ --}}
@php
    $__aidPresets = \App\Modules\User\Support\DashboardPresets::forFrontend();
@endphp
<section id="ai-dashboard" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="ai-dashboard-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">AI Dashboard</div>
            <h2 id="ai-dashboard-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Design a dashboard <span class="grad-text">only you would build.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Pick one of 5 curated presets, or just tell the AI designer what you care about and it arranges the
                right widgets from your existing dashboard, nothing invented, nothing hidden.
            </p>
        </div>

        <div class="reveal rd-3 mb-10">
            @include('common.partials.ai-dashboard-demo', ['presets' => $__aidPresets, 'variant' => 'compact'])
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-10">
            @foreach($__aidPresets as $i => $preset)
                <div class="reveal rd-{{ min($i + 1, 6) }} glass rounded-2xl p-5 lift">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 grad-bar">
                        <i class="fas {{ $preset['icon'] ?? 'fa-gauge-high' }} text-white text-sm"></i>
                    </div>
                    <h3 class="text-sm font-bold mb-1">{{ $preset['label'] }}</h3>
                    <p class="text-xs text-gray-400 leading-relaxed">{{ $preset['description'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal rd-5 flex flex-wrap items-center justify-center gap-4">
            @guest
                <a href="{{ route('register.page') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                    Customize my dashboard <i class="fas fa-arrow-right text-xs"></i>
                </a>
            @else
                <a href="{{ route('user.dashboard') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                    Go to your dashboard <i class="fas fa-arrow-right text-xs"></i>
                </a>
            @endguest
            <a href="{{ route('site.ai-dashboard') }}" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-full text-sm font-bold border border-white/15 text-gray-200 hover:bg-white/5 transition">
                See how it works
            </a>
        </div>
    </div>
</section>
