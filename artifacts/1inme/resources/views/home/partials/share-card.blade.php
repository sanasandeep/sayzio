{{--
    One Share card, and the panel it expands into.

    The panel used to be a CLONE of the card — which is exactly why it looked
    like a card floating in an empty white box: it was one. Same words, same
    three lines, nothing gained by opening it. Each card now carries its own
    detail markup in a <template>, so expanding it shows something the card
    could not: what you actually get, the numbers, and a way in. The template
    ships with the page because it is a few hundred bytes of text; only the
    nineteen link-type panels, which used to be iframes, are worth a fetch.

    `$card` comes from the including view and carries: key, icon, title,
    blurb, lead, points[], stats[], cta, g1, g2, rd.
--}}
<article class="share-card glass reveal rd-{{ $card['rd'] }}"
         style="--g1:{{ $card['g1'] }}; --g2:{{ $card['g2'] }}">
    <span class="share-wash" aria-hidden="true"></span>

    <div class="share-head">
        <span class="share-ico" aria-hidden="true"><i class="fas {{ $card['icon'] }}"></i></span>
        <h3 class="share-title">{{ $card['title'] }}</h3>
        <p class="share-blurb">{!! $card['blurb'] !!}</p>
    </div>

    <div class="share-demo">
        @include('home.partials.share-visual', ['key' => $card['key'], 'scale' => 'card'])
    </div>

    <template class="xc-detail">
        {{-- The pair lives on the wrapper, not just the visual panel: the
             icon chip, the tick marks and the CTA in the left column all read
             --g1/--g2 too, and without this they silently fell back to the
             default blue on every card. --}}
        <div class="xcd" style="--g1:{{ $card['g1'] }}; --g2:{{ $card['g2'] }}">
            <div class="xcd-main">
                <span class="xcd-ico" aria-hidden="true"><i class="fas {{ $card['icon'] }}"></i></span>
                <h3 class="xcd-title">{{ $card['title'] }}</h3>
                <p class="xcd-lead">{{ $card['lead'] }}</p>

                <ul class="xcd-points">
                    @foreach($card['points'] as $point)
                        <li><i class="fas fa-check" aria-hidden="true"></i><span>{!! $point !!}</span></li>
                    @endforeach
                </ul>

                <dl class="xcd-stats">
                    @foreach($card['stats'] as [$value, $label])
                        <div><dt>{{ $value }}</dt><dd>{{ $label }}</dd></div>
                    @endforeach
                </dl>

                <button type="button" class="xcd-cta"
                        onclick="window.trackMarketingEvent&&window.trackMarketingEvent('home_share_modal','{{ addslashes($card['title']) }}');window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))">
                    {{ $card['cta'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>

            <div class="xcd-visual" style="--g1:{{ $card['g1'] }}; --g2:{{ $card['g2'] }}">
                <span class="share-wash" aria-hidden="true"></span>
                <div class="xcd-demo">
                    @include('home.partials.share-visual', ['key' => $card['key'], 'scale' => 'modal'])
                </div>
            </div>
        </div>
    </template>
</article>
