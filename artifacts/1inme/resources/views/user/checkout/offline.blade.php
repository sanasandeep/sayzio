@extends('user.layouts.app')
@section('title', 'Manual payment instructions')
@section('content')
<div class="max-w-2xl mx-auto p-6 space-y-4">
    <h1 class="text-2xl font-semibold text-white">Thanks, your order is pending approval</h1>
    <p class="text-white/60 text-sm">Your invoice <span class="font-mono text-white">{{ $invoice->number }}</span> has been created. Pay the amount below through bank transfer / UPI and your plan will be activated within one business day.</p>

    @if(session('success'))
        <div class="px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 text-sm text-white/80 space-y-2">
        <div class="flex justify-between"><span class="text-white/50">Amount</span><span class="font-mono text-white">{{ number_format($invoice->grand_total_minor/100, 2) }} {{ $invoice->currency }}</span></div>
        @if($payee_name)<div class="flex justify-between"><span class="text-white/50">Payee</span><span class="text-white">{{ $payee_name }}</span></div>@endif
        @if($bank_details)
            <div class="pt-2 border-t border-white/10"><div class="text-white/50 mb-1">Bank details</div><pre class="whitespace-pre-wrap text-white/80 text-xs">{{ $bank_details }}</pre></div>
        @endif
        @if($upi_id)<div class="flex justify-between"><span class="text-white/50">UPI ID</span><span class="font-mono text-white">{{ $upi_id }}</span></div>@endif
    </div>

    @if(!empty($upi_link))
        <div class="rounded-2xl border border-indigo-400/20 bg-indigo-500/[0.04] p-5 space-y-4">
            <div>
                <div class="text-white font-medium">Pay instantly with UPI</div>
                <div class="text-xs text-white/50">Tap to open GPay / PhonePe / Paytm on your phone, or scan the QR from another device. The payee and amount are filled in for you.</div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                {{-- Scannable QR (rendered by the QR Studio engine) --}}
                <div class="shrink-0 mx-auto sm:mx-0">
                    <div id="upi-qr" data-upi="{{ $upi_link }}"
                         class="w-44 h-44 rounded-xl bg-white p-2 flex items-center justify-center text-[11px] text-slate-400">
                        Generating QR…
                    </div>
                </div>

                <div class="flex-1 space-y-3">
                    <a href="{{ $upi_link }}"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium w-full sm:w-auto">
                        <i class="fa-solid fa-mobile-screen-button"></i> Pay with UPI app
                    </a>
                    <div class="text-xs text-white/50">
                        Paying <span class="font-mono text-white/80">{{ $upi_amount }} {{ $invoice->currency }}</span>
                        to <span class="font-mono text-white/80">{{ $upi_id }}</span>.
                        After paying, enter your transaction reference below so we can match it faster.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 text-sm text-white/70 whitespace-pre-wrap">{{ $instructions }}</div>

    {{-- Buyer-submitted UPI transaction reference / UTR (optional, best-effort) --}}
    <form method="POST" action="{{ route('user.checkout.offline.reference', $invoice) }}"
          class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 space-y-3">
        @csrf
        <label for="upi_reference" class="block text-white font-medium text-sm">UPI transaction reference / UTR <span class="text-white/40 font-normal">(optional)</span></label>
        <p class="text-xs text-white/50">If you've already paid, enter the reference / UTR number from your UPI app. This helps us confirm your payment faster. It's optional, we'll still verify manually.</p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" id="upi_reference" name="upi_reference" maxlength="190"
                   value="{{ old('upi_reference', $buyer_reference) }}"
                   placeholder="e.g. 412345678901"
                   class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-sm font-mono placeholder:text-white/30 focus:outline-none focus:border-indigo-400/50">
            <button type="submit" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-sm font-medium">Submit reference</button>
        </div>
        @error('upi_reference')<div class="text-rose-300 text-xs">{{ $message }}</div>@enderror
        @if($buyer_reference)
            <div class="text-xs text-emerald-300/80"><i class="fa-solid fa-check"></i> We have your reference on file: <span class="font-mono">{{ $buyer_reference }}</span></div>
        @endif
    </form>

    <div class="flex gap-3">
        <a href="{{ route('user.invoices.pdf', $invoice) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-sm">Download invoice PDF</a>
        <a href="{{ route('user.upgrade') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/70 text-sm">Back</a>
    </div>
</div>

@if(!empty($upi_link))
    {{-- qrcode-generator from CDN; QrStudio engine reads window.qrcode --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script src="{{ asset('js/qr-studio/engine.js') }}?v={{ filemtime(public_path('js/qr-studio/engine.js')) }}"></script>
    <script>
        (function () {
            var el = document.getElementById('upi-qr');
            if (!el) return;
            var payload = el.getAttribute('data-upi');
            function draw() {
                if (!window.QrStudio) { setTimeout(draw, 120); return; }
                try {
                    var result = window.QrStudio.render({
                        data: payload,
                        errorCorrection: 'M',
                        modulePx: 6,
                        margin: 2,
                        dotShape: 'square',
                        outerEyeShape: 'square',
                        innerEyeShape: 'square',
                        fgColor: '#0f172a',
                        bgColor: '#ffffff'
                    });
                    el.innerHTML = result.svg;
                    var svg = el.querySelector('svg');
                    if (svg) { svg.style.width = '100%'; svg.style.height = '100%'; }
                } catch (e) {
                    el.textContent = 'Scan the UPI ID above with your UPI app.';
                }
            }
            draw();
        })();
    </script>
@endif
@endsection
