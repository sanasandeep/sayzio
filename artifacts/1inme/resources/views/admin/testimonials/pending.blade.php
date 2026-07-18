@extends('admin.layouts.app')
@section('title', 'Pending Testimonials')
@section('page-title', 'Pending Testimonials')

@section('content')
<div class="max-w-4xl space-y-6">

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Pending public submissions</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    These testimonials were submitted via the public form and are awaiting your review.
                    Approve to set the marquee row, accent colour, and sort order then publish — or reject to dismiss.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.testimonials.index') }}"
                   class="px-3 py-2 rounded-xl text-xs font-medium bg-white/5 border border-white/10 text-white/70 hover:bg-white/10">
                    <i class="fas fa-arrow-left text-xs mr-1"></i> All testimonials
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @forelse($testimonials as $t)
        <div class="glass rounded-2xl border border-amber-500/20 overflow-hidden" x-data="{ open: false }">
            {{-- Header / summary --}}
            <div class="flex items-start justify-between gap-4 p-5 flex-wrap">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-bold text-white mt-0.5"
                          style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                        {{ $t->initial() }}
                    </span>
                    <div class="min-w-0">
                        <div class="font-semibold text-white/90">{{ $t->author_name }}</div>
                        @if($t->author_role)
                            <div class="text-xs text-white/50">{{ $t->author_role }}</div>
                        @endif
                        @if($t->submitter_email)
                            <div class="text-xs text-blue-300/70 mt-0.5">{{ $t->submitter_email }}</div>
                        @endif
                        @if($t->submitted_at)
                            <div class="text-[11px] text-white/40 mt-0.5">Submitted {{ $t->submitted_at->diffForHumans() }}</div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-amber-500/15 text-amber-200 border border-amber-500/20">
                        Pending
                    </span>
                    <div class="text-amber-300 text-xs whitespace-nowrap">
                        @for($i = 0; $i < $t->rating; $i++)<i class="fas fa-star text-[10px]"></i>@endfor
                    </div>
                    <form method="POST" action="{{ route('admin.testimonials.reject', $t) }}" class="inline">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Reject this testimonial? It will be hidden from the public.')"
                                class="px-3 py-1.5 rounded-xl text-xs font-medium bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 border border-rose-500/20">
                            <i class="fas fa-times text-[10px] mr-1"></i> Reject
                        </button>
                    </form>
                    <button type="button" @click="open = !open"
                            class="px-3 py-1.5 rounded-xl text-xs font-medium bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-200 border border-emerald-500/25">
                        <i class="fas fa-check text-[10px] mr-1"></i>
                        <span x-text="open ? 'Cancel' : 'Approve'">Approve</span>
                    </button>
                </div>
            </div>

            {{-- Quote preview --}}
            <div class="px-5 pb-4">
                <blockquote class="text-sm text-white/70 italic border-l-2 border-blue-500/40 pl-3 leading-relaxed">
                    &ldquo;{{ $t->quote }}&rdquo;
                </blockquote>
            </div>

            {{-- Approve panel --}}
            <div x-show="open" x-cloak class="border-t border-white/10 bg-white/[0.02] px-5 py-5">
                <form method="POST" action="{{ route('admin.testimonials.approve', $t) }}">
                    @csrf
                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-white/60 mb-1">Marquee row <span class="text-rose-400">*</span></label>
                            <select name="row"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none">
                                <option value="top">Top row (→)</option>
                                <option value="bottom">Bottom row (←)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-white/60 mb-1">Accent colour</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="accent_color" value="#3d6bff"
                                       class="w-12 h-9 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
                                <span class="text-[11px] text-white/40">Avatar gradient</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-white/60 mb-1">Sort order</label>
                            <input type="number" name="sort_order" value="0" min="0" max="99999"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-sm text-white/80 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked
                                       class="rounded bg-white/5 border-white/20 text-blue-500 focus:ring-blue-500">
                                <span>Show on homepage</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white">
                        <i class="fas fa-check text-xs mr-1"></i> Confirm approval
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="glass rounded-2xl border border-white/10 px-6 py-14 text-center">
            <div class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-inbox text-white/30 text-xl"></i>
            </div>
            <p class="text-white/50 text-sm">No pending submissions. Share the form link to collect testimonials from customers.</p>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ route('testimonials.submit.show') }}').then(()=>this.textContent='Copied!')"
                    class="mt-4 px-4 py-2 rounded-xl text-xs font-medium bg-white/5 hover:bg-white/10 text-white/70 border border-white/10 inline-flex items-center gap-2">
                <i class="fas fa-link text-[10px]"></i> Copy form link
            </button>
        </div>
    @endforelse

</div>
@endsection
