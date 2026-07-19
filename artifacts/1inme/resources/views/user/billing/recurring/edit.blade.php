@extends('user.layouts.app')
@section('title', $template->exists ? 'Edit Recurring Invoice' : 'New Recurring Invoice')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8"
     x-data="recurringForm(@js($template->exists ? (array) $template->line_items : [['label' => '', 'amount_minor' => 0, 'quantity' => 1, 'tax_rate_bps' => 0]]))">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">{{ $template->exists ? 'Edit Recurring Invoice' : 'New Recurring Invoice' }}</h1>
            <p class="hero-subtitle">Auto-generate invoices on a fixed schedule.</p>
        </div>
        <a href="{{ route('user.billing.recurring.index') }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <form action="{{ $template->exists ? route('user.billing.recurring.update', $template) : route('user.billing.recurring.store') }}" method="POST" class="space-y-6">
        @csrf
        @if($template->exists)@method('PUT')@endif

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Title<input name="title" value="{{ old('title', $template->title) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Billing company
                    <select name="billing_company_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">None</option>
                        @foreach($companies as $co)<option value="{{ $co->id }}" @selected(old('billing_company_id', $template->billing_company_id) == $co->id)>{{ $co->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Vault client
                    <select name="vault_client_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">None</option>
                        @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('vault_client_id', $template->vault_client_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Recipient email<input type="email" name="recipient_email" value="{{ old('recipient_email', $template->recipient_email) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Currency<input name="currency" maxlength="3" value="{{ old('currency', $template->currency ?: 'USD') }}" class="block w-full mt-1 p-2 rounded-lg border uppercase" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Discount (minor)<input type="number" min="0" name="discount_minor" value="{{ old('discount_minor', $template->discount_minor ?? 0) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Schedule</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">Interval
                    <select name="interval" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        @foreach(['weekly','monthly','quarterly','yearly'] as $iv)
                            <option value="{{ $iv }}" @selected(old('interval', $template->interval) === $iv)>{{ ucfirst($iv) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Every N<input type="number" min="1" name="interval_count" value="{{ old('interval_count', $template->interval_count ?: 1) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Start date<input type="date" name="start_date" value="{{ old('start_date', \Illuminate\Support\Carbon::parse($template->start_date ?? now())->toDateString()) }}" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">End date<input type="date" name="end_date" value="{{ old('end_date', $template->end_date ? \Illuminate\Support\Carbon::parse($template->end_date)->toDateString() : '') }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Max occurrences<input type="number" min="1" name="max_occurrences" value="{{ old('max_occurrences', $template->max_occurrences) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Status
                    <select name="status" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        @foreach(['active','paused','cancelled','completed'] as $st)
                            <option value="{{ $st }}" @selected(old('status', $template->status ?: 'active') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="flex items-center gap-2 mt-3 text-sm" style="color: var(--text-primary);"><input type="checkbox" name="auto_send" value="1" @checked(old('auto_send', $template->auto_send)) > Automatically email each generated invoice</label>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold" style="color: var(--text-primary);">Line items</h2>
                <button type="button" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);" @click="addLine()"><i class="fas fa-plus mr-1"></i>Add line</button>
            </div>
            <template x-for="(line, idx) in lines" :key="idx">
                <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                    <input :name="`line_items[${idx}][label]`" x-model="line.label" placeholder="Description" class="col-span-5 p-2 rounded border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="1" :name="`line_items[${idx}][quantity]`" x-model.number="line.quantity" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="0" :name="`line_items[${idx}][amount_minor]`" x-model.number="line.amount_minor" placeholder="Amount" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <input type="number" min="0" :name="`line_items[${idx}][tax_rate_bps]`" x-model.number="line.tax_rate_bps" placeholder="Tax bps" class="col-span-2 p-2 rounded border text-sm text-right" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <button type="button" class="col-span-1 text-rose-600" @click="removeLine(idx)"><i class="fas fa-times"></i></button>
                </div>
            </template>
        </section>

        <label class="block text-xs" style="color: var(--text-muted);">Notes<textarea name="notes_md" rows="2" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">{{ old('notes_md', $template->notes_md) }}</textarea></label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.billing.recurring.index') }}" class="px-4 py-2 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);">Cancel</a>
            <button class="btn-primary"><i class="fas fa-save mr-2"></i>{{ $template->exists ? 'Save changes' : 'Create template' }}</button>
        </div>
    </form>
</div>
<script>
function recurringForm(initial) {
    return {
        lines: initial.length ? initial.map(l => ({ label: l.label || '', amount_minor: l.amount_minor || 0, quantity: l.quantity || 1, tax_rate_bps: l.tax_rate_bps || 0 })) : [{ label: '', amount_minor: 0, quantity: 1, tax_rate_bps: 0 }],
        addLine() { this.lines.push({ label: '', amount_minor: 0, quantity: 1, tax_rate_bps: 0 }); },
        removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
    };
}
</script>
@endsection
