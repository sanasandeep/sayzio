@php
    $flow = \App\Modules\User\Models\ConversationFlow::where('link_id', $link->id)->where('is_published', true)->first();
    $alias = $link->alias;
    $theme = $link->settings['biolink']['theme'] ?? [];
    $bg = $theme['background'] ?? '#0f172a';
    $accent = $theme['accent'] ?? '#8b5cf6';
    $bubbleBot = $theme['bubble_bot'] ?? '#1e293b';
    $bubbleUser = $theme['bubble_user'] ?? '#7c3aed';
    $textColor = $theme['text'] ?? '#f8fafc';
    $title = $link->title ?: $link->alias;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
@if($link->seo_description)
    <meta name="description" content="{{ $link->seo_description }}">
@endif
@if($link->favicon)
    <link rel="icon" href="{{ $link->favicon }}">
@endif
<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    body {
        background: {{ $bg }};
        color: {{ $textColor }};
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: stretch;
    }
    .cv-shell {
        width: 100%;
        max-width: 460px;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        padding: 16px;
    }
    .cv-header {
        display: flex; align-items: center; gap: 12px;
        padding: 8px 4px 16px; border-bottom: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 12px;
    }
    .cv-avatar {
        width: 40px; height: 40px; border-radius: 50%;
        background: {{ $accent }}; color: white;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
    }
    .cv-title { font-weight: 600; font-size: 15px; }
    .cv-subtitle { font-size: 12px; opacity: 0.7; }
    .cv-stream {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 8px 0 16px;
        overflow-y: auto;
    }
    .cv-msg {
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 18px;
        font-size: 15px;
        line-height: 1.4;
        white-space: pre-wrap;
        word-wrap: break-word;
        animation: cv-in 0.25s ease-out;
    }
    .cv-msg.bot  { background: {{ $bubbleBot }}; color: {{ $textColor }}; align-self: flex-start; border-bottom-left-radius: 4px; }
    .cv-msg.user { background: {{ $bubbleUser }}; color: white;          align-self: flex-end;   border-bottom-right-radius: 4px; }
    .cv-typing {
        background: {{ $bubbleBot }}; align-self: flex-start;
        padding: 12px 16px; border-radius: 18px;
        display: inline-flex; gap: 4px;
    }
    .cv-typing span {
        width: 7px; height: 7px; border-radius: 50%;
        background: rgba(255,255,255,0.6);
        animation: cv-bounce 1.2s infinite;
    }
    .cv-typing span:nth-child(2) { animation-delay: 0.15s; }
    .cv-typing span:nth-child(3) { animation-delay: 0.3s; }
    .cv-choices {
        display: flex; flex-wrap: wrap; gap: 8px;
        padding: 8px 0 16px;
    }
    .cv-choice {
        background: transparent;
        border: 1.5px solid {{ $accent }};
        color: {{ $textColor }};
        padding: 10px 16px;
        border-radius: 22px;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.15s;
        font-family: inherit;
    }
    .cv-choice:hover { background: {{ $accent }}; color: white; }
    .cv-input-row {
        display: flex; gap: 8px; padding: 8px 0;
    }
    .cv-input {
        flex: 1;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.12);
        color: {{ $textColor }};
        padding: 12px 14px;
        border-radius: 22px;
        font-size: 15px;
        font-family: inherit;
        outline: none;
    }
    .cv-input:focus { border-color: {{ $accent }}; }
    .cv-send {
        background: {{ $accent }}; color: white; border: none;
        padding: 0 20px; border-radius: 22px; cursor: pointer;
        font-size: 15px; font-weight: 600;
    }
    .cv-cta {
        display: inline-block;
        background: {{ $accent }}; color: white !important;
        padding: 12px 22px; border-radius: 12px;
        text-decoration: none; font-weight: 600;
        margin: 12px 0;
    }
    .cv-restart {
        margin-top: auto;
        padding: 12px 0;
        text-align: center;
        font-size: 12px;
        opacity: 0.55;
    }
    .cv-restart a { color: inherit; text-decoration: underline; cursor: pointer; }
    .cv-error {
        background: rgba(239, 68, 68, 0.15);
        color: #fecaca;
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 14px;
    }
    .cv-revealed-block {
        margin: 12px 0; padding: 16px;
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.08);
    }
    @keyframes cv-bounce { 0%, 80%, 100% { transform: translateY(0); opacity: 0.5; } 40% { transform: translateY(-6px); opacity: 1; } }
    @keyframes cv-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    .cv-msg:focus, .cv-choice:focus { outline: 2px solid {{ $accent }}; outline-offset: 2px; }
