@extends('admin.layouts.app')
@section('title', 'Edit Email Template')
@section('page-title', 'Edit Email Template')

@section('content')
<div class="max-w-5xl space-y-6" x-data="emailTemplateEditor()">

    <a href="{{ route('admin.email-templates.index') }}" class="inline-flex items-center gap-2 text-xs text-white/50 hover:text-white ak-muted">
        <i class="fas fa-arrow-left"></i> All templates
    </a>

    @if (session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red">
            <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-lg font-semibold text-white ak-strong">{{ $entry['label'] ?? $key }}</h1>
            @if ($override)
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/25 text-amber-300 ak-amber">Customised</span>
            @else
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-white/50 ak-muted">Default</span>
            @endif
        </div>
        <p class="text-xs text-white/40 mt-1 ak-note">{{ $entry['description'] ?? '' }}</p>
        <div class="text-[11px] text-white/30 mt-1 ak-note">{{ $category }} &middot; <code>{{ $key }}</code></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Editor --}}
        <form method="POST" action="{{ route('admin.email-templates.update', $key) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1 ak-strong">Subject</label>
                <input type="text" name="subject" x-model="subject"
                       class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input"
                       value="{{ old('subject', $override['subject'] ?? ($entry['subject'] ?? '')) }}">
            </div>

            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1 ak-strong">Format</label>
                <select name="format" x-model="format"
                        class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
                    @php $curFormat = old('format', $override['format'] ?? ($entry['format'] ?? 'html')); @endphp
                    <option value="html" @selected($curFormat === 'html')>HTML</option>
                    <option value="text" @selected($curFormat === 'text')>Plain text</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1 ak-strong">Body</label>
                <textarea name="body" rows="14" x-model="body"
                          class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-white/30 ak-strong ak-input"
                          placeholder="{{ $entry['view'] ?? '' ? 'Leave the saved/default content, view-rendered templates only customise here if you provide full markup.' : '' }}">{{ old('body', $override['body'] ?? ($entry['body'] ?? $preview['body'])) }}</textarea>
                @if (!empty($entry['view']))
                    <p class="text-[11px] text-amber-300/70 mt-1 ak-amber">
                        This template's default body is a rich Blade layout ({{ $entry['view'] }}). Saving an
                        override replaces it entirely with the text above, leave it on the pre-filled content
                        unless you intend to fully replace the design.
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500/15 border border-emerald-500/25 text-emerald-200 text-xs font-semibold hover:bg-emerald-500/25 ak-green">
                    Save override
                </button>
                <button type="button" @click="refreshPreview()" class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-white/70 text-xs font-semibold hover:bg-white/10 ak-strong">
                    Update preview
                </button>
            </div>
        </form>

        {{-- Variables + preview --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4">
                <h3 class="text-xs font-semibold text-white/70 mb-2 ak-strong">Available variables</h3>
                @if (!empty($entry['variables']))
                    <table class="w-full text-xs">
                        <tbody class="divide-y divide-white/5">
                        @foreach ($entry['variables'] as $token => $meta)
                            <tr>
                                <td class="py-1 pr-3"><code class="text-white/80 ak-strong">&#123;&#123;{{ $token }}&#125;&#125;</code></td>
                                <td class="py-1 text-white/40 ak-note">{{ $meta['label'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-xs text-white/40 ak-note">This template has no substitutable variables.</p>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4">
                <h3 class="text-xs font-semibold text-white/70 mb-2 ak-strong">Live preview (sample data)</h3>
                <div class="text-xs text-white/40 mb-1 ak-note">Subject</div>
                <div class="text-sm text-white mb-3 break-words ak-strong" x-text="previewSubject"></div>
                <div class="text-xs text-white/40 mb-1 ak-note">Body</div>
                <template x-if="previewFormat === 'text'">
                    <pre class="text-xs text-white/80 whitespace-pre-wrap bg-black/20 rounded-lg p-3 max-h-96 overflow-auto ak-strong" x-text="previewBody"></pre>
                </template>
                <template x-if="previewFormat !== 'text'">
                    <iframe class="w-full h-96 rounded-lg bg-white" x-ref="previewFrame"></iframe>
                </template>
            </div>

            @if ($override)
                <form method="POST" action="{{ route('admin.email-templates.reset', $key) }}"
                      onsubmit="return confirm('Reset this template to its built-in default? Your customised content will be removed.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs font-semibold hover:bg-red-500/20 ak-red">
                        Reset to default
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
function emailTemplateEditor() {
    return {
        subject: @json(old('subject', $override['subject'] ?? ($entry['subject'] ?? ''))),
        body: @json(old('body', $override['body'] ?? ($entry['body'] ?? $preview['body']))),
        format: @json(old('format', $override['format'] ?? ($entry['format'] ?? 'html'))),
        previewSubject: @json($preview['subject']),
        previewBody: @json($preview['body']),
        previewFormat: @json($preview['format']),
        init() { this.$nextTick(() => this.paintFrame()); },
        paintFrame() {
            if (this.previewFormat !== 'text' && this.$refs.previewFrame) {
                this.$refs.previewFrame.srcdoc = this.previewBody;
            }
        },
        refreshPreview() {
            fetch(@json(route('admin.email-templates.preview', $key)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ subject: this.subject, body: this.body, format: this.format }),
            })
            .then(r => r.json())
            .then(d => {
                this.previewSubject = d.subject;
                this.previewBody = d.body;
                this.previewFormat = d.format;
                this.$nextTick(() => this.paintFrame());
            })
            .catch(() => {});
        },
    };
}
</script>
@endsection
