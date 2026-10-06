@extends('user.layouts.app')
@section('title', 'New Receipt')
@section('content')
@include('user.billing.partials.invoice-estimate')
@php
    $emailsByClient = collect();
    $catalogJs = $catalog->map(fn($c) => [
        'id' => $c->id, 'name' => $c->name, 'amount_minor' => (int) $c->unit_price_minor,
        'tax_rule_id' => $c->tax_rule_id,
    ])->values()->toArray();
    $companyProfiles = $companies->map(fn($c) => ['id' => $c->id, 'is_default' => (bool) $c->is_default, 'rule' => $c->defaultTaxRule?->is_active ? $c->defaultTaxRule->only(['id','name','rate_bps','inclusive']) : null])->values();
    $lineProfiles = $taxRules->map(fn($rule) => $rule->only(['id','name','rate_bps','inclusive','billing_company_id']))->values();
@endphp
<div class="max-w-4xl mx-auto px-4 py-8"
     x-data="receiptForm(@js($catalogJs), @js($companyProfiles), @js($lineProfiles))">
    @include('user.billing.partials.company-actions')
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">New Receipt</h1>
            <p class="hero-subtitle">Record a payment already collected, no invoice or pay link needed.</p>
        </div>
        <a href="{{ route('user.client-invoices.dashboard') }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <form action="{{ route('user.client-invoices.receipts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Issuer &amp; recipient</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Billing company
                    <select name="billing_company_id" x-model="companyId" @change="lines.forEach(line => { line.tax_rule_id = ''; line.tax_profile = null; })" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">No company</option>
                        @foreach($companies as $co)<option value="{{ $co->id }}" @selected($co->is_default)>{{ $co->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Currency<input name="currency" maxlength="3" value="USD" class="block w-full mt-1 p-2 rounded-lg border uppercase" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Vault client
                    <select name="vault_client_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="0">None</option>
                        @foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Contact / lead
                    <select name="contact_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">None</option>
                        @foreach($contacts as $ct)<option value="{{ $ct->id }}">{{ $ct->nameForDisplay() }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Recipient email<input type="email" name="recipient_email" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Recipient name<input name="recipient_name" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="block md:col-span-2 text-xs" style="color: var(--text-muted);">Recipient address (optional)<textarea name="recipient_address" autocomplete="street-address" rows="2" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></textarea></label>
                <label class="text-xs" style="color: var(--text-muted);">Payment method
                    <select name="method" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="manual">Manual / other</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="card">Card</option>
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Reference (optional)<input name="reference" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Letterhead override (optional)</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Leave blank to use the billing company's default letterhead.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Letterhead image
                    <input type="file" name="letterhead" accept="image/png,image/jpeg,image/webp" class="block w-full mt-1 p-2 rounded-lg border text-xs" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Orientation
                    <select name="letterhead_orientation" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </label>
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold" style="color: var(--text-primary);">Line items</h2>
                <div class="flex items-center gap-2">
                    <select @change="addCatalog($event.target.value); $event.target.value=''" class="text-xs p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">+ From catalog</option>
                        <template x-for="ci in catalog" :key="ci.id"><option :value="ci.id" x-text="ci.name"></option></template>
                    </select>
                    <button type="button" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);" @click="addLine()"><i class="fas fa-plus mr-1"></i>Add line</button>
                </div>
            </div>
            <template x-for="(line, idx) in lines" :key="idx">
                <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                    <input :name="`line_items[${idx}][label]`" x-model="line.label" placeholder="Description" class="col-span-5 p-2 rounded border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="1" :name="`line_items[${idx}][quantity]`" x-model.number="line.quantity" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="0" :name="`line_items[${idx}][amount_minor]`" x-model.number="line.amount_minor" placeholder="Amount" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="0" :name="`line_items[${idx}][tax_rate_bps]`" x-model="line.tax_rate_bps" @input="line.tax_rule_id=''; line.tax_profile=null" placeholder="Manual tax bps" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <select :name="`line_items[${idx}][tax_rule_id]`" x-model="line.tax_rule_id" @change="line.tax_profile = lineProfiles.find(p => String(p.id) === String(line.tax_rule_id)) || null; line.tax_rate_bps = ''" class="col-span-12 p-2 rounded border text-sm" style="background:var(--bg-glass-input);color:var(--text-primary)" aria-label="Line tax profile">
                        <option value="">Company default / manual rate</option>
                        <template x-for="profile in lineProfiles.filter(p => !p.billing_company_id || String(p.billing_company_id) === String(companyId))" :key="profile.id"><option :value="profile.id" x-text="profile.name + ' (' + profile.rate_bps / 100 + '% ' + (profile.inclusive ? 'inclusive' : 'exclusive') + ')'"></option></template>
                    </select>
                    <button type="button" class="col-span-1 text-rose-600" @click="removeLine(idx)"><i class="fas fa-times"></i></button>
                </div>
            </template>
            <div class="mt-4 flex justify-end">
                <div class="w-64 text-sm space-y-1" style="color: var(--text-primary);">
                    <div class="flex justify-between"><span style="color: var(--text-muted);">Subtotal</span><span x-text="fmt(subtotal)"></span></div>
                    <div class="flex justify-between items-center"><span style="color: var(--text-muted);">Discount (minor)</span><input type="number" min="0" name="discount_minor" x-model.number="discount" class="w-24 p-1 rounded border text-right text-xs" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></div>
                    <div class="flex justify-between"><span style="color: var(--text-muted);">Tax</span><span x-text="fmt(taxTotal)"></span></div>
                    <div class="flex justify-between font-bold border-t pt-1" style="border-color: var(--border-soft);"><span>Total collected</span><span x-text="fmt(grandTotal)"></span></div>
                </div>
            </div>
        </section>

        <label class="block text-xs" style="color: var(--text-muted);">Notes<textarea name="notes_md" rows="2" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></textarea></label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.client-invoices.dashboard') }}" class="px-4 py-2 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);">Cancel</a>
            <button class="btn-primary"><i class="fas fa-receipt mr-2"></i>Create receipt</button>
        </div>
    </form>
</div>
<script>
function receiptForm(catalog, companyProfiles, lineProfiles) {
    return {
        catalog,
        companyProfiles, lineProfiles, companyId: String(companyProfiles.find(p => p.is_default)?.id || ''),
        discount: 0,
        lines: [{ label: '', amount_minor: 0, quantity: 1, tax_rate_bps: '', tax_rule_id: '', tax_profile: null }],
        addLine() { this.lines.push({ label: '', amount_minor: 0, quantity: 1, tax_rate_bps: '', tax_rule_id: '', tax_profile: null }); },
        removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
        addCatalog(id) {
            const ci = this.catalog.find(c => String(c.id) === String(id));
            if (!ci) return;
            this.lines.push({ label: ci.name, amount_minor: ci.amount_minor, quantity: 1, tax_rate_bps: '', tax_rule_id: ci.tax_rule_id || '', tax_profile: lineProfiles.find(p => String(p.id) === String(ci.tax_rule_id)) || null });
        },
        get calculation() { return billingEstimate(this.lines, this.discount, this.companyProfiles.find(p => String(p.id) === String(this.companyId))?.rule); },
        get subtotal() { return this.calculation.subtotal; },
        get taxTotal() { return this.calculation.tax; },
        get grandTotal() { return this.calculation.total; },
        fmt(m) { return (m / 100).toFixed(2); },
    };
}
</script>
@endsection
