@extends('user.layouts.app')
@section('title', 'AI Credit history')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-white text-xl font-semibold">AI credit history</h2>
        <a href="{{ route('user.ai-credits.show') }}" class="text-xs text-violet-300 hover:underline">← Back</a>
    </div>

    <form method="GET" class="rounded-2xl border border-white/10 bg-white/[0.02] p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="text-[10px] uppercase tracking-wider text-white/40 mb-1 block">Type</label>
            <select name="type" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
                <option value="">All</option>
                @foreach(['purchase','spend','refund','grant','admin_adjustment'] as $opt)
                    <option value="{{ $opt }}" {{ $filters['type']===$opt?'selected':'' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-white/40 mb-1 block">Feature</label>
            <select name="feature" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
                <option value="">All</option>
                @foreach($featureOptions as $f)
                    <option value="{{ $f }}" {{ $filters['feature']===$f?'selected':'' }}>{{ $f }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-white/40 mb-1 block">From</label>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-white/40 mb-1 block">To</label>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
        </div>
        <button class="px-4 py-1.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">Apply</button>
        <a href="{{ route('user.ai-credits.transactions', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="px-3 py-1.5 bg-white/10 text-white rounded-lg text-xs font-medium hover:bg-white/20 ml-auto">
            <i class="fas fa-file-csv mr-1"></i> Export CSV
        </a>
    </form>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
        @if($page->isEmpty())
            <p class="text-sm text-white/40">No transactions yet.</p>
        @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th>
                <th class="text-left">Type</th>
                <th class="text-left">Feature / model</th>
                <th class="text-right">In tok</th>
                <th class="text-right">Out tok</th>
                <th class="text-right">Δ Credits</th>
                <th class="text-right">Balance</th>
                <th class="text-left pl-3">Reason</th>
            </tr></thead>
            <tbody>
            @foreach($page as $tx)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white/60">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-white/60 text-xs">{{ \App\Modules\User\Models\AiCreditTransaction::featureLabel($tx->feature) }}{{ $tx->model ? ' · '.$tx->model : '' }}</td>
                    <td class="text-right text-white/40 text-xs">{{ $tx->tokens_in ? number_format($tx->tokens_in) : '—' }}</td>
                    <td class="text-right text-white/40 text-xs">{{ $tx->tokens_out ? number_format($tx->tokens_out) : '—' }}</td>
                    <td class="text-right font-semibold {{ $tx->delta_credits >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_credits >= 0 ? '+' : '' }}{{ number_format($tx->delta_credits) }}
                    </td>
                    <td class="text-right text-white/80">{{ number_format($tx->balance_after) }}</td>
                    <td class="pl-3 text-white/50 text-xs">{{ $tx->reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $page->links() }}</div>
        @endif
    </div>
</div>
@endsection
