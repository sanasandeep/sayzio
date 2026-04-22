@extends('user.layouts.app')
@section('title', $mind->name)

@section('content')
@php
    use App\Modules\User\Models\AiMindSource;
    $statusColor = [
        AiMindSource::STATUS_QUEUED     => 'bg-amber-500/10 text-amber-300',
        AiMindSource::STATUS_PROCESSING => 'bg-blue-500/10 text-blue-300',
        AiMindSource::STATUS_READY      => 'bg-emerald-500/10 text-emerald-300',
        AiMindSource::STATUS_FAILED     => 'bg-red-500/10 text-red-300',
        AiMindSource::STATUS_DISABLED   => 'bg-white/10 text-white/60',
    ];
@endphp
<div class="max-w-5xl mx-auto px-4 py-8 space-y-6"
     x-data="mindEdit({{ Js::from(['askUrl' => route('user.minds.ask', $mind), 'csrf' => csrf_token()]) }})">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('user.minds.index') }}" class="text-xs text-white/40 hover:text-white/60"><i class="fas fa-arrow-left"></i> All minds</a>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $mind->name }}
                @if($isPlatform)<span class="ml-2 text-[10px] uppercase tracking-wider text-cyan-300/80 align-middle">Platform default</span>@endif
                @if($mind->is_disabled)<span class="ml-2 text-[10px] uppercase tracking-wider text-red-300 align-middle">Disabled</span>@endif
            </h1>
            <p class="text-sm text-white/50 mt-1">{{ $mind->description }}</p>
        </div>
        <div class="text-xs text-white/40 text-right">
            {{ $sources->count() }} / {{ $caps['max_sources_per_mind'] }} sources<br>
            {{ number_format($mind->chunks_count ?? $mind->chunks()->count()) }} chunks indexed
        </div>
    </div>

    {{-- Credit usage (last 30 days) --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2">
                <i class="fas fa-coins text-amber-300"></i> AI credits used
            </h3>
            <span class="text-[11px] uppercase tracking-wider text-white/40">Last {{ $creditUsage['days'] }} days</span>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-4">
            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                <p class="text-[10px] uppercase tracking-wider text-white/40">Ingestion</p>
                <p class="text-xl font-bold text-cyan-300 mt-1">{{ number_format($creditUsage['ingest']) }}</p>
                <p class="text-[11px] text-white/40 mt-1">Embedding sources</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                <p class="text-[10px] uppercase tracking-wider text-white/40">Questions</p>
                <p class="text-xl font-bold text-violet-300 mt-1">{{ number_format($creditUsage['query']) }}</p>
                <p class="text-[11px] text-white/40 mt-1">Live test queries</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                <p class="text-[10px] uppercase tracking-wider text-white/40">Total</p>
                <p class="text-xl font-bold text-white mt-1">{{ number_format($creditUsage['total']) }}</p>
                <p class="text-[11px] text-white/40 mt-1">Combined spend</p>
            </div>
        </div>
        @if($creditUsage['total'] === 0)
            <p class="text-[11px] text-white/40 mt-3">No credits spent on this Mind in the last {{ $creditUsage['days'] }} days.</p>
        @endif
    </div>

    @unless($isPlatform)
    <form method="POST" action="{{ route('user.minds.update', $mind) }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
        @csrf @method('PUT')
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs uppercase tracking-wider text-white/40">Name</label>
                <input name="name" required maxlength="120" value="{{ old('name', $mind->name) }}"
                    class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-white/40">Description</label>
                <input name="description" maxlength="2000" value="{{ old('description', $mind->description) }}"
                    class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            </div>
        </div>
        <div class="flex justify-end"><button class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-sm">Save</button></div>
    </form>
    @endunless

    {{-- Add source --}}
    @if(!$isPlatform || auth()->user()->isSuperAdmin())
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-white font-semibold">Add a source</h3>
            <div class="flex gap-1 text-xs">
                @foreach(['text'=>'Text','faq'=>'FAQ','document'=>'Document','link'=>'Link','feature'=>'1INME data'] as $k=>$lbl)
                    <button type="button" @click="addType='{{ $k }}'" :class="addType==='{{ $k }}' ? 'bg-cyan-600 text-white' : 'bg-white/5 text-white/60 hover:bg-white/10'"
                        class="px-3 py-1.5 rounded-lg">{{ $lbl }}</button>
                @endforeach
            </div>
        </div>

        {{-- TEXT --}}
        <form method="POST" action="{{ route('user.minds.sources.store', $mind) }}" x-show="addType==='text'" class="space-y-3">
            @csrf <input type="hidden" name="type" value="text">
            <input name="title" required placeholder="Title" maxlength="200" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <textarea name="body" required rows="6" maxlength="{{ $caps['max_text_chars'] }}" placeholder="Paste content here ({{ number_format($caps['max_text_chars']) }} char max)" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm"></textarea>
            <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm">Add text</button>
        </form>

        {{-- FAQ --}}
        <form method="POST" action="{{ route('user.minds.sources.store', $mind) }}" x-show="addType==='faq'" x-data="{ rows: [{q:'',a:''}] }" class="space-y-3">
            @csrf <input type="hidden" name="type" value="faq">
            <input name="title" required placeholder="FAQ set title" maxlength="200" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <template x-for="(r, i) in rows" :key="i">
                <div class="flex flex-col gap-2 rounded-xl border border-white/10 p-3">
                    <input :name="`qs[${i}][q]`" x-model="r.q" required placeholder="Question" maxlength="500" class="w-full bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                    <textarea :name="`qs[${i}][a]`" x-model="r.a" required rows="2" placeholder="Answer" maxlength="5000" class="w-full bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-white text-sm"></textarea>
                    <button type="button" @click="rows.splice(i,1)" x-show="rows.length>1" class="self-end text-xs text-red-300">Remove</button>
                </div>
            </template>
            <div class="flex justify-between">
                <button type="button" @click="rows.push({q:'',a:''})" class="text-xs text-cyan-300 hover:underline"><i class="fas fa-plus"></i> Add row</button>
                <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm">Add FAQ</button>
            </div>
        </form>

        {{-- DOCUMENT --}}
        <form method="POST" action="{{ route('user.minds.sources.store', $mind) }}" enctype="multipart/form-data" x-show="addType==='document'" class="space-y-3">
            @csrf <input type="hidden" name="type" value="document">
            <input name="title" required placeholder="Document title" maxlength="200" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <input type="file" name="file" required accept=".pdf,.docx,.txt,.md" class="w-full text-sm text-white/70">
            <p class="text-[11px] text-white/40">PDF, DOCX, TXT, MD · max {{ $caps['max_doc_size_mb'] }}MB · max {{ $caps['max_docs_per_mind'] }} docs per mind. Image-only PDFs are skipped.</p>
            <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm">Upload</button>
        </form>

        {{-- LINK --}}
        <form method="POST" action="{{ route('user.minds.sources.store', $mind) }}" x-show="addType==='link'" class="space-y-3">
            @csrf <input type="hidden" name="type" value="link">
            <input name="title" required placeholder="Page title" maxlength="200" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <input name="url" required type="url" placeholder="https://..." maxlength="2048" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <div>
                <label class="text-xs text-white/50">Refresh every (minutes, min {{ max(15, $caps['link_refresh_min_minutes']) }})</label>
                <input name="refresh_minutes" type="number" min="{{ max(15, $caps['link_refresh_min_minutes']) }}" max="43200" value="1440"
                    class="w-32 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            </div>
            <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm">Add link</button>
        </form>

        {{-- FEATURE --}}
        <form method="POST" action="{{ route('user.minds.sources.store', $mind) }}" x-show="addType==='feature'" class="space-y-3">
            @csrf <input type="hidden" name="type" value="feature">
            <p class="text-xs text-white/40">Snapshots live data from a 1INME feature whenever this Mind is asked a question.</p>
            <select name="feature_key" required class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                @foreach($features as $key=>$label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm">Attach feature</button>
        </form>
    </div>
    @endif

    {{-- Existing sources --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-white font-semibold">Sources</h3>
            <span class="text-xs text-white/40">
                @foreach($sourceCounts as $t => $n)<span class="mr-2">{{ ucfirst($t) }}: {{ $n }}</span>@endforeach
            </span>
        </div>
        @if($sources->isEmpty())
            <p class="text-sm text-white/40">No sources yet.</p>
        @else
            <ul class="divide-y divide-white/5">
                @foreach($sources as $s)
                    <li class="py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-white/60">
                            <i class="fas {{ ['text'=>'fa-align-left','faq'=>'fa-circle-question','document'=>'fa-file-lines','link'=>'fa-link','feature'=>'fa-cubes-stacked'][$s->type] ?? 'fa-file' }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-sm truncate">{{ $s->title }}</p>
                            <p class="text-[11px] text-white/40 truncate">
                                {{ ucfirst($s->type) }}
                                @if($s->type==='link') · <a href="{{ $s->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $s->url }}</a> · refresh every {{ $s->refresh_minutes }}m@endif
                                @if($s->type==='document') · {{ number_format(($s->size_bytes ?? 0)/1024, 1) }} KB@endif
                                @if($s->type==='feature') · {{ \App\Services\AI\AiMindFeatureAdapter::label($s->feature_key) }}@endif
                                · {{ number_format($s->chunks_count ?? $s->chunks()->count()) }} chunks
                                @if(($sourceCreditSpend[$s->id] ?? 0) > 0)
                                    · <span class="text-amber-300" title="Credits spent embedding this source in the last {{ $creditUsage['days'] }} days">{{ number_format($sourceCreditSpend[$s->id]) }} credits / 30d</span>
                                @endif
                                @if($s->status_message) · <span class="text-red-300">{{ $s->status_message }}</span>@endif
                            </p>
                        </div>
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $statusColor[$s->status] ?? 'bg-white/10 text-white/60' }}">{{ $s->status }}</span>
                        @if(!$isPlatform || auth()->user()->isSuperAdmin())
                        <form method="POST" action="{{ route('user.minds.sources.refresh', [$mind, $s]) }}">@csrf
                            <button class="text-xs text-white/60 hover:text-white px-2 py-1" title="Re-ingest"><i class="fas fa-rotate"></i></button>
                        </form>
                        <form method="POST" action="{{ route('user.minds.sources.destroy', [$mind, $s]) }}" onsubmit="return confirm('Remove this source?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-300/80 hover:text-red-200 px-2 py-1"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Test chat --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h3 class="text-white font-semibold flex items-center gap-2"><i class="fas fa-comment-dots text-violet-300"></i> Test this Mind</h3>
        <p class="text-xs text-white/40 mt-1">Ask a question to verify the Mind answers from your sources. Costs AI credits.</p>
        <form @submit.prevent="ask" class="mt-3 flex gap-2">
            <input x-model="question" required maxlength="1500" placeholder="What do you want to know?" class="flex-1 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            <button :disabled="loading" class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium">
                <span x-show="!loading">Ask</span><span x-show="loading">…</span>
            </button>
        </form>
        <template x-if="error">
            <div class="mt-3 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm" x-text="error"></div>
        </template>
        <template x-if="answer">
            <div class="mt-3 space-y-3">
                <div class="rounded-xl bg-white/[0.04] border border-white/10 p-3 text-white text-sm whitespace-pre-wrap" x-text="answer"></div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] text-white/50">
                    <span x-text="`${creditsSpent} credits`"></span>
                    <template x-for="c in citations" :key="c.id">
                        <span class="px-2 py-0.5 rounded bg-white/5" x-text="c.title"></span>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function mindEdit(cfg) {
    return {
        addType: 'text',
        question: '',
        answer: '',
        citations: [],
        creditsSpent: 0,
        error: '',
        loading: false,
        async ask() {
            this.error = ''; this.answer = ''; this.citations = []; this.loading = true;
            try {
                const res = await fetch(cfg.askUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({ question: this.question }),
                });
                const data = await res.json();
                if (!res.ok) { this.error = data.error || 'Request failed.'; return; }
                this.answer = data.answer || '(no answer)';
                this.citations = data.citations || [];
                this.creditsSpent = data.credits_spent || 0;
            } catch (e) {
                this.error = e.message || 'Network error.';
            } finally { this.loading = false; }
        },
    };
}
</script>
@endsection
