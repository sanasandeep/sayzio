@extends('public.layouts.site')
@section('title', 'Get Sayzio for Android')
@section('meta_description', 'Download the Sayzio Android app and manage your links, biolinks and analytics on the go.')

@php
    /** @var \App\Modules\Admin\Models\AndroidApkRelease|null $release */
    $available = !is_null($release);
    $accent    = '#6366f1';
@endphp

@section('content')
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
              style="background:{{ $accent }}1a;border-color:{{ $accent }}33;color:#c7d2fe;">
            <i class="fa-brands fa-android text-[10px]"></i> Android App
        </span>

        <h1 class="mt-5 text-4xl sm:text-5xl font-bold tracking-tight leading-tight">
            Sayzio on Android.
            <span class="block grad-text">Your links, anywhere.</span>
        </h1>

        <p class="mt-5 text-base sm:text-lg text-white/55 max-w-xl mx-auto">
            Manage links, biolinks, QR codes and analytics right from your phone.
        </p>

        <div class="mt-10">
            @if($available)
                {{-- File metadata strip --}}
                <div class="inline-flex flex-wrap justify-center gap-5 text-sm text-white/40 mb-7">
                    <span><i class="fas fa-tag mr-1.5 text-indigo-400/60"></i>Version {{ $release->version_name }}</span>
                    @if($release->build_number)
                        <span><i class="fas fa-hammer mr-1.5 text-indigo-400/60"></i>Build {{ $release->build_number }}</span>
                    @endif
                    <span><i class="fas fa-weight-hanging mr-1.5 text-indigo-400/60"></i>{{ $release->size_human }}</span>
                    <span><i class="fas fa-calendar mr-1.5 text-indigo-400/60"></i>{{ $release->created_at->format('M j, Y') }}</span>
                </div>

                <a href="{{ route('android.download') }}"
                   class="inline-flex items-center gap-3 px-7 py-4 rounded-2xl text-white font-semibold text-base shadow-lg shadow-indigo-500/20 transition-all hover:scale-[1.02] hover:shadow-indigo-500/30"
                   style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                    <i class="fa-brands fa-android text-xl"></i>
                    <span>Download APK&nbsp;<span class="opacity-60 font-normal text-sm">({{ $release->size_human }})</span></span>
                </a>

                <p class="mt-4 text-xs text-white/30">
                    <i class="fas fa-info-circle mr-1"></i>
                    This is a direct APK. On Android, tap the file after downloading, then allow "Install from unknown sources" if prompted.
                </p>
            @else
                {{-- Not available yet state --}}
                <div class="inline-flex flex-col items-center gap-4 py-10 px-8 rounded-2xl glass border border-white/10">
                    <i class="fa-brands fa-android text-5xl text-white/15"></i>
                    <div>
                        <p class="text-white/60 font-medium">The Android app isn't available for download yet.</p>
                        <p class="text-sm text-white/35 mt-1">Check back soon — we're working on it.</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Feature highlights --}}
        <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-5 text-left">
            @php
            $features = [
                ['icon' => 'fa-link',         'title' => 'Create & manage links',   'desc' => 'Short links, biolinks, QR codes and more — all from your phone.'],
                ['icon' => 'fa-chart-bar',    'title' => 'Live analytics',           'desc' => 'See who clicked, from where, and on what device, in real time.'],
                ['icon' => 'fa-bell',         'title' => 'Instant notifications',    'desc' => 'Get notified the moment someone subscribes or follows your page.'],
            ];
            @endphp
            @foreach($features as $f)
                <div class="glass rounded-2xl border border-white/10 p-5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3"
                         style="background:{{ $accent }}1a;border:1px solid {{ $accent }}33;">
                        <i class="fas {{ $f['icon'] }} text-sm" style="color:#a5b4fc;"></i>
                    </div>
                    <h3 class="font-semibold text-white text-sm mb-1">{{ $f['title'] }}</h3>
                    <p class="text-xs text-white/45 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>

    </div>
</section>
@endsection
