@php
    $heading = $settings['heading'] ?? ($link->title ?: 'Reviews');
    $subheading = $settings['subheading'] ?? 'See what people are saying — and leave your own.';
    $showSummary = $settings['show_summary'] ?? true;
    $allowSub = $settings['allow_submissions'] ?? true;
    $collectMedia = $settings['collect_media'] ?? true;
    $requireVerification = $settings['require_verification'] ?? false;
    $collectEmail = ($settings['collect_email'] ?? true) || $requireVerification;
    $layout = $settings['layout'] ?? 'grid';
    $avg = $summary['average'] ?? 0;
    $pageTitle = $link->seo_title ?? ($link->title ? $link->title . ' — Reviews' : 'Reviews');
    $pageDesc = $link->seo_description ?? $subheading;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDesc }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDesc }}">
    <meta property="og:type" content="website">
    @if($avg > 0)
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $link->title ?: 'Reviews',
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => $avg,
            'reviewCount' => $summary['rated'] ?? 0,
            'bestRating' => 5,
        ],
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0b0b13; --card:#15151f; --line:rgba(255,255,255,.08); --ink:#f4f4f8; --muted:#9aa0ad; --accent:#8b5cf6; --star:#fbbf24; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Space Grotesk',system-ui,sans-serif; background:radial-gradient(1200px 600px at 50% -10%, #1c1430 0%, var(--bg) 60%); color:var(--ink); min-height:100vh; }
        .wrap { max-width:880px; margin:0 auto; padding:40px 20px 80px; }
        .head { text-align:center; margin-bottom:28px; }
        .head h1 { font-size:30px; font-weight:700; margin:0 0 6px; }
        .head p { color:var(--muted); margin:0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:20px; padding:22px; }
        .summary { display:flex; gap:26px; align-items:center; flex-wrap:wrap; margin-bottom:24px; }
        .summary .avg { font-size:54px; font-weight:700; line-height:1; }
        .summary .avg small { display:block; font-size:13px; color:var(--muted); font-weight:500; margin-top:6px; }
        .bars { flex:1; min-width:220px; }
        .bar-row { display:flex; align-items:center; gap:10px; font-size:12px; color:var(--muted); margin:3px 0; }
        .bar { flex:1; height:8px; background:rgba(255,255,255,.07); border-radius:99px; overflow:hidden; }
        .bar i { display:block; height:100%; background:var(--star); border-radius:99px; }
        .stars { color:var(--star); letter-spacing:2px; }
        .stars .off { color:rgba(255,255,255,.18); }
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin:30px 0 14px; }
        .toolbar h2 { font-size:18px; margin:0; }
        .btn { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:12px; padding:11px 18px; font:inherit; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; }
        .btn.ghost { background:rgba(255,255,255,.06); }
        .grid { display:grid; gap:14px; }
        .grid.cols { grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); }
        .review { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; }
        .review .top { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
        .avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#ec4899); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:15px; color:#fff; overflow:hidden; }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .review .name { font-weight:600; font-size:14px; }
        .review .meta { font-size:11px; color:var(--muted); }
        .review .body { font-size:14px; color:#dcdce4; line-height:1.5; margin:6px 0; white-space:pre-wrap; }
        .source-tag { font-size:10px; padding:2px 8px; border-radius:99px; background:rgba(255,255,255,.07); color:var(--muted); margin-left:auto; }
        .media { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
        .media img, .media video { width:84px; height:84px; object-fit:cover; border-radius:10px; }
        .media audio { width:100%; }
        .answers { margin:8px 0 0; padding:10px; background:rgba(255,255,255,.03); border-radius:10px; font-size:12.5px; }
        .answers b { color:var(--ink); }
        .reply { margin-top:10px; padding:10px 12px; border-left:3px solid var(--accent); background:rgba(139,92,246,.08); border-radius:0 10px 10px 0; font-size:13px; }
        .reply .who { font-size:11px; color:var(--accent); font-weight:600; margin-bottom:3px; }
        .pin { font-size:10px; color:var(--star); font-weight:600; }
        .verified { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:600; color:#34d399; background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.35); border-radius:99px; padding:1px 7px; vertical-align:middle; }
        .empty { text-align:center; color:var(--muted); padding:40px 0; }
        /* form */
        .modal { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:flex-start; justify-content:center; padding:30px 16px; overflow:auto; z-index:50; }
        .modal.open { display:flex; }
        .modal .card { width:100%; max-width:520px; }
        .field { margin-bottom:14px; }
        .field label { display:block; font-size:13px; color:var(--muted); margin-bottom:5px; }
        .field input[type=text], .field input[type=email], .field textarea, .field select { width:100%; background:#0e0e16; border:1px solid var(--line); border-radius:11px; padding:10px 12px; color:var(--ink); font:inherit; font-size:14px; }
        .field textarea { min-height:90px; resize:vertical; }
        .rate { display:flex; gap:6px; font-size:30px; color:rgba(255,255,255,.18); cursor:pointer; }
        .rate span.on { color:var(--star); }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
        .flash { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.4); color:#6ee7b7; border-radius:12px; padding:11px 14px; margin-bottom:16px; font-size:14px; }
        .flash.err { background:rgba(239,68,68,.12); border-color:rgba(239,68,68,.4); color:#fca5a5; }
        .foot { text-align:center; margin-top:40px; }
        .foot a { color:var(--muted); font-size:12px; text-decoration:none; }
    </style>
</head>
<body>
@php
    $renderStars = function ($rating) {
        $r = (int) round($rating);
        $out = '';
        for ($i = 1; $i <= 5; $i++) {
            $out .= '<span class="' . ($i <= $r ? '' : 'off') . '">&#9733;</span>';
        }
        return $out;
    };
@endphp
<div class="wrap">
    <div class="head">
        <h1>{{ $heading }}</h1>
        @if($subheading)<p>{{ $subheading }}</p>@endif
    </div>

    @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif

    @if($showSummary && ($summary['total'] ?? 0) > 0)
    <div class="card summary">
        <div class="avg">
            {{ number_format($avg, 1) }}
            <small><span class="stars">{!! $renderStars($avg) !!}</span></small>
            <small>{{ $summary['total'] }} review{{ $summary['total'] == 1 ? '' : 's' }}</small>
        </div>
        <div class="bars">
            @for($s = 5; $s >= 1; $s--)
            <div class="bar-row">
                <span>{{ $s }}★</span>
                <span class="bar"><i style="width:{{ $summary['percent'][$s] ?? 0 }}%"></i></span>
                <span>{{ $summary['breakdown'][$s] ?? 0 }}</span>
            </div>
            @endfor
        </div>
    </div>
    @endif

    <div class="toolbar">
        <h2>{{ count($items) }} review{{ count($items) == 1 ? '' : 's' }}</h2>
        @if($allowSub)
        <button class="btn" onclick="document.getElementById('rv-modal').classList.add('open')">
            &#9733; Write a review
        </button>
        @endif
    </div>

    @if(count($items) === 0)
        <div class="card empty">No reviews yet. @if($allowSub)Be the first to leave one!@endif</div>
    @else
    <div class="grid {{ $layout === 'grid' ? 'cols' : '' }}">
        @foreach($items as $item)
        <div class="review">
            <div class="top">
                <div class="avatar">
                    @if(!empty($item['author_avatar']))<img src="{{ $item['author_avatar'] }}" alt="">@else{{ strtoupper(substr($item['author_name'] ?: 'A', 0, 1)) }}@endif
                </div>
                <div>
                    <div class="name">{{ $item['author_name'] }} @if(!empty($item['verified']))<span class="verified" title="Verified customer">&#10003; Verified customer</span>@endif @if($item['is_pinned'])<span class="pin">&#9733; Pinned</span>@endif</div>
                    <div class="meta">{{ $item['created_at']?->diffForHumans() }}</div>
                </div>
                <span class="source-tag">{{ $item['source_label'] }}</span>
            </div>
            @if($item['rating'])<div class="stars">{!! $renderStars($item['rating']) !!}</div>@endif
            @if($item['body'])<div class="body">{{ $item['body'] }}</div>@endif

            @if(!empty($item['answers']))
            <div class="answers">
                @foreach($item['answers'] as $a)
                <div><b>{{ $a['prompt'] }}:</b> {{ $a['answer'] }}</div>
                @endforeach
            </div>
            @endif

            @if(!empty($item['media']))
            <div class="media">
                @foreach($item['media'] as $m)
                    @if($m['type'] === 'image')<img src="{{ $m['url'] }}" alt="" loading="lazy">
                    @elseif($m['type'] === 'video')<video src="{{ $m['url'] }}" controls></video>
                    @elseif($m['type'] === 'audio')<audio src="{{ $m['url'] }}" controls></audio>
                    @endif
                @endforeach
            </div>
            @endif

            @if(!empty($item['source_url']))
            <div class="meta" style="margin-top:8px"><a href="{{ $item['source_url'] }}" target="_blank" rel="nofollow noopener" style="color:var(--accent);text-decoration:none">View on {{ $item['source_label'] }} &rarr;</a></div>
            @endif

            @if(!empty($item['reply']))
            <div class="reply">
                <div class="who">Owner replied</div>
                {{ $item['reply'] }}
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <div class="foot"><a href="https://1in.me" target="_blank" rel="noopener">Powered by Sayzio</a></div>
</div>

@if($allowSub)
<div class="modal" id="rv-modal">
    <form class="card" method="POST" action="{{ route('redirect.reviews.submit', $link->alias) }}" enctype="multipart/form-data" x-data>
        @csrf
        <div class="toolbar" style="margin:0 0 16px">
            <h2>Write a review</h2>
            <button type="button" class="btn ghost" style="padding:6px 12px" onclick="document.getElementById('rv-modal').classList.remove('open')">✕</button>
        </div>

        <div class="field">
            <label>Your rating</label>
            <div class="rate" id="rv-rate">
                @for($i = 1; $i <= 5; $i++)<span data-v="{{ $i }}">&#9733;</span>@endfor
            </div>
            <input type="hidden" name="rating" id="rv-rating" value="">
        </div>

        <div class="field">
            <label>Your name</label>
            <input type="text" name="author_name" maxlength="120" placeholder="e.g. Alex">
        </div>

        @if($collectEmail)
        <div class="field">
            <label>Email {{ $requireVerification ? '(required to verify — not shown publicly)' : '(optional, not shown publicly)' }}</label>
            <input type="email" name="author_email" maxlength="255" placeholder="you@example.com" {{ $requireVerification ? 'required' : '' }}>
        </div>
        @endif

        <div class="field">
            <label>Your review</label>
            <textarea name="body" maxlength="5000" placeholder="Share your experience…"></textarea>
        </div>

        @foreach($questions as $q)
        <div class="field">
            <label>{{ $q->prompt }}@if($q->is_required) *@endif</label>
            @if($q->type === 'choice' && is_array($q->options))
                <select name="answers[{{ $q->id }}]" @if($q->is_required) required @endif>
                    <option value="">Choose…</option>
                    @foreach($q->options as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                </select>
            @elseif($q->type === 'rating')
                <select name="answers[{{ $q->id }}]" @if($q->is_required) required @endif>
                    <option value="">Choose…</option>
                    @for($n = 1; $n <= 5; $n++)<option value="{{ $n }}">{{ $n }} ★</option>@endfor
                </select>
            @else
                <input type="text" name="answers[{{ $q->id }}]" maxlength="2000" @if($q->is_required) required @endif>
            @endif
        </div>
        @endforeach

        @if($collectMedia)
        <div class="field">
            <label>Add photos, audio or video (optional)</label>
            <input type="file" name="media[]" multiple accept="image/*,audio/*,video/*">
        </div>
        @endif

        <div class="hp"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <button type="submit" class="btn" style="width:100%;justify-content:center">Submit review</button>
    </form>
</div>
<script>
    (function () {
        var rate = document.getElementById('rv-rate');
        var input = document.getElementById('rv-rating');
        if (!rate) return;
        rate.querySelectorAll('span').forEach(function (star) {
            star.addEventListener('click', function () {
                var v = parseInt(this.getAttribute('data-v'), 10);
                input.value = v;
                rate.querySelectorAll('span').forEach(function (s, i) {
                    s.classList.toggle('on', i < v);
                });
            });
        });
    })();
</script>
@endif
</body>
</html>
