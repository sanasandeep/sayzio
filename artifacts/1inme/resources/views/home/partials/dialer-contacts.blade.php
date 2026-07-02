{{-- ============================ DIALER & CONTACTS ============================ --}}
<style>
    /* Dialer & Contacts section animations */
    .dc-wrap { position: relative; }
    .dc-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: rgba(27,212,217,.06);
        filter: blur(40px); pointer-events:none; z-index:0;
        animation: dcMesh 15s ease-in-out infinite alternate;
    }
    @keyframes dcMesh { 0% { transform: translate3d(0,0,0) scale(1); } 100% { transform: translate3d(-2%,2%,0) scale(1.06); } }

    /* Phone frame that houses the dialer + resolved caller card */
    .dc-phone {
        position: relative; width: 300px; max-width: 84vw; margin: 0 auto;
        aspect-ratio: 300 / 600; border-radius: 42px; padding: 12px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow:
            0 40px 90px -30px rgba(61,107,255,.55),
            0 14px 34px -12px rgba(0,0,0,.7),
            inset 0 0 0 1.5px rgba(255,255,255,.08);
        animation: dcFloat 6.5s ease-in-out infinite;
    }
    html.light-mode .dc-phone { background: linear-gradient(160deg, #101827, #060b16); }
    @keyframes dcFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
    .dc-screen {
        position: absolute; inset: 12px; border-radius: 32px; overflow: hidden;
        background: linear-gradient(180deg, #14091f 0%, #0a0a14 100%);
        display: flex; flex-direction: column;
    }
    .dc-notch {
        position:absolute; top: 8px; left: 50%; transform: translateX(-50%);
        width: 86px; height: 20px; border-radius: 999px; background: #05030a; z-index: 5;
    }

    /* Resolved caller-ID card that slides in over the keypad */
    .dc-callerid {
        margin: 30px 14px 0; border-radius: 18px; padding: 14px;
        background: linear-gradient(135deg, rgba(61,107,255,.22), rgba(27,212,217,.14));
        border: 1px solid rgba(255,255,255,.12);
        box-shadow: 0 18px 40px -20px rgba(61,107,255,.6);
        backdrop-filter: blur(8px);
    }
    .dc-avatar {
        width: 46px; height: 46px; border-radius: 14px; flex-shrink:0;
        background: linear-gradient(135deg, #3d6bff, #1bd4d9);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size: 17px;
        box-shadow: 0 8px 20px -6px rgba(61,107,255,.7);
    }
    .dc-verified { color:#3d6bff; font-size: 11px; }
    .dc-chan {
        width: 34px; height: 34px; border-radius: 11px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 13px; flex-shrink:0;
        transition: transform .25s ease;
    }
    .dc-chan:hover { transform: translateY(-3px); }

    /* T9 keypad */
    .dc-keys { margin: 16px 22px 20px; display:grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
    .dc-key {
        aspect-ratio: 1; border-radius: 999px;
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        color:#fff; line-height: 1;
    }
    .dc-key .d { font-size: 18px; font-weight: 700; }
    .dc-key .l { font-size: 7px; letter-spacing: .12em; color: rgba(255,255,255,.45); margin-top: 2px; }
    .dc-key.lit {
        background: linear-gradient(135deg, rgba(61,107,255,.35), rgba(27,212,217,.28));
        border-color: rgba(61,107,255,.55);
        box-shadow: 0 0 0 2px rgba(61,107,255,.25), 0 10px 24px -10px rgba(61,107,255,.8);
    }
    .dc-call {
        margin: 0 22px 18px; height: 50px; border-radius: 999px;
        background: linear-gradient(135deg, #16a34a, #22c55e);
        display:flex; align-items:center; justify-content:center; gap:8px;
        color:#fff; font-weight:700; font-size: 14px;
        box-shadow: 0 14px 30px -12px rgba(34,197,94,.8);
    }

    @media (prefers-reduced-motion: no-preference) {
        /* Sequentially light up keypad keys to "type" a number */
        .dc-armed .dc-key.k-seq { animation: dcKey 5.5s ease-in-out infinite; }
        @keyframes dcKey {
            0%, 8%   { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow:none; }
            10%, 22% { background: linear-gradient(135deg, rgba(61,107,255,.35), rgba(27,212,217,.28)); border-color: rgba(61,107,255,.55); box-shadow: 0 0 0 2px rgba(61,107,255,.25), 0 10px 24px -10px rgba(61,107,255,.8); }
            30%,100% { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow:none; }
        }
        .dc-armed .dc-callerid { animation: dcCard 5.5s ease-in-out infinite; }
        @keyframes dcCard {
            0%, 30%  { opacity: 0; transform: translateY(14px) scale(.97); }
            42%, 92% { opacity: 1; transform: translateY(0) scale(1); }
            100%     { opacity: 0; transform: translateY(-8px) scale(.99); }
        }
    }

    /* Feature pills */
    .dc-feat { transition: transform .35s ease, background .35s ease, border-color .35s ease; }
    .dc-feat:hover { transform: translateX(6px); border-color: rgba(27,212,217,.45); background: rgba(27,212,217,.08); }
    .dc-feat-icon {
        width: 44px; height: 44px; border-radius: 14px;
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
        color: #fff; box-shadow: 0 12px 28px -10px var(--dc-c, #3d6bff);
        background: var(--dc-c, #3d6bff); position: relative;
    }
    .dc-feat-icon::after {
        content:""; position:absolute; inset:-5px; border-radius:18px;
        border: 2px solid color-mix(in srgb, var(--dc-c, #3d6bff) 50%, transparent);
        opacity:.35; animation: dcPulse 2.6s ease-in-out infinite;
    }
    @keyframes dcPulse { 0%,100% { transform: scale(1); opacity:.25; } 50% { transform: scale(1.08); opacity:.6; } }

    .dc-bubble {
        position: absolute; padding: 8px 12px; border-radius: 14px;
        background: rgba(15,18,28,.85); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,.08); color:#fff;
        font-size: 11px; font-weight: 700; display:flex; align-items:center; gap:8px;
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7); z-index: 6;
        animation: dcBubble 6s ease-in-out infinite;
    }
    .dc-bubble i { color: #1bd4d9; }
    .dc-bubble-1 { top: 4%; right: -14px; animation-delay: .3s; }
    .dc-bubble-2 { bottom: 10%; left: -18px; animation-delay: 1.6s; }
    @keyframes dcBubble { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    @media (prefers-reduced-motion: reduce) {
        .dc-mesh::before, .dc-phone, .dc-bubble, .dc-feat-icon::after { animation: none !important; }
        .dc-callerid { opacity: 1 !important; transform: none !important; }
    }
</style>
<section id="dialer-contacts" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="dc-h">
    <div class="dc-mesh absolute inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Dialer &amp; Contacts</div>
            <h2 id="dc-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Your whole phonebook,<br><span class="grad-text">turned into profiles.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A smart T9 dialer, quick call / SMS / WhatsApp channels, two-way Google Contacts sync and phone-to-biolink caller&nbsp;ID &mdash; so every number in your address book becomes a rich, connected profile.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- LEFT: animated phone / dialer --}}
            <div class="reveal rd-3 dc-wrap relative flex justify-center">
                <div class="dc-phone" role="img" aria-label="Sayzio dialer resolving a phone number into a profile">
                    <div class="dc-screen">
                        <div class="dc-notch" aria-hidden="true"></div>

                        {{-- Resolved caller-ID card (phone number → biolink profile) --}}
                        <div class="dc-callerid">
                            <div class="flex items-center gap-3">
                                <div class="dc-avatar">AR</div>
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-white leading-tight flex items-center gap-1.5">
                                        Aisha Rahman <i class="fas fa-circle-check dc-verified"></i>
                                    </div>
                                    <div class="text-[11px] text-gray-300">@aisha &middot; on Sayzio</div>
                                </div>
                            </div>
                            <div class="text-[11px] text-gray-300 mt-2.5 flex items-center gap-1.5">
                                <i class="fas fa-phone text-[9px] text-emerald-400"></i> +1 (415) 555-0182
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <div class="dc-chan" style="background:#22c55e;" title="Call"><i class="fas fa-phone"></i></div>
                                <div class="dc-chan" style="background:#3d6bff;" title="SMS"><i class="fas fa-comment-sms"></i></div>
                                <div class="dc-chan" style="background:#25d366;" title="WhatsApp"><i class="fab fa-whatsapp"></i></div>
                                <div class="dc-chan" style="background:#229ed9;" title="Telegram"><i class="fab fa-telegram"></i></div>
                                <div class="dc-chan ml-auto" style="background:rgba(255,255,255,.12);" title="Open biolink"><i class="fas fa-link text-[11px]"></i></div>
                            </div>
                        </div>

                        {{-- T9 keypad --}}
                        <div class="dc-keys mt-auto" aria-hidden="true">
                            @php
                                $keys = [
                                    ['1',''], ['2','ABC'], ['3','DEF'],
                                    ['4','GHI'], ['5','JKL'], ['6','MNO'],
                                    ['7','PQRS'], ['8','TUV'], ['9','WXYZ'],
                                    ['*',''], ['0','+'], ['#',''],
                                ];
                                // Keys that light up in sequence to "dial" a number.
                                $seq = ['4','1','5'];
                            @endphp
                            @foreach($keys as $k)
                                <div class="dc-key {{ in_array($k[0], $seq, true) ? 'k-seq' : '' }}" @if(in_array($k[0], $seq, true)) style="animation-delay: {{ array_search($k[0], $seq, true) * 0.5 }}s;" @endif>
                                    <span class="d">{{ $k[0] }}</span>
                                    @if($k[1] !== '')<span class="l">{{ $k[1] }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                        <div class="dc-call" aria-hidden="true"><i class="fas fa-phone"></i> Call</div>
                    </div>
                </div>

                <div class="dc-bubble dc-bubble-1" aria-hidden="true"><i class="fas fa-rotate"></i> Google Contacts synced</div>
                <div class="dc-bubble dc-bubble-2" aria-hidden="true"><i class="fas fa-id-card" style="color:#ff8a3c;"></i> Card scanned &middot; 1.4s</div>
            </div>

            {{-- RIGHT: features --}}
            <div class="space-y-4">
                @foreach([
                    ['fa-phone-volume', '#1bd4d9', 'Smart T9 dialer &amp; keypad',   'Type a name on the keypad and T9 finds the contact. Flip to a full alphanumeric keyboard whenever you want.'],
                    ['fa-comments',     '#3d6bff', 'Quick channels, one tap',       'Call, SMS, WhatsApp, Telegram, Signal or Viber &mdash; jump straight into the right app from any contact.'],
                    ['fa-rotate',       '#e94e8c', 'Two-way Google Contacts sync',  'Contacts stay in lockstep with Google via the People API &mdash; add or edit anywhere, changes flow both ways.'],
                    ['fa-magnifying-glass', '#ff8a3c', 'Universal finder',          'One search spans contacts, people on Sayzio, your links, biolinks and workspaces &mdash; grouped and ready to act.'],
                    ['fa-id-card',      '#22c55e', 'AI business-card scanner',      'Snap a card or brochure and AI extracts the name, numbers, emails and socials into a clean new contact.'],
                    ['fa-address-card', '#22d3ee', 'Phone &rarr; biolink caller ID', 'Numbers resolve to rich Sayzio profiles, and a tap exports any contact as a shareable vCard.'],
                ] as $i => $f)
                    <div class="reveal rd-{{ ($i % 4) + 1 }} dc-feat glass rounded-2xl p-4 flex items-start gap-4">
                        <div class="dc-feat-icon" style="--dc-c: {{ $f[1] }};"><i class="fas {{ $f[0] }}"></i></div>
                        <div class="min-w-0">
                            <div class="text-base font-bold mb-1">{!! $f[2] !!}</div>
                            <div class="text-sm text-gray-400 leading-relaxed">{!! $f[3] !!}</div>
                        </div>
                    </div>
                @endforeach

                <div class="reveal rd-4 pt-3 flex flex-wrap items-center gap-3">
                    <a href="{{ route('site.dialer-contacts') }}" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Explore Dialer &amp; Contacts <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('site.dialer-contacts') }}#sync" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        See how sync works <i class="fas fa-rotate text-[10px]"></i>
                    </a>
                </div>

                <div class="reveal rd-4 pt-2">
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-gray-500 mb-2.5">Take the dialer with you</div>
                    @include('public.partials.store-buttons')
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var section = document.getElementById('dialer-contacts');
    if (!section) return;
    var wrap = section.querySelector('.dc-wrap');
    if (!wrap) return;

    var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMq.matches) return;
    if (typeof window.IntersectionObserver !== 'function') return;

    var io = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
            wrap.classList.toggle('dc-armed', entries[i].isIntersecting);
        }
    }, { threshold: 0.25, rootMargin: '0px 0px -10% 0px' });
    io.observe(section);
})();
</script>