</style>
</head>
<body>
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

    <div class="cv-restart">
        <a id="cv-restart-link">Start over</a>
    </div>
</div>
<script>
(function () {
    const STREAM = document.getElementById('cv-stream');
    const INPUT_AREA = document.getElementById('cv-input-area');
    const ALIAS = @json($alias);
    const START_URL  = @json(rtrim(config('app.url'), '/') . '/cv/' . $alias . '/start');
    const ANSWER_URL = (id) => @json(rtrim(config('app.url'), '/') . '/cv/') + id + '/answer';
    const DROP_URL   = (id) => @json(rtrim(config('app.url'), '/') . '/cv/') + id + '/drop';
    const STORAGE_KEY = 'cv_page_session_' + ALIAS;
    const CSRF = '{{ csrf_token() }}';
    const TYPING_MS = 600;

    function makeId() {
        try {
            const a = new Uint8Array(16);
            crypto.getRandomValues(a);
            return 'pg_' + Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
        } catch (e) {
            return 'pg_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 14);
        }
    }
    let pageSessionId = localStorage.getItem(STORAGE_KEY);
    if (!pageSessionId) {
        pageSessionId = makeId();
        try { localStorage.setItem(STORAGE_KEY, pageSessionId); } catch (e) {}
    }
    let publicId = null;
    let busy = false;

    function pushBubble(text, who, opts) {
        opts = opts || {};
        const div = document.createElement('div');
        div.className = 'cv-msg ' + who;
        div.textContent = text;
        if (opts.html) { div.innerHTML = text; }
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

    function clearInputArea() { INPUT_AREA.innerHTML = ''; }

    function showError(msg) {
        const e = document.createElement('div');
        e.className = 'cv-error';
        e.textContent = msg;
        INPUT_AREA.appendChild(e);
    }

    async function botSay(text, then) {
        const t = pushTyping();
        await new Promise(r => setTimeout(r, TYPING_MS));
        t.remove();
        pushBubble(text, 'bot');
        if (then) await new Promise(r => setTimeout(r, 200));
        if (then) then();
    }

    function renderStep(step) {
        clearInputArea();
        botSay(step.message_text, () => {
            if (step.kind === 'question' && step.choices && step.choices.length) {
                const wrap = document.createElement('div');
                wrap.className = 'cv-choices';
                wrap.setAttribute('role', 'group');
                wrap.setAttribute('aria-label', 'Reply options');
                step.choices.forEach(c => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'cv-choice';
                    btn.textContent = c.label;
                    btn.addEventListener('click', () => sendAnswer({ choice_value: c.value, label: c.label }));
                    wrap.appendChild(btn);
                });
                INPUT_AREA.appendChild(wrap);
                wrap.querySelector('button')?.focus();
            } else if (step.kind === 'input') {
                const row = document.createElement('form');
                row.className = 'cv-input-row';
                row.innerHTML =
                    '<input class="cv-input" type="' + (step.input_kind === 'email' ? 'email' : 'text') +
                    '" placeholder="' + (step.placeholder || 'Type your answer…') + '" required aria-label="Your reply">' +
                    '<button class="cv-send" type="submit">Send</button>';
                row.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const val = row.querySelector('input').value.trim();
                    if (!val) return;
                    sendAnswer({ input_value: val, label: val });
                });
                INPUT_AREA.appendChild(row);
                row.querySelector('input').focus();
            } else {
                // message / end with no choices — auto-advance is handled server-side
                // by the next answer call; for end we never reach this branch.
                sendAnswer({});
            }
        });
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
                    choice_value: body.choice_value || null,
                    input_value:  body.input_value || null,
                }),
            });
            const j = await r.json();
            typing.remove();
            if (!j.ok) { showError(j.error || 'Something went wrong'); busy = false; return; }
            if (j.done) {
                renderEnding(j.action);
            } else {
                renderStep(j.step);
            }
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
            botSay(action.label || "Here's where you should go next:", () => {
                const a = document.createElement('a');
                a.href = action.url; a.className = 'cv-cta';
                a.target = '_blank'; a.rel = 'noopener';
                a.textContent = action.label || 'Open link';
                INPUT_AREA.appendChild(a);
                setTimeout(() => { window.location.href = action.url; }, 800);
            });
        } else if (action.kind === 'book_calendar' && action.url) {
            botSay(action.label || 'Pick a time that works for you:', () => {
                const a = document.createElement('a');
                a.href = action.url; a.className = 'cv-cta'; a.target = '_blank'; a.rel = 'noopener';
                a.textContent = 'Book a time';
                INPUT_AREA.appendChild(a);
            });
        } else if (action.kind === 'show_block') {
            botSay(action.label || "Here's what you're looking for:", () => {
                const div = document.createElement('div');
                div.className = 'cv-revealed-block';
                if (action.html) {
                    // Server pre-renders the same partial used by the
                    // static biolink view, so the live block (CTA,
                    // calendar, embed, etc.) drops straight into chat.
                    div.innerHTML = action.html;
                } else if (action.block_id) {
                    const a = document.createElement('a');
                    a.href = '/' + ALIAS + '#block-' + action.block_id;
                    a.className = 'cv-cta';
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.textContent = 'Open it';
                    div.appendChild(a);
                }
                INPUT_AREA.appendChild(div);
            });
        } else if (action.kind === 'message') {
            botSay(action.text || action.label || 'Thanks!');
        } else if (action.kind === 'capture_email') {
            botSay(action.label || 'Drop your email to stay in the loop:', () => {
                const form = document.createElement('form');
                form.className = 'cv-capture-email';
                form.innerHTML =
                    '<input type="email" required placeholder="you@example.com" class="cv-input" />' +
                    '<button type="submit" class="cv-cta">' +
                        (action.cta || 'Subscribe') +
                    '</button>' +
                    '<div class="cv-capture-status small text-muted mt-1"></div>';
                INPUT_AREA.appendChild(form);
                const status = form.querySelector('.cv-capture-status');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const email = form.querySelector('input').value.trim();
                    if (!email) return;
                    status.textContent = 'Saving…';
                    try {
                        const r = await fetch(
                            @json(rtrim(config('app.url'), '/') . '/cv/') + publicId + '/capture-email',
                            {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ email })
                            }
                        );
                        const j = await r.json();
                        if (j.ok) {
                            form.replaceWith(Object.assign(document.createElement('div'), {
                                className: 'cv-capture-done text-success small',
                                textContent: 'Thanks! You\'re on the list.',
                            }));
                        } else {
                            status.textContent = j.error || 'Something went wrong.';
                        }
                    } catch (err) {
                        status.textContent = 'Network error. Try again.';
                    }
                });
            });
        }
    }

    // Boot.
    fetch(START_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ page_session_id: pageSessionId })
    }).then(r => r.json()).then(j => {
        if (!j.ok) {
            pushBubble(j.error || "This conversation isn't ready yet.", 'bot');
            return;
        }
        publicId = j.session.id;
        if (j.flow.intro_message) {
            botSay(j.flow.intro_message, () => renderStep(j.step));
        } else {
            renderStep(j.step);
        }
    }).catch(() => {
        pushBubble('Could not load conversation.', 'bot');
    });

    document.getElementById('cv-restart-link').addEventListener('click', () => {
        localStorage.removeItem(STORAGE_KEY);
        location.reload();
    });

    // Best-effort drop-off ping.
    window.addEventListener('beforeunload', () => {
        if (publicId) {
            try {
                navigator.sendBeacon(DROP_URL(publicId), new Blob([JSON.stringify({})], { type: 'application/json' }));
            } catch (e) {}
        }
    });
})();
</script>
</body>
</html>
