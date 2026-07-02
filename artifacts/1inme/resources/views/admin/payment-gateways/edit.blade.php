@extends('admin.layouts.app')
@section('title', 'Configure '.$row->display_name)
@section('page-title', 'Configure '.$row->display_name)

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

    <a href="{{ route('admin.payment-gateways.index') }}" class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Payment Gateways
    </a>

    <div>
        <h1 class="text-2xl font-semibold text-white">{{ $row->display_name }}</h1>
        <p class="text-sm text-white/50 font-mono">{{ $row->gateway_slug }}</p>
    </div>

    {{-- Per-gateway setup help --}}
    @switch($row->gateway_slug)

        @case('razorpay')
            @include('admin.partials.help-note', [
                'body' => '<strong>Razorpay</strong> processes cards, UPI, net-banking, and wallets for Indian customers.
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>Key ID &amp; Key Secret</strong> — from <a class="underline" href="https://dashboard.razorpay.com/app/keys" target="_blank" rel="noopener">Dashboard → Settings → API Keys</a>. Generate a separate key pair for Test and Live modes.</li>
                        <li><strong>Webhook Secret</strong> — create a webhook at <a class="underline" href="https://dashboard.razorpay.com/app/webhooks" target="_blank" rel="noopener">Dashboard → Developer Controls → Webhooks</a>. Paste the webhook URL below into the "Webhook URL" field and copy the secret back here.</li>
                        <li>Switch to <em>Live</em> mode here once you have live API keys. Test and Live key pairs are separate.</li>
                    </ul>',
            ])
            @include('admin.partials.copy-uri', [
                'label' => 'Webhook URL — register this in Razorpay → Developer Controls → Webhooks',
                'value' => route('webhooks.handle', ['gateway' => 'razorpay']),
            ])
            @break

        @case('stripe')
            @include('admin.partials.help-note', [
                'body' => '<strong>Stripe</strong> processes cards and local payment methods globally.
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>Publishable Key</strong> — the <code>pk_test_…</code> / <code>pk_live_…</code> key from <a class="underline" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard → Developers → API Keys</a>. Not a secret — it\'s embedded in the checkout page.</li>
                        <li><strong>Secret Key</strong> — the <code>sk_test_…</code> / <code>sk_live_…</code> key from the same page. Keep this private; it can do anything on your Stripe account.</li>
                        <li><strong>Webhook Secret</strong> — add an endpoint at <a class="underline" href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe → Developers → Webhooks</a> pointing to the URL below. Stripe generates a <code>whsec_…</code> signing secret for that endpoint — paste it here.</li>
                        <li>Test and Live keys are separate. Set <em>Test</em> mode here while using test keys.</li>
                    </ul>',
            ])
            @include('admin.partials.copy-uri', [
                'label' => 'Webhook URL — register this in Stripe → Developers → Webhooks',
                'value' => route('webhooks.handle', ['gateway' => 'stripe']),
            ])
            @break

        @case('paypal')
            @include('admin.partials.help-note', [
                'body' => '<strong>PayPal</strong> processes cards and PayPal-balance payments globally.
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>Client ID &amp; Client Secret</strong> — create a REST API app at <a class="underline" href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener">PayPal Developer → My Apps &amp; Credentials</a>. Create one app for Sandbox and one for Live.</li>
                        <li><strong>Webhook ID</strong> — in the same app, add a webhook pointing to the URL below. PayPal shows a Webhook ID after creation — paste that here. The platform verifies PayPal\'s <code>PAYPAL-TRANSMISSION-SIG</code> header using this ID.</li>
                        <li>Toggle <em>Live</em> mode here only when using Live credentials. PayPal Sandbox credentials start with a different prefix and won\'t work in Live mode.</li>
                    </ul>',
            ])
            @include('admin.partials.copy-uri', [
                'label' => 'Webhook URL — register this in PayPal → My Apps & Credentials → Webhooks',
                'value' => route('webhooks.handle', ['gateway' => 'paypal']),
            ])
            @break

        @case('cashfree')
            @include('admin.partials.help-note', [
                'body' => '<strong>Cashfree Payments</strong> processes cards, UPI, and net-banking for Indian customers.
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>App ID &amp; Secret Key</strong> — from <a class="underline" href="https://merchant.cashfree.com/merchants/developers" target="_blank" rel="noopener">Cashfree Merchant Dashboard → Developers → API Keys</a>. Sandbox and Production keys are separate.</li>
                        <li><strong>Webhook Secret</strong> — add a webhook in <a class="underline" href="https://merchant.cashfree.com/merchants/pg/settings/webhook" target="_blank" rel="noopener">Dashboard → Payment Gateway → Webhooks</a>. Paste the URL below and copy the signing secret back here.</li>
                        <li>Use <em>Test</em> mode with Sandbox credentials; switch to <em>Live</em> with Production credentials.</li>
                    </ul>',
            ])
            @include('admin.partials.copy-uri', [
                'label' => 'Webhook URL — register this in Cashfree → Payment Gateway → Webhooks',
                'value' => route('webhooks.handle', ['gateway' => 'cashfree']),
            ])
            @break

        @case('payumoney')
            @include('admin.partials.help-note', [
                'body' => '<strong>PayUMoney</strong> processes cards, net-banking, UPI, and wallets (India).
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>Merchant Key &amp; Salt</strong> — from <a class="underline" href="https://onboarding.payu.in/app/account/api-keys" target="_blank" rel="noopener">PayU Dashboard → My Account → API Keys &amp; Salt</a>. Test and Production keys are separate.</li>
                        <li>PayU uses a browser-redirect payment flow — no separate webhook endpoint is required. Payment results are verified server-side via hash validation.</li>
                        <li>Use <em>Test</em> mode with test credentials; switch to <em>Live</em> once verified by PayU.</li>
                    </ul>',
            ])
            @break

        @case('offline')
            @include('admin.partials.help-note', [
                'type' => 'tip',
                'body' => '<strong>Manual / offline payment</strong> — no third-party service. Customers see your payment instructions (bank transfer details, UPI ID, etc.) at checkout and pay outside the platform. You activate their plan manually after confirming receipt.
                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                        <li><strong>Payee name</strong> — the account/business name customers should transfer to.</li>
                        <li><strong>Bank details</strong> — full instructions shown to the customer at checkout (account number, sort/IFSC code, UPI ID, etc.).</li>
                        <li><strong>Instructions</strong> — any extra notes, e.g. how to submit a payment reference.</li>
                    </ul>',
            ])
            @break

        @default
            @include('admin.partials.help-note', [
                'body' => 'Credentials are encrypted at rest and never displayed back. Leave a field blank to keep the stored value unchanged. Switch to <em>Live</em> mode only after replacing test credentials with production keys.',
            ])

    @endswitch

    @if($errors->any())
        <div class="rounded-xl bg-rose-500/10 border border-rose-400/30 p-3 text-sm text-rose-200">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payment-gateways.update', $row->gateway_slug) }}" class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-xs text-white/50 mb-1">Display name</label>
            <input type="text" name="display_name" value="{{ old('display_name', $row->display_name) }}" required class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-white/50 mb-1">Mode</label>
                <select name="mode" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
                    <option value="test" @selected($row->mode === 'test')>Test</option>
                    <option value="live" @selected($row->mode === 'live')>Live</option>
                </select>
                <p class="text-[11px] text-white/30 mt-1">Use <em>Test</em> with sandbox/test credentials; switch to <em>Live</em> only with production keys.</p>
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Sort order</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $row->sort_order) }}" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="is_enabled" value="1" @checked($row->is_enabled) class="accent-blue-500">
            Enable this gateway on the checkout page
        </label>

        <div class="pt-3 border-t border-white/10 space-y-3">
            <p class="text-xs text-white/50">Credentials are encrypted at rest. Leave a field blank to keep the stored value unchanged. Stored values are never shown here.</p>
            @foreach($fields as $f)
                @php
                    $isSecret = !in_array($f, ['payee_name','bank_details','upi_id','instructions']);
                    $stored = $row->credential($f);
                    $placeholder = $stored ? '•••• configured ••••' : '';
                    $isLong = in_array($f, ['bank_details','instructions']);
                    $fieldHints = [
                        'key_id'          => 'Razorpay Key ID (starts with rzp_test_ or rzp_live_). Not a secret.',
                        'key_secret'      => 'Razorpay Key Secret — keep private.',
                        'webhook_secret'  => 'Signing secret provided by the gateway when you register the webhook URL above.',
                        'publishable_key' => 'Stripe Publishable Key (pk_test_… / pk_live_…). Safe to display in checkout pages.',
                        'secret_key'      => 'Stripe Secret Key (sk_test_… / sk_live_…) — keep private.',
                        'client_id'       => 'OAuth Client ID / App ID from the provider\'s developer console.',
                        'client_secret'   => 'OAuth Client Secret — keep private.',
                        'webhook_id'      => 'PayPal Webhook ID (not the secret) — shown in the dashboard after creating the webhook endpoint.',
                        'app_id'          => 'Cashfree App ID from Developers → API Keys.',
                        'secret_key'      => 'Secret Key from the provider\'s developer console — keep private.',
                        'merchant_key'    => 'PayU Merchant Key — from PayU Dashboard → API Keys.',
                        'salt'            => 'PayU Salt — used to sign payment hashes. Keep private.',
                        'payee_name'      => 'Name of the bank account or UPI holder customers should transfer money to.',
                        'bank_details'    => 'Full bank / UPI transfer instructions shown to the customer at checkout.',
                        'instructions'    => 'Any additional guidance, e.g. how to submit a payment reference after transferring.',
                    ];
                    $hint = $fieldHints[$f] ?? null;
                @endphp
                <div>
                    <label class="block text-xs text-white/50 mb-1">{{ ucwords(str_replace('_',' ', $f)) }}</label>
                    @if($isLong)
                        <textarea name="credentials[{{ $f }}]" rows="3" placeholder="{{ $placeholder }}" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white font-mono text-xs">{{ old("credentials.$f") }}</textarea>
                    @else
                        <input type="{{ $isSecret ? 'password' : 'text' }}" name="credentials[{{ $f }}]" value="{{ old("credentials.$f") }}" placeholder="{{ $placeholder }}" autocomplete="off" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white font-mono text-xs">
                    @endif
                    @if($hint)
                        <p class="text-[11px] text-white/30 mt-0.5">{{ $hint }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">Save</button>
            <a href="{{ route('admin.payment-gateways.index') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white/70 rounded-xl">Cancel</a>
        </div>
    </form>
</div>
@endsection
