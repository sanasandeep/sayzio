@extends('admin.layouts.app')
@section('title', $digest->exists ? 'Edit Digest' : 'New Digest')
@section('content')
<div class="max-w-6xl mx-auto space-y-6"
     x-data="zioDigestComposer({
        blocks: @js($digest->blocks ?? []),
        audienceMode: @js($digest->audience['mode'] ?? 'opted_in'),
        planIds: @js(array_map('intval', $digest->audience['plan_ids'] ?? [])),
        countUrl: @js(route('admin.zio-digests.audience-count')),
        uploadUrl: @js(route('admin.zio-digests.upload')),
        csrf: @js(csrf_token()),
     })">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-white">{{ $digest->exists ? 'Edit digest' : 'New digest' }}</h2>
        <div class="flex items-center gap-3">
            @if($digest->exists)
                <a href="{{ route('admin.zio-digests.preview', $digest) }}" target="_blank" class="text-xs text-white/60 hover:text-white"><i class="fas fa-eye mr-1"></i> Preview</a>
            @endif
            <a href="{{ route('admin.zio-digests.index') }}" class="text-xs text-white/60 hover:text-white"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST"
          action="{{ $digest->exists ? route('admin.zio-digests.update', $digest) : route('admin.zio-digests.store') }}"
          @submit="serialize()" class="space-y-6">
        @csrf
        @if($digest->exists) @method('PUT') @endif
        <input type="hidden" name="blocks_json" x-ref="blocksJson">

        <div class="glass rounded-2xl p-6 space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Title</label>
                    <input type="text" name="title" required maxlength="255" value="{{ old('title', $digest->title) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        <option value="draft" {{ old('status', $digest->status) === 'draft' ? 'selected' : '' }}>Draft (hidden from public)</option>
                        <option value="published" {{ old('status', $digest->status) === 'published' ? 'selected' : '' }}>Published</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Summary <span class="normal-case text-white/30">(shown on the page, in emails and WhatsApp)</span></label>
                <textarea name="summary" rows="2" maxlength="5000"
                          class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('summary', $digest->summary) }}</textarea>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Lead image URL</label>
                <div class="flex gap-2">
                    <input type="url" name="lead_image" maxlength="2048" value="{{ old('lead_image', $digest->lead_image) }}" x-ref="leadImage"
                           class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <label class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-xs text-white cursor-pointer">
                        <i class="fas fa-upload mr-1"></i> Upload
                        <input type="file" accept="image/*" class="hidden" @change="uploadTo($event, url => $refs.leadImage.value = url)">
                    </label>
                </div>
            </div>
            @if($digest->exists)
                <p class="text-xs text-white/40">Public page: <span class="text-white/70">{{ $digest->publicUrl() }}</span> ({{ $digest->isPublished() ? 'live' : 'hidden while draft' }})</p>
            @endif
        </div>

        {{-- Blocks composer --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Content blocks</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                    <template x-for="t in ['heading','text','image','video','link','embed']" :key="t">
                        <button type="button" @click="addBlock(t)"
                                class="px-2.5 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-white capitalize">
                            <i class="fas fa-plus mr-1 text-white/40"></i><span x-text="t"></span>
                        </button>
                    </template>
                </div>
            </div>

            <p x-show="blocks.length === 0" class="text-sm text-white/40 py-6 text-center">No blocks yet — add a heading or text block to get started.</p>

            <template x-for="(block, i) in blocks" :key="block._key">
                <div class="border border-white/10 rounded-xl p-4 space-y-3 bg-white/[0.03]">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wider text-white/40" x-text="block.type"></span>
                        <div class="flex items-center gap-2 text-white/50 text-xs">
                            <button type="button" @click="move(i, -1)" :disabled="i === 0" class="hover:text-white disabled:opacity-30"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" @click="move(i, 1)" :disabled="i === blocks.length - 1" class="hover:text-white disabled:opacity-30"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" @click="blocks.splice(i, 1)" class="text-red-300/70 hover:text-red-300"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>

                    <template x-if="block.type === 'heading'">
                        <input type="text" x-model="block.text" placeholder="Section heading"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    </template>

                    <template x-if="block.type === 'text'">
                        <textarea x-model="block.text" rows="4" placeholder="Write your paragraph…"
                                  class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                    </template>

                    <template x-if="block.type === 'image'">
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <input type="url" x-model="block.url" placeholder="Image URL"
                                       class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                                <label class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-xs text-white cursor-pointer">
                                    <i class="fas fa-upload mr-1"></i> Upload
                                    <input type="file" accept="image/*" class="hidden" @change="uploadTo($event, url => block.url = url)">
                                </label>
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <input type="text" x-model="block.alt" placeholder="Alt text" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                                <input type="text" x-model="block.caption" placeholder="Caption (optional)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                        </div>
                    </template>

                    <template x-if="block.type === 'video'">
                        <div class="space-y-2">
                            <input type="url" x-model="block.url" placeholder="YouTube / Vimeo / MP4 URL"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <input type="text" x-model="block.title" placeholder="Label shown in the email (optional)"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        </div>
                    </template>

                    <template x-if="block.type === 'link'">
                        <div class="grid gap-2 md:grid-cols-2">
                            <input type="url" x-model="block.url" placeholder="https://…" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white md:col-span-2">
                            <input type="text" x-model="block.title" placeholder="Link title" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <input type="text" x-model="block.description" placeholder="Short description (optional)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        </div>
                    </template>

                    <template x-if="block.type === 'embed'">
                        <div class="space-y-2">
                            <input type="url" x-model="block.url" placeholder="Instagram / TikTok / X / YouTube post URL"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <input type="text" x-model="block.title" placeholder="Label shown in the email (optional)"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Audience --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Audience</h3>
                <div class="text-xs text-white/60" x-show="counts">
                    <span class="text-white" x-text="counts?.total ?? 0"></span> users —
                    email-eligible <span class="text-emerald-300" x-text="counts?.email ?? 0"></span>,
                    WhatsApp-eligible <span class="text-emerald-300" x-text="counts?.whatsapp ?? 0"></span>
                    <span class="text-white/40">(opted out: <span x-text="counts?.email_opted_out ?? 0"></span>, no phone: <span x-text="counts?.no_phone ?? 0"></span>)</span>
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-3 text-sm text-white/80">
                <label class="flex items-center gap-2 px-3 py-2 border border-white/10 rounded-lg cursor-pointer" :class="audienceMode === 'all' && 'bg-white/10'">
                    <input type="radio" name="audience_mode" value="all" x-model="audienceMode" @change="refreshCounts()"> All users
                </label>
                <label class="flex items-center gap-2 px-3 py-2 border border-white/10 rounded-lg cursor-pointer" :class="audienceMode === 'opted_in' && 'bg-white/10'">
                    <input type="radio" name="audience_mode" value="opted_in" x-model="audienceMode" @change="refreshCounts()"> Opted-in only
                </label>
                <label class="flex items-center gap-2 px-3 py-2 border border-white/10 rounded-lg cursor-pointer" :class="audienceMode === 'plans' && 'bg-white/10'">
                    <input type="radio" name="audience_mode" value="plans" x-model="audienceMode" @change="refreshCounts()"> By plan
                </label>
            </div>
            <div x-show="audienceMode === 'plans'" class="flex flex-wrap gap-2 text-xs">
                @foreach($plans as $plan)
                    <label class="flex items-center gap-1.5 px-2.5 py-1.5 border border-white/10 rounded-lg text-white/70 cursor-pointer"
                           :class="planIds.includes({{ $plan->id }}) && 'bg-white/10 text-white'">
                        <input type="checkbox" name="audience_plan_ids[]" value="{{ $plan->id }}"
                               :checked="planIds.includes({{ $plan->id }})"
                               @change="togglePlan({{ $plan->id }})"> {{ $plan->name }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-white/40">Email always skips users who unsubscribed from digests; WhatsApp skips users without a phone number. Suspended accounts are never included.</p>
        </div>

        <div class="flex items-center gap-3">
            <button class="px-4 py-2 bg-white/15 hover:bg-white/25 border border-white/10 rounded-lg text-sm text-white">
                <i class="fas fa-floppy-disk mr-1"></i> {{ $digest->exists ? 'Save digest' : 'Create digest' }}
            </button>
        </div>
    </form>

    @if($digest->exists)
        <div class="grid gap-4 md:grid-cols-2">
            <div class="glass rounded-2xl p-6 space-y-3">
                <h3 class="text-sm font-semibold text-white">Send test email</h3>
                <form method="POST" action="{{ route('admin.zio-digests.send-test', $digest) }}" class="flex gap-2">
                    @csrf
                    <input type="email" name="email" required placeholder="you@example.com"
                           class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <button class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-sm text-white">Send test</button>
                </form>
                <p class="text-xs text-white/40">Sends the current saved version via SendGrid. Save first if you just edited.</p>
            </div>
            <div class="glass rounded-2xl p-6 space-y-3">
                <h3 class="text-sm font-semibold text-white">Broadcast</h3>
                <form method="POST" action="{{ route('admin.zio-digests.send', $digest) }}" class="space-y-3"
                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Send this digest?', message: 'It will be queued to the selected channels for the configured audience.', confirmText: 'Send', confirmIcon: 'fa-paper-plane', iconClass: 'fa-paper-plane'})">
                    @csrf
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" name="channels[]" value="email" checked> Email (SendGrid)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp
                    </label>
                    <button class="px-3 py-2 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/30 rounded-lg text-sm text-emerald-200">
                        <i class="fas fa-paper-plane mr-1"></i> Send digest
                    </button>
                    @unless($digest->isPublished())
                        <p class="text-xs text-amber-300/80">Publish the digest before sending — messages link to its public page.</p>
                    @endunless
                </form>
                <a href="{{ route('admin.zio-digests.report', $digest) }}" class="inline-block text-xs text-white/60 hover:text-white"><i class="fas fa-chart-simple mr-1"></i> View delivery report</a>
            </div>
        </div>
    @endif
</div>

<script>
function zioDigestComposer(opts) {
    let keySeq = 1;
    return {
        blocks: (opts.blocks || []).map(b => ({ _key: keySeq++, ...b })),
        audienceMode: opts.audienceMode || 'opted_in',
        planIds: opts.planIds || [],
        counts: null,
        init() { this.refreshCounts(); },
        addBlock(type) {
            this.blocks.push({ _key: keySeq++, type, text: '', url: '', alt: '', caption: '', title: '', description: '' });
        },
        move(i, delta) {
            const j = i + delta;
            if (j < 0 || j >= this.blocks.length) return;
            const [b] = this.blocks.splice(i, 1);
            this.blocks.splice(j, 0, b);
        },
        togglePlan(id) {
            this.planIds = this.planIds.includes(id) ? this.planIds.filter(p => p !== id) : [...this.planIds, id];
            this.refreshCounts();
        },
        serialize() {
            this.$refs.blocksJson.value = JSON.stringify(this.blocks.map(({ _key, ...b }) => b));
        },
        async refreshCounts() {
            try {
                const res = await fetch(opts.countUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': opts.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ mode: this.audienceMode, plan_ids: this.planIds }),
                });
                if (res.ok) this.counts = await res.json();
            } catch (e) { /* count is advisory only */ }
        },
        async uploadTo(event, apply) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const form = new FormData();
            form.append('file', file);
            try {
                const res = await fetch(opts.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': opts.csrf, 'Accept': 'application/json' },
                    body: form,
                });
                const data = await res.json();
                if (res.ok && data.url) { apply(data.url); }
                else { alert(data.message || 'Upload failed.'); }
            } catch (e) {
                alert('Upload failed.');
            } finally {
                event.target.value = '';
            }
        },
    };
}
</script>
@endsection
