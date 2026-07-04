@extends('user.layouts.app')
@section('title', 'Invoice ' . $invoice->number)
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Invoice {{ $invoice->number }}</h1>
            <p class="hero-subtitle">Status: <strong>{{ strtoupper($invoice->status) }}</strong> · {{ strtoupper($invoice->currency) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('client-invoice.pdf', now()->addDay(), ['invoice' => $invoice->id]) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
               style="border-color: var(--border-soft); color: var(--text-primary); background: var(--bg-card);">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
            <a href="{{ route('user.client-invoices.dashboard') }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    @if(!empty($lastSendFailed) && $invoice->status !== 'paid')
        <div class="mb-4 p-4 rounded-xl border" style="border-color: rgba(225,29,72,0.35); background: rgba(225,29,72,0.08);">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation mt-0.5" style="color:#e11d48;"></i>
                <div class="flex-1">
                    <p class="text-sm font-semibold" style="color:#e11d48;">Last send failed — this invoice was not delivered.</p>
                    @if(!empty($lastSendReason))
                        <p class="text-xs mt-1 font-medium" style="color:#e11d48;">
                            <i class="fas fa-circle-info mr-1"></i>{{ $lastSendReason }}
                        </p>
                    @endif
                    <p class="text-xs mt-1" style="color: var(--text-muted);">
                        The invoice email to {{ $invoice->recipient_email ?? 'the recipient' }} couldn't be delivered. Retry the send, or copy the pay link below to share it manually.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <form action="{{ route('user.client-invoices.send', $invoice) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:#e11d48;">
                                <i class="fas fa-rotate-right mr-1"></i> Retry send
                            </button>
                        </form>
                        <input type="text" readonly value="{{ $payUrl }}" onclick="this.select()"
                               class="flex-1 min-w-[12rem] px-2 py-1.5 rounded-lg border text-xs font-mono"
                               style="border-color: var(--border-soft); background: var(--bg-glass-input); color: var(--text-primary);">
                        <a href="{{ $payUrl }}" target="_blank" rel="noopener"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                            Open pay link
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('user.client-invoices.update', $invoice) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')

        @php
            // Group emails by vault_client_id so the JS picker can populate
            // the email <select> when a Vault Client is chosen.
            $emailsByClient = $emails->groupBy('client_id')->map(function ($g) {
                return $g->map(fn($e) => ['id' => $e->id, 'email' => $e->email, 'label' => $e->label ?? null]);
            })->toArray();
        @endphp
        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);"
                 x-data="{
                     vaultId: @js((int) $invoice->vault_client_id),
                     email: @js((string) $invoice->recipient_email),
                     emailsByClient: @js($emailsByClient),
                     get clientEmails() { return this.emailsByClient[this.vaultId] || []; },
                 }">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Recipient</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Vault Client
                    <select name="vault_client_id" x-model.number="vaultId"
                            @change="if (clientEmails.length === 1) email = clientEmails[0].email"
                            class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                        <option value="0">— None —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Contact / lead
                    <select name="contact_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                        <option value="">— None —</option>
                        @foreach($contacts as $ct)
                            <option value="{{ $ct->id }}" @selected((int) old('contact_id', $invoice->contact_id) === $ct->id)>{{ $ct->nameForDisplay() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);" x-show="clientEmails.length > 0" x-cloak>
                    Contact email on file
                    <select @change="if ($event.target.value) email = $event.target.value"
                            class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                        <option value="">— Choose contact —</option>
                        <template x-for="ce in clientEmails" :key="ce.id">
                            <option :value="ce.email" :selected="ce.email === email"
                                    x-text="(ce.label ? ce.label + ' · ' : '') + ce.email"></option>
                        </template>
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Recipient Email
                    <input type="email" name="recipient_email" x-model="email"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Recipient name
                    <input name="recipient_name" value="{{ old('recipient_name', $invoice->recipient_name) }}"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                </label>
                <label class="block md:col-span-3 text-xs" style="color: var(--text-muted);">Recipient address
                    <textarea name="recipient_address" rows="2"
                              class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">{{ old('recipient_address', $invoice->recipient_address) }}</textarea>
                </label>
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Letterhead override</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Overrides the billing company's default letterhead for this invoice only.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                @if($invoice->letterhead_path)
                    <img src="{{ asset('storage/' . $invoice->letterhead_path) }}" alt="Letterhead preview"
                         class="w-16 h-20 rounded-xl object-cover bg-white p-1" style="border: 1px solid var(--border-soft);">
                @endif
                <label class="text-xs" style="color: var(--text-muted);">Letterhead image
                    <input type="file" name="letterhead" accept="image/png,image/jpeg,image/webp" class="block w-full mt-1 p-2 rounded-lg border text-xs" style="background: var(--bg-glass-input);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Orientation
                    <select name="letterhead_orientation" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                        <option value="portrait" @selected(($invoice->letterhead_orientation ?: 'portrait') === 'portrait')>Portrait</option>
                        <option value="landscape" @selected($invoice->letterhead_orientation === 'landscape')>Landscape</option>
                    </select>
                </label>
                @if($invoice->letterhead_path)
                    <label class="flex items-center gap-2 text-[11px]" style="color: var(--text-muted);">
                        <input type="hidden" name="remove_letterhead" value="0">
                        <input type="checkbox" name="remove_letterhead" value="1" class="accent-rose-500">
                        Remove this override (fall back to the company default)
                    </label>
                @endif
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Line items</h2>
            <table class="w-full text-sm">
                <thead><tr style="color: var(--text-muted);">
                    <th class="text-left p-2">Description</th>
                    <th class="text-right p-2 w-32">Qty</th>
                    <th class="text-right p-2 w-40">Amount (minor)</th>
                </tr></thead>
                <tbody>
                @foreach((array) $invoice->line_items as $i => $li)
                    <tr>
                        <td class="p-2"><input name="line_items[{{ $i }}][label]" value="{{ $li['label'] ?? '' }}" class="w-full p-2 rounded border" style="background: var(--bg-glass-input);"></td>
                        <td class="p-2"><input type="number" min="1" name="line_items[{{ $i }}][quantity]" value="{{ $li['quantity'] ?? 1 }}" class="w-full p-2 rounded border text-right" style="background: var(--bg-glass-input);"></td>
                        <td class="p-2"><input type="number" min="0" name="line_items[{{ $i }}][amount_minor]" value="{{ $li['amount_minor'] ?? 0 }}" class="w-full p-2 rounded border text-right" style="background: var(--bg-glass-input);"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div class="grid grid-cols-2 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Discount (minor)
                    <input type="number" min="0" name="discount_minor" value="{{ (int) $invoice->discount_minor }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Tax (minor)
                    <input type="number" min="0" name="tax_total_minor" value="{{ (int) $invoice->tax_total_minor }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Due date
                    <input type="date" name="due_date" value="{{ optional($invoice->due_date)->format('Y-m-d') }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">
                </label>
            </div>
            <label class="block mt-3 text-xs" style="color: var(--text-muted);">Notes
                <textarea name="notes_md" rows="3" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input);">{{ $invoice->notes_md }}</textarea>
            </label>
            <div class="mt-4 text-right text-sm" style="color: var(--text-primary);">
                Subtotal: <strong>{{ number_format($invoice->subtotal_minor / 100, 2) }}</strong>
                · Total: <strong>{{ number_format($invoice->grand_total_minor / 100, 2) }}</strong>
            </div>
        </section>

        <div class="flex items-center justify-between">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">Save changes</button>
        </div>
    </form>

    @if($invoice->status !== 'paid')
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <form action="{{ route('user.client-invoices.send', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-1"></i> Send invoice with pay link
                </button>
            </form>
            @if($invoice->sent_at && !in_array($invoice->status, ['refunded', 'partially_refunded'], true))
                <form action="{{ route('user.client-invoices.remind', $invoice) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                        <i class="fas fa-bell mr-1"></i> Send payment reminder
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
