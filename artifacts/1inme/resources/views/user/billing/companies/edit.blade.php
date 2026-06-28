@extends('user.layouts.app')
@section('title', $company->exists ? 'Edit Company' : 'New Company')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">{{ $company->exists ? 'Edit Company' : 'New Company' }}</h1>
            <p class="hero-subtitle">Business identity shown on invoices and receipts.</p>
        </div>
        <a href="{{ route('user.billing.companies.index') }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <form action="{{ $company->exists ? route('user.billing.companies.update', $company) : route('user.billing.companies.store') }}" method="POST" class="space-y-6">
        @csrf
        @if($company->exists)@method('PUT')@endif

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Identity</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @php
                    $field = function ($name, $label, $type = 'text', $value = null) use ($company) {
                        $val = old($name, $value ?? $company->{$name});
                        return '<label class="text-xs" style="color: var(--text-muted);">' . e($label)
                            . '<input type="' . $type . '" name="' . $name . '" value="' . e($val) . '" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>';
                    };
                @endphp
                {!! $field('name', 'Display name *') !!}
                {!! $field('legal_name', 'Legal name') !!}
                {!! $field('email', 'Email', 'email') !!}
                {!! $field('phone', 'Phone') !!}
                {!! $field('website', 'Website') !!}
                <label class="text-xs" style="color: var(--text-muted);">Default currency
                    <input name="default_currency" maxlength="3" value="{{ old('default_currency', $company->default_currency ?: 'USD') }}" class="block w-full mt-1 p-2 rounded-lg border uppercase" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Address</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {!! $field('address_line1', 'Address line 1') !!}
                {!! $field('address_line2', 'Address line 2') !!}
                {!! $field('city', 'City') !!}
                {!! $field('state', 'State / Region') !!}
                {!! $field('postal_code', 'Postal code') !!}
                {!! $field('country', 'Country (2-letter)') !!}
            </div>
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Tax &amp; numbering</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {!! $field('tax_id_label', 'Tax ID label (e.g. VAT)') !!}
                {!! $field('tax_id_value', 'Tax ID value') !!}
                {!! $field('secondary_tax_label', 'Secondary tax label') !!}
                {!! $field('secondary_tax_value', 'Secondary tax value') !!}
                {!! $field('invoice_prefix', 'Invoice number prefix') !!}
                <label class="text-xs" style="color: var(--text-muted);">Default tax rule
                    <select name="default_tax_rule_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">— None —</option>
                        @foreach($taxRules as $rule)
                            <option value="{{ $rule->id }}" @selected(old('default_tax_rule_id', $company->default_tax_rule_id) == $rule->id)>{{ $rule->name }} ({{ number_format($rule->rate_bps / 100, 2) }}%)</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="block text-xs mt-3" style="color: var(--text-muted);">Notes (shown on documents)
                <textarea name="notes" rows="2" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">{{ old('notes', $company->notes) }}</textarea>
            </label>
            <label class="flex items-center gap-2 mt-3 text-sm" style="color: var(--text-primary);">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $company->is_default))>
                Make this my default billing company
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.billing.companies.index') }}" class="px-4 py-2 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);">Cancel</a>
            <button class="btn-primary"><i class="fas fa-save mr-2"></i>{{ $company->exists ? 'Save changes' : 'Create company' }}</button>
        </div>
    </form>
</div>
@endsection
