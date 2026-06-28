@extends('user.layouts.app')
@section('title', 'Billing Companies')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Billing Companies</h1>
            <p class="hero-subtitle">The legal entities that issue your invoices &amp; receipts.</p>
        </div>
        <a href="{{ route('user.billing.companies.create') }}" class="btn-primary"><i class="fas fa-plus mr-2"></i>New Company</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    @if($companies->isEmpty())
        <div class="p-8 rounded-xl border text-center" style="border-color: var(--border-soft); background: var(--bg-card); color: var(--text-muted);">
            <i class="fas fa-building text-3xl mb-3 opacity-50"></i>
            <p>No billing companies yet. Create one to start issuing invoices under your business identity.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($companies as $company)
                <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-bold" style="color: var(--text-primary);">
                                {{ $company->name }}
                                @if($company->is_default)<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 align-middle">Default</span>@endif
                            </h3>
                            @if($company->legal_name)<p class="text-xs" style="color: var(--text-muted);">{{ $company->legal_name }}</p>@endif
                        </div>
                        <span class="text-xs" style="color: var(--text-muted);">{{ $company->default_currency ?: 'USD' }}</span>
                    </div>
                    <dl class="mt-3 text-xs space-y-1" style="color: var(--text-muted);">
                        @if($company->email)<div><i class="fas fa-envelope w-4"></i> {{ $company->email }}</div>@endif
                        @if($company->phone)<div><i class="fas fa-phone w-4"></i> {{ $company->phone }}</div>@endif
                        @if($company->tax_id_value)<div><i class="fas fa-hashtag w-4"></i> {{ $company->tax_id_label ?: 'Tax ID' }}: {{ $company->tax_id_value }}</div>@endif
                        @if($company->invoice_prefix)<div><i class="fas fa-tag w-4"></i> Prefix: {{ $company->invoice_prefix }}</div>@endif
                    </dl>
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('user.billing.companies.edit', $company) }}" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);"><i class="fas fa-pen mr-1"></i>Edit</a>
                        <form action="{{ route('user.billing.companies.destroy', $company) }}" method="POST" onsubmit="return confirm('Delete this company?');">
                            @csrf @method('DELETE')
                            <button class="text-xs px-3 py-1.5 rounded-lg text-rose-600"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
