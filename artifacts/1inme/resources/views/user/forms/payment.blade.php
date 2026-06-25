@extends('user.layouts.app')
@section('title', 'Payments · ' . $form->title)

@section('content')
<div class="max-w-3xl mx-auto" x-data="{ paidEnabled: {{ ($payment['enabled'] ?? false) ? 'true' : 'false' }}, payMode: '{{ ($payment['mode'] ?? 'fixed') === 'per_field' ? 'per_field' : 'fixed' }}' }">
    @include('user.partials.page-hero', [
        'title' => 'Payments',
        'subtitle' => 'Charge customers to submit this form. Funds go straight to your connected payment gateway — Sayzio takes 0%.',
        'icon' => 'fa-credit-card',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #f87171;">
        <i class="fas fa-triangle-exclamation mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @unless($canPaidForms)
        {{-- Plan gate (Pro and above) --}}
        <div class="card-premium p-6 mb-6" style="background: rgba(92,131,255,0.06); border: 1px solid rgba(92,131,255,0.25);">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(92,131,255,0.15);">
                    <i class="fas fa-crown text-blue-400"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Paid forms are a Pro feature</h3>
                    <p class="text-[12px] mt-1" style="color: var(--text-muted);">Upgrade to a Pro plan or above to charge customers to submit this form and unlock advanced form analytics.</p>
                    <a href="{{ route('user.upgrade') }}" class="btn-primary inline-flex items-center gap-2 mt-3 text-xs">
                        <i class="fas fa-arrow-up"></i> See upgrade options
                    </a>
                </div>
            </div>
        </div>
    @endunless

    @unless($hasGateway)
        <div class="mb-6 px-4 py-3 rounded-xl text-sm flex items-start gap-2" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
            <i class="fas fa-plug mt-0.5"></i>
            <span>
                Connect a payment gateway before you can charge for this form — funds are paid straight to your own gateway.
                <a href="{{ route('user.payouts') }}" class="underline font-semibold">Set up payouts →</a>
            </span>
        </div>
    @endunless

    <form method="POST" action="{{ route('user.forms.payment.update', $form) }}" class="space-y-6 {{ $canPaidForms ? '' : 'opacity-60 pointer-events-none' }}">
        @csrf @method('PUT')

        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.12);">
                        <i class="fas fa-money-bill-wave text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Require payment to submit</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Visitors are sent to checkout after filling the form; the submission is recorded once payment clears.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" class="sr-only peer" x-model="paidEnabled">
                    <div class="w-11 h-6 rounded-full peer-checked:bg-emerald-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"></div>
                    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>

            <div x-show="paidEnabled" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-4 ml-13" style="margin-left: 3.25rem;">
                {{-- Pricing mode --}}
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Pricing mode</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2 p-3 rounded-xl cursor-pointer" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="payMode === 'fixed' ? 'border-color: rgb(16,185,129);' : ''">
                            <input type="radio" name="mode" value="fixed" x-model="payMode" class="mt-0.5 text-emerald-500">
                            <span>
                                <span class="block text-xs font-semibold" style="color: var(--text-primary);">Fixed price</span>
                                <span class="block text-[11px] mt-0.5" style="color: var(--text-faint);">One price for the whole form.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 p-3 rounded-xl cursor-pointer" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="payMode === 'per_field' ? 'border-color: rgb(16,185,129);' : ''">
                            <input type="radio" name="mode" value="per_field" x-model="payMode" class="mt-0.5 text-emerald-500">
                            <span>
                                <span class="block text-xs font-semibold" style="color: var(--text-primary);">Variable (per field)</span>
                                <span class="block text-[11px] mt-0.5" style="color: var(--text-faint);">Total is built from priced fields &amp; options.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                            <span x-show="payMode === 'fixed'">Price</span>
                            <span x-show="payMode === 'per_field'" x-cloak>Base fee <span class="text-[10px]" style="color: var(--text-faint);">— optional</span></span>
                        </label>
                        <input type="number" name="amount" step="0.01" min="0" value="{{ old('amount', number_format(($payment['amount_cents'] ?? 0) / 100, 2, '.', '')) }}" placeholder="9.99" class="theme-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Currency</label>
                        <input type="text" name="currency" maxlength="3" value="{{ old('currency', strtoupper($payment['currency'] ?? 'USD')) }}" placeholder="USD" class="theme-input w-full uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button label <span class="text-[10px]" style="color: var(--text-faint);">— optional</span></label>
                        <input type="text" name="label" maxlength="60" value="{{ old('label', $payment['label'] ?? '') }}" placeholder="Pay &amp; submit" class="theme-input w-full">
                    </div>
                </div>

                <div class="text-[11px] flex items-start gap-1.5" style="color: var(--text-faint);">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <span x-show="payMode === 'fixed'">A single fixed price applies to the whole form.</span>
                    <span x-show="payMode === 'per_field'" x-cloak>Attach prices to fields and options in the <a href="{{ route('user.forms.builder', $form) }}" class="underline font-semibold">form builder</a>. The visitor's total is the base fee plus everything they select.</span>
                </div>
            </div>
        </div>

        @if($canPaidForms)
        <div class="flex justify-end">
            <button type="submit" class="btn-primary inline-flex items-center gap-2">
                <i class="fas fa-save"></i> Save payment settings
            </button>
        </div>
        @endif
    </form>
</div>
@endsection
