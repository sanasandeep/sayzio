@extends('public.layouts.site')

@push('head')
@php
    $__faqsForSchema = [];
    foreach (($groups ?? []) as $cat => $items) {
        foreach ($items as $row) {
            $__faqsForSchema[] = ['q' => $row['q'] ?? '', 'a' => $row['a'] ?? ''];
        }
    }
    $__faqNode = \App\Modules\Common\Support\MarketingSchema::faqPage($__faqsForSchema);
@endphp
@if($__faqNode)
<script type="application/ld+json">{!! json_encode(\App\Modules\Common\Support\MarketingSchema::graph([$__faqNode]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
<style>
    .faq-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; transition: background .2s ease, border-color .2s ease; }
    .faq-card:hover { background: rgba(255,255,255,.05); border-color: rgba(124,58,237,.35); }
    .faq-card[open] { border-color: rgba(124,58,237,.5); background: rgba(124,58,237,.06); }
    .faq-card[open] .faq-chevron { transform: rotate(180deg); }
    .faq-chevron { transition: transform .25s ease; }
    .faq-anchor { scroll-margin-top: 100px; }
    .faq-cat-link { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 10px; font-size: 13px; color: rgba(229,231,235,.85); transition: background .15s ease, color .15s ease; }
    .faq-cat-link:hover { background: rgba(255,255,255,.06); color: #fff; }
    .faq-cat-link .count { font-size: 11px; opacity: .55; font-weight: 600; }
    .faq-chip { padding: 6px 14px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); color: #d1d5db; cursor: pointer; transition: all .2s ease; }
    .faq-chip:hover { color: #fff; background: rgba(255,255,255,.08); }
    .faq-chip.is-active { background: #7c3aed; color: #fff; border-color: transparent; box-shadow: 0 8px 24px -10px rgba(124,58,237,.6); }
    mark.faq-hl { background: rgba(124,58,237,.28); color: #fff; padding: 0 2px; border-radius: 3px; }
</style>
@endpush

@section('content')
@php
    $__totalFaqs = collect($groups ?? [])->reduce(fn($c, $items) => $c + count($items), 0);
@endphp
<section class="pt-16 pb-8 lg:pt-24 lg:pb-10 text-center">
    <div class="max-w-3xl mx-auto px-4">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-[.2em] mb-4" style="background:rgba(124,58,237,.12); color:#a78bfa;">
            <i class="fas fa-circle-question"></i> Help centre
        </div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">{{ $page->title }}</h1>
        <p class="mt-4 text-lg text-gray-400">
            {{ $page->meta_description ?: 'Quick answers to the most common 1INME questions — search, filter or browse by topic.' }}
        </p>
        <div class="mt-3 text-xs text-gray-500">{{ $__totalFaqs }} answers · last reviewed {{ now()->format('M Y') }}</div>
    </div>
</section>

<section class="pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="{
            q: '',
            cat: 'All',
            escapeRx(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); },
            highlight(el, term) {
                el.querySelectorAll('[data-hl-target]').forEach(t => {
                    const original = t.getAttribute('data-original') || t.innerHTML;
                    t.setAttribute('data-original', original);
                    if (!term) { t.innerHTML = original; return; }
                    const rx = new RegExp('(' + this.escapeRx(term) + ')', 'ig');
                    const tmp = document.createElement('div');
                    tmp.innerHTML = original;
                    const walk = (node) => {
                        if (node.nodeType === 3) {
                            if (rx.test(node.nodeValue)) {
                                const span = document.createElement('span');
                                span.innerHTML = node.nodeValue.replace(rx, '<mark class=\'faq-hl\'>$1</mark>');
                                node.parentNode.replaceChild(span, node);
                            }
                        } else if (node.nodeType === 1 && node.tagName !== 'MARK') {
                            Array.from(node.childNodes).forEach(walk);
                        }
                    };
                    Array.from(tmp.childNodes).forEach(walk);
                    t.innerHTML = tmp.innerHTML;
                });
            },
            apply() {
                const term = this.q.trim();
                let visible = 0;
                this.$root.querySelectorAll('[data-faq-group]').forEach(g => {
                    const groupName = g.dataset.faqGroup;
                    let groupVisible = 0;
                    g.querySelectorAll('[data-faq]').forEach(item => {
                        const matchCat = (this.cat === 'All' || groupName === this.cat);
                        const txt = (item.textContent || '').toLowerCase();
                        const matchQ = !term || txt.includes(term.toLowerCase());
                        const ok = matchCat && matchQ;
                        item.style.display = ok ? '' : 'none';
                        if (ok) {
                            groupVisible++;
                            this.highlight(item, term);
                            if (term) item.setAttribute('open', '');
                        } else {
                            this.highlight(item, '');
                        }
                    });
                    g.style.display = groupVisible > 0 ? '' : 'none';
                    visible += groupVisible;
                });
                const empty = this.$root.querySelector('[data-faq-empty]');
                if (empty) empty.style.display = visible === 0 ? '' : 'none';
            }
         }"
         x-init="$nextTick(() => apply()); $watch('q', () => apply()); $watch('cat', () => apply())">

        {{-- Search + chips --}}
        <div class="max-w-3xl mx-auto mb-10">
            <label class="relative block">
                <span class="absolute inset-y-0 left-4 flex items-center text-gray-500"><i class="fas fa-search"></i></span>
                <input type="search" x-model="q" placeholder="Search {{ $__totalFaqs }} answers — try ‘refund’, ‘custom domain’, ‘QR code’…"
                       class="w-full pl-11 pr-4 py-4 rounded-2xl bg-white/[.04] border border-white/10 focus:border-[#7c3aed] focus:ring-2 focus:ring-[#7c3aed]/30 outline-none text-sm text-white placeholder-gray-500"
                       aria-label="Search FAQs">
            </label>
            <div class="mt-4 flex flex-wrap gap-2 justify-center">
                <button type="button" @click="cat = 'All'" :class="cat === 'All' ? 'is-active' : ''" class="faq-chip">All</button>
                @foreach(($groups ?? []) as $cat => $items)
                    <button type="button" @click="cat = '{{ addslashes($cat) }}'" :class="cat === '{{ addslashes($cat) }}' ? 'is-active' : ''" class="faq-chip">
                        {{ $cat }} <span class="opacity-60 ml-1">{{ count($items) }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid lg:grid-cols-12 gap-8">
            {{-- Sticky desktop TOC --}}
            <aside class="hidden lg:block lg:col-span-3">
                <div class="sticky top-24 glass rounded-2xl p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-3 text-gray-500">On this page</div>
                    <nav class="space-y-1">
                        @foreach(($groups ?? []) as $cat => $items)
                            <a href="#cat-{{ \Illuminate\Support\Str::slug($cat) }}" class="faq-cat-link">
                                <span>{{ $cat }}</span>
                                <span class="count">{{ count($items) }}</span>
                            </a>
                        @endforeach
                    </nav>
                    <div class="mt-5 pt-4 border-t border-white/10">
                        <a href="{{ route('site.contact') }}" class="block text-center px-3 py-2.5 rounded-xl grad-bar text-white text-xs font-bold">
                            Still stuck? Talk to us
                        </a>
                    </div>
                </div>
            </aside>

            {{-- Q&A groups --}}
            <div class="lg:col-span-9 space-y-12">
                @foreach(($groups ?? []) as $cat => $items)
                    <section data-faq-group="{{ $cat }}" id="cat-{{ \Illuminate\Support\Str::slug($cat) }}" class="faq-anchor">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">{{ $cat }}</h2>
                            <span class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full" style="background:rgba(124,58,237,.12); color:#a78bfa;">{{ count($items) }} answers</span>
                        </div>
                        <div class="space-y-3">
                            @foreach($items as $row)
                                <details data-faq id="{{ $row['anchor'] }}" class="faq-card faq-anchor group">
                                    <summary class="flex items-center justify-between gap-4 cursor-pointer px-5 py-4">
                                        <span data-hl-target class="text-base font-semibold text-white pr-4">{{ $row['q'] }}</span>
                                        <span class="faq-chevron w-7 h-7 rounded-full grad-bar text-white flex items-center justify-center font-bold flex-shrink-0">
                                            <i class="fas fa-chevron-down text-[10px]"></i>
                                        </span>
                                    </summary>
                                    <div class="px-5 pb-5 text-sm text-gray-300 leading-relaxed">
                                        <span data-hl-target>{!! nl2br(e($row['a'])) !!}</span>
                                        <div class="mt-3 flex items-center gap-3 text-[11px] text-gray-500">
                                            <a href="#{{ $row['anchor'] }}" class="hover:text-white transition-colors"><i class="fas fa-link mr-1"></i>Copy link to this answer</a>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div data-faq-empty class="hidden glass rounded-2xl p-10 text-center">
                    <i class="fas fa-binoculars text-3xl mb-3" style="color:#a78bfa"></i>
                    <h3 class="text-xl font-bold text-white mb-1">No answers match that search.</h3>
                    <p class="text-sm text-gray-400 mb-4">Try a different keyword, or get in touch — we are usually a few minutes away.</p>
                    <a href="{{ route('site.contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full grad-bar text-white text-sm font-bold">
                        Contact support <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>

                {{-- Closer --}}
                <div class="glass rounded-3xl p-6 sm:p-8 text-center">
                    <h3 class="text-xl sm:text-2xl font-bold text-white">Didn't find your answer?</h3>
                    <p class="text-sm text-gray-400 mt-2 mb-5 max-w-xl mx-auto">Real humans, fast replies. Most questions get answered within one business day.</p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('site.contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full grad-bar text-white text-sm font-bold">
                            <i class="fas fa-envelope"></i> Contact support
                        </a>
                        <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full glass text-white hover:bg-white/10 text-sm font-semibold">
                            <i class="fas fa-route"></i> How it works
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'More questions? Stay in the loop.',
    'subtext' => 'Pick how you want to hear from us — email, WhatsApp Channel, or DM. We answer questions, ship features and share playbooks.',
    'source'  => 'faqs',
])
@endsection
