@extends('user.layouts.settings')
@section('title', 'Billing Companies')
@section('settings-content')
<div>
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Billing Companies</h1>
            <p class="hero-subtitle">The legal entities that issue your invoices &amp; receipts.</p>
        </div>
        <a href="{{ route('user.billing.companies.create') }}" class="btn-primary"><i class="fas fa-plus mr-2"></i>New Company</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    {{-- Wallet balance + invoice history summary (Task #3234). Read-only cards
         that reuse the existing wallet + client-invoice surfaces. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        @if($wallet)
            @php $__low = (int) ($walletLowThreshold ?? 0); @endphp
            <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(245,158,11,0.12));">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-[11px] uppercase tracking-wide font-semibold" style="color: var(--text-muted);">Coin wallet</div>
                        <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($wallet->balance) }} <span class="text-lg">🪙</span></div>
                        @if($__low > 0 && $wallet->balance < $__low)
                            <div class="text-xs mt-1 text-amber-600"><i class="fas fa-triangle-exclamation mr-1"></i>Balance below {{ number_format($__low) }} coins — top up to keep using coin add-ons.</div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        <a href="{{ route('user.wallet.show') }}" class="text-xs px-3 py-1.5 rounded-lg border text-center" style="border-color: var(--border-soft); color: var(--text-primary);"><i class="fas fa-wallet mr-1"></i>View wallet</a>
                        <a href="{{ route('user.wallet.buy') }}" class="text-xs px-3 py-1.5 rounded-lg text-center text-white" style="background: var(--color-primary-600, #2563eb);"><i class="fas fa-coins mr-1"></i>Buy coins</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-[11px] uppercase tracking-wide font-semibold" style="color: var(--text-muted);">Invoices &amp; receipts</div>
                    <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">Your invoice &amp; receipt history</div>
                    <p class="text-xs mt-1" style="color: var(--text-muted);">Review, download and manage the invoices &amp; receipts you issue.</p>
                </div>
                <span class="nav-icon-wrap shrink-0" style="width:2.25rem;height:2.25rem;"><i class="fas fa-file-invoice-dollar"></i></span>
            </div>

            {{-- Inline preview of the most recent client invoices (Task #3240).
                 Read-only; each row opens the existing invoice edit screen. --}}
            @if(!empty($recentInvoices) && $recentInvoices->isNotEmpty())
                <ul class="mt-3 divide-y" style="--tw-divide-opacity:1;">
                    @foreach($recentInvoices as $inv)
                        <li class="py-2" style="border-top: 1px solid var(--border-soft);">
                            <a href="{{ route('user.client-invoices.edit', $inv) }}" class="flex items-center justify-between gap-3 group">
                                <div class="min-w-0">
                                    <div class="text-xs font-mono truncate" style="color: var(--text-primary);">{{ $inv->number ?? '—' }}</div>
                                    <div class="text-[11px]" style="color: var(--text-muted);">{{ optional($inv->issued_at)->format('M j, Y') ?? '—' }}</div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs font-semibold" style="color: var(--text-primary);">{{ strtoupper($inv->currency ?: 'USD') }} {{ number_format(((int) $inv->grand_total_minor) / 100, 2) }}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background: rgba(61,107,255,0.12); color: #3d6bff;">{{ strtoupper($inv->status) }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-xs" style="color: var(--text-muted);">No invoices yet.</p>
            @endif

            <div class="mt-4">
                <a href="{{ route('user.client-invoices.dashboard') }}" class="text-xs px-3 py-1.5 rounded-lg border inline-flex items-center" style="border-color: var(--border-soft); color: var(--text-primary);">View all <i class="fas fa-arrow-right ml-1.5"></i></a>
            </div>
        </div>
    </div>

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
