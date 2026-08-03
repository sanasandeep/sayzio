@extends('user.layouts.settings')
@section('title', $company->exists ? 'Edit Company' : 'New Company')
@section('settings-content')
<div class="max-w-3xl">
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

        @php
            $logoPolicy = \App\Services\UploadPolicy::for('billing.logo', auth()->user());
            $logoUrl = ($company->exists && $company->logo_path) ? asset('storage/' . $company->logo_path) : null;
            $letterheadPolicy = \App\Services\UploadPolicy::for('billing.letterhead', auth()->user());
            $letterheadUrl = ($company->exists && $company->letterhead_path) ? asset('storage/' . $company->letterhead_path) : null;
        @endphp

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Logo</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Shown on this company's invoice &amp; receipt PDFs. PNG, JPG, GIF, WEBP or SVG, up to 2&nbsp;MB.</p>
            @include('user.partials.dropzone-input', [
                'name'        => 'logo',
                'policy'      => $logoPolicy,
                'currentUrl'  => $logoUrl,
                'currentName' => $company->logo_path ? basename($company->logo_path) : null,
                'label'       => null,
                'previewKind' => 'image',
                'compact'     => true,
            ])
            @if($company->exists && $company->logo_path)
                <label class="flex items-center gap-2 mt-2 text-[11px]" style="color: var(--text-muted);">
                    <input type="hidden" name="remove_logo" value="0">
                    <input type="checkbox" name="remove_logo" value="1" class="accent-rose-500">
                    Remove the current logo
                </label>
            @endif
        </section>

        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-1" style="color: var(--text-primary);">Letterhead</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">Full-page background used on this company's invoice &amp; receipt PDFs (unless a specific invoice overrides it). JPG, PNG or WEBP, up to 5&nbsp;MB, between 400&times;400 and 6000&times;6000&nbsp;px, matching the chosen orientation.</p>
            @include('user.partials.dropzone-input', [
                'name'        => 'letterhead',
                'policy'      => $letterheadPolicy,
                'currentUrl'  => $letterheadUrl,
                'currentName' => $company->letterhead_path ? basename($company->letterhead_path) : null,
                'label'       => null,
                'previewKind' => 'image',
                'compact'     => true,
            ])
            @if($company->exists && $company->letterhead_path)
                <label class="flex items-center gap-2 mt-2 text-[11px]" style="color: var(--text-muted);">
                    <input type="hidden" name="remove_letterhead" value="0">
                    <input type="checkbox" name="remove_letterhead" value="1" class="accent-rose-500">
                    Remove the current letterhead
                </label>
            @endif
            <div class="mt-3 space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs" style="color: var(--text-muted);">Orientation
                        <select name="letterhead_orientation" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                            <option value="portrait" @selected(old('letterhead_orientation', $company->letterhead_orientation ?: 'portrait') === 'portrait')>Portrait</option>
                            <option value="landscape" @selected(old('letterhead_orientation', $company->letterhead_orientation ?: 'portrait') === 'landscape')>Landscape</option>
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    @foreach(['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'] as $side => $label)
                        <label class="text-xs" style="color: var(--text-muted);">{{ $label }} margin (mm)
                            <input type="number" min="0" max="60" name="letterhead_margin_{{ $side }}" value="{{ old('letterhead_margin_' . $side, $company->{'letterhead_margin_' . $side} ?? 0) }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        @php
            $companyCountryInit = strtoupper((string) old('country',     $company->country ?? ''));
            $companyCityInit    = (string) old('city',        $company->city        ?? '');
            $companyStateInit   = (string) old('state',       $company->state       ?? '');
            $companyPostalInit  = (string) old('postal_code', $company->postal_code ?? '');
        @endphp
        <section class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);"
                 @country-picked="onCountryInput($event.detail)"
                 x-data="{
                     country: @js($companyCountryInit),
                     cityVal: @js($companyCityInit),
                     stateVal: @js($companyStateInit),
                     cityEdited: false,
                     stateEdited: false,
                     lookupTimer: null,
                     lookupUrl: @js(route('user.profile.postal.lookup')),
                     onCountryInput(val) {
                         this.country = val;
                         this.scheduleLookup();
                     },
                     scheduleLookup() {
                         clearTimeout(this.lookupTimer);
                         this.lookupTimer = setTimeout(() => this.doLookup(), 600);
                     },
                     async doLookup() {
                         const country = this.country.trim();
                         const postalEl = this.$el.querySelector('[name=postal_code]');
                         const postal = postalEl ? postalEl.value.trim() : '';
                         if (country.length !== 2 || !postal) return;
                         try {
                             const r = await fetch(this.lookupUrl + '?country=' + encodeURIComponent(country) + '&postal_code=' + encodeURIComponent(postal), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                             if (!r.ok) return;
                             const d = await r.json();
                             if (d.city && !this.cityEdited) this.cityVal = d.city;
                             if (!this.stateEdited && (d.region || d.region_code)) this.stateVal = d.region || d.region_code;
                         } catch (e) {}
                     }
                 }">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Address</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {!! $field('address_line1', 'Address line 1') !!}
                {!! $field('address_line2', 'Address line 2') !!}
                <label class="text-xs" style="color: var(--text-muted);">Country
                    <div class="mt-1">
                        @include('common.partials.country-select', [
                            'csName'        => 'country',
                            'csValue'       => $companyCountryInit,
                            'csId'          => 'company-country',
                            'csPlaceholder' => 'Select country',
                        ])
                    </div>
                </label>
                <label class="text-xs" style="color: var(--text-muted);">Postal code
                    <input type="text" name="postal_code" value="{{ $companyPostalInit }}" maxlength="32"
                           x-on:input="scheduleLookup()" x-on:blur="doLookup()"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">City
                    <input type="text" name="city" x-model="cityVal" x-on:input="cityEdited = true" maxlength="120"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
                <label class="text-xs" style="color: var(--text-muted);">State / Region
                    <input type="text" name="state" x-model="stateVal" x-on:input="stateEdited = true" maxlength="120"
                           class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                </label>
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
                        <option value="">None</option>
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

            @if(!empty($smtpWarning))
                @php $isDanger = ($smtpWarning['level'] ?? 'warning') === 'danger'; @endphp
                <div class="mt-3 p-3 rounded-lg border flex items-start gap-2 {{ $isDanger ? 'bg-rose-50 border-rose-200' : 'bg-amber-50 border-amber-200' }}">
                    <i class="fas {{ $isDanger ? 'fa-circle-exclamation text-rose-600' : 'fa-triangle-exclamation text-amber-600' }} mt-0.5"></i>
                    <div class="text-xs {{ $isDanger ? 'text-rose-800' : 'text-amber-800' }}">
                        <p class="font-semibold">{{ $smtpWarning['title'] }}</p>
                        <p class="mt-0.5">{{ $smtpWarning['body'] }}</p>
                        @if(!empty($smtpWarning['link']))
                            <a href="{{ $smtpWarning['link']['url'] }}" class="inline-flex items-center gap-1 mt-2 font-semibold underline {{ $isDanger ? 'text-rose-900 hover:text-rose-700' : 'text-amber-900 hover:text-amber-700' }}">
                                <i class="fas fa-file-invoice"></i>{{ $smtpWarning['link']['label'] }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Task #6632 — reuse a saved email connection instead of retyping
                 SMTP details per company. Picking one copies its settings into
                 the per-company fields on save (they stay the source of truth,
                 so the fully-configured-only fallback rule keeps working). --}}
            <div class="mt-3 p-3 rounded-lg border" style="border-color: var(--border-soft); background: var(--bg-glass-input);">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);"><i class="fas fa-plug mr-1" style="color: #818cf8;"></i>Fill from a saved connection</span>
                    <a href="{{ route('user.email-connections.index') }}" class="text-[11px] underline" style="color: var(--accent);">Manage connections</a>
                </div>
                @include('common.partials.integration-picker', [
                    'name'       => 'smtp_connection_id',
                    'kind'       => 'email',
                    'value'      => old('smtp_connection_id'),
                    'providers'  => ['smtp', 'sendgrid'],
                    'emptyLabel' => 'Keep the settings below',
                ])
                @error('smtp_connection_id') <p class="text-[11px] mt-1 text-rose-500">{{ $message }}</p> @enderror
                <p class="text-[11px] mt-1" style="color: var(--text-muted);">On save, the selected connection's host, credentials, and from-address replace the fields below and enable SMTP for this company.</p>
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
                    @include('common.partials.password-field', [
                        'name' => 'smtp_password',
                        'autocomplete' => 'new-password',
                        'placeholder' => $smtp->hasPassword() ? 'Paste a new password to replace' : '••••••••',
                        'wrapClass' => 'mt-1',
                        'inputClass' => 'block w-full p-2 rounded-lg border',
                        'inputStyle' => 'background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);',
                    ])
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
            @php($allowedTestRecipients = $company->allowedTestRecipients(auth()->user()))
            <div class="flex flex-col sm:flex-row gap-3">
                <form action="{{ route('user.billing.companies.smtp.verify', $company) }}" method="POST">
                    @csrf
                    <button class="px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">
                        <i class="fas fa-plug mr-1"></i>Verify connection
                    </button>
                </form>
                <form action="{{ route('user.billing.companies.smtp.test', $company) }}" method="POST" class="flex gap-2 flex-1">
                    @csrf
                    <input type="email" name="test_email" required
                           value="{{ old('test_email', $allowedTestRecipients[0] ?? '') }}"
                           list="smtp-test-recipients"
                           placeholder="{{ $allowedTestRecipients[0] ?? 'you@example.com' }}"
                           class="flex-1 p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    @if(!empty($allowedTestRecipients))
                        <datalist id="smtp-test-recipients">
                            @foreach($allowedTestRecipients as $recipient)
                                <option value="{{ $recipient }}"></option>
                            @endforeach
                        </datalist>
                    @endif
                    <button class="px-3 py-2 rounded-lg border text-sm" style="border-color: var(--border-soft); color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-1"></i>Send test
                    </button>
                </form>
            </div>
            <p class="text-[11px] mt-2" style="color: var(--text-muted);">
                <i class="fas fa-shield-alt mr-1"></i>To prevent abuse, test emails can only be sent to your own account email, this company's contact email, or its sender (from) address.
            </p>
            @error('test_email')
                <p class="text-[11px] mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
            @enderror
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
