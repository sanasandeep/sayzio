@extends('admin.layouts.app')

@section('title', 'Feature States')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-white ak-strong">Feature States</h1>
        <p class="mt-1 text-sm text-white/60 ak-muted">
            Control the app-wide “Coming soon” state for each feature. Features whose integration or
            configuration isn’t connected yet automatically show as coming soon. You can also force any
            feature to coming soon regardless of its status.
        </p>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200 ak-green">
            <i class="fas fa-check-circle mr-1"></i>{{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        @foreach($features as $f)
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/5 text-white/70 ak-strong">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-white ak-strong">{{ $f['label'] }}</p>
                            @if($f['status'] === 'coming_soon')
                                <span class="inline-flex items-center rounded-full border border-amber-400/30 bg-amber-400/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300 ak-amber">
                                    Coming soon
                                    @if($f['reason'] === 'forced') · forced @else · not connected @endif
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-400/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-300 ak-green">
                                    Ready
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-white/55 ak-muted">{{ $f['blurb'] }}</p>
                        <p class="mt-1.5 text-xs text-white/40 ak-note">
                            <i class="fas {{ $f['auto_ready'] ? 'fa-plug-circle-check text-emerald-300/70 ak-green' : 'fa-plug-circle-xmark text-amber-300/70 ak-amber' }} mr-1"></i>
                            Integration: {{ $f['auto_ready'] ? 'connected' : 'not connected' }}
                        </p>
                    </div>

                    <form action="{{ route('admin.feature-states.update', ['key' => $f['key']]) }}" method="POST" class="shrink-0">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="forced" value="{{ $f['forced'] ? '0' : '1' }}">
                        @if($f['forced'])
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white/80 hover:bg-white/10 ak-strong">
                                <i class="fas fa-rotate-left text-[10px]"></i>
                                Clear override
                            </button>
                        @else
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg border border-amber-400/30 bg-amber-400/15 px-3 py-1.5 text-xs font-semibold text-amber-200 hover:bg-amber-400/25 ak-amber">
                                <i class="fas fa-clock text-[10px]"></i>
                                Mark coming soon
                            </button>
                        @endif
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
