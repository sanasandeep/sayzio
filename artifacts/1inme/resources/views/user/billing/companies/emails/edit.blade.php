@extends('user.layouts.app')
@section('title', 'Edit Client Email Template')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8" x-data="companyEmailTemplateEditor()">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">{{ $entry['label'] ?? $key }}</h1>
            <p class="hero-subtitle">{{ $company->name }} &middot; {{ $entry['description'] ?? '' }}</p>
        </div>
        <a href="{{ route('user.billing.companies.emails.index', $company) }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <div class="flex items-center gap-2 mb-4">
        @if($override)
            <span class="text-[11px] px-2 py-1 rounded-lg bg-amber-50 text-amber-700">Customised for this company</span>
        @else
            <span class="text-[11px] px-2 py-1 rounded-lg" style="background: var(--bg-glass-input); color: var(--text-muted);">Using inherited default</span>
        @endif
        <code class="text-[11px]" style="color: var(--text-muted);">{{ $key }}</code>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Editor --}}
        <form method="POST" action="{{ route('user.billing.companies.emails.update', [$company, $key]) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <label class="block text-xs" style="color: var(--text-muted);">Subject
                <input type="text" name="subject" x-model="subject"
                       class="block w-full mt-1 p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"
                       value="{{ old('subject', $override['subject'] ?? ($entry['subject'] ?? '')) }}">
            </label>

            <label class="block text-xs" style="color: var(--text-muted);">Format
                <select name="format" x-model="format"
                        class="block w-full mt-1 p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    @php $curFormat = old('format', $override['format'] ?? ($entry['format'] ?? 'html')); @endphp
                    <option value="html" @selected($curFormat === 'html')>HTML</option>
                    <option value="text" @selected($curFormat === 'text')>Plain text</option>
                </select>
            </label>

            <label class="block text-xs" style="color: var(--text-muted);">Body
                <textarea name="body" rows="14" x-model="body"
                          class="block w-full mt-1 p-2 rounded-lg border text-xs font-mono" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">{{ old('body', $override['body'] ?? ($entry['body'] ?? $preview['body'])) }}</textarea>
            </label>
            @if(!empty($entry['view']))
                <p class="text-[11px] text-amber-600">
                    This email's default body is a rich branded layout. Saving here replaces it entirely
                    with the content above for this company — leave it on the pre-filled content unless you
                    intend to fully replace the design.
                </p>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i>Save for this company</button>
                <button type="button" @click="refreshPreview()" class="px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">Update preview</button>
            </div>
        </form>

        {{-- Variables + preview --}}
        <div class="space-y-4">
            <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                <h3 class="text-xs font-semibold mb-2" style="color: var(--text-primary);">Available variables</h3>
                @if(!empty($entry['variables']))
                    <table class="w-full text-xs">
                        <tbody>
                        @foreach($entry['variables'] as $token => $meta)
                            <tr>
                                <td class="py-1 pr-3"><code style="color: var(--text-primary);">&#123;&#123;{{ $token }}&#125;&#125;</code></td>
                                <td class="py-1" style="color: var(--text-muted);">{{ $meta['label'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-xs" style="color: var(--text-muted);">This template has no substitutable variables.</p>
                @endif
            </div>

            <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                <h3 class="text-xs font-semibold mb-2" style="color: var(--text-primary);">Live preview (sample data)</h3>
                <div class="text-xs mb-1" style="color: var(--text-muted);">Subject</div>
                <div class="text-sm mb-3 break-words" style="color: var(--text-primary);" x-text="previewSubject"></div>
                <div class="text-xs mb-1" style="color: var(--text-muted);">Body</div>
                <template x-if="previewFormat === 'text'">
                    <pre class="text-xs whitespace-pre-wrap rounded-lg p-3 max-h-96 overflow-auto" style="background: var(--bg-glass-input); color: var(--text-primary);" x-text="previewBody"></pre>
                </template>
                <template x-if="previewFormat !== 'text'">
                    <iframe class="w-full h-96 rounded-lg bg-white" x-ref="previewFrame"></iframe>
                </template>
            </div>

            @if($override)
                <form method="POST" action="{{ route('user.billing.companies.emails.reset', [$company, $key]) }}"
                      onsubmit="return confirm('Reset this template? This company will use the inherited default content.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-2 rounded-lg border text-sm text-rose-600" style="border-color: var(--border-soft);">
                        <i class="fas fa-undo mr-1"></i>Reset to default
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
function companyEmailTemplateEditor() {
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
            fetch(@json(route('user.billing.companies.emails.preview', [$company, $key])), {
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
