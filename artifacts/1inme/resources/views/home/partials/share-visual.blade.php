{{--
    The little product demo that sits in a Share card, and again — larger —
    in that card's expanded panel. One copy of each, switched on `$key`, so
    the card and the modal can never drift apart: there is only one of them.

    `$key` and `$scale` ('card' | 'modal') come from the including view.
--}}
@switch($key)

@case('short-links')
    <div class="sl-pill">
        <i class="fas fa-link text-[10px]" style="color:var(--c1)"></i>
        <span class="host">{{ $shareHost ?? '1in.me/' }}</span><span class="slug">spring-drop</span>
    </div>
    <div class="sl-dest" aria-hidden="true">
        <i class="fas fa-arrow-turn-down"></i>
        <span class="url">shop.yourbrand.com/spring-24</span>
        <span class="tag">editable</span>
    </div>

    {{-- Where the clicks came from. Three bars is enough to read as real
         analytics without turning the card into a chart. --}}
    <div class="sl-sources" aria-hidden="true">
        @foreach([['Instagram', 46, 'var(--g1)'], ['TikTok', 31, 'var(--g2)'], ['Direct', 23, 'var(--g1)']] as [$src, $pct, $col])
            <div class="sl-src">
                <span class="lbl">{{ $src }}</span>
                <span class="bar"><i style="--pct:{{ $pct }}%; --col:{{ $col }}"></i></span>
                <span class="pct">{{ $pct }}%</span>
            </div>
        @endforeach
    </div>

    <div class="sl-counter">
        <span><span class="num">1,284</span> clicks today</span>
        <span class="sl-spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
    </div>
    @break

