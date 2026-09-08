@extends('public.layouts.site', ['skin' => 'schematic'])

@section('content')
@include('public.partials.schematic.styles')

@php
    $__cur   = $currency ?? 'INR';
    $__cycle = in_array(($cycle ?? 'monthly'), ['monthly', 'annual'], true) ? $cycle : 'monthly';
@endphp

<section class="sch-wrap sch-hero">
    <div>
        <p class="sch-mono sch-route" style="margin-bottom:24px">
            <span class="slug">sayz.io/pricing</span><span>{{ count($plans) }} tiers</span>
        </p>
        <h1 class="sch-disp sch-h1" style="max-width:16ch">Free forever is a plan, not a trial.</h1>
        <p class="sch-lede" style="margin-top:20px">
            A creator with one page and an agency with forty clients do not need the same thing.
            Start with no card, and move up only when a limit actually gets in your way.
        </p>
        <p style="margin-top:26px;display:flex;align-items:center;flex-wrap:wrap">
            <span class="sch-cycle" role="group" aria-label="Billing cycle">
                <button type="button" data-cycle="monthly" aria-pressed="{{ $__cycle === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                <button type="button" data-cycle="annual" aria-pressed="{{ $__cycle === 'annual' ? 'true' : 'false' }}">Annual</button>
            </span>
            <span class="sch-cycle-note" id="schCycleNote">Annual saves about 17%</span>
        </p>
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-scroll">
            <table class="sch-ladder" id="schLadder" data-currency="{{ $__cur }}">
                <caption>All prices in {{ $__cur }}, excluding tax. Cancel anytime.</caption>
                <thead>
                    <tr>
                        <th scope="col">Plan</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col"><span class="sch-sr">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $row)
                        @php
                            $plan     = $row['model'];
                            $monthly  = $row['prices'][$__cur]['monthly'] ?? [];
                            $annual   = $row['prices'][$__cur]['annual'] ?? [];
                            $isFree   = (bool) ($row['is_free'] ?? false);
                            $popular  = (bool) ($plan->is_popular ?? false);
                        @endphp
                        <tr class="{{ $popular ? 'pick' : '' }}"
                            @unless($isFree)
                                data-m-minor="{{ (int) ($monthly['amount_minor'] ?? 0) }}"
                                data-a-minor="{{ (int) ($annual['amount_minor'] ?? 0) }}"
                                data-a-formatted="{{ $annual['formatted'] ?? '' }}"
                            @endunless>
                            <td>
                                <span class="pname">{{ $plan->name }}@if($popular)<span class="sch-flag">Most popular</span>@endif</span>
                                <span class="pfor">{{ $plan->description }}</span>
                            </td>
                            <td class="num">
                                @if($isFree)
                                    <span class="amt">{{ $monthly['formatted'] ?? '0' }}</span>
                                    <span class="per">free forever</span>
                                @else
                                    <span class="amt js-amt">{{ ($__cycle === 'annual' ? ($annual['formatted'] ?? '') : ($monthly['formatted'] ?? '')) }}</span>
                                    <span class="per js-per">{{ $__cycle === 'annual' ? 'per year' : 'per month' }}</span>
                                @endif
                            </td>
                            <td class="act">
                                <a href="{{ route('user.register') }}">{{ $isFree ? 'Start' : 'Choose' }} &rarr;</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top:20px;font-size:14px;color:var(--ink-3)">
            Every plan's limits come straight from the plan settings in the admin.
            <a href="{{ route('site.pricing') }}" style="color:var(--violet)">See every limit</a>.
        </p>
    </div>
</section>

@if(!empty($packages) && count($packages))
<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/pricing</span><span>ai coins</span></p>
            <div><h2 class="sch-disp sch-h2">AI is metered in coins,<br>so you never get a surprise bill.</h2></div>
        </div>
        <div class="sch-coins">
            @foreach(collect($packages)->take(4) as $pack)
                <div class="sch-coin">
                    <b class="sch-tnum">{{ number_format((int) ($pack['total_coins'] ?? 0)) }}</b>
                    <span>coins &middot; {{ $pack['prices'][$__cur]['formatted'] ?? '' }}</span>
                    <em>{{ $pack['model']->description ?? '' }}</em>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/pricing</span><span>questions</span></p>
            <div><h2 class="sch-disp sch-h2">Before you pick.</h2></div>
        </div>
        <div class="sch-faq">
            <details open>
                <summary>Is the free plan really free?</summary>
                <p>Yes. Starter costs nothing and does not expire. You re-confirm it once a year so dormant accounts do not sit on the servers forever, and there is no card at sign-up.</p>
            </details>
            <details>
                <summary>What happens if I outgrow a limit?</summary>
                <p>Nothing breaks. You are told which limit you have reached and offered the next tier. Existing links keep working either way.</p>
            </details>
            <details>
                <summary>Can I change plans later?</summary>
                <p>Up or down, any time. Upgrades are charged pro rata; downgrades take effect at the end of the period you have already paid for.</p>
            </details>
            <details>
                <summary>Do I need coins as well as a plan?</summary>
                <p>Only if you use AI heavily. Every plan includes an AI allowance, and coins are for going beyond it.</p>
            </details>
            <details>
                <summary>Can I pay in another currency?</summary>
                <p>Yes. Every plan is priced in both INR and USD, and you are billed in whichever suits your card.</p>
            </details>
        </div>
    </div>
</section>

@push('scripts')
@verbatim
<script>
(function () {
  var table = document.getElementById('schLadder');
  var note = document.getElementById('schCycleNote');
  if (!table) return;
  var cur = table.getAttribute('data-currency') || 'INR';
  var locale = cur === 'INR' ? 'en-IN' : 'en-US';
  var fmt = new Intl.NumberFormat(locale, { style: 'currency', currency: cur, maximumFractionDigits: 0 });
  var btns = document.querySelectorAll('.sch-cycle button');

  function apply(mode) {
    btns.forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.cycle === mode)); });
    if (note) { note.textContent = mode === 'annual' ? 'Two months free every year' : 'Annual saves about 17%'; }
    table.querySelectorAll('tbody tr[data-m-minor]').forEach(function (tr) {
      var amt = tr.querySelector('.js-amt'), per = tr.querySelector('.js-per');
      if (!amt || !per) return;
      var aMinor = Number(tr.dataset.aMinor || 0);
      var mMinor = Number(tr.dataset.mMinor || 0);
      if (mode === 'annual') {
        amt.textContent = fmt.format(Math.round(aMinor / 12) / 100);
        per.textContent = 'per month · ' + (tr.dataset.aFormatted || '') + ' a year';
      } else {
        amt.textContent = fmt.format(mMinor / 100);
        per.textContent = 'per month';
      }
    });
  }

  btns.forEach(function (b) {
    b.addEventListener('click', function () {
      apply(b.dataset.cycle);
      try {
        fetch('/pricing/billing-cycle', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || ''
          },
          body: JSON.stringify({ cycle: b.dataset.cycle })
        });
      } catch (e) {}
    });
  });

  var pressed = document.querySelector('.sch-cycle button[aria-pressed="true"]');
  apply(pressed ? pressed.dataset.cycle : 'monthly');
})();
</script>
@endverbatim
@endpush
@endsection
