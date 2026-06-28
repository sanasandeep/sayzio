@extends('user.layouts.app')
@section('title', 'Recurring Invoices')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Recurring Invoices</h1>
            <p class="hero-subtitle">Templates that auto-generate invoices on a schedule.</p>
        </div>
        <a href="{{ route('user.billing.recurring.create') }}" class="btn-primary"><i class="fas fa-plus mr-2"></i>New Template</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    @forelse($templates as $tpl)
        <div class="p-4 rounded-xl border mb-3 flex items-center justify-between" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div>
                <h3 class="font-bold" style="color: var(--text-primary);">
                    {{ $tpl->title ?: 'Untitled template' }}
                    <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full {{ $tpl->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ ucfirst($tpl->status) }}</span>
                </h3>
                <p class="text-xs" style="color: var(--text-muted);">
                    Every {{ $tpl->interval_count > 1 ? $tpl->interval_count . ' ' : '' }}{{ $tpl->interval }}
                    @if($tpl->recipient_email) · {{ $tpl->recipient_email }}@endif
                    @if($tpl->next_run_date) · Next: {{ \Illuminate\Support\Carbon::parse($tpl->next_run_date)->format('M j, Y') }}@endif
                    @if($tpl->billingCompany) · {{ $tpl->billingCompany->name }}@endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <form action="{{ route('user.billing.recurring.run', $tpl) }}" method="POST">@csrf<button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);"><i class="fas fa-bolt mr-1"></i>Run now</button></form>
                <form action="{{ route('user.billing.recurring.toggle', $tpl) }}" method="POST">@csrf<button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);">{{ $tpl->status === 'active' ? 'Pause' : 'Resume' }}</button></form>
                <a href="{{ route('user.billing.recurring.edit', $tpl) }}" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);"><i class="fas fa-pen"></i></a>
                <form action="{{ route('user.billing.recurring.destroy', $tpl) }}" method="POST" onsubmit="return confirm('Delete this template?');">@csrf @method('DELETE')<button class="text-xs px-3 py-1.5 rounded-lg text-rose-600"><i class="fas fa-trash"></i></button></form>
            </div>
        </div>
    @empty
        <div class="p-8 rounded-xl border text-center" style="border-color: var(--border-soft); background: var(--bg-card); color: var(--text-muted);">
            <i class="fas fa-repeat text-3xl mb-3 opacity-50"></i>
            <p>No recurring templates yet.</p>
        </div>
    @endforelse

    <div class="mt-4">{{ $templates->links() }}</div>
</div>
@endsection
