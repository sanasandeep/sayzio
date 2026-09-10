@extends('admin.layouts.app')

@section('title', "Zio's lines")

@section('content')
{{--
    One page, inline rows. Every other content editor here sends you to a
    separate screen to change a record, but a line is one short string --
    a whole page round trip to fix four words is the wrong trade. Each row
    is its own small form posting to the ordinary REST routes, so this is
    only a different layout, not a different mechanism.
--}}
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ limit: {{ \App\Modules\Admin\Models\ZioLine::MAX_LENGTH }} }">

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-white ak-strong">Zio&rsquo;s lines</h1>
        <p class="text-sm text-gray-400 mt-1 ak-muted">
            What Zio says in the speech bubble on the homepage. Active lines take
            turns, four seconds each, in the order below.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-sm ak-green">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-200 text-sm ak-red">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] divide-y divide-white/5">
        @forelse($lines as $line)
            <div class="p-4 flex flex-wrap items-start gap-3">
                <form method="POST" action="{{ route('admin.zio-lines.update', $line) }}"
                      class="flex-1 min-w-0 flex flex-wrap items-start gap-3">
                    @csrf @method('PUT')

                    <div class="flex-1 min-w-[16rem]"
                         x-data="{ text: @js($line->text) }">
                        <input type="text" name="text" x-model="text" maxlength="{{ \App\Modules\Admin\Models\ZioLine::MAX_LENGTH }}"
                               class="w-full px-3 py-2 rounded-lg bg-white/[0.04] border border-white/10 text-white text-sm ak-strong focus:outline-none focus:border-cyan-400/50">
                        {{-- The bubble is a fixed ellipse, so this counter is the
                             real constraint, not a style guide. --}}
                        <div class="mt-1 text-[11px] ak-muted"
                             :class="text.length > limit - 8 ? 'text-amber-300' : 'text-gray-500'">
                            <span x-text="text.length"></span>/<span x-text="limit"></span> characters
                            <span x-show="text.length > limit - 8" x-cloak>&middot; getting close to the edge of the bubble</span>
                        </div>
                    </div>

                    <label class="text-[11px] text-gray-400 ak-muted">
                        Order
                        <input type="number" name="sort_order" value="{{ $line->sort_order }}" min="0" max="99999"
                               class="mt-1 block w-20 px-2 py-2 rounded-lg bg-white/[0.04] border border-white/10 text-white text-sm ak-strong focus:outline-none focus:border-cyan-400/50">
                    </label>

                    <input type="hidden" name="is_active" value="{{ $line->is_active ? 1 : 0 }}">

                    <button class="mt-[18px] px-3 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white text-sm font-semibold hover:opacity-90">
                        Save
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.zio-lines.toggle', $line) }}" class="mt-[18px]">@csrf
                    <button class="text-xs px-2 py-2 rounded-full {{ $line->is_active ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 ak-green' : 'bg-gray-500/15 text-gray-400 border border-gray-500/30 ak-muted' }}">
                        {{ $line->is_active ? 'Shown' : 'Hidden' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.zio-lines.destroy', $line) }}" class="mt-[18px]"
                      onsubmit="event.preventDefault(); themedConfirmAsync({title:'Delete this line?',body:'Zio will stop saying it. This cannot be undone.',confirmText:'Delete',variant:'danger'}).then(ok=>{ if(ok) this.submit(); });">
                    @csrf @method('DELETE')
                    <button class="px-2 py-2 text-rose-300 hover:text-rose-200 ak-red"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-gray-500 ak-muted">
                No lines yet, so Zio falls back to the four he shipped with. Add one below.
            </div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('admin.zio-lines.store') }}"
          class="mt-6 p-4 rounded-2xl border border-white/10 bg-white/[0.03] flex flex-wrap items-start gap-3"
          x-data="{ text: '' }">
        @csrf
        <div class="flex-1 min-w-[16rem]">
            <input type="text" name="text" x-model="text" placeholder="Something else for Zio to say&hellip;"
                   maxlength="{{ \App\Modules\Admin\Models\ZioLine::MAX_LENGTH }}"
                   class="w-full px-3 py-2 rounded-lg bg-white/[0.04] border border-white/10 text-white text-sm ak-strong placeholder:text-gray-600 focus:outline-none focus:border-cyan-400/50">
            <div class="mt-1 text-[11px] ak-muted"
                 :class="text.length > limit - 8 ? 'text-amber-300' : 'text-gray-500'">
                <span x-text="text.length"></span>/<span x-text="limit"></span> characters
            </div>
        </div>

        <label class="text-[11px] text-gray-400 ak-muted">
            Order
            <input type="number" name="sort_order" value="{{ ($lines->max('sort_order') ?? 0) + 10 }}" min="0" max="99999"
                   class="mt-1 block w-20 px-2 py-2 rounded-lg bg-white/[0.04] border border-white/10 text-white text-sm ak-strong focus:outline-none focus:border-cyan-400/50">
        </label>

        <input type="hidden" name="is_active" value="1">

        <button class="mt-[18px] inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white text-sm font-semibold hover:opacity-90">
            <i class="fas fa-plus"></i> Add line
        </button>
    </form>

    <p class="mt-4 text-xs text-gray-500 ak-muted">
        Hide every line and Zio falls back to the four he shipped with, rather
        than standing there with an empty bubble.
    </p>
</div>
@endsection
