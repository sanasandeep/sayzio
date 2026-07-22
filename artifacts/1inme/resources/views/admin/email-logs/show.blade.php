@extends('admin.layouts.app')
@section('title', 'Email Detail')
@section('page-title', 'Email Detail')

@section('content')
<div class="max-w-4xl space-y-5">

    <a href="{{ route('admin.email-logs.index') }}" class="inline-flex items-center gap-2 text-xs text-white/50 hover:text-white ak-muted">
        <i class="fas fa-arrow-left"></i> Back to log
    </a>

    @if (session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4 space-y-2 text-xs">
        <div class="grid grid-cols-2 gap-2">
            <div><span class="text-white/40 ak-note">Recipient</span><div class="text-white/80 ak-strong">{{ $log->recipient }}</div></div>
            <div><span class="text-white/40 ak-note">Status</span>
                <div>
                    @if ($log->status === 'failed')
                        <span class="text-red-300 ak-red">Failed</span> &middot; <span class="text-white/50 ak-muted">{{ $log->error }}</span>
                    @elseif (in_array($log->transport, ['log', 'array'], true))
                        <span class="text-amber-300 ak-amber">Log driver (not delivered)</span>
                        <div class="text-white/40 mt-0.5 ak-note">Written to the <code>{{ $log->transport }}</code> driver, recorded as sent but not actually delivered.</div>
                    @else
                        <span class="text-emerald-300 ak-green">Sent</span>
                    @endif
                </div>
            </div>
            <div><span class="text-white/40 ak-note">Template</span><div class="text-white/80 ak-strong"><code>{{ $log->email_key }}</code></div></div>
            <div><span class="text-white/40 ak-note">Area</span><div class="text-white/80 ak-strong">{{ $log->category }}</div></div>
            <div><span class="text-white/40 ak-note">Format</span><div class="text-white/80 ak-strong">{{ $log->format }}</div></div>
            <div><span class="text-white/40 ak-note">When</span><div class="text-white/80 ak-strong">{{ optional($log->created_at)->toDateTimeString() }}</div></div>
            @if ($log->user)
                <div><span class="text-white/40 ak-note">User</span><div class="text-white/80 ak-strong">{{ $log->user->name }} ({{ $log->user->email }})</div></div>
            @endif
            @if ($log->isResend())
                <div><span class="text-white/40 ak-note">Resend of</span><div class="text-white/80 ak-strong">#{{ $log->meta['resent_from'] }}</div></div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4">
        <div class="text-xs text-white/40 mb-1 ak-note">Subject</div>
        <div class="text-sm text-white mb-3 break-words ak-strong">{{ $log->subject ?: '—' }}</div>
        <div class="text-xs text-white/40 mb-1 ak-note">Body</div>
        @if ($log->format === 'text')
            <pre class="text-xs text-white/80 whitespace-pre-wrap bg-black/20 rounded-lg p-3 max-h-[32rem] overflow-auto ak-strong">{{ $log->body }}</pre>
        @else
            <iframe class="w-full h-[32rem] rounded-lg bg-white" srcdoc="{{ $log->body }}"></iframe>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.email-logs.resend', $log) }}"
          onsubmit="return confirm('Resend this email to {{ $log->recipient }}?');">
        @csrf
        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-500/15 border border-blue-500/25 text-blue-200 text-xs font-semibold hover:bg-blue-500/25 ak-blue">
            Resend to {{ $log->recipient }}
        </button>
    </form>
</div>
@endsection
