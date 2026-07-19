@extends('admin.layouts.app')
@section('title', 'Testimonials')
@section('page-title', 'Testimonials')

@section('content')
<div class="max-w-6xl space-y-6">

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Homepage testimonials</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    These are the rotating quotes shown in the &ldquo;Loved by people who do the most&rdquo; section
                    on the public homepage. The top row scrolls left → right, the bottom row scrolls right → left.
                    Disabled testimonials are hidden everywhere.
                </p>
                <div class="flex items-center gap-2 mt-3 text-[11px] text-white/60 flex-wrap">
                    <span class="px-2 py-1 rounded-md bg-white/5 border border-white/10">Total: <strong class="text-white/90">{{ $counts['total'] }}</strong></span>
                    <span class="px-2 py-1 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-emerald-200">Active: <strong>{{ $counts['active'] }}</strong></span>
                    <span class="px-2 py-1 rounded-md bg-white/5 border border-white/10">Top row: <strong class="text-white/90">{{ $counts['top'] }}</strong></span>
                    <span class="px-2 py-1 rounded-md bg-white/5 border border-white/10">Bottom row: <strong class="text-white/90">{{ $counts['bottom'] }}</strong></span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Pending badge --}}
                @if(($counts['pending'] ?? 0) > 0)
                    <a href="{{ route('admin.testimonials.pending') }}"
                       class="px-3 py-2 rounded-xl text-xs font-semibold bg-amber-500/15 border border-amber-500/30 text-amber-200 inline-flex items-center gap-1.5 hover:bg-amber-500/25">
                        <i class="fas fa-clock text-[10px]"></i>
                        {{ $counts['pending'] }} pending
                    </a>
                @else
                    <a href="{{ route('admin.testimonials.pending') }}"
                       class="px-3 py-2 rounded-xl text-xs font-medium bg-white/5 border border-white/10 text-white/60 hover:bg-white/10">
                        Pending (0)
                    </a>
                @endif

                {{-- Copy public form link --}}
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ route('testimonials.submit.show') }}').then(()=>{this.innerHTML='<i class=\'fas fa-check text-xs\'></i> Copied!';setTimeout(()=>this.innerHTML='<i class=\'fas fa-link text-xs\'></i> Copy form link',2000)})"
                        class="px-3 py-2 rounded-xl text-xs font-medium bg-white/5 border border-white/10 text-white/70 hover:bg-white/10 inline-flex items-center gap-1.5">
                    <i class="fas fa-link text-xs"></i> Copy form link
                </button>

                <a href="{{ route('admin.testimonials.index') }}"
                   class="px-3 py-2 rounded-xl text-xs font-medium border {{ !$row ? 'bg-blue-600/20 border-blue-500/40 text-blue-100' : 'bg-white/5 border-white/10 text-white/70 hover:bg-white/10' }}">All</a>
                <a href="{{ route('admin.testimonials.index', ['row' => 'top']) }}"
                   class="px-3 py-2 rounded-xl text-xs font-medium border {{ $row === 'top' ? 'bg-blue-600/20 border-blue-500/40 text-blue-100' : 'bg-white/5 border-white/10 text-white/70 hover:bg-white/10' }}">Top row</a>
                <a href="{{ route('admin.testimonials.index', ['row' => 'bottom']) }}"
                   class="px-3 py-2 rounded-xl text-xs font-medium border {{ $row === 'bottom' ? 'bg-blue-600/20 border-blue-500/40 text-blue-100' : 'bg-white/5 border-white/10 text-white/70 hover:bg-white/10' }}">Bottom row</a>
                <a href="{{ route('admin.testimonials.create') }}"
                   class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> Add testimonial
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/[.03] text-white/60 text-[11px] uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Author</th>
                    <th class="px-4 py-3 text-left">Quote</th>
                    <th class="px-4 py-3 text-left">Row</th>
                    <th class="px-4 py-3 text-left">Source</th>
                    <th class="px-4 py-3 text-center">Rating</th>
                    <th class="px-4 py-3 text-center">Order</th>
                    <th class="px-4 py-3 text-center">Live</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($testimonials as $t)
                    <tr class="hover:bg-white/[.02]">
                        <td class="px-4 py-3 align-top">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                      style="background:linear-gradient(135deg, {{ $t->accent_color }}, #ec4899);">
                                    {{ $t->initial() }}
                                </span>
                                <div>
                                    <div class="font-semibold text-white/90">{{ $t->author_name }}</div>
                                    <div class="text-[11px] text-white/50">{{ $t->author_role }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top text-white/80 max-w-md">
                            <div class="line-clamp-2">&ldquo;{{ $t->quote }}&rdquo;</div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <span class="px-2 py-0.5 rounded-md text-[11px] font-medium {{ $t->row === 'top' ? 'bg-cyan-500/15 text-cyan-200' : 'bg-pink-500/15 text-pink-200' }}">
                                {{ ucfirst($t->row) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 align-top">
                            @if(($t->source ?? 'admin') === 'public')
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-primary-500/15 text-primary-200">Public</span>
                            @else
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-white/5 text-white/50">Admin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-center text-amber-300 whitespace-nowrap">
                            @for ($i = 0; $i < $t->rating; $i++)<i class="fas fa-star text-[10px]"></i>@endfor
                        </td>
                        <td class="px-4 py-3 align-top text-center text-white/60">{{ $t->sort_order }}</td>
                        <td class="px-4 py-3 align-top text-center">
                            <form method="POST" action="{{ route('admin.testimonials.toggle', $t) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="px-2 py-1 rounded-md text-[11px] font-medium {{ $t->is_active ? 'bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25' : 'bg-white/5 text-white/50 hover:bg-white/10' }}">
                                    {{ $t->is_active ? 'On' : 'Off' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                            <a href="{{ route('admin.testimonials.edit', $t) }}"
                               class="px-2 py-1 rounded-md text-xs bg-white/5 hover:bg-white/10 text-white/80 border border-white/10">
                                <i class="fas fa-pen text-[10px]"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" class="inline"
                                  onsubmit="return confirm('Delete this testimonial? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-2 py-1 rounded-md text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 border border-rose-500/20">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-white/50">No testimonials yet, add your first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
