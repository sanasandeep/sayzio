@php
    $flow = \App\Modules\User\Models\ConversationFlow::where('link_id', $link->id)->where('is_published', true)->first();
    $alias = $link->alias;
    $theme = $link->settings['biolink']['theme'] ?? [];
    $bg = $theme['background'] ?? '#0f172a';
    $accent = $theme['accent'] ?? '#5c83ff';
    $bubbleBot = $theme['bubble_bot'] ?? '#1e293b';
    $bubbleUser = $theme['bubble_user'] ?? '#3d6bff';
    $textColor = $theme['text'] ?? '#f8fafc';
    $title = $link->title ?: $link->alias;

    // Honor the same background settings the list-mode renderer uses so
    // a creator can run conversational mode on top of a slideshow / image
    // background. Falls back to the flat $bg when no media is configured.
    $cvBs               = $link->settings['biolink'] ?? [];
    $cvBgType           = $cvBs['background_type'] ?? null;
    $cvSlideshowImages  = is_array($cvBs['slideshow_images'] ?? null) ? array_values($cvBs['slideshow_images']) : [];
    $cvSlideshowInterval = (int) ($cvBs['slideshow_interval'] ?? 5);
    $cvBgImage          = (string) ($cvBs['background_image'] ?? '');
    $cvHasSlideshow     = $cvBgType === 'slideshow' && count($cvSlideshowImages) > 0;
    $cvHasBgImage       = $cvBgType === 'image' && $cvBgImage !== '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
@if($link->seo_description)<meta name="description" content="{{ $link->seo_description }}">@endif
@if($link->favicon)<link rel="icon" href="{{ \App\Support\PublicStorageUrl::resolve($link->favicon) }}">@endif
<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    body { background: {{ $bg }}; color: {{ $textColor }}; min-height: 100vh; display: flex; justify-content: center; position: relative; }
    @if($cvHasSlideshow)
    body { background: #000; }
    .cv-bg-slideshow { position: fixed; inset: 0; z-index: 0; }
    .cv-bg-slideshow img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 1.2s ease-in-out; }
    .cv-bg-slideshow img.active { opacity: 1; }
    .cv-bg-overlay { position: fixed; inset: 0; z-index: 0; background: rgba(0,0,0,0.55); }
    .cv-shell { position: relative; z-index: 1; }
    @elseif($cvHasBgImage)
    body { background: #000 url('{{ $cvBgImage }}') center/cover no-repeat fixed; }
    .cv-bg-overlay { position: fixed; inset: 0; z-index: 0; background: rgba(0,0,0,0.55); }
    .cv-shell { position: relative; z-index: 1; }
    @endif
    .cv-shell { width: 100%; max-width: 460px; display: flex; flex-direction: column; min-height: 100vh; padding: 16px; }
    .cv-header { display: flex; align-items: center; gap: 12px; padding: 8px 4px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 12px; }
    .cv-avatar { width: 40px; height: 40px; border-radius: 50%; background: {{ $accent }}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }
    .cv-title { font-weight: 600; font-size: 15px; }
    .cv-subtitle { font-size: 12px; opacity: 0.7; }
    .cv-stream { flex: 1; display: flex; flex-direction: column; gap: 10px; padding: 8px 0 16px; overflow-y: auto; }
    .cv-msg { max-width: 85%; padding: 10px 14px; border-radius: 18px; font-size: 15px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word; animation: cv-in 0.25s ease-out; }
    .cv-msg.bot  { background: {{ $bubbleBot }}; color: {{ $textColor }}; align-self: flex-start; border-bottom-left-radius: 4px; }
    .cv-msg.user { background: {{ $bubbleUser }}; color: white;          align-self: flex-end;   border-bottom-right-radius: 4px; }
    .cv-typing { background: {{ $bubbleBot }}; align-self: flex-start; padding: 12px 16px; border-radius: 18px; display: inline-flex; gap: 4px; }
    .cv-typing span { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.6); animation: cv-bounce 1.2s infinite; }
    .cv-typing span:nth-child(2) { animation-delay: 0.15s; }
    .cv-typing span:nth-child(3) { animation-delay: 0.3s; }
    .cv-choices { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px 0 16px; }
    .cv-choice {
        background: transparent; border: 1.5px solid {{ $accent }}; color: {{ $textColor }};
        padding: 10px 16px; border-radius: 22px; font-size: 14px; cursor: pointer;
        transition: background 0.15s; font-family: inherit;
    }
    .cv-choice:hover, .cv-choice.is-on { background: {{ $accent }}; color: white; }
    .cv-input-row { display: flex; gap: 8px; padding: 8px 0; }
    .cv-input {
        flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
        color: {{ $textColor }}; padding: 12px 14px; border-radius: 22px; font-size: 15px; font-family: inherit; outline: none;
    }
    .cv-input:focus { border-color: {{ $accent }}; }
    .cv-send {
        background: {{ $accent }}; color: white; border: none;
        padding: 0 20px; border-radius: 22px; cursor: pointer; font-size: 15px; font-weight: 600;
    }
    .cv-cta {
        display: inline-block; background: {{ $accent }}; color: white !important;
        padding: 12px 22px; border-radius: 12px; text-decoration: none; font-weight: 600; margin: 12px 0;
    }
    .cv-restart { margin-top: auto; padding: 12px 0; text-align: center; font-size: 12px; opacity: 0.55; }
    .cv-restart a { color: inherit; text-decoration: underline; cursor: pointer; }
    .cv-error { background: rgba(239, 68, 68, 0.15); color: #fecaca; padding: 10px 14px; border-radius: 12px; font-size: 14px; }
    .cv-revealed-block { margin: 12px 0; padding: 16px; background: rgba(255,255,255,0.05); border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); }
    .cv-media { max-width: 85%; align-self: flex-start; border-radius: 18px; overflow: hidden; }
    .cv-media img, .cv-media video { display: block; width: 100%; border-radius: 18px; }
    .cv-media audio { width: 100%; }
    .cv-rating { display: flex; gap: 6px; padding: 8px 0; flex-wrap: wrap; }
    .cv-rating button {
        background: transparent; border: 1.5px solid {{ $accent }}; color: {{ $textColor }};
        width: 40px; height: 40px; border-radius: 50%; font-size: 16px; cursor: pointer;
    }
    .cv-rating.cv-emoji button { font-size: 22px; border: none; background: rgba(255,255,255,0.05); }
    .cv-rating button:hover, .cv-rating button.is-on { background: {{ $accent }}; color: white; }
    .cv-multi-submit { margin-top: 8px; }
    @keyframes cv-bounce { 0%, 80%, 100% { transform: translateY(0); opacity: 0.5; } 40% { transform: translateY(-6px); opacity: 1; } }
    @keyframes cv-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
