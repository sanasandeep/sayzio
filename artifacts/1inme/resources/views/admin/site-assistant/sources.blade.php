@extends('admin.layouts.app')
@section('title', 'Site Assistant: Knowledge Sources')
@section('page-title', 'Site Assistant, Knowledge Sources')

@section('content')
<div class="max-w-6xl space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red">
            <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="text-sm text-white/60 ak-muted"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <div class="glass rounded-2xl border border-white/10 p-6 space-y-2">
        <h3 class="font-semibold text-white ak-strong">Custom content for the assistant</h3>
        <p class="text-sm text-white/60 ak-muted">
            Add URLs, paste text, or upload documents (PDF, DOCX, PPTX, RTF, TXT, MD) the assistant should learn from. Sources are chunked, embedded, and stored in the
            <span class="text-white ak-strong">{{ $mind->name }}</span> knowledge base. Optionally scope a source to a specific
            marketing page so the assistant prefers it when a visitor is on that page.
        </p>
        <p class="text-xs text-white/40 ak-note">
            Page pattern uses fnmatch, match a route name (e.g. <code class="text-white/70 ak-strong">marketing.pricing</code>) or
            a URL path (e.g. <code class="text-white/70 ak-strong">/pricing*</code>). Leave blank to apply everywhere.
        </p>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="font-semibold text-white mb-4 ak-strong">Add a new source</h3>
        <form method="POST" action="{{ route('admin.site-assistant.sources.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Type</label>
                    <select name="kind" id="sa-source-kind" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="url">URL (web page)</option>
                        <option value="text">Pasted text</option>
                        <option value="document">Document (PDF, DOCX, PPTX, RTF, TXT, MD)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Title</label>
                    <input type="text" name="title" maxlength="200" required value="{{ old('title') }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="e.g. Pricing FAQ">
                </div>
            </div>
            <div id="sa-source-url-row">
                <label class="block text-xs text-white/60 mb-1 ak-muted">URL</label>
                <input type="url" name="url" maxlength="2048" value="{{ old('url') }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="https://example.com/help/pricing">
            </div>
            <div id="sa-source-text-row" style="display:none;">
                <label class="block text-xs text-white/60 mb-1 ak-muted">Pasted content</label>
                <textarea name="body" rows="6" maxlength="50000" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Paste FAQ answers, product details, policies…">{{ old('body') }}</textarea>
            </div>
            <div id="sa-source-file-row" style="display:none;">
                <label class="block text-xs text-white/60 mb-1 ak-muted">Document</label>
                <input type="file" name="file" accept=".pdf,.docx,.doc,.rtf,.pptx,.txt,.md" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <p class="text-[11px] text-white/40 mt-1 ak-note">Up to 25 MB. Supported: PDF, DOCX, DOC, RTF, PPTX, TXT, MD. Scanned PDFs use OCR fallback.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Page pattern (optional)</label>
                    <input type="text" name="page_pattern" maxlength="200" value="{{ old('page_pattern') }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="marketing.pricing or /pricing*">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Surface</label>
                    <select name="assistant_surface" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="">Any</option>
                        <option value="marketing" {{ old('assistant_surface')==='marketing' ? 'selected':'' }}>Marketing only</option>
                        <option value="app" {{ old('assistant_surface')==='app' ? 'selected':'' }}>App only</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">URL refresh (minutes, links only)</label>
                    <input type="number" name="refresh_minutes" min="15" max="43200" value="{{ old('refresh_minutes', 1440) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                </div>
            </div>
            <div>
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white text-sm ak-strong">Add &amp; ingest</button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase ak-muted">
                <tr>
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Type</th>
                    <th class="text-left p-3">Page scope</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-right p-3">Chunks</th>
                    <th class="text-right p-3" title="Assistant messages that cited this source since the start of the month">Used (mo.)</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($sources as $s)
                    <tr id="source-{{ $s->id }}" class="border-t border-white/5 align-top target:bg-indigo-500/10">
                        <td class="p-3 text-white ak-strong">
                            <div class="font-medium">{{ $s->title }}</div>
                            @if($s->type === 'link' && $s->url)
                                <div class="text-xs text-white/40 truncate max-w-xs ak-note"><a class="hover:text-white" target="_blank" href="{{ $s->url }}">{{ $s->url }}</a></div>
                            @elseif($s->type === 'text' && $s->body)
                                <div class="text-xs text-white/40 truncate max-w-xs ak-note">{{ \Illuminate\Support\Str::limit(strip_tags($s->body), 90) }}</div>
                            @endif
                        </td>
                        <td class="p-3 text-white/70 ak-strong">{{ $s->type === 'link' ? 'URL' : ($s->type === 'document' ? 'Document' : 'Text') }}</td>
                        <td class="p-3 text-white/70 ak-strong">
                            @if($s->page_pattern)
                                <code class="text-indigo-200 ak-blue">{{ $s->page_pattern }}</code>
                                @if($s->assistant_surface)<div class="text-[10px] text-white/40 uppercase ak-note">{{ $s->assistant_surface }}</div>@endif
                            @else
                                <span class="text-white/40 ak-note">all pages</span>
                            @endif
                        </td>
                        <td class="p-3 text-white/70 ak-strong">
                            <span class="text-[10px] uppercase tracking-wide rounded px-1.5 py-0.5
                                @if($s->status === 'ready') bg-emerald-500/20 text-emerald-200 ak-green
                                @elseif($s->status === 'failed') bg-red-500/20 text-red-200 ak-red
                                @else bg-white/10 text-white/70 ak-muted @endif">{{ $s->status }}</span>
                            @if($s->status_message)<div class="text-xs text-white/40 mt-1 ak-note">{{ $s->status_message }}</div>@endif
                        </td>
                        <td class="p-3 text-right text-white/70 ak-strong">{{ (int) $s->chunks_count }}</td>
                        <td class="p-3 text-right text-white/70 ak-strong">
                            @php $usedN = (int) ($usageThisMonth[$s->id] ?? 0); @endphp
                            @if($usedN > 0)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] bg-indigo-500/15 text-indigo-200 ak-blue" title="Used {{ $usedN }} time{{ $usedN === 1 ? '' : 's' }} this month">{{ $usedN }}</span>
                            @else
                                <span class="text-white/30 text-xs ak-note" title="Not cited yet this month">-</span>
                            @endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.site-assistant.sources.reingest', $s) }}" class="inline">
                                @csrf
                                <button class="px-2 py-1 rounded bg-white/10 text-white text-xs hover:bg-white/15 ak-strong">Re-ingest</button>
                            </form>
                            <form method="POST" action="{{ route('admin.site-assistant.sources.destroy', $s) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this knowledge source?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                @csrf @method('DELETE')
                                <button class="px-2 py-1 rounded bg-red-500/20 text-red-200 text-xs hover:bg-red-500/30 ak-red">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-white/50 ak-muted">No knowledge sources yet. Add your first URL or paste content above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $sources->links() }}</div>
</div>

<script>
(function () {
    var sel = document.getElementById('sa-source-kind');
    var urlRow = document.getElementById('sa-source-url-row');
    var textRow = document.getElementById('sa-source-text-row');
    var fileRow = document.getElementById('sa-source-file-row');
    function sync() {
        urlRow.style.display = sel.value === 'url' ? '' : 'none';
        textRow.style.display = sel.value === 'text' ? '' : 'none';
        fileRow.style.display = sel.value === 'document' ? '' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