@case('custom-domain')
    <div class="cd-stage surface-lit">
        {{-- The URL bar says what it means with colour -- scheme muted, brand
             bright, tld and path each their own accent -- so it is one of the
             places that has to escape `surface-lit`'s white. --}}
        <div class="cd-bar surface-lit-keep">
            <span class="lock"><i class="fas fa-lock"></i></span>
            <span class="sub">https://</span><span class="brand">links.</span><span class="brand">yourbrand</span><span class="tld">.com</span><span class="path">/launch</span>
        </div>
        <div class="cd-rows" aria-hidden="true">
            @foreach([
                ['CNAME', 'links → cname.1in.me'],
                ['TXT',   '_1inme-verify=ok-91a2'],
                ['SSL',   "Let's Encrypt · auto-renew"],
            ] as [$ty, $val])
                <div class="cd-rec">
                    <span class="ty">{{ $ty }}</span>
                    <span class="val">{{ $val }}</span>
                    <span class="ok"><i class="fas fa-circle-check"></i></span>
                </div>
            @endforeach
        </div>
        <span class="cd-status"><span class="pulse"></span>Live · secured</span>
    </div>
    @break

@case('qr-codes')
    {{-- A real 29x29 matrix rather than a picture of one: three finder
         squares, one alignment square, both timing runs, and a deterministic
         fill for the rest (seeded, so the same code renders every time). The
         centre is cleared for the mark. --}}
    @php
        $qrSize = 29;
        $qrGrid = array_fill(0, $qrSize, array_fill(0, $qrSize, 0));
        $qrFinder = function (&$g, $ox, $oy) {
            for ($i = 0; $i < 7; $i++) {
                for ($j = 0; $j < 7; $j++) {
                    $on = ($i === 0 || $i === 6 || $j === 0 || $j === 6)
                        || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                    $g[$oy + $i][$ox + $j] = $on ? 1 : 0;
                }
            }
        };
        $qrFinder($qrGrid, 0, 0);
        $qrFinder($qrGrid, 22, 0);
        $qrFinder($qrGrid, 0, 22);
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 5; $j++) {
                $on = ($i === 0 || $i === 4 || $j === 0 || $j === 4) || ($i === 2 && $j === 2);
                $qrGrid[20 + $i][20 + $j] = $on ? 1 : 0;
            }
        }
        for ($i = 8; $i <= 20; $i++) {
            $qrGrid[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $qrGrid[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }
        $qrReserved = function ($x, $y) {
            if ($x < 8 && $y < 8) return true;
            if ($x >= 22 && $y < 8) return true;
            if ($x < 8 && $y >= 22) return true;
            if ($x >= 20 && $x < 25 && $y >= 20 && $y < 25) return true;
            if ($x === 6 || $y === 6) return true;
            return false;
        };
        mt_srand(20251128);
        for ($y = 0; $y < $qrSize; $y++) {
            for ($x = 0; $x < $qrSize; $x++) {
                if (! $qrReserved($x, $y)) {
                    $qrGrid[$y][$x] = (mt_rand(0, 100) < 47) ? 1 : 0;
                }
            }
        }
        for ($y = 12; $y <= 16; $y++) {
            for ($x = 12; $x <= 16; $x++) {
                $qrGrid[$y][$x] = 0;
            }
        }
        $qrGradId = 'qrLogoGrad-' . ($scale ?? 'card');
    @endphp
    <div class="qr-row">
    <div class="qr-stage qr-stage--left" aria-hidden="true">
        <span class="qr-corner tl"></span>
        <span class="qr-corner tr"></span>
        <span class="qr-corner bl"></span>
        <span class="qr-corner br"></span>
        <span class="qr-scans-pill">+128 scans · today</span>
        <svg class="qr-svg" viewBox="0 0 29 29" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $qrGradId }}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#e94e8c"/>
                    <stop offset="1" stop-color="#3d6bff"/>
                </linearGradient>
            </defs>
            @for ($y = 0; $y < $qrSize; $y++)
                @for ($x = 0; $x < $qrSize; $x++)
                    @if ($qrGrid[$y][$x])
                        <rect x="{{ $x }}" y="{{ $y }}" width="1.04" height="1.04" rx="0.18" ry="0.18" fill="#0e0e10"/>
                    @endif
                @endfor
            @endfor
            <rect x="11.4" y="11.4" width="6.2" height="6.2" rx="1.3" ry="1.3" fill="#fff"/>
            <rect x="12.1" y="12.1" width="4.8" height="4.8" rx="1" ry="1" fill="url(#{{ $qrGradId }})"/>
            <text x="14.5" y="15.95" text-anchor="middle" font-family="Inter,system-ui,-apple-system,sans-serif" font-weight="900" font-size="3.2" fill="#fff">1</text>
        </svg>
    </div>

    {{-- Beside the code: the two facts that make a dynamic QR different from
         a printed one — it has been repointed, and the poster never changed. --}}
    <div class="qr-meta" aria-hidden="true">
        <div class="qr-meta-row"><dt>Scans</dt><dd>8,412</dd></div>
        <div class="qr-meta-row"><dt>Repointed</dt><dd>3&times;</dd></div>
        <div class="qr-meta-row"><dt>Reprints</dt><dd class="zero">0</dd></div>
        <div class="qr-fmt"><span>PNG</span><span>SVG</span><span>PDF</span></div>
    </div>
    </div>
    @break

@case('channels')
    {{-- What a "share card" is, shown rather than named: the link preview
         that unfurls when the link is pasted anywhere. --}}
    <div class="ch-og" aria-hidden="true">
        <div class="ch-og-img"><i class="fas fa-image"></i></div>
        <div class="ch-og-meta">
            <span class="ch-og-site">1in.me</span>
            <span class="ch-og-title">Spring drop is live</span>
            <span class="ch-og-desc">Everything in one link, shop, playlist, tour dates.</span>
        </div>
    </div>

    <div class="ch-grid">
        @foreach(['fa-instagram'=>'#e94e8c','fa-tiktok'=>'#1bd4d9','fa-youtube'=>'#e94e8c','fa-x-twitter'=>'#3d6bff','fa-linkedin'=>'#1bd4d9','fa-facebook'=>'#3d6bff'] as $ic => $col)
            <span class="ch-icon" style="color:{{ $col }}"><i class="fab {{ $ic }}"></i></span>
        @endforeach
    </div>
    <div class="ch-tags" aria-hidden="true">
        <span>OG</span><span>Pixels</span><span>UTM</span><span>UTM-A/B</span>
    </div>
    @break

@endswitch
