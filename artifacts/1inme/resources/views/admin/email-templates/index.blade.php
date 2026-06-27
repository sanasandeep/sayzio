@extends('admin.layouts.app')
@section('title', 'Email Templates')
@section('page-title', 'Email Templates')

@section('content')
<div class="max-w-5xl space-y-6">

    @if (session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">{{ session('success') }}</div>
    @endif

    <p class="text-sm text-white/50">
        Every transactional email the platform sends is listed below, grouped by area. Edit any one to
        customise its subject and body &mdash; templates with no override keep sending their built-in
        content. Variables like <code class="text-white/70">&#123;&#123;name&#125;&#125;</code> are documented on each editor.
    </p>

    @foreach ($grouped as $category => $group)
        <section class="rounded-2xl border border-white/10 bg-white/[0.02]">
            <div class="px-4 py-3 border-b border-white/10">
                <h2 class="text-sm font-semibold text-white">{{ $group['label'] }}</h2>
            </div>
            <div class="divide-y divide-white/5">
                @foreach ($group['rows'] as $key => $row)
                    <a href="{{ route('admin.email-templates.edit', $key) }}"
                       class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-white/[0.03] transition-colors">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-white truncate">{{ $row['entry']['label'] ?? $key }}</span>
                                @if ($row['override'])
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/25 text-amber-300">Customised</span>
                                @endif
                            </div>
                            <div class="text-xs text-white/40 truncate">{{ $row['entry']['description'] ?? '' }}</div>
                            <code class="text-[10px] text-white/30">{{ $key }}</code>
                        </div>
                        <i class="fas fa-chevron-right text-white/30 text-xs shrink-0"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

</div>
@endsection
