@extends('user.layouts.app')
@section('title', 'Reviews — ' . ($link->title ?: $link->alias))

@php
    $publicUrl = url('/' . $link->alias);
    $renderStars = function ($rating) {
        $r = (int) round($rating); $out = '';
        for ($i = 1; $i <= 5; $i++) { $out .= '<span style="color:' . ($i <= $r ? '#fbbf24' : 'rgba(255,255,255,.2)') . '">&#9733;</span>'; }
        return $out;
    };
@endphp

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ tab: 'reviews' }">
    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-white">{{ $link->title ?: 'Reviews Page' }}</h1>
                <a href="{{ $publicUrl }}" target="_blank" class="text-xs text-violet-400 hover:underline">{{ $publicUrl }} <i class="fas fa-external-link-alt ml-1"></i></a>
            </div>
        </div>
        <div class="glass rounded-xl px-4 py-2 text-center">
            <div class="text-xl font-bold text-white">{{ number_format($summary['average'] ?? 0, 1) }} <span class="text-sm">{!! $renderStars($summary['average'] ?? 0) !!}</span></div>
            <div class="text-[11px] text-white/40">{{ $summary['total'] ?? 0 }} reviews · {{ $summary['native'] ?? 0 }} native · {{ $summary['external'] ?? 0 }} imported</div>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="flex gap-2 mb-6 border-b border-white/10">
        @foreach(['reviews' => 'Moderation', 'settings' => 'Settings', 'questions' => 'Questions', 'providers' => 'Integrations'] as $key => $label)
        <button @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'text-white border-violet-500' : 'text-white/40 border-transparent'" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── Moderation ── --}}
    <div x-show="tab==='reviews'" x-cloak class="space-y-3">
        @forelse($reviews as $review)
        <div class="glass rounded-2xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-white font-semibold shrink-0">
                    {{ strtoupper(substr($review->author_name ?: 'A', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-white text-sm">{{ $review->author_name ?: 'Anonymous' }}</span>
                        @if($review->rating)<span class="text-sm">{!! $renderStars($review->rating) !!}</span>@endif
                        @php $statusColors = ['approved' => 'emerald', 'pending' => 'amber', 'hidden' => 'gray', 'unverified' => 'sky']; $c = $statusColors[$review->status] ?? 'gray'; $statusLabel = $review->status === 'unverified' ? 'awaiting verification' : $review->status; @endphp
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-{{ $c }}-500/15 text-{{ $c }}-300 capitalize">{{ $statusLabel }}</span>
                        @if($review->verified_at)<span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300" title="{{ ucfirst($review->verification_method ?? 'email') }}-verified customer"><i class="fas fa-circle-check mr-0.5"></i>Verified</span>@endif
                        @if($review->is_pinned)<span class="text-[10px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300">Pinned</span>@endif
                        @if($review->is_spam)<span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/15 text-red-300">Spam</span>@endif
                        <span class="text-[11px] text-white/30 ml-auto">{{ $review->created_at?->diffForHumans() }}</span>
                    </div>
                    @if($review->body)<p class="text-sm text-white/70 mt-1.5 whitespace-pre-wrap">{{ $review->body }}</p>@endif

                    @if($review->answers->count())
                    <div class="mt-2 p-2.5 rounded-lg bg-white/5 text-xs text-white/60 space-y-1">
                        @foreach($review->answers as $a)<div><span class="text-white/80 font-medium">{{ $a->prompt }}:</span> {{ $a->answer }}</div>@endforeach
                    </div>
                    @endif

                    @if($review->media->count())
                    <div class="flex gap-2 mt-2 flex-wrap">
                        @foreach($review->media as $m)
                            @if($m->type === 'image')<img src="{{ $m->url }}" class="w-16 h-16 object-cover rounded-lg">
                            @elseif($m->type === 'video')<video src="{{ $m->url }}" class="w-16 h-16 object-cover rounded-lg" muted></video>
                            @else<span class="text-xs text-white/40 px-2 py-1 bg-white/5 rounded"><i class="fas fa-volume-up"></i> audio</span>@endif
                        @endforeach
                    </div>
                    @endif

                    @if($review->reply)
                    <div class="mt-2 pl-3 border-l-2 border-violet-500 text-sm text-white/60"><span class="text-violet-400 text-xs font-medium">Your reply:</span> {{ $review->reply }}</div>
                    @endif

                    <div class="flex items-center gap-1.5 mt-3 flex-wrap">
                        @if($review->status !== 'approved')
                        <form method="POST" action="{{ route('user.links.reviews.approve', $review) }}">@csrf<button class="text-xs px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25"><i class="fas fa-check mr-1"></i>Approve</button></form>
                        @endif
                        @if($review->status !== 'hidden')
                        <form method="POST" action="{{ route('user.links.reviews.hide', $review) }}">@csrf<button class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10"><i class="fas fa-eye-slash mr-1"></i>Hide</button></form>
                        @endif
                        <form method="POST" action="{{ route('user.links.reviews.pin', $review) }}">@csrf<button class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10"><i class="fas fa-thumbtack mr-1"></i>{{ $review->is_pinned ? 'Unpin' : 'Pin' }}</button></form>
                        <details class="relative">
                            <summary class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10 cursor-pointer list-none"><i class="fas fa-reply mr-1"></i>Reply</summary>
                            <form method="POST" action="{{ route('user.links.reviews.reply', $review) }}" class="absolute z-10 mt-2 w-72 glass rounded-xl p-3">@csrf
                                <textarea name="reply" rows="3" class="w-full bg-black/30 border border-white/10 rounded-lg px-2.5 py-2 text-sm text-white" placeholder="Write a public reply…">{{ $review->reply }}</textarea>
                                <button class="mt-2 w-full text-xs px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white">Save reply</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('user.links.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review permanently?')">@csrf @method('DELETE')<button class="text-xs px-3 py-1.5 rounded-lg bg-red-500/10 text-red-300 hover:bg-red-500/20"><i class="fas fa-trash mr-1"></i>Delete</button></form>
                        @php
                            $shareStars = $review->rating ? str_repeat('★', (int) $review->rating) . str_repeat('☆', 5 - (int) $review->rating) . ' ' : '';
                            $shareText  = trim($shareStars . ($review->body ? '“' . \Illuminate\Support\Str::limit($review->body, 180) . '” — ' : '') . ($review->author_name ?: 'A happy customer'));
                            $shareImg   = optional($review->media->firstWhere('type', 'image'))->url;
                        @endphp
                        <details class="relative">
                            <summary class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10 cursor-pointer list-none"><i class="fas fa-share-nodes mr-1"></i>Share</summary>
                            <div class="absolute z-10 mt-2 w-60 glass rounded-xl p-3 space-y-1.5 right-0">
                                <a href="https://twitter.com/intent/tweet?text={{ rawurlencode($shareText) }}&url={{ rawurlencode($publicUrl) }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-lg text-white/70 hover:bg-white/10"><i class="fab fa-x-twitter w-4"></i>Share on X</a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($publicUrl) }}&quote={{ rawurlencode($shareText) }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-lg text-white/70 hover:bg-white/10"><i class="fab fa-facebook w-4"></i>Share on Facebook</a>
                                <a href="https://wa.me/?text={{ rawurlencode($shareText . ' ' . $publicUrl) }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-lg text-white/70 hover:bg-white/10"><i class="fab fa-whatsapp w-4"></i>Share on WhatsApp</a>
                                <button type="button" onclick="navigator.clipboard.writeText(@js($shareText . ' ' . $publicUrl)).then(()=>{this.textContent='Copied!';setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy w-4 mr-2\'></i>Copy text',1500)})" class="flex items-center gap-2 w-full text-left text-xs px-2.5 py-1.5 rounded-lg text-white/70 hover:bg-white/10"><i class="fas fa-copy w-4"></i>Copy text</button>
                                @if($shareImg)
                                <a href="{{ $shareImg }}" download target="_blank" rel="noopener" class="flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-lg text-white/70 hover:bg-white/10"><i class="fas fa-image w-4"></i>Download image</a>
                                @endif
                            </div>
                        </details>
                        <a href="{{ $publicUrl }}" target="_blank" class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10"><i class="fas fa-up-right-from-square mr-1"></i>View live</a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="glass rounded-2xl p-10 text-center text-white/40">No reviews yet. Share your page to start collecting them.</div>
        @endforelse
        <div class="mt-4">{{ $reviews->links() }}</div>
    </div>

    {{-- ── Settings ── --}}
    <div x-show="tab==='settings'" x-cloak>
        <form method="POST" action="{{ route('user.links.reviews.settings', $link) }}" class="glass rounded-2xl p-6 space-y-4">@csrf
            <div class="grid md:grid-cols-2 gap-4">
                <div><label class="block text-sm text-white/60 mb-1">Heading</label><input type="text" name="heading" value="{{ $settings['heading'] }}" maxlength="120" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white"></div>
                <div><label class="block text-sm text-white/60 mb-1">Subheading</label><input type="text" name="subheading" value="{{ $settings['subheading'] }}" maxlength="255" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white"></div>
            </div>
            <div class="grid md:grid-cols-4 gap-4">
                <div><label class="block text-sm text-white/60 mb-1">Source</label><select name="source" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white">@foreach(['both' => 'Native + Imported', 'native' => 'Native only', 'external' => 'Imported only'] as $v => $l)<option value="{{ $v }}" @selected($settings['source'] === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-sm text-white/60 mb-1">Sort</label><select name="sort" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white">@foreach(['recent' => 'Most recent', 'rating' => 'Highest rated'] as $v => $l)<option value="{{ $v }}" @selected($settings['sort'] === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-sm text-white/60 mb-1">Layout</label><select name="layout" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white">@foreach(['grid' => 'Grid', 'list' => 'List'] as $v => $l)<option value="{{ $v }}" @selected($settings['layout'] === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-sm text-white/60 mb-1">Max shown</label><input type="number" name="limit" value="{{ $settings['limit'] }}" min="1" max="200" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white"></div>
            </div>
            @php $selectedProviders = is_array($settings['providers'] ?? null) ? $settings['providers'] : []; @endphp
            <div>
                <label class="block text-sm text-white/60 mb-1">Imported providers to show</label>
                <p class="text-[11px] text-white/40 mb-2">Leave all unchecked to show every connected provider.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($providers as $slug => $p)
                    <label class="flex items-center gap-2 text-sm text-white/70 bg-white/5 rounded-xl px-3 py-2 cursor-pointer">
                        <input type="checkbox" name="providers[]" value="{{ $slug }}" @checked(in_array($slug, $selectedProviders, true)) class="rounded">
                        <i class="{{ $p['icon'] }}" style="color: {{ $p['tint'] }}"></i>{{ $p['name'] }}
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-2.5">
                @foreach(['show_summary' => 'Show rating summary', 'allow_submissions' => 'Allow public submissions', 'require_approval' => 'Require approval before showing', 'require_verification' => 'Require email verification (adds “Verified customer” badge)', 'collect_media' => 'Allow photo / audio / video', 'collect_email' => 'Collect reviewer email (private)'] as $flag => $label)
                <label class="flex items-center gap-2.5 text-sm text-white/70 bg-white/5 rounded-xl px-3 py-2.5"><input type="checkbox" name="{{ $flag }}" value="1" @checked($settings[$flag] ?? false) data-review-flag="{{ $flag }}" class="rounded">{{ $label }}</label>
                @endforeach
            </div>
            <p class="text-xs text-white/40 -mt-1">Email verification needs an address, so it keeps “Collect reviewer email” turned on.</p>
            <div class="flex justify-end"><button class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white">Save settings</button></div>
        </form>
        <script>
        (function () {
            var verify = document.querySelector('[data-review-flag="require_verification"]');
            var email  = document.querySelector('[data-review-flag="collect_email"]');
            if (!verify || !email) return;
            function sync() {
                if (verify.checked) { email.checked = true; email.disabled = true; }
                else { email.disabled = false; }
            }
            verify.addEventListener('change', sync);
            sync();
        })();
        </script>
    </div>

    {{-- ── Questions ── --}}
    <div x-show="tab==='questions'" x-cloak class="space-y-4">
        <div class="glass rounded-2xl p-6">
            <h3 class="text-white font-semibold mb-3">Custom review questions</h3>
            <p class="text-sm text-white/40 mb-4">Ask reviewers structured questions alongside their star rating.</p>
            @if($questions->count())
            <div class="space-y-2 mb-5">
                @foreach($questions as $q)
                <div class="flex items-center gap-3 bg-white/5 rounded-xl px-4 py-3">
                    <div class="flex-1"><span class="text-sm text-white">{{ $q->prompt }}</span> <span class="text-[11px] text-white/40">({{ $q->type }}@if($q->is_required), required @endif)</span></div>
                    <form method="POST" action="{{ route('user.links.reviews.questions.destroy', $q) }}" onsubmit="return confirm('Remove this question?')">@csrf @method('DELETE')<button class="text-red-300 hover:text-red-200 text-sm"><i class="fas fa-trash"></i></button></form>
                </div>
                @endforeach
            </div>
            @endif
            <form method="POST" action="{{ route('user.links.reviews.questions.store', $link) }}" x-data="{ type: 'text' }" class="grid md:grid-cols-12 gap-3 items-end">@csrf
                <div class="md:col-span-5"><label class="block text-xs text-white/50 mb-1">Prompt</label><input type="text" name="prompt" required maxlength="255" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white" placeholder="e.g. Would you recommend us?"></div>
                <div class="md:col-span-3"><label class="block text-xs text-white/50 mb-1">Type</label><select name="type" x-model="type" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white"><option value="text">Text</option><option value="rating">Rating</option><option value="choice">Choice</option></select></div>
                <div class="md:col-span-2" x-show="type==='choice'"><label class="block text-xs text-white/50 mb-1">Options (comma)</label><input type="text" x-bind:name="type==='choice' ? 'options_csv' : ''" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white" placeholder="Yes, No" @input="$el.closest('form').querySelectorAll('input[name^=options]').forEach(e=>e.remove()); this.value.split(',').map(o=>o.trim()).filter(Boolean).forEach(o=>{let i=document.createElement('input');i.type='hidden';i.name='options[]';i.value=o;$el.closest('form').appendChild(i);})"></div>
                <div class="md:col-span-1"><label class="flex items-center gap-1.5 text-xs text-white/60"><input type="checkbox" name="is_required" value="1" class="rounded">Req</label></div>
                <div class="md:col-span-1"><button class="w-full px-3 py-2.5 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white">Add</button></div>
            </form>
        </div>
    </div>

    {{-- ── Integrations / Providers ── --}}
    <div x-show="tab==='providers'" x-cloak class="grid md:grid-cols-2 gap-4">
        @foreach($providers as $slug => $p)
        @php $conn = $connections->get($slug); @endphp
        <div class="glass rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white" style="background: {{ $p['tint'] }}22; color: {{ $p['tint'] }}"><i class="{{ $p['icon'] }} text-lg"></i></div>
                <div><div class="font-semibold text-white">{{ $p['name'] }}</div>@if($conn)<div class="text-[11px] {{ $conn->status === 'connected' ? 'text-emerald-300' : 'text-amber-300' }} capitalize">{{ $conn->status }}@if($conn->last_synced_at) · synced {{ $conn->last_synced_at->diffForHumans() }}@endif</div>@endif</div>
            </div>
            <p class="text-sm text-white/50 mb-3">{{ $p['short'] }}</p>
            @if($conn && $conn->status_reason)<p class="text-[11px] text-amber-300/80 mb-3">{{ $conn->status_reason }}</p>@endif
            <form method="POST" action="{{ route('user.links.reviews.providers.connect', $slug) }}" class="space-y-2">@csrf
                <input type="text" name="external_ref" value="{{ $conn->external_ref ?? '' }}" placeholder="{{ $p['ref_label'] }}" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                <div class="flex gap-2">
                    <button class="flex-1 px-3 py-2 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white">{{ $conn ? 'Save & sync' : 'Connect' }}</button>
                </div>
            </form>
            @if($conn)
            <div class="flex gap-2 mt-2">
                <form method="POST" action="{{ route('user.links.reviews.providers.refresh', $conn) }}" class="flex-1">@csrf<button class="w-full px-3 py-2 rounded-xl text-sm bg-white/5 text-white/70 hover:bg-white/10"><i class="fas fa-sync mr-1"></i>Refresh now</button></form>
                <form method="POST" action="{{ route('user.links.reviews.providers.disconnect', $conn) }}" onsubmit="return confirm('Disconnect and remove imported reviews?')">@csrf @method('DELETE')<button class="px-3 py-2 rounded-xl text-sm bg-red-500/10 text-red-300 hover:bg-red-500/20"><i class="fas fa-unlink"></i></button></form>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
