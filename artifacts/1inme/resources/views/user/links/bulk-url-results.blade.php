@extends('user.layouts.app')
@section('title', 'Bulk Create — Results')

@section('content')
<div class="max-w-5xl mx-auto" x-data="bulkResults({{ \Illuminate\Support\Js::from($results) }})">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white transition-colors" title="Back to links"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-white">Batch results</h1>
            <p class="text-xs text-white/40 mt-0.5">
                <span class="text-emerald-400">{{ $created }} created</span>
                @if($skipped > 0) · <span class="text-amber-400">{{ $skipped }} skipped</span>@endif
            </p>
        </div>
        <button type="button" @click="downloadCsv()" class="bg-white/5 hover:bg-white/10 text-white border border-white/10 px-4 py-2 rounded-xl text-xs font-medium transition-all">
            <i class="fas fa-download mr-1.5"></i> Download CSV
        </button>
        <a href="{{ route('user.links.url.bulk') }}" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-xs font-medium transition-all">
            <i class="fas fa-plus mr-1.5"></i> New batch
        </a>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 border-b border-white/10 text-[11px] uppercase tracking-wider text-white/40">
                <tr>
                    <th class="px-4 py-2 text-left w-12">#</th>
                    <th class="px-4 py-2 text-left">Original URL</th>
                    <th class="px-4 py-2 text-left">Short URL / Issue</th>
                    <th class="px-4 py-2 text-left w-24">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach($results as $i => $r)
                <tr class="{{ $r['status'] === 'created' ? '' : 'bg-amber-500/5' }}">
                    <td class="px-4 py-3 text-white/40 align-top">{{ $i + 1 }}</td>
                    <td class="px-4 py-3 align-top">
                        <span class="text-xs text-white/60 font-mono break-all">{{ $r['original_url'] }}</span>
                    </td>
                    <td class="px-4 py-3 align-top">
                        @if($r['status'] === 'created')
                            <div class="flex items-center gap-2" x-data="{ copied: false }">
                                <a href="{{ $r['short_url'] }}" target="_blank" rel="noopener" class="text-xs text-violet-300 hover:underline font-mono break-all">{{ $r['short_url'] }}</a>
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $r['short_url'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="text-white/40 hover:text-violet-300 flex-shrink-0" title="Copy">
                                    <i x-show="!copied" class="fas fa-copy text-xs"></i>
                                    <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
                                </button>
                            </div>
                        @else
                            <span class="text-xs text-amber-300">{{ $r['error'] ?: '—' }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 align-top">
                        @if($r['status'] === 'created')
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-300"><i class="fas fa-check-circle"></i> Created</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[11px] text-amber-300"><i class="fas fa-minus-circle"></i> Skipped</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    window.Alpine.data('bulkResults', (rows) => ({
        rows,
        downloadCsv() {
            const escape = (v) => {
                const s = (v ?? '').toString();
                return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            };
            const header = ['original_url', 'short_url', 'alias', 'status', 'error'];
            const lines = [header.join(',')];
            for (const r of this.rows) {
                lines.push(header.map(h => escape(r[h])).join(','));
            }
            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'bulk-links-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    }));
});
</script>
@endsection