@if($cvHasSlideshow)
<div class="cv-bg-slideshow" aria-hidden="true">
    @foreach($cvSlideshowImages as $si => $sImg)
    <img src="{{ $sImg }}" alt="" loading="eager" class="{{ $si === 0 ? 'active' : '' }}">
    @endforeach
</div>
<div class="cv-bg-overlay" aria-hidden="true"></div>
@elseif($cvHasBgImage)
<div class="cv-bg-overlay" aria-hidden="true"></div>
@endif
<div class="cv-shell" role="main">
    <div class="cv-header">
        @if(!empty($link->verified_logo))
            <img src="{{ $link->verified_logo }}" class="cv-avatar" alt="" style="object-fit:cover;">
        @else
            <div class="cv-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</div>
        @endif
        <div>
            <div class="cv-title">{{ $title }}</div>
            <div class="cv-subtitle">Online · usually replies instantly</div>
        </div>
    </div>

    <div class="cv-stream" id="cv-stream" aria-live="polite" aria-atomic="false"></div>
    <div id="cv-input-area"></div>
    <div class="cv-restart"><a id="cv-restart-link">Start over</a></div>
</div>
<script>
(function () {
    const STREAM = document.getElementById('cv-stream');
    const INPUT_AREA = document.getElementById('cv-input-area');
    const ALIAS = @json($alias);
    const START_URL  = '/cv/' + encodeURIComponent(ALIAS) + '/start';
    const ANSWER_URL = (id) => '/cv/' + encodeURIComponent(id) + '/answer';
    const DROP_URL   = (id) => '/cv/' + encodeURIComponent(id) + '/drop';
    const UPLOAD_URL = (id) => '/cv/' + encodeURIComponent(id) + '/upload';
    const STORAGE_KEY = 'cv_page_session_' + ALIAS;
    const CSRF = '{{ csrf_token() }}';
    let DEFAULT_TYPING_MS = 600;

    function makeId() {
        try {
            const a = new Uint8Array(16); crypto.getRandomValues(a);
            return 'pg_' + Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
        } catch (e) {
            return 'pg_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 14);
        }
    }
    let pageSessionId = localStorage.getItem(STORAGE_KEY);
    if (!pageSessionId) { pageSessionId = makeId(); try { localStorage.setItem(STORAGE_KEY, pageSessionId); } catch (e) {} }
    let publicId = null;
    let busy = false;

    function pushBubble(text, who, opts) {
        opts = opts || {};
        const div = document.createElement('div');
        div.className = 'cv-msg ' + who;
        if (opts.html) div.innerHTML = text; else div.textContent = text;
        STREAM.appendChild(div);
        STREAM.scrollTop = STREAM.scrollHeight;
        return div;
    }
    function pushTyping() {
        const t = document.createElement('div');
        t.className = 'cv-typing';
        t.innerHTML = '<span></span><span></span><span></span>';
        STREAM.appendChild(t);
        STREAM.scrollTop = STREAM.scrollHeight;
        return t;
    }
    function pushMedia(media) {
        const wrap = document.createElement('div');
        wrap.className = 'cv-media';
        if (media.kind === 'image' || media.kind === 'gif') {
            const img = document.createElement('img'); img.src = media.url; img.alt = media.alt || '';
            wrap.appendChild(img);
        } else if (media.kind === 'video') {
            const v = document.createElement('video'); v.src = media.url; v.controls = true; v.playsInline = true;
            wrap.appendChild(v);
        } else if (media.kind === 'audio') {
            const a = document.createElement('audio'); a.src = media.url; a.controls = true;
            wrap.appendChild(a);
        }
        STREAM.appendChild(wrap);
        STREAM.scrollTop = STREAM.scrollHeight;
    }
    function clearInputArea() { INPUT_AREA.innerHTML = ''; }
    function showError(msg) {
        const e = document.createElement('div');
        e.className = 'cv-error'; e.textContent = msg;
        INPUT_AREA.appendChild(e);
    }
    async function botSay(text, delay, then) {
        const t = pushTyping();
        await new Promise(r => setTimeout(r, delay));
        t.remove();
        pushBubble(text, 'bot');
        if (then) { await new Promise(r => setTimeout(r, 200)); then(); }
    }

    function renderStep(step) {
        clearInputArea();
        const delay = Math.max(0, step.typing_ms != null ? step.typing_ms : DEFAULT_TYPING_MS);
        botSay(step.message_text, delay, () => {
            switch (step.kind) {
                case 'question':       return renderQuestion(step);
                case 'input':          return renderInput(step);
                case 'ai_freetext':    return renderInput(step);
                case 'media':          return renderMedia(step);
                case 'file_upload':    return renderFile(step);
                case 'rating':         return renderRating(step);
                case 'datetime':       return renderDatetime(step);
                default:               return sendAnswer({}); // message / end auto-advance
            }
        });
    }

    function renderQuestion(step) {
        const wrap = document.createElement('div');
        wrap.className = 'cv-choices'; wrap.setAttribute('role', 'group');
        const isMulti = !!step.multi_select;
        const picked = new Set();
        (step.choices || []).forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'cv-choice'; btn.textContent = c.label;
            btn.addEventListener('click', () => {
                if (isMulti) {
                    if (picked.has(c.value)) { picked.delete(c.value); btn.classList.remove('is-on'); }
                    else { picked.add(c.value); btn.classList.add('is-on'); }
                } else {
                    sendAnswer({ choice_value: c.value, label: c.label });
                }
            });
            wrap.appendChild(btn);
        });
        INPUT_AREA.appendChild(wrap);
        if (isMulti) {
            const submit = document.createElement('button');
            submit.className = 'cv-send cv-multi-submit'; submit.type = 'button';
            submit.textContent = 'Continue';
            submit.addEventListener('click', () => {
                const values = Array.from(picked);
                if (values.length < (step.min_choices||1)) {
                    showError('Pick at least ' + (step.min_choices||1) + ' option(s)'); return;
                }
                sendAnswer({ choice_values: values, label: values.join(', ') });
            });
            INPUT_AREA.appendChild(submit);
        }
    }
    function renderInput(step) {
        const row = document.createElement('form');
        row.className = 'cv-input-row';
        const inputType = (step.input_kind === 'email') ? 'email'
                       : (step.input_kind === 'url')   ? 'url'
                       : (step.input_kind === 'number')? 'number'
                       : (step.input_kind === 'phone') ? 'tel' : 'text';
        row.innerHTML =
            '<input class="cv-input" type="' + inputType + '" placeholder="' +
            (step.placeholder || 'Type your answer…') + '" required>' +
            '<button class="cv-send" type="submit">Send</button>';
        row.addEventListener('submit', (e) => {
            e.preventDefault();
            const val = row.querySelector('input').value.trim();
            if (!val) return;
            sendAnswer({ input_value: val, label: val });
        });
        INPUT_AREA.appendChild(row);
        row.querySelector('input').focus();
    }
    function renderMedia(step) {
        if (step.media && step.media.url) pushMedia(step.media);
        // Media auto-advances — no input needed.
        sendAnswer({});
    }
    function renderFile(step) {
        const row = document.createElement('form');
        row.className = 'cv-input-row';
        const accept = step.file && step.file.accept
            ? step.file.accept.split(',').map(e => '.' + e.trim().replace(/^\./, '')).join(',')
            : '';
        row.innerHTML =
            '<input class="cv-input" type="file" required ' + (accept ? 'accept="' + accept + '"' : '') + '>' +
            '<button class="cv-send" type="submit">Upload</button>';
        row.addEventListener('submit', async (e) => {
            e.preventDefault();
            const f = row.querySelector('input').files[0];
            if (!f) return;
            const fd = new FormData(); fd.append('file', f); fd.append('_token', CSRF);
            row.querySelector('button').disabled = true;
            try {
                const r = await fetch(UPLOAD_URL(publicId), { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
                const j = await r.json();
                if (!j.ok) { showError(j.error || 'Upload failed'); row.querySelector('button').disabled = false; return; }
                sendAnswer({ label: f.name });
            } catch (err) {
                showError('Upload failed'); row.querySelector('button').disabled = false;
            }
        });
        INPUT_AREA.appendChild(row);
    }
    function renderRating(step) {
        const cfg = step.rating || { scale: 'star', min: 1, max: 5 };
        const wrap = document.createElement('div');
        wrap.className = 'cv-rating' + (cfg.scale === 'emoji' ? ' cv-emoji' : '');
        const emoji = ['😞','😕','😐','🙂','😄'];
        for (let v = cfg.min; v <= cfg.max; v++) {
            const b = document.createElement('button'); b.type = 'button';
            if (cfg.scale === 'star') b.textContent = '★';
            else if (cfg.scale === 'emoji') b.textContent = emoji[v - 1] || '⭐';
            else b.textContent = String(v);
            b.addEventListener('click', () => {
                wrap.querySelectorAll('button').forEach(x => x.classList.remove('is-on'));
                b.classList.add('is-on');
                sendAnswer({ rating_value: v, label: cfg.scale === 'star' ? '★'.repeat(v) : String(v) });
            });
            wrap.appendChild(b);
        }
        INPUT_AREA.appendChild(wrap);
    }
    function renderDatetime(step) {
        const cfg = step.datetime || { mode: 'datetime' };
        const type = cfg.mode === 'date' ? 'date' : (cfg.mode === 'time' ? 'time' : 'datetime-local');
        const row = document.createElement('form');
        row.className = 'cv-input-row';
        row.innerHTML =
            '<input class="cv-input" type="' + type + '" required ' +
            (cfg.min ? 'min="' + cfg.min + '"' : '') + ' ' +
            (cfg.max ? 'max="' + cfg.max + '"' : '') + '>' +
            '<button class="cv-send" type="submit">Send</button>';
        row.addEventListener('submit', (e) => {
            e.preventDefault();
            const val = row.querySelector('input').value;
            if (!val) return;
            sendAnswer({ datetime_value: val, label: val });
        });
        INPUT_AREA.appendChild(row);
    }

    async function sendAnswer(body) {
        if (busy || !publicId) return;
        busy = true;
        if (body.label) pushBubble(body.label, 'user');
        clearInputArea();
        const typing = pushTyping();
        try {
            const r = await fetch(ANSWER_URL(publicId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    choice_value:   body.choice_value   || null,
                    choice_values:  body.choice_values  || null,
                    input_value:    body.input_value    || null,
                    rating_value:   body.rating_value   != null ? body.rating_value : null,
                    datetime_value: body.datetime_value || null,
                }),
            });
            const j = await r.json();
            typing.remove();
            if (!j.ok) { showError(j.error || 'Something went wrong'); busy = false; return; }
            if (j.done) renderEnding(j.action);
            else        renderStep(j.step);
        } catch (e) {
            typing.remove();
            showError('Connection lost. Please try again.');
        }
        busy = false;
    }

    function renderEnding(action) {
        clearInputArea();
        if (!action) return;
        if (action.kind === 'open_link' && action.url) {
            botSay(action.label || "Here's where you should go next:", DEFAULT_TYPING_MS, () => {
                const a = document.createElement('a');
                a.href = action.url; a.className = 'cv-cta'; a.target = '_blank'; a.rel = 'noopener';
                a.textContent = action.label || 'Open link';
                INPUT_AREA.appendChild(a);
                setTimeout(() => { window.location.href = action.url; }, 800);
            });
        } else if (action.kind === 'book_calendar' && action.url) {
            botSay(action.label || 'Pick a time that works for you:', DEFAULT_TYPING_MS, () => {
                const a = document.createElement('a');
                a.href = action.url; a.className = 'cv-cta'; a.target = '_blank'; a.rel = 'noopener';
                a.textContent = 'Book a time';
                INPUT_AREA.appendChild(a);
            });
        } else if (action.kind === 'show_block') {
            botSay(action.label || "Here's what you're looking for:", DEFAULT_TYPING_MS, () => {
                const div = document.createElement('div');
                div.className = 'cv-revealed-block';
                if (action.html) div.innerHTML = action.html;
                else if (action.block_id) {
                    const a = document.createElement('a');
                    a.href = '/' + ALIAS + '#block-' + action.block_id;
                    a.className = 'cv-cta'; a.target = '_blank'; a.rel = 'noopener';
                    a.textContent = 'Open it';
                    div.appendChild(a);
                }
                INPUT_AREA.appendChild(div);
            });
        } else if (action.kind === 'message') {
            botSay(action.text || action.label || 'Thanks!', DEFAULT_TYPING_MS);
        } else if (action.kind === 'capture_email') {
            botSay(action.label || 'Drop your email to stay in the loop:', DEFAULT_TYPING_MS, () => {
                const form = document.createElement('form');
                form.innerHTML =
                    '<input type="email" required placeholder="you@example.com" class="cv-input" />' +
                    '<button type="submit" class="cv-cta">' + (action.cta || 'Subscribe') + '</button>' +
                    '<div class="cv-capture-status small text-muted mt-1"></div>';
                INPUT_AREA.appendChild(form);
                const status = form.querySelector('.cv-capture-status');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const email = form.querySelector('input').value.trim();
                    if (!email) return;
                    status.textContent = 'Saving…';
                    try {
                        const r = await fetch('/cv/' + encodeURIComponent(publicId) + '/capture-email', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                            body: JSON.stringify({ email })
                        });
                        const j = await r.json();
                        if (j.ok) form.replaceWith(Object.assign(document.createElement('div'), { className: 'cv-capture-done', textContent: "Thanks! You're on the list." }));
                        else status.textContent = j.error || 'Something went wrong.';
                    } catch (err) { status.textContent = 'Network error. Try again.'; }
                });
            });
        }
    }

    fetch(START_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ page_session_id: pageSessionId })
    }).then(r => r.json()).then(j => {
        if (!j.ok) { pushBubble(j.error || "This conversation isn't ready yet.", 'bot'); return; }
        publicId = j.session.id;
        DEFAULT_TYPING_MS = j.flow.default_typing_ms || DEFAULT_TYPING_MS;
        if (j.flow.intro_message) botSay(j.flow.intro_message, DEFAULT_TYPING_MS, () => renderStep(j.step));
        else renderStep(j.step);
    }).catch(() => pushBubble('Could not load conversation.', 'bot'));

    document.getElementById('cv-restart-link').addEventListener('click', () => {
        localStorage.removeItem(STORAGE_KEY); location.reload();
    });
    window.addEventListener('beforeunload', () => {
        if (publicId) { try { navigator.sendBeacon(DROP_URL(publicId), new Blob([JSON.stringify({})], { type: 'application/json' })); } catch (e) {} }
    });
})();
</script>
@if($cvHasSlideshow && count($cvSlideshowImages) > 1)
<script>
(function () {
    var imgs = document.querySelectorAll('.cv-bg-slideshow img');
    if (imgs.length < 2) return;
    var i = 0;
    setInterval(function () {
        imgs[i].classList.remove('active');
        i = (i + 1) % imgs.length;
        imgs[i].classList.add('active');
    }, {{ $cvSlideshowInterval * 1000 }});
})();
</script>
@endif
</body>
</html>
