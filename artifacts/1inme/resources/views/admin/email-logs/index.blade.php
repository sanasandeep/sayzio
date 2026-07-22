@extends('admin.layouts.app')
@section('title', 'Email Log')
@section('page-title', 'Email Log')

@section('content')
<div class="max-w-6xl space-y-5">

    @if (session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red">{{ session('error') }}</div>
    @endif

    <p class="text-sm text-white/50 ak-muted">
        Every outbound email is recorded here. Search by recipient or subject, filter by area or status,
        and resend any individual message.
    </p>

    <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-6 gap-2">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Recipient or subject"
               class="lg:col-span-2 rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
        <select name="category" class="rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
            <option value="">All areas</option>
            @foreach ($categories as $value => $label)
                <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
            <option value="">Any status</option>
            <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
            <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}"
               class="rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
        <input type="date" name="to" value="{{ $filters['to'] }}"
               class="rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:border-white/30 ak-strong ak-input">
        <div class="lg:col-span-6 flex gap-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-white/10 border border-white/15 text-white text-xs font-semibold hover:bg-white/15 ak-strong">Filter</button>
            <a href="{{ route('admin.email-logs.index') }}" class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-white/60 text-xs font-semibold hover:bg-white/10 ak-muted">Clear</a>
        </div>
    </form>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-white/[0.03] text-white/40 ak-note">
                <tr>
                    <th class="text-left font-medium px-3 py-2">When</th>
                    <th class="text-left font-medium px-3 py-2">Recipient</th>
                    <th class="text-left font-medium px-3 py-2">Subject</th>
                    <th class="text-left font-medium px-3 py-2">Area</th>
                    <th class="text-left font-medium px-3 py-2">Status</th>
                    <th class="text-right font-medium px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($logs as $log)
                    <tr class="hover:bg-white/[0.02]">
                        <td class="px-3 py-2 text-white/50 whitespace-nowrap ak-muted">{{ optional($log->created_at)->format('M j, H:i') }}</td>
                        <td class="px-3 py-2 text-white/80 ak-strong">{{ $log->recipient }}</td>
                        <td class="px-3 py-2 text-white/70 max-w-xs truncate ak-strong">
                            {{ $log->subject ?: '—' }}
                            @if ($log->isResend())<span class="text-[10px] text-white/40 ak-note">(resend)</span>@endif
                        </td>
                        <td class="px-3 py-2 text-white/50 ak-muted">{{ $log->category }}</td>
                        <td class="px-3 py-2">
                            @if ($log->status === 'failed')
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-500/15 border border-red-500/25 text-red-300 ak-red">Failed</span>
                            @elseif (in_array($log->transport, ['log', 'array'], true))
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/25 text-amber-300 ak-amber" title="Written to the {{ $log->transport }} driver, not actually delivered.">Log driver (not delivered)</span>
                            @else
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/25 text-emerald-300 ak-green">Sent</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('admin.email-logs.show', $log) }}" class="text-white/50 hover:text-white ak-muted">View</a>
                            <form method="POST" action="{{ route('admin.email-logs.resend', $log) }}" class="inline ml-2"
                                  onsubmit="return confirm('Resend this email to {{ $log->recipient }}?');">
                                @csrf
                                <button type="submit" class="text-blue-300 hover:text-blue-200 ak-blue">Resend</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-8 text-center text-white/40 ak-note">No emails match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
@endsection
