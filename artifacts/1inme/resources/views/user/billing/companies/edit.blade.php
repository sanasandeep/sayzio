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

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <form action="{{ $company->exists ? route('user.billing.companies.update', $company) : route('user.billing.companies.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
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
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Logo</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Shown on this company's invoice &amp; receipt PDFs. PNG, JPG, GIF, WEBP or SVG, up to 2&nbsp;MB.</p>
            <div class="flex items-center gap-4">
                @if($company->exists && $company->logo_path)
                    <img src="{{ asset('storage/' . $company->logo_path) }}" alt="Company logo"
                         class="w-16 h-16 rounded-xl object-contain bg-white p-1" style="border: 1px solid var(--border-soft);">
                @endif
                <div class="flex-1">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                           class="block w-full text-xs" style="color: var(--text-primary);">
                    @if($company->exists && $company->logo_path)
                        <label class="flex items-center gap-2 mt-2 text-[11px]" style="color: var(--text-muted);">
                            <input type="hidden" name="remove_logo" value="0">
                            <input type="checkbox" name="remove_logo" value="1" class="accent-rose-500">
                            Remove the current logo
                        </label>
                    @endif
                </div>
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

        @php
            $smtp = \App\Services\Billing\CompanyMailSettings::for($company);
            $curEnc = old('smtp_encryption', $company->smtp_encryption ?: 'tls');
        @endphp
        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);"
                 x-data="{ enabled: {{ old('smtp_enabled', $company->smtp_enabled) ? 'true' : 'false' }} }">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div>
                    <h2 class="font-bold" style="color: var(--text-primary);">Client email delivery (SMTP)</h2>
                    <p class="text-xs" style="color: var(--text-muted);">
                        Send this company's client-facing invoices &amp; receipts from your own mail server.
                        When off, they go out through the platform's email service.
                    </p>
                </div>
                @if($company->exists && $company->smtp_verified_at)
                    <span class="shrink-0 text-[11px] px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700" title="Last verified {{ $company->smtp_verified_at->toDayDateTimeString() }}">
                        <i class="fas fa-check-circle mr-1"></i>Verified {{ $company->smtp_verified_at->diffForHumans() }}
                    </span>
                @endif
            </div>

            <label class="flex items-center gap-2 mt-3 mb-1 text-sm" style="color: var(--text-primary);">
                <input type="hidden" name="smtp_enabled" value="0">
                <input type="checkbox" name="smtp_enabled" value="1" x-model="enabled" @checked(old('smtp_enabled', $company->smtp_enabled))>
                Use my own SMTP server for this company's client emails
            </label>

            <div x-show="enabled" x-cloak class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="text-xs" style="color: var(--text-muted);">SMTP host
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $company->smtp_host) }}" autocomplete="off" placeholder="smtp.mailgun.org"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">SMTP port
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $company->smtp_port) }}" min="1" max="65535" placeholder="587" autocomplete="off"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Encryption
                    <select name="smtp_encryption" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="tls" @selected($curEnc === 'tls')>TLS / STARTTLS (587)</option>
                        <option value="ssl" @selected($curEnc === 'ssl')>SSL (465)</option>
                        <option value="none" @selected($curEnc === 'none')>None</option>
                    </select>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Username
                    <input type="text" name="smtp_username" value="{{ old('smtp_username', $company->smtp_username) }}" autocomplete="off" placeholder="postmaster@mg.example.com"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs md:col-span-2" style="color: var(--text-muted);">Password
                    @if($smtp->hasPassword())
                        <span class="block mt-1 mb-1 text-[11px]" style="color: var(--text-muted);">Stored: <span class="font-mono text-amber-600">{{ $smtp->maskedPassword() }}</span></span>
                    @endif
                    <input type="password" name="smtp_password" autocomplete="new-password" placeholder="{{ $smtp->hasPassword() ? 'Paste a new password to replace' : '••••••••' }}"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    @if($smtp->hasPassword())
                        <span class="flex items-center gap-2 mt-2 text-[11px]" style="color: var(--text-muted);">
                            <input type="hidden" name="smtp_clear_password" value="0">
                            <input type="checkbox" name="smtp_clear_password" value="1" class="accent-rose-500">
                            Remove the stored password
                        </span>
                    @endif
                    <span class="block mt-1 text-[11px]" style="color: var(--text-muted);">Encrypted at rest and never shown again. Leave blank to keep the stored value.</span>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">From address
                    <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $company->smtp_from_address) }}" autocomplete="off" placeholder="{{ $company->email ?: 'billing@yourdomain.com' }}"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">From name
                    <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $company->smtp_from_name) }}" autocomplete="off" placeholder="{{ $company->name }}"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.billing.companies.index') }}" class="px-4 py-2 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);">Cancel</a>
            <button class="btn-primary"><i class="fas fa-save mr-2"></i>{{ $company->exists ? 'Save changes' : 'Create company' }}</button>
        </div>
    </form>

    @if($company->exists)
        <section class="mt-6 p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Verify &amp; test SMTP</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Save your changes first, then check the connection or send yourself a test message.</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <form action="{{ route('user.billing.companies.smtp.verify', $company) }}" method="POST">
                    @csrf
                    <button class="px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">
                        <i class="fas fa-plug mr-1"></i>Verify connection
                    </button>
                </form>
                <form action="{{ route('user.billing.companies.smtp.test', $company) }}" method="POST" class="flex gap-2 flex-1">
                    @csrf
                    <input type="email" name="test_email" required placeholder="you@example.com"
                           class="flex-1 p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <button class="px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-1"></i>Send test
                    </button>
                </form>
            </div>
        </section>

        <section class="mt-6 p-4 rounded-xl border flex items-center justify-between gap-3" style="border-color: var(--border-soft); background: var(--bg-card);">
            <div>
                <h2 class="font-bold" style="color: var(--text-primary);">Client email templates</h2>
                <p class="text-xs" style="color: var(--text-muted);">Customise the subject &amp; body of the invoice and receipt emails sent to this company's clients.</p>
            </div>
            <a href="{{ route('user.billing.companies.emails.index', $company) }}" class="shrink-0 px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">
                <i class="fas fa-envelope-open-text mr-1"></i>Edit templates
            </a>
        </section>
    @endif
</div>
@endsection
