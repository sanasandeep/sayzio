<section class="rounded-xl border p-4 my-5 space-y-3" style="border-color:var(--border-soft);">
<h3 class="font-bold">Apple &amp; Android pricing</h3>
<p class="text-sm opacity-70">Amounts are in cents / paise. Leave blank for automatic pricing: web price ÷ 0.7, rounded to the nearest whole dollar or rupee. Website customers see web prices only. Configure matching store products before mobile release.</p>
@foreach(['USD','INR'] as $currency)
@foreach($platformCycles as $cycle)
@php $row = $platformModel->prices->first(fn($p) => $p->currency === $currency && $p->billing_cycle === $cycle); $automatic = \App\Services\PlatformPricing::defaultMinor((int)($row->amount_minor_units ?? 0)); @endphp
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3"><strong>{{ $currency }} · {{ ucfirst($cycle) }}</strong>
@foreach(['ios'=>'Apple / iOS','android'=>'Android'] as $platform=>$label)
<label class="text-sm">{{ $label }}<input data-platform-price data-currency="{{ $currency }}" data-cycle="{{ $cycle }}" data-kind="{{ $platformModel instanceof \App\Modules\Admin\Models\Plan ? 'plan' : 'coins' }}" class="w-full rounded-lg border p-2" style="background:var(--bg-card);color:var(--text-primary);border-color:var(--border-soft);" type="number" min="0" step="1" name="platform_prices[{{ $currency }}][{{ $cycle }}][{{ $platform }}]" value="{{ old('platform_prices.'.$currency.'.'.$cycle.'.'.$platform, $row?->{$platform.'_amount_minor'}) }}" placeholder="Auto: {{ $automatic }}"></label>
@endforeach</div>
@endforeach
@endforeach
@error('platform_prices')<p role="alert">{{ $message }}</p>@enderror
</section>

<script>
(function(){const fields=document.querySelectorAll('[data-platform-price]');function update(){fields.forEach(function(field){const cur=field.dataset.currency,cycle=field.dataset.cycle;const baseName=field.dataset.kind==='plan'?(cycle==='annual'?'annual_price':'monthly_price')+(cur==='INR'?'_secondary':''):(cur==='INR'?'price_inr':'price_usd');const base=document.querySelector('[name="'+baseName+'"]');if(base)field.placeholder='Auto: '+Math.round(Number(base.value||0)/70)*100;});}document.addEventListener('input',update);update();})();
</script>
