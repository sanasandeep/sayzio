{{--
    Renders one CreatorPost on the /@handle public profile. Variants by
    post_type: text | image | gallery | video | audio | link.
--}}
@php $type = $post->effectiveType(); @endphp
<article class="cp-card overflow-hidden">
    {{-- ── Header ───────────────────────────── --}}
    <div class="px-5 pt-4 flex items-center gap-3">
        @if($creator->avatar)
            <img src="{{ $creator->avatar }}" alt="" class="w-9 h-9 rounded-full object-cover">
        @else
            <div class="w-9 h-9 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center font-bold text-sm">{{ $creator->getInitials() }}</div>
        @endif
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold truncate">{{ $creator->name }}
                @if($post->isPinned())
                    <span class="ml-1 text-[10px] uppercase tracking-wider text-amber-600 font-bold align-middle"><i class="fas fa-thumbtack"></i> Pinned</span>
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
        @if(!empty($post->body))
            <p class="text-sm text-slate-700 mt-1 whitespace-pre-line leading-relaxed">{{ $post->body }}</p>
        @endif
    </div>

    {{-- ── Media variants ───────────────────── --}}
    @if($type === \App\Modules\User\Models\CreatorPost::TYPE_IMAGE && $post->image)
        <img src="{{ $post->image }}" alt="" class="mt-3 w-full max-h-[640px] object-cover bg-slate-100">
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
        <a href="{{ $post->media['url'] }}" target="_blank" rel="noopener nofollow" class="mt-3 mx-5 mb-1 flex items-stretch border border-slate-200 rounded-xl overflow-hidden hover:border-violet-300 transition">
            @if(!empty($post->media['image']))
                <img src="{{ $post->media['image'] }}" alt="" class="w-28 h-28 object-cover bg-slate-100 shrink-0">
            @else
                <div class="w-28 h-28 bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center shrink-0">
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

    {{-- ── Reactions row ────────────────────── --}}
    <div class="px-5 py-3 border-t border-slate-100 mt-3 flex items-center justify-between gap-3">
        @include('common.partials.branded-reactions', [
            'post' => $post,
            'creator' => $creator,
            'totals' => $totals,
            'myReaction' => $myReaction,
            'reactionDefs' => $reactionDefs,
        ])
        <button type="button" data-cp-toggle-comments="#cp-comments-{{ $post->id }}"
                class="text-xs text-slate-500 hover:text-slate-700 font-semibold whitespace-nowrap">
            <i class="far fa-comment mr-1"></i> {{ (int) $post->comments_count }} comment{{ (int) $post->comments_count === 1 ? '' : 's' }}
        </button>
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

                        {{-- Replies (one level) --}}
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
                                class="text-[11px] text-slate-400 hover:text-violet-600 mt-1"
                                data-cp-toggle-comments="#cp-reply-form-{{ $c->id }}">
                            <i class="fas fa-reply mr-1"></i> Reply
                        </button>
                        <form data-cp-comment-form
                              data-cp-endpoint="{{ route('creator-profile.comment', ['handle' => $creator->handle, 'post' => $post->id]) }}"
                              id="cp-reply-form-{{ $c->id }}" class="mt-1 hidden flex gap-2">
                            <input type="hidden" name="parent_id" value="{{ $c->id }}">
                            <input type="text" name="body" maxlength="2000" placeholder="Reply…" class="flex-1 text-xs px-2 py-1 rounded-md border border-slate-200 focus:border-violet-400 focus:outline-none">
                            <button type="submit" class="text-xs px-3 py-1 rounded-md bg-violet-600 text-white font-semibold">Send</button>
                        </form>
                    </div>
                </div>
            @endforeach
            @if(count($comments) === 0)
                <p class="text-xs text-slate-400 italic">No comments yet — be the first.</p>
            @endif
        </div>

        {{-- Top-level comment form --}}
        <form data-cp-comment-form
              data-cp-endpoint="{{ route('creator-profile.comment', ['handle' => $creator->handle, 'post' => $post->id]) }}"
              class="mt-3 flex gap-2">
            <input type="text" name="body" maxlength="2000" placeholder="{{ $viewer ? 'Add a comment…' : 'Sign in to comment' }}"
                   class="flex-1 text-xs px-3 py-2 rounded-lg border border-slate-200 focus:border-violet-400 focus:outline-none">
            <button type="submit" class="text-xs px-3 py-2 rounded-lg bg-slate-900 text-white font-semibold">Post</button>
        </form>
    </div>
</article>
