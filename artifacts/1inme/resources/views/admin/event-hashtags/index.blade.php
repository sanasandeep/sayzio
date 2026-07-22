@extends('admin.layouts.app')
@section('title', 'Event Hashtags')
@section('page-title', 'Event Hashtags')

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">/events hashtag row</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    These predefined hashtags show first on the public <code class="text-white/60 ak-muted">/events</code> page,
                    in the order below. Auto-computed trending hashtags backfill the rest of the row, deduped against
                    this list.
                </p>
            </div>
            <a href="{{ route('admin.event-hashtags.create') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
                <i class="fas fa-plus text-xs"></i> New hashtag
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10 ak-note">
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Hashtag</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hashtags as $i => $tag)
                    <tr class="border-b border-white/5 last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('admin.event-hashtags.move', $tag) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" @if($i === 0) disabled @endif
                                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white/70 disabled:opacity-25 disabled:cursor-not-allowed ak-strong">
                                        <i class="fas fa-chevron-up text-[10px]"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.event-hashtags.move', $tag) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" @if($i === $hashtags->count() - 1) disabled @endif
                                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white/70 disabled:opacity-25 disabled:cursor-not-allowed ak-strong">
                                        <i class="fas fa-chevron-down text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-white/90 ak-strong">#{{ $tag->tag }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.event-hashtags.edit', $tag) }}"
                                   class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 ak-blue">
                                    <i class="fas fa-pen text-[10px] mr-1"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.event-hashtags.destroy', $tag) }}"
                                      onsubmit="return confirm('Remove #{{ addslashes($tag->tag) }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 ak-red">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($hashtags->isEmpty())
            <div class="p-8 text-center text-white/60 text-sm ak-muted">No predefined hashtags yet.</div>
        @endif
    </div>
</div>
@endsection
