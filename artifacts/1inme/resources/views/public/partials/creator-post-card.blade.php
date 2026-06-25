{{--
    Renders one CreatorPost on the /@handle public profile.
    Variants by post_type: text | image | gallery | video | audio | link.
    With paywall (Task #1209): if $access['can'] is false the body and
    media are replaced with a blurred teaser + Subscribe / Unlock CTA.
--}}
@php
    $type   = $post->effectiveType();
    $access = $access ?? ['can' => true, 'reason' => 'free'];
    $locked = !($access['can'] ?? true);
    $teaser = $post->teaserCaption();
    $blur   = $post->blurIntensity();
    $blurPx = ['low' => '6px', 'medium' => '14px', 'high' => '24px'][$blur] ?? '14px';
    $needsSub = !empty($access['requires_subscription']);
    $needsPpv = !empty($access['requires_ppv']);
    $lowestTier = $access['lowest_tier'] ?? null;

    // Task #1211 — when the creator has watermarking on, swap raw image URLs
    // for the watermark proxy so screenshots can be traced. Visitors who are
    // not logged in still see the un-watermarked original (no viewer name to
    // stamp), so this only kicks in for authenticated viewers. Uses the
    // dedicated watermark.serve route (re-checks viewer + creator settings
    // server-side) — NOT signed-media.serve which is a paywall-token flow.
    $wmEnabled = !$locked
        && app(\App\Modules\Common\Services\WatermarkService::class)->isEnabled($creator)
        && (!empty($viewer) && (int)($viewer->id ?? 0) !== (int)$creator->id);
    $wmUrl = static function ($idx) use ($post, $wmEnabled) {
        if (!$wmEnabled) return null;
        return route('watermark.serve', ['post' => $post->id, 'idx' => $idx]);
    };
    $primaryImg = $wmUrl(0) ?: ($post->image ?? null);
@endphp
<article class="cp-card overflow-hidden">
    {{-- ── Header ───────────────────────────── --}}
    <div class="px-5 pt-4 flex items-center gap-3">
        @if($creator->avatar)
            <img src="{{ $creator->avatar }}" alt="" class="w-9 h-9 rounded-full object-cover">
        @else
            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">{{ $creator->getInitials() }}</div>
        @endif
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold truncate">{{ $creator->name }}
                @if($post->isPinned())
                    <span class="ml-1 text-[10px] uppercase tracking-wider text-amber-600 font-bold align-middle"><i class="fas fa-thumbtack"></i> Pinned</span>
                @endif
                @if($post->visibility === \App\Modules\User\Models\CreatorPost::VISIBILITY_TIER)
                    <span class="ml-1 inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-blue-600 font-bold align-middle">
                        <i class="fas fa-gem"></i> {{ $lowestTier?->name ?? 'Tier' }}
                    </span>
                @elseif($post->visibility === \App\Modules\User\Models\CreatorPost::VISIBILITY_PPV)
                    <span class="ml-1 inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-sky-600 font-bold align-middle">
                        <i class="fas fa-lock"></i> ${{ number_format(($post->ppv_price_cents ?: 0) / 100, 2) }}
                    </span>
                @endif
            </p>
            <p class="text-[11px] text-slate-500">{{ $post->published_at?->diffForHumans() }}</p>
        </div>
    </div>

    {{-- ── Body / title ─────────────────────── --}}
    <div class="px-5 pt-3">
        @if($post->title)
            <h3 class="text-lg font-bold leading-snug">{{ $post->title }}</h3>
        @endif
        @if(!$locked)
            @if(!empty($post->body))
                <p class="text-sm text-slate-700 mt-1 whitespace-pre-line leading-relaxed">{{ $post->body }}</p>
            @endif
        @else
            @if($teaser)
                <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $teaser }}</p>
            @elseif(!empty($post->body))
                <p class="text-sm text-slate-700 mt-1 leading-relaxed line-clamp-2">{{ \Illuminate\Support\Str::limit($post->body, 180) }}</p>
            @endif
        @endif
    </div>

    {{-- ── Media / Locked overlay ──────────── --}}
    @if($locked)
        @php
            // Creator-controlled preview limits (Task #1209). The composer
            // lets the creator opt in to revealing the first N gallery
            // items or the video poster as a teaser; default is none.
            // We never emit the locked asset URLs — CSS blur is cosmetic,
            // not access control, so we use a coloured gradient placeholder
            // for the unrevealed surface.
            $previewItems   = ($type === \App\Modules\User\Models\CreatorPost::TYPE_GALLERY)
                ? array_slice((array) ($post->media['items'] ?? []), 0, $post->galleryPreviewCount())
                : [];
            $previewPoster  = ($type === \App\Modules\User\Models\CreatorPost::TYPE_VIDEO && $post->videoPreviewSeconds() > 0)
                ? ($post->media['poster'] ?? null)
                : null;
            $palettes = [
                ['from-blue-300', 'via-fuchsia-300', 'to-sky-300'],
                ['from-rose-300',   'via-orange-300',  'to-amber-300'],
                ['from-emerald-300','via-teal-300',    'to-cyan-300'],
                ['from-indigo-300', 'via-indigo-300',  'to-pink-300'],
            ];
            $palette = $palettes[$post->id % count($palettes)];
        @endphp
        <div class="relative mt-3">
            @if(!empty($previewItems))
                {{-- Gallery: show the creator-approved N items in the clear,
                     plus a "+X more" placeholder card if there are more. --}}
                <div class="grid grid-cols-3 gap-1 px-1">
                    @foreach($previewItems as $item)
                        <img src="{{ $item['url'] }}" alt="" class="w-full h-32 sm:h-40 object-cover rounded-md bg-slate-100">
                    @endforeach
                    @if(count((array) ($post->media['items'] ?? [])) > count($previewItems))
                        <div class="w-full h-32 sm:h-40 rounded-md bg-gradient-to-br {{ $palette[0] }} {{ $palette[1] }} {{ $palette[2] }} flex items-center justify-center text-white font-bold text-lg">
                            +{{ count((array) $post->media['items']) - count($previewItems) }}
                        </div>
                    @endif
                </div>
            @elseif($previewPoster)
                {{-- Video poster + duration teaser. The video file URL is
                     never sent to the client — only the poster + the
                     creator-configured preview duration. --}}
                <div class="relative">
                    <img src="{{ $previewPoster }}" alt="" class="w-full max-h-[420px] object-cover bg-black select-none pointer-events-none">
                    <div class="absolute bottom-2 right-2 px-2 py-1 rounded-full bg-black/70 text-white text-[11px] font-bold">
                        {{ $post->videoPreviewSeconds() }}s preview
                    </div>
                </div>
            @else
                <div class="w-full h-64 bg-gradient-to-br {{ $palette[0] }} {{ $palette[1] }} {{ $palette[2] }}"
                     style="filter: blur({{ $blurPx }}); transform: scale(1.02);"></div>
            @endif

            {{-- Lock overlay --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-t from-slate-900/80 via-slate-900/30 to-transparent text-center px-5">
                <div class="bg-white/95 backdrop-blur rounded-2xl p-5 max-w-sm shadow-xl">
                    <div class="flex items-center justify-center w-11 h-11 rounded-full mx-auto mb-2"
                         style="background: #3d6bff; color: white;">
                        <i class="fas fa-lock"></i>
                    </div>
                    @if($needsSub)
                        <p class="text-sm font-bold text-slate-900">
                            {{ $lowestTier ? $lowestTier->name : 'Subscribers' }}-only post
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            Subscribe to {{ $creator->name }} to unlock this and every post in this tier.
                        </p>
                        <a href="{{ route('creator-profile.subscribe.show', ['handle' => $creator->handle]) }}"
                           class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-blue-600 to-fuchsia-600">
                            <i class="fas fa-gem"></i> Subscribe
                            @if($lowestTier)
                                <span class="opacity-80">· from ${{ number_format($lowestTier->price_monthly_cents / 100, 2) }}/mo</span>
                            @endif
                        </a>
                    @elseif($needsPpv)
                        <p class="text-sm font-bold text-slate-900">Unlock this post</p>
                        <p class="text-xs text-slate-500 mt-1">A one-time payment unlocks the full content forever.</p>
                        <form method="POST" action="{{ route('creator-profile.unlock', ['handle' => $creator->handle, 'post' => $post->id]) }}" class="mt-3">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-600">
                                <i class="fas fa-lock-open"></i>
                                Unlock for ${{ number_format(($post->ppv_price_cents ?: 0) / 100, 2) }}
                            </button>
                        </form>
                    @else
                        <p class="text-sm font-bold text-slate-900">Locked</p>
                        <p class="text-xs text-slate-500 mt-1">Sign in to view this post.</p>
                    @endif
                    <p class="text-[10px] text-slate-400 mt-3">100% goes to {{ $creator->name }}.</p>
                </div>
            </div>
        </div>
    @else
        @if($type === \App\Modules\User\Models\CreatorPost::TYPE_IMAGE && $post->image)
            <img src="{{ $primaryImg }}" alt="" class="mt-3 w-full max-h-[640px] object-cover bg-slate-100">
        @elseif($type === \App\Modules\User\Models\CreatorPost::TYPE_GALLERY && !empty($post->media['items']))
            <div class="mt-3 grid {{ count($post->media['items']) === 1 ? 'grid-cols-1' : (count($post->media['items']) === 2 ? 'grid-cols-2' : 'grid-cols-3') }} gap-1 px-1">
                @foreach($post->media['items'] as $item)
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="w-full h-40 sm:h-48 object-cover rounded-md bg-slate-100">
                @endforeach
            </div>
        @elseif($type === \App\Modules\User\Models\CreatorPost::TYPE_VIDEO && !empty($post->media['url']))
            <div class="mt-3 bg-black">
                <video src="{{ $post->media['url'] }}"
                       poster="{{ $post->media['poster'] ?? '' }}"
                       controls preload="metadata" playsinline
                       class="w-full max-h-[640px]"></video>
            </div>
        @elseif($type === \App\Modules\User\Models\CreatorPost::TYPE_AUDIO && !empty($post->media['url']))
            <div class="mt-3 px-5">
                @if(!empty($post->media['title']))
                    <p class="text-xs text-slate-500 mb-1">{{ $post->media['title'] }}</p>
                @endif
                <audio src="{{ $post->media['url'] }}" controls preload="metadata" class="w-full"></audio>
            </div>
        @elseif($type === \App\Modules\User\Models\CreatorPost::TYPE_LINK && !empty($post->media['url']))
            <a href="{{ $post->media['url'] }}" target="_blank" rel="noopener nofollow" class="mt-3 mx-5 mb-1 flex items-stretch border border-slate-200 rounded-xl overflow-hidden hover:border-blue-300 transition">
                @if(!empty($post->media['image']))
                    <img src="{{ $post->media['image'] }}" alt="" class="w-28 h-28 object-cover bg-slate-100 shrink-0">
                @else
                    <div class="w-28 h-28 bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center shrink-0">
                        <i class="fas fa-link text-2xl"></i>
                    </div>
                @endif
                <div class="p-3 min-w-0 flex-1">
                    <p class="text-[11px] text-slate-400 truncate">{{ parse_url($post->media['url'], PHP_URL_HOST) }}</p>
                    <p class="text-sm font-semibold text-slate-900 mt-0.5 line-clamp-2">{{ $post->media['title'] ?? $post->media['url'] }}</p>
                    @if(!empty($post->media['description']))
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $post->media['description'] }}</p>
                    @endif
                </div>
            </a>
        @endif
    @endif

    {{-- ── Reactions row ────────────────────── --}}
    <div class="px-5 py-3 border-t border-slate-100 mt-3 flex items-center justify-between gap-3">
        @include('common.partials.branded-reactions', [
            'post' => $post,
            'creator' => $creator,
            'totals' => $totals,
            'myReaction' => $myReaction,
            'reactionDefs' => $reactionDefs,
        ])
        <div class="flex items-center gap-3">
            {{-- Tip button is always available — even on locked / paid
                 posts. Tipping is independent of access; fans can show
                 appreciation regardless of whether they've subscribed. --}}
            <button type="button"
                    data-cp-open-tip
                    data-cp-tip-creator="{{ $creator->id }}"
                    data-cp-tip-handle="{{ $creator->handle }}"
                    data-cp-tip-post="{{ $post->id }}"
                    class="text-xs text-slate-500 hover:text-rose-600 font-semibold whitespace-nowrap">
                <i class="far fa-heart mr-1"></i> Tip
            </button>
            <button type="button" data-cp-toggle-comments="#cp-comments-{{ $post->id }}"
                    class="text-xs text-slate-500 hover:text-slate-700 font-semibold whitespace-nowrap">
                <i class="far fa-comment mr-1"></i> {{ (int) $post->comments_count }} comment{{ (int) $post->comments_count === 1 ? '' : 's' }}
            </button>
        </div>
    </div>

    {{-- ── Comments thread (lazy-shown) ─────── --}}
    <div id="cp-comments-{{ $post->id }}" class="px-5 py-3 border-t border-slate-100 hidden" data-cp-comments>
        <div data-cp-toplevel class="space-y-2">
            @foreach($comments as $c)
                <div class="flex items-start gap-2">
                    @if($c->viewer && $c->viewer->avatar)
                        <img src="{{ $c->viewer->avatar }}" class="w-7 h-7 rounded-full object-cover">
                    @else
                        <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-semibold text-slate-600">
                            {{ $c->viewer?->getInitials() ?? '?' }}
                        </div>
                    @endif
                    <div class="flex-1 text-xs">
                        <p>
                            <span class="font-semibold text-slate-900">{{ $c->viewer?->name ?? 'Someone' }}</span>
                            <span class="text-slate-400"> · {{ $c->created_at?->diffForHumans() }}</span>
                        </p>
                        <p class="text-slate-700 mt-0.5">{{ $c->body }}</p>

                        @if($c->replies->count() > 0)
                            <div class="mt-2 ml-3 space-y-2 border-l border-slate-100 pl-3">
                                @foreach($c->replies as $r)
                                    <div class="flex items-start gap-2">
                                        @if($r->viewer && $r->viewer->avatar)
                                            <img src="{{ $r->viewer->avatar }}" class="w-6 h-6 rounded-full object-cover">
                                        @else
                                            <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-semibold text-slate-600">{{ $r->viewer?->getInitials() ?? '?' }}</div>
                                        @endif
                                        <div class="flex-1">
                                            <p>
                                                <span class="font-semibold text-slate-900">{{ $r->viewer?->name ?? 'Someone' }}</span>
                                                <span class="text-slate-400"> · {{ $r->created_at?->diffForHumans() }}</span>
                                            </p>
                                            <p class="text-slate-700 mt-0.5">{{ $r->body }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div data-cp-replies="{{ $c->id }}" class="mt-1"></div>

                        <button type="button"
                                class="text-[11px] text-slate-400 hover:text-blue-600 mt-1"
                                data-cp-toggle-comments="#cp-reply-form-{{ $c->id }}">
                            <i class="fas fa-reply mr-1"></i> Reply
                        </button>
                        <form data-cp-comment-form
                              data-cp-endpoint="{{ route('creator-profile.comment', ['handle' => $creator->handle, 'post' => $post->id]) }}"
                              id="cp-reply-form-{{ $c->id }}" class="mt-1 hidden flex gap-2">
                            <input type="hidden" name="parent_id" value="{{ $c->id }}">
                            <input type="text" name="body" maxlength="2000" placeholder="Reply…" class="flex-1 text-xs px-2 py-1 rounded-md border border-slate-200 focus:border-blue-400 focus:outline-none">
                            <button type="submit" class="text-xs px-3 py-1 rounded-md bg-blue-600 text-white font-semibold">Send</button>
                        </form>
                    </div>
                </div>
            @endforeach
            @if(count($comments) === 0)
                <p class="text-xs text-slate-400 italic">No comments yet — be the first.</p>
            @endif
        </div>

        <form data-cp-comment-form
              data-cp-endpoint="{{ route('creator-profile.comment', ['handle' => $creator->handle, 'post' => $post->id]) }}"
              class="mt-3 flex gap-2">
            <input type="text" name="body" maxlength="2000" placeholder="{{ $viewer ? 'Add a comment…' : 'Sign in to comment' }}"
                   class="flex-1 text-xs px-3 py-2 rounded-lg border border-slate-200 focus:border-blue-400 focus:outline-none">
            <button type="submit" class="text-xs px-3 py-2 rounded-lg bg-slate-900 text-white font-semibold">Post</button>
        </form>
    </div>
</article>
