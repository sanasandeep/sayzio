@extends('admin.layouts.app')
@section('title', 'Site Assistant: AI Knowledge Bases')
@section('page-title', 'Site Assistant, AI Knowledge Bases')

@section('content')
<div class="max-w-6xl space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>@endif
    <div class="text-sm text-white/60 ak-muted"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <div class="glass rounded-2xl border border-white/10 p-6 space-y-2">
        <h3 class="font-semibold text-white ak-strong">AI Knowledge Bases</h3>
        <p class="text-sm text-white/60 ak-muted">These are the platform-wide AI Knowledge Bases the assistant retrieves from. Pick which ones to use on the <a href="{{ route('admin.site-assistant.edit') }}" class="text-indigo-300 underline ak-blue">settings page</a>. Use the AI Knowledge Bases admin to add or edit content; this page lists status and lets you queue a re-index.</p>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase ak-muted">
                <tr>
                    <th class="text-left p-3">Mind</th>
                    <th class="text-right p-3">Sources</th>
                    <th class="text-right p-3">Chunks</th>
                    <th class="text-left p-3">In use</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($minds as $mind)
                    <tr class="border-t border-white/5">
                        <td class="p-3 text-white ak-strong">
                            {{ $mind->name }}
                            @if($mind->is_default)<span class="ml-2 text-[10px] uppercase tracking-wide bg-indigo-500/20 text-indigo-200 rounded px-1.5 py-0.5 ak-blue">default</span>@endif
                            @if($mind->is_disabled)<span class="ml-2 text-[10px] uppercase tracking-wide bg-red-500/20 text-red-200 rounded px-1.5 py-0.5 ak-red">disabled</span>@endif
                        </td>
                        <td class="p-3 text-right text-white/70 ak-strong">{{ (int) $mind->sources_count }}</td>
                        <td class="p-3 text-right text-white/70 ak-strong">{{ (int) $mind->chunks_count }}</td>
                        <td class="p-3 text-white/70 ak-strong">
                            @if(in_array((int)$mind->id, $picked, true))
                                <span class="text-emerald-300 ak-green">selected</span>
                            @elseif($mind->is_default && empty($picked))
                                <span class="text-emerald-300 ak-green">default fallback</span>
                            @else
                                <span class="text-white/40 ak-note">not used</span>
                            @endif
                        </td>
                        <td class="p-3 text-right">
                            <form method="POST" action="{{ route('admin.site-assistant.knowledge.reindex', $mind) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Queue a full re-index?', message: 'Every source in this knowledge base will be re-indexed.', confirmText: 'Re-index', confirmIcon: 'fa-rotate', iconClass: 'fa-rotate'})">
                                @csrf
                                <button class="px-3 py-1.5 rounded-lg bg-white/10 text-white text-xs hover:bg-white/15 ak-strong">Re-index</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-white/50 ak-muted">No platform-level knowledge bases yet. Create one in the AI Knowledge Bases admin and mark it as a platform AI Knowledge Base.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
