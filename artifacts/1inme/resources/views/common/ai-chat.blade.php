@php
    /** @var \App\Modules\User\Models\Link $link */
    $companion = $link->aiCompanion();
    $config    = $companion ? $companion->effectiveConfig() : [];
    $accent    = $config['accent'] ?? '#3d6bff';
    $theme     = $config['theme'] ?? 'auto';
    $greeting  = $config['greeting'] ?? ($companion?->persona?->greeting);
    $starters  = array_values(array_filter((array) ($config['starters'] ?? [])));
    $title     = $link->title ?: ($companion?->name ?: $link->alias);
    $postUrl   = route('public.companion.message', ['publicId' => $companion->public_id]);
@endphp
<!doctype html>
<html lang="en" @if($theme !== 'auto') data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light dark; --accent: {{ $accent }}; }
        html[data-theme="light"] { color-scheme: light; }
        html[data-theme="dark"]  { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; height:100%; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#f6f6f9; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        html[data-theme="dark"], html[data-theme="dark"] body { background:#0b0b10; color:#f5f5f7; }
        html[data-theme="light"], html[data-theme="light"] body { background:#f6f6f9; color:#111; }
        .page { display:flex; flex-direction:column; height:100dvh; max-width:760px; margin:0 auto; }
        .header {
            display:flex; align-items:center; gap:12px;
            padding:16px 18px; border-bottom:1px solid rgba(0,0,0,.08);
            position:sticky; top:0; backdrop-filter:saturate(160%) blur(8px);
        }
        @media (prefers-color-scheme: dark) { .header { border-color: rgba(255,255,255,.08); } }
        html[data-theme="dark"] .header { border-color: rgba(255,255,255,.08); }
        .avatar { width:36px; height:36px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px; flex:0 0 auto; }
        .htitle { font-weight:700; font-size:15px; line-height:1.2; }
        .hsub { font-size:11px; opacity:.6; }
        .body { flex:1; overflow:auto; padding:18px; display:flex; flex-direction:column; gap:10px; }
        .msg { max-width:80%; padding:11px 14px; border-radius:16px; font-size:14.5px; line-height:1.45; white-space:pre-wrap; word-wrap:break-word; }
        .msg.u { margin-left:auto; background:var(--accent); color:#fff; border-bottom-right-radius:5px; }
        .msg.a { background:rgba(0,0,0,.05); color:inherit; border-bottom-left-radius:5px; }
        .msg.e { background:#fef2f2; color:#991b1b; }
        @media (prefers-color-scheme: dark) { .msg.a { background:rgba(255,255,255,.07); } }
        html[data-theme="dark"] .msg.a { background:rgba(255,255,255,.07); }
        .cite { margin-top:6px; font-size:10.5px; opacity:.6; }
        .starters { display:flex; flex-wrap:wrap; gap:8px; padding:0 18px 10px; }
        .chip { border:1px solid rgba(0,0,0,.12); background:transparent; color:inherit; border-radius:999px; padding:7px 13px; font-size:13px; cursor:pointer; }
        @media (prefers-color-scheme: dark) { .chip { border-color:rgba(255,255,255,.16); } }
        html[data-theme="dark"] .chip { border-color:rgba(255,255,255,.16); }
        .chip:hover { border-color:var(--accent); color:var(--accent); }
        form { display:flex; gap:8px; padding:12px 14px calc(12px + env(safe-area-inset-bottom)); border-top:1px solid rgba(0,0,0,.08); }
        @media (prefers-color-scheme: dark) { form { border-color:rgba(255,255,255,.08); } }
        html[data-theme="dark"] form { border-color:rgba(255,255,255,.08); }
        textarea { flex:1; border:1px solid rgba(0,0,0,.14); border-radius:14px; padding:11px 13px; font-size:14.5px; resize:none; max-height:140px; height:46px; outline:none; background:transparent; color:inherit; }
        @media (prefers-color-scheme: dark) { textarea { border-color:rgba(255,255,255,.14); } }
        html[data-theme="dark"] textarea { border-color:rgba(255,255,255,.14); }
        button.send { border:0; border-radius:14px; padding:0 18px; font-size:14.5px; font-weight:600; color:#fff; background:var(--accent); cursor:pointer; }
        button.send[disabled] { opacity:.5; cursor:not-allowed; }
        .foot { font-size:10.5px; text-align:center; padding:8px; opacity:.5; }
        .foot a { color:inherit; }
    </style>
</head>
<body>
@php $brand = $companion->brandingConfig(); @endphp
<div class="page">
    <div class="header">
        @if(!empty($brand['avatar_url']))
            <div class="avatar" style="overflow:hidden"><img src="{{ $brand['avatar_url'] }}" alt="" style="width:100%;height:100%;object-fit:cover"></div>
        @else
            <div class="avatar"><i class="fa fa-robot" aria-hidden="true">🤖</i></div>
        @endif
        <div>
            <div class="htitle">{{ $title }}</div>
            <div class="hsub">AI assistant</div>
        </div>
    </div>

    <div class="body" id="body" role="log" aria-live="polite">
        @if(!empty($greeting))
            <div class="msg a">{{ $greeting }}</div>
        @endif
    </div>

    @if($starters)
        <div class="starters" id="starters">
            @foreach($starters as $s)
                <button type="button" class="chip" data-q="{{ $s }}">{{ $s }}</button>
            @endforeach
        </div>
    @endif

    <form id="f">
        <textarea id="t" rows="1" placeholder="{{ $config['placeholder'] ?? 'Ask me anything…' }}" aria-label="Message"></textarea>
        <button id="s" class="send" type="submit">Send</button>
    </form>

    @if(!empty($brand['show_branding']))
        @if(!empty($brand['brand_text']))
            <div class="foot">@if(!empty($brand['brand_url']))<a href="{{ $brand['brand_url'] }}" target="_blank" rel="noopener">{{ $brand['brand_text'] }}</a>@else{{ $brand['brand_text'] }}@endif</div>
        @else
            <div class="foot">Powered by <a href="{{ url('/') }}" target="_blank" rel="noopener">Sayzio</a></div>
        @endif
    @endif
</div>

<script>
(function(){
    var endpoint = @json($postUrl);
    var storageKey = 'imc_page_visitor_' + @json($companion->public_id);
    var body = document.getElementById('body');
    var f = document.getElementById('f');
    var t = document.getElementById('t');
    var s = document.getElementById('s');
    var startersEl = document.getElementById('starters');

    function token(){ try { return localStorage.getItem(storageKey) || ''; } catch(_) { return ''; } }
    function remember(v){ try { if(v) localStorage.setItem(storageKey, v); } catch(_){} }
    function add(role, text, cites){
        var d = document.createElement('div');
        d.className = 'msg ' + (role === 'user' ? 'u' : role === 'error' ? 'e' : 'a');
        d.textContent = text;
        if (cites && cites.length) {
            var c = document.createElement('div'); c.className = 'cite';
            c.textContent = 'Sources: ' + cites.map(function(x){ return x.title || x.type || ''; }).filter(Boolean).join(', ');
            d.appendChild(c);
        }
        body.appendChild(d); body.scrollTop = body.scrollHeight; return d;
    }

    var sending = false;
    function send(v){
        v = (v || '').trim();
        if (!v || sending) return;
        if (startersEl) startersEl.style.display = 'none';
        add('user', v); t.value = ''; sending = true; s.disabled = true;
        var typing = add('assistant', '…');
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ message: v, visitor_token: token() }),
            credentials: 'omit'
        })
        .then(function(r){ return r.json().then(function(d){ return { status: r.status, data: d }; }); })
        .then(function(res){
            typing.remove();
            var d = res.data || {};
            if (res.status === 200 && d.ok) { remember(d.visitor_token); add('assistant', d.answer || '', d.citations || []); }
            else { add('error', d.error || 'Sorry, something went wrong.'); }
        })
        .catch(function(){ typing.remove(); add('error', 'Network error.'); })
        .finally(function(){ sending = false; s.disabled = false; t.focus(); });
    }

    f.addEventListener('submit', function(e){ e.preventDefault(); send(t.value); });
    t.addEventListener('keydown', function(e){ if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(t.value); } });
    if (startersEl) {
        startersEl.querySelectorAll('.chip[data-q]').forEach(function(btn){
            btn.addEventListener('click', function(){ send(btn.getAttribute('data-q')); });
        });
    }
})();
</script>
</body>
</html>
