@extends('user.layouts.app')
@section('title', 'My Posts')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-start justify-between mb-6 gap-3">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">My Posts</h1>
        @if(!empty($approvalEnabled))
            <span class="text-xs px-2 py-1 rounded-full" style="background: rgba(61,107,255,0.12); color: var(--text-primary);">
                <i class="fas fa-shield-check mr-1"></i>
                @if(!empty($userIsApprover))
                    Approval workflow on (you can approve)
                @else
                    Approval workflow on (posts go to review)
                @endif
            </span>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    @canInWorkspace('posts.create')
    @include('user.cloud-files._attach-picker')
    <div x-data="cloudAttachPicker({ mode: 'form' })" class="rounded-2xl border p-5 mb-6 space-y-3" style="background: var(--bg-card); border-color: var(--border-soft);">
        <form action="{{ route('user.posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="text" name="title" placeholder="Title (optional)" class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);" value="{{ old('title') }}"/>
            <textarea name="body" placeholder="Share an update with your followers..." rows="3" required class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">{{ old('body') }}</textarea>

            {{-- Picked cloud-library files become hidden inputs the controller reads. --}}
            <template x-for="f in picked" :key="f.id">
                <input type="hidden" name="cloud_file_ids[]" :value="f.id">
            </template>
            <div x-show="picked.length > 0" class="flex flex-wrap gap-2">
                <template x-for="f in picked" :key="'chip' + f.id">
                    <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full"
                          style="background: rgba(61,107,255,0.12); color: var(--text-primary);">
                        <i :class="f.provider_icon" class="text-[11px]" style="color: var(--text-muted);"></i>
                        <span x-text="f.name" class="max-w-[200px] truncate"></span>
                        <button type="button" @click="remove(f.id)" class="text-[11px]" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                    </span>
                </template>
            </div>

            {{-- ── Paywall block (Task #1209) ─────────────────────────
                 Three visibility modes; tier and PPV reveal extra controls.
                 The links to Monetization let creators set up tiers
                 without leaving the post composer flow. --}}
            <div x-data="{
                    visibility: '{{ old('visibility', 'free') }}',
                    selectedTiers: {{ json_encode(array_map('intval', (array) old('visible_tier_ids', []))) }},
                    toggleTier(id) {
                        const i = this.selectedTiers.indexOf(id);
                        if (i === -1) this.selectedTiers.push(id);
                        else this.selectedTiers.splice(i, 1);
                    }
                 }"
                 class="rounded-xl p-3 border" style="background: rgba(92,131,255,0.05); border-color: rgba(92,131,255,0.2);">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-bold flex items-center gap-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-gem" style="color: #5c83ff;"></i> Audience &amp; paywall
                    </span>
                    <a href="{{ route('user.monetization.tiers') }}" target="_blank" class="text-[11px]" style="color: #5c83ff;">
                        Manage tiers <i class="fas fa-up-right-from-square text-[9px]"></i>
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-1.5 mb-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="visibility" value="free" x-model="visibility" class="hidden">
                        <div class="px-2 py-1.5 rounded-lg border text-center text-xs"
                             :style="visibility === 'free' ? 'background:#5c83ff;color:white;border-color:#5c83ff;' : 'background:var(--bg-soft);color:var(--text-primary);border-color:var(--border-soft);'">
                            <i class="fas fa-globe"></i> Public
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="visibility" value="tier" x-model="visibility" class="hidden">
                        <div class="px-2 py-1.5 rounded-lg border text-center text-xs"
                             :style="visibility === 'tier' ? 'background:#5c83ff;color:white;border-color:#5c83ff;' : 'background:var(--bg-soft);color:var(--text-primary);border-color:var(--border-soft);'">
                            <i class="fas fa-layer-group"></i> Tier-only
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="visibility" value="ppv" x-model="visibility" class="hidden">
                        <div class="px-2 py-1.5 rounded-lg border text-center text-xs"
                             :style="visibility === 'ppv' ? 'background:#5c83ff;color:white;border-color:#5c83ff;' : 'background:var(--bg-soft);color:var(--text-primary);border-color:var(--border-soft);'">
                            <i class="fas fa-lock"></i> Pay-per-view
                        </div>
                    </label>
                </div>

                {{-- Tier picker --}}
                <div x-show="visibility === 'tier'" x-cloak class="mt-2">
                    @if($monetizationTiers->isEmpty())
                        <p class="text-xs" style="color: var(--text-faint);">
                            No paid tiers yet — <a href="{{ route('user.monetization.tiers') }}" class="underline" style="color: #5c83ff;">create one</a> first.
                        </p>
                    @else
                        <p class="text-[11px] mb-1.5" style="color: var(--text-faint);">Visible to subscribers of:</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($monetizationTiers as $tier)
                                <label class="cursor-pointer">
                                    <input type="checkbox" class="hidden"
                                           :checked="selectedTiers.includes({{ (int) $tier->id }})"
                                           @change="toggleTier({{ (int) $tier->id }})">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold border"
                                          :style="selectedTiers.includes({{ (int) $tier->id }}) ? 'background:#5c83ff;color:white;border-color:#5c83ff;' : 'background:transparent;color:var(--text-secondary);border-color:var(--border-soft);'">
                                        {{ $tier->badge ? $tier->badge.' ' : '' }}{{ $tier->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <template x-for="tid in selectedTiers" :key="'vt-' + tid">
                            <input type="hidden" name="visible_tier_ids[]" :value="tid">
                        </template>
                    @endif
                </div>

                {{-- PPV price --}}
                <div x-show="visibility === 'ppv'" x-cloak class="mt-2 grid grid-cols-2 gap-2">
                    <label class="text-xs" style="color: var(--text-secondary);">
                        Price ($)
                        <input type="number" name="ppv_price" min="1" max="500" step="0.5" value="{{ old('ppv_price', 5) }}"
                               class="w-full mt-1 px-2 py-1.5 rounded border text-sm"
                               style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">
                    </label>
                    <label class="text-xs" style="color: var(--text-secondary);">
                        Blur intensity
                        <select name="blur_intensity" class="w-full mt-1 px-2 py-1.5 rounded border text-sm"
                                style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">
                            @foreach(['low' => 'Low (preview-y)', 'medium' => 'Medium', 'high' => 'High (silhouette only)'] as $k => $l)
                                <option value="{{ $k }}" {{ old('blur_intensity', 'medium') === $k ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                {{-- Teaser --}}
                <div x-show="visibility !== 'free'" x-cloak class="mt-2">
                    <input type="text" name="teaser_caption" maxlength="280"
                           value="{{ old('teaser_caption') }}"
                           placeholder="Teaser caption shown on the locked card (optional)"
                           class="w-full px-2 py-1.5 rounded border text-xs"
                           style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">
                </div>

                {{--
                    Preview controls (Task #1209): how much of the locked
                    post the creator wants to give away as a teaser. We
                    cap gallery preview at 3 items and video preview at
                    30 seconds so a paywall can't be bypassed by setting
                    "show everything". 0 = gradient placeholder only.
                --}}
                <div x-show="visibility !== 'free'" x-cloak class="mt-2 grid grid-cols-2 gap-2">
                    <label class="text-xs" style="color: var(--text-secondary);">
                        Gallery preview (items)
                        <input type="number" name="gallery_preview_count" min="0" max="3" step="1"
                               value="{{ old('gallery_preview_count', 0) }}"
                               class="w-full mt-1 px-2 py-1.5 rounded border text-sm"
                               style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">
                        <span class="block text-[10px] mt-0.5" style="color: var(--text-faint);">0 – 3 unblurred items shown to non-subscribers.</span>
                    </label>
                    <label class="text-xs" style="color: var(--text-secondary);">
                        Video preview (seconds)
                        <input type="number" name="video_preview_seconds" min="0" max="30" step="1"
                               value="{{ old('video_preview_seconds', 0) }}"
                               class="w-full mt-1 px-2 py-1.5 rounded border text-sm"
                               style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">
                        <span class="block text-[10px] mt-0.5" style="color: var(--text-faint);">0 – 30 second teaser. 0 hides the poster too.</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <input type="file" name="image" accept="image/*" class="text-xs" style="color: var(--text-muted);"/>
                <label class="text-xs flex items-center gap-2" style="color: var(--text-muted);">
                    <i class="far fa-clock"></i> Schedule for:
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="px-2 py-1 rounded border text-xs" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"/>
                </label>
                <label class="text-xs flex items-center gap-1.5" style="color: var(--text-muted);">
                    <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }}/>
                    <i class="fas fa-thumbtack"></i> Pin this post
                </label>
                <button type="button" @click="show()" class="text-xs px-3 py-1.5 rounded-lg border"
                        style="border-color: var(--border-soft); color: var(--text-primary);">
                    <i class="fas fa-cloud mr-1"></i> Attach from Cloud Files
                </button>
                <button class="ml-auto px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                    @if(!empty($approvalEnabled) && empty($userIsApprover))
                        Submit for review
                    @else
                        Publish / Schedule
                    @endif
                </button>
            </div>
            @error('body')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('scheduled_at')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="text-[11px]" style="color: var(--text-faint);">
                @if(!empty($approvalEnabled) && empty($userIsApprover))
                    A reviewer will need to approve before this goes live. They'll see your title, body, image and schedule.
                @else
                    Leave the schedule field empty to publish immediately. Pinned posts appear at the top of your followers' feeds and on your Link in Bio.
                @endif
            </p>
        </form>
        @include('user.cloud-files._attach-modal', ['confirmLabel' => 'Add to post'])
    </div>
    @else
    <div class="rounded-2xl border p-4 mb-6 flex items-center gap-3 text-sm" style="background: rgba(245,158,11,0.06); border-color: rgba(245,158,11,0.25); color: #b45309;">
        <i class="fas fa-lock"></i>
        <span>Your role doesn't allow creating posts in this workspace. Ask a workspace admin if you need access.</span>
    </div>
    @endcanInWorkspace

    @if($posts->count() === 0)
        <div class="text-center py-10 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <p style="color: var(--text-muted);">You haven't published any posts yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($posts as $post)
                @php
                    $status = $post->statusLabel();
                    $badgeClasses = [
                        'Pinned'            => 'bg-amber-100 text-amber-800',
                        'Scheduled'         => 'bg-sky-100 text-sky-800',
                        'Published'         => 'bg-emerald-100 text-emerald-800',
                        'Pending review'    => 'bg-blue-100 text-blue-800',
                        'Changes requested' => 'bg-orange-100 text-orange-800',
                        'Rejected'          => 'bg-rose-100 text-rose-800',
                        'Draft'             => 'bg-slate-100 text-slate-700',
                    ][$status] ?? 'bg-slate-100 text-slate-700';
                    $isMine = (int) ($post->created_by_user_id ?? 0) === (int) auth()->id();
                @endphp
                <div class="rounded-2xl border p-4 {{ $post->isPinned() ? 'ring-2 ring-amber-300' : '' }} {{ $post->isPendingReview() ? 'ring-2 ring-blue-300' : '' }}" style="background: var(--bg-card); border-color: var(--border-soft);">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badgeClasses }}">
                                    @if($status === 'Pinned')<i class="fas fa-thumbtack text-[9px]"></i>@endif
                                    @if($status === 'Scheduled')<i class="far fa-clock text-[9px]"></i>@endif
                                    @if($status === 'Pending review')<i class="fas fa-hourglass-half text-[9px]"></i>@endif
                                    @if($status === 'Changes requested')<i class="fas fa-pen text-[9px]"></i>@endif
                                    @if($status === 'Rejected')<i class="fas fa-ban text-[9px]"></i>@endif
                                    {{ $status }}
                                </span>
                                @if($post->isScheduled())
                                    <span class="text-xs" style="color: var(--text-faint);">Goes live {{ $post->scheduled_at->format('M j, Y g:i A') }} ({{ $post->scheduled_at->diffForHumans() }})</span>
                                @endif
                                @if($post->isPendingReview() && $post->intended_scheduled_at)
                                    <span class="text-xs" style="color: var(--text-faint);">Will publish {{ $post->intended_scheduled_at->format('M j, Y g:i A') }} once approved</span>
                                @endif
                                @if($post->approval_decided_at && $post->approvalDecider)
                                    <span class="text-xs" style="color: var(--text-faint);">
                                        {{ $status === 'Rejected' ? 'Rejected' : ($status === 'Changes requested' ? 'Reviewed' : 'Approved') }}
                                        by {{ $post->approvalDecider->name }} {{ $post->approval_decided_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                            @if($post->title)<h3 class="font-bold" style="color: var(--text-primary);">{{ $post->title }}</h3>@endif
                            <p class="text-sm whitespace-pre-line mt-1" style="color: var(--text-muted);">{{ $post->body }}</p>
                            @if($post->image)<img src="{{ $post->image }}" class="mt-3 rounded-lg max-h-72"/>@endif
                            @if($post->cloudAttachments->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($post->cloudAttachments as $att)
                                        @php($cf = $att->cloudFile)
                                        @if($cf)
                                            <a href="{{ $cf->link }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border hover:underline"
                                               style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"
                                               title="{{ $cf->providerLabel() }} · {{ $cf->humanSize() }}">
                                                <i class="{{ $cf->providerIcon() }}" style="color: var(--text-muted);"></i>
                                                <span class="max-w-[220px] truncate">{{ $cf->name }}</span>
                                                <i class="fas fa-arrow-up-right-from-square text-[10px]" style="color: var(--text-faint);"></i>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-xs mt-2" style="color: var(--text-faint);">
                                @if($post->isPublished())
                                    Published {{ $post->published_at->diffForHumans() }}
                                @else
                                    Created {{ $post->created_at->diffForHumans() }}
                                @endif
                            </p>

                            {{-- Approval thread + actions --}}
                            @if($post->approval_status)
                                <div x-data="{ open: {{ $post->isPendingReview() ? 'true' : 'false' }} }" class="mt-4 border-t pt-3" style="border-color: var(--border-soft);">
                                    <button type="button" @click="open = !open" class="text-xs font-semibold flex items-center gap-1" style="color: var(--text-primary);">
                                        <i class="fas fa-comments"></i>
                                        Review thread ({{ $post->approvalComments->count() }})
                                        <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                    <div x-show="open" x-cloak class="mt-3 space-y-3">
                                        @forelse($post->approvalComments as $cmt)
                                            <div class="flex items-start gap-2 text-sm">
                                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-[10px] font-bold uppercase" style="background: rgba(61,107,255,0.15); color: var(--text-primary);">
                                                    {{ strtoupper(mb_substr($cmt->user->name ?? '?', 0, 1)) }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <strong class="text-xs" style="color: var(--text-primary);">{{ $cmt->user->name ?? 'Someone' }}</strong>
                                                        @if($cmt->actionLabel())
                                                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase tracking-wide" style="background: rgba(61,107,255,0.10); color: var(--text-muted);">{{ $cmt->actionLabel() }}</span>
                                                        @endif
                                                        <span class="text-[11px]" style="color: var(--text-faint);">{{ $cmt->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    @if($cmt->body)
                                                        <p class="text-sm whitespace-pre-line mt-0.5" style="color: var(--text-muted);">{{ $cmt->body }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs italic" style="color: var(--text-faint);">No comments yet.</p>
                                        @endforelse

                                        {{-- Reviewer actions --}}
                                        @if(!empty($userIsApprover) && $post->isPendingReview())
                                            <div class="mt-3 border-t pt-3 space-y-2" style="border-color: var(--border-soft);">
                                                <form action="{{ route('user.posts.approve', $post) }}" method="POST" class="flex flex-col gap-2">
                                                    @csrf
                                                    <textarea name="note" rows="2" placeholder="Optional note for the editor…" class="w-full px-2 py-1.5 rounded border text-xs" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"></textarea>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <button class="px-3 py-1.5 rounded bg-emerald-600 text-white text-xs font-semibold">
                                                            <i class="fas fa-check mr-1"></i> Approve
                                                        </button>
                                                        <button type="submit" formaction="{{ route('user.posts.request-changes', $post) }}" class="px-3 py-1.5 rounded bg-orange-600 text-white text-xs font-semibold">
                                                            <i class="fas fa-pen mr-1"></i> Request changes
                                                        </button>
                                                        <button type="submit" formaction="{{ route('user.posts.reject', $post) }}" class="px-3 py-1.5 rounded bg-rose-600 text-white text-xs font-semibold"
                                                                onclick="return confirm('Reject this post? It won\'t publish.');">
                                                            <i class="fas fa-ban mr-1"></i> Reject
                                                        </button>
                                                        <span class="text-[11px]" style="color: var(--text-faint);">A note is required when requesting changes.</span>
                                                    </div>
                                                </form>
                                            </div>
                                        @endif

                                        {{-- Resubmit (author of a rejected / changes_requested post) --}}
                                        @if($isMine && ($post->needsChanges() || $post->wasRejected()))
                                            <form action="{{ route('user.posts.resubmit', $post) }}" method="POST" class="mt-3 border-t pt-3 flex flex-col gap-2" style="border-color: var(--border-soft);">
                                                @csrf
                                                <textarea name="note" rows="2" placeholder="What did you change?" class="w-full px-2 py-1.5 rounded border text-xs" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"></textarea>
                                                <div>
                                                    <button class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-semibold">
                                                        <i class="fas fa-paper-plane mr-1"></i> Re-send for review
                                                    </button>
                                                </div>
                                            </form>
                                        @endif

                                        {{-- Plain reply (anyone in the thread can chime in) --}}
                                        @if($isMine || !empty($userIsApprover))
                                            <form action="{{ route('user.posts.comments.store', $post) }}" method="POST" class="mt-3 border-t pt-3 flex items-start gap-2" style="border-color: var(--border-soft);">
                                                @csrf
                                                <input type="text" name="body" placeholder="Reply…" required maxlength="2000"
                                                       class="flex-1 px-2 py-1.5 rounded border text-xs"
                                                       style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"/>
                                                <button class="px-3 py-1.5 rounded border text-xs font-semibold" style="border-color: var(--border-soft); color: var(--text-primary);">Send</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            @canInWorkspace('posts.edit')
                                @if($post->isPublished())
                                    @if($post->isPinned())
                                        <form action="{{ route('user.posts.unpin', $post) }}" method="POST">
                                            @csrf
                                            <button class="text-xs text-amber-700 font-semibold"><i class="fas fa-thumbtack"></i> Unpin</button>
                                        </form>
                                    @else
                                        <form action="{{ route('user.posts.pin', $post) }}" method="POST">
                                            @csrf
                                            <button class="text-xs text-blue-600 font-semibold"><i class="fas fa-thumbtack"></i> Pin</button>
                                        </form>
                                    @endif
                                @endif
                            @endcanInWorkspace
                            @canInWorkspace('posts.delete')
                                <form action="{{ route('user.posts.destroy', $post) }}" method="POST" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this post?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 font-semibold">Delete</button>
                                </form>
                            @else
                                <button type="button" disabled class="text-xs font-semibold cursor-not-allowed opacity-50" style="color: var(--text-faint);" title="Your role doesn't allow deleting posts">
                                    <i class="fas fa-lock text-[10px] mr-1"></i>Delete
                                </button>
                            @endcanInWorkspace
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
