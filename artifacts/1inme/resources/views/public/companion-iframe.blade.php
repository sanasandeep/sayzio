<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $companion->name }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --accent: {{ $config['accent'] ?? '#3d6bff' }};
        }
        html, body { margin:0; padding:0; height:100%; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#fff; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        .wrap { display:flex; flex-direction:column; height:100vh; }
        .header { background: var(--accent); color:#fff; padding:12px 14px; font-weight:600; font-size:14px; display:flex; align-items:center; justify-content:space-between; }
        .body { flex:1; overflow:auto; padding:14px; }
        .msg { max-width:85%; padding:9px 12px; border-radius:14px; margin-bottom:8px; font-size:14px; line-height:1.4; white-space:pre-wrap; word-wrap:break-word; }
        .msg.u { margin-left:auto; background:#eef2ff; color:#111; border-bottom-right-radius:4px; }
        .msg.a { background:#f5f5f7; color:#111; border-bottom-left-radius:4px; }
        .msg.e { background:#fef2f2; color:#991b1b; }
        @media (prefers-color-scheme: dark) {
            .msg.u { background: rgba(61,107,255,.18); color:#f5f5f7; }
            .msg.a { background: rgba(255,255,255,.06); color:#f5f5f7; }
        }
        .cite { margin-top:6px; font-size:10px; opacity:.6; }
        form { display:flex; gap:8px; padding:10px; border-top:1px solid rgba(0,0,0,.08); }
        @media (prefers-color-scheme: dark) { form { border-color: rgba(255,255,255,.08); } }
        textarea { flex:1; border:1px solid rgba(0,0,0,.12); border-radius:12px; padding:8px 10px; font-size:14px; resize:none; height:40px; outline:none; background:transparent; color:inherit; }
        @media (prefers-color-scheme: dark) { textarea { border-color: rgba(255,255,255,.12); } }
        button { border:0; border-radius:12px; padding:0 14px; font-size:14px; font-weight:600; color:#fff; background:var(--accent); cursor:pointer; }
        button[disabled]{ opacity:.5; cursor:not-allowed; }
        .foot { font-size:10px; text-align:center; padding:6px; opacity:.55; }
    </style>
</head>
<body>
@php $brand = $companion->brandingConfig(); @endphp
<div class="wrap">
    <div class="header">
        @if(!empty($brand['avatar_url']))
            <img src="{{ $brand['avatar_url'] }}" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle">
        @endif
        <span>{{ $companion->name }}</span>
    </div>
    <div class="body" id="body" role="log" aria-live="polite">
        @if(!empty($companion->persona->greeting))
            <div class="msg a">{{ $companion->persona->greeting }}</div>
        @endif
    </div>
    <form id="f">
        <textarea id="t" rows="1" placeholder="{{ $config['placeholder'] ?? 'Ask me anything…' }}" aria-label="Message"></textarea>
        <button id="s" type="submit">Send</button>
    </form>
    @if(!empty($brand['show_branding']))
        @if(!empty($brand['brand_text']))
            <div class="foot">@if(!empty($brand['brand_url']))<a href="{{ $brand['brand_url'] }}" target="_blank" rel="noopener" style="color:inherit">{{ $brand['brand_text'] }}</a>@else{{ $brand['brand_text'] }}@endif</div>
        @else
            <div class="foot">Powered by Sayzio AI Companion</div>
        @endif
    @endif
</div>
<script>
(function(){
    var endpoint = @json($postUrl);
    var iframeToken = @json($iframeToken ?? null);
    var storageKey = 'imc_iframe_visitor_' + @json($companion->public_id);
    var body = document.getElementById('body');
    var f = document.getElementById('f');
    var t = document.getElementById('t');
    var s = document.getElementById('s');
    function token(){ try{ return localStorage.getItem(storageKey)||''; }catch(_){return '';} }
    function remember(v){ try{ if(v) localStorage.setItem(storageKey,v); }catch(_){} }
    function add(role, text, cites){
        var d=document.createElement('div'); d.className='msg '+(role==='user'?'u':role==='error'?'e':'a'); d.textContent=text;
        if(cites && cites.length){ var c=document.createElement('div'); c.className='cite'; c.textContent='Sources: '+cites.map(function(x){return x.title||x.type||'';}).filter(Boolean).join(', '); d.appendChild(c); }
        body.appendChild(d); body.scrollTop=body.scrollHeight; return d;
    }
    var sending=false;
    f.addEventListener('submit', function(e){
        e.preventDefault();
        if(sending) return;
        var v=(t.value||'').trim(); if(!v) return;
        add('user', v); t.value=''; sending=true; s.disabled=true;
        var typing=add('assistant','…');
        fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({message:v,visitor_token:token(),iframe_token:iframeToken}),credentials:'omit'})
            .then(function(r){return r.json().then(function(d){return {status:r.status,data:d};});})
            .then(function(res){ typing.remove(); var d=res.data||{}; if(res.status===200 && d.ok){ remember(d.visitor_token); add('assistant', d.answer||'', d.citations||[]); } else { add('error', d.error||'Sorry, something went wrong.'); } })
            .catch(function(){ typing.remove(); add('error','Network error.'); })
            .finally(function(){ sending=false; s.disabled=false; t.focus(); });
    });
    t.addEventListener('keydown', function(e){ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); f.requestSubmit(); } });
})();
</script>
</body>
</html>
