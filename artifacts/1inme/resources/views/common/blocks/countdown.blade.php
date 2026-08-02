@php
    /* Rich countdown block renderer.
     *
     * The outer `.block-styled` wrapper (see common/biolink.blade.php) already
     * paints bg_color / border / shadow / font_family from `_style`, so here we
     * only render the timer content and consume the countdown-specific color
     * overrides for the digits, labels, and unit boxes.
     *
     * Ticking is plain inline JS (no Alpine dependency) gated on
     * DOMContentLoaded so it runs after @vite's deferred scripts. Each block
     * instance is scoped by a unique DOM id so multiple countdowns on one page
     * never collide. */
    $style = $s['_style'] ?? [];

    $title    = trim((string) ($s['title'] ?? ''));
    $subtitle = trim((string) ($s['subtitle'] ?? ''));
    $target   = trim((string) ($s['target_date'] ?? ''));

    // Unit toggles default to true when the key is missing (legacy blocks).
    $showDays    = array_key_exists('show_days', $s) ? (bool) $s['show_days'] : true;
    $showHours   = array_key_exists('show_hours', $s) ? (bool) $s['show_hours'] : true;
    $showMinutes = array_key_exists('show_minutes', $s) ? (bool) $s['show_minutes'] : true;
    $showSeconds = array_key_exists('show_seconds', $s) ? (bool) $s['show_seconds'] : true;

    $labelStyleRaw = $s['label_style'] ?? 'full';
    $labelStyle = in_array($labelStyleRaw, ['full', 'short', 'hidden'], true) ? $labelStyleRaw : 'full';

    $expiredMessage = trim((string) ($s['expired_message'] ?? "Time's up!"));
    if ($expiredMessage === '') $expiredMessage = "Time's up!";
    $expiredActionRaw = $s['expired_action'] ?? 'message';
    $expiredAction  = in_array($expiredActionRaw, ['message', 'hide_block'], true) ? $expiredActionRaw : 'message';

    $buttonText = trim((string) ($s['button_text'] ?? ''));
    $buttonUrl  = trim((string) ($s['button_url'] ?? ''));
    $hasCta     = $buttonText !== '' && $buttonUrl !== '';

    // Countdown-specific colors — fall back to the block/theme font color.
    $digitColor = $style['_countdown_digit_color'] ?? '';
    if ($digitColor === '') $digitColor = $style['text_color'] ?? $fontColor;
    $labelColor = $style['_countdown_label_color'] ?? '';
    if ($labelColor === '') $labelColor = $fontColor;
    $boxBg = $style['_countdown_box_bg'] ?? '';

    // Inline variant => plain text digits, no unit boxes.
    $isInline = ($style['display_mode'] ?? 'card') === 'content';
    $variant  = $style['_variant'] ?? '';

    // Full vs short unit labels.
    $labelMap = $labelStyle === 'short'
        ? ['days' => 'D', 'hours' => 'H', 'minutes' => 'M', 'seconds' => 'S']
        : ['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Min', 'seconds' => 'Sec'];

    $units = [];
    if ($showDays)    $units['days']    = $labelMap['days'];
    if ($showHours)   $units['hours']   = $labelMap['hours'];
    if ($showMinutes) $units['minutes'] = $labelMap['minutes'];
    if ($showSeconds) $units['seconds'] = $labelMap['seconds'];
    // Never render an empty timer.
    if (empty($units)) { $units = ['days' => $labelMap['days'], 'hours' => $labelMap['hours'], 'minutes' => $labelMap['minutes'], 'seconds' => $labelMap['seconds']]; }

    $uid = 'cd_' . ($block->id ?? uniqid());
    $boxStyle = ($boxBg !== '' && $boxBg !== 'transparent' && !$isInline)
        ? "background:{$boxBg};border-radius:12px;padding:10px 6px;min-width:56px;"
        : ($isInline ? '' : 'min-width:52px;');

    // Dimmed subtitle / unit-label styles. We MUST NOT append a hex-alpha
    // suffix to the label color — several variants set _countdown_label_color
    // to an rgba(...) value (glass_cards, gradient_pop_cd, ...), where "b3"
    // would produce invalid CSS and hide the text. Use CSS `opacity` on the
    // element instead, which is color-format agnostic (hex, rgba, named).
    $subtitleStyle   = "color:{$labelColor};opacity:0.7;";
    $unitLabelStyle  = "color:{$labelColor};opacity:0.6;";

    // CTA button colors. Each variant SHOULD provide an explicit, tested
    // pair (_countdown_cta_bg / _countdown_cta_text) so the button never
    // depends on fragile derivation from the digit color (which is often
    // white and previously produced an invisible "white pill, white text"
    // button on glass/gradient variants). When a variant omits them we fall
    // back to safe, high-contrast defaults.
    $isSolidColor = fn($v) => is_string($v) && $v !== '' && $v !== 'transparent' && !preg_match('/gradient\(/i', $v);

    $ctaBg   = $style['_countdown_cta_bg']   ?? '';
    $ctaText = $style['_countdown_cta_text'] ?? '';

    // Fallback CTA background: prefer the (solid) digit color, else a solid
    // card bg, else a neutral dark chip. Never a gradient string.
    if (!$isSolidColor($ctaBg)) {
        $ctaBg = $isSolidColor($digitColor) ? $digitColor
            : ($isSolidColor($style['bg_color'] ?? '') ? $style['bg_color'] : '#111827');
    }
    // Fallback CTA text color: pick black/white for contrast against $ctaBg.
    if (!$isSolidColor($ctaText)) {
        $hex = ltrim((string) $ctaBg, '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $lum = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2)));
            $ctaText = $lum > 150 ? '#111827' : '#ffffff';
        } else {
            $ctaText = '#111827';
        }
    }
@endphp

<div id="{{ $uid }}" class="mb-1 text-center"
     data-countdown-target="{{ $target }}"
     data-countdown-expired-action="{{ $expiredAction }}">
    @if($title !== '')
        <p class="text-sm font-semibold mb-1" style="color:{{ $labelColor }};">{{ $title }}</p>
    @endif
    @if($subtitle !== '')
        <p class="text-xs mb-3" style="{{ $subtitleStyle }}">{{ $subtitle }}</p>
    @endif

    <div data-countdown-timer class="flex justify-center items-baseline {{ $isInline ? 'gap-1' : 'gap-3' }} flex-wrap">
        @foreach($units as $unitKey => $unitLabel)
            @if($isInline && !$loop->first)
                <span class="text-2xl font-bold" style="color:{{ $digitColor }};opacity:0.5;">:</span>
            @endif
            <div class="{{ $isInline ? 'inline-flex items-baseline gap-1' : 'flex flex-col items-center' }}" style="{{ $boxStyle }}">
                <span data-cd-unit="{{ $unitKey }}" class="{{ $isInline ? 'text-2xl' : 'text-3xl' }} font-bold leading-none tabular-nums" style="color:{{ $digitColor }};">0</span>
                @if($labelStyle !== 'hidden')
                    <span class="text-[10px] uppercase tracking-wider {{ $isInline ? 'ml-0.5' : 'mt-1.5' }}" style="{{ $unitLabelStyle }}">{{ $unitLabel }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <p data-countdown-expired class="hidden text-base font-semibold py-2" style="color:{{ $digitColor }};">{{ $expiredMessage }}</p>

    @if($hasCta)
        <a href="{{ $buttonUrl }}" target="_blank" rel="noopener"
           class="inline-block mt-3 text-center font-semibold transition-all duration-300 hover:-translate-y-0.5"
           style="background:{{ $ctaBg }};color:{{ $ctaText }};padding:10px 22px;border-radius:10px;font-size:14px;">
            {{ $buttonText }}
        </a>
    @endif
</div>

<script>
(function () {
    function initCountdown{{ str_replace(['-', '.'], '_', $uid) }}() {
        var root = document.getElementById(@js($uid));
        if (!root || root.dataset.cdInit === '1') return;
        root.dataset.cdInit = '1';

        var targetStr = root.getAttribute('data-countdown-target') || '';
        var expiredAction = root.getAttribute('data-countdown-expired-action') || 'message';
        var timerEl = root.querySelector('[data-countdown-timer]');
        var expiredEl = root.querySelector('[data-countdown-expired]');
        var target = targetStr ? new Date(targetStr.replace(' ', 'T')).getTime() : NaN;

        function setUnit(key, val) {
            var el = root.querySelector('[data-cd-unit="' + key + '"]');
            if (el) el.textContent = (val < 10 ? '0' : '') + val;
        }

        function tick() {
            var now = Date.now();
            var diff = isNaN(target) ? -1 : (target - now);
            if (diff <= 0) {
                setUnit('days', 0); setUnit('hours', 0); setUnit('minutes', 0); setUnit('seconds', 0);
                if (expiredAction === 'hide_block') {
                    var wrap = root.closest('.biolink-block-wrap') || root;
                    wrap.style.display = 'none';
                } else {
                    if (timerEl) timerEl.classList.add('hidden');
                    if (expiredEl) expiredEl.classList.remove('hidden');
                }
                if (window.__cdTimers && window.__cdTimers[@js($uid)]) {
                    clearInterval(window.__cdTimers[@js($uid)]);
                }
                return;
            }
            var s = Math.floor(diff / 1000);
            setUnit('days', Math.floor(s / 86400));
            setUnit('hours', Math.floor((s % 86400) / 3600));
            setUnit('minutes', Math.floor((s % 3600) / 60));
            setUnit('seconds', s % 60);
        }

        window.__cdTimers = window.__cdTimers || {};
        tick();
        window.__cdTimers[@js($uid)] = setInterval(tick, 1000);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCountdown{{ str_replace(['-', '.'], '_', $uid) }});
    } else {
        initCountdown{{ str_replace(['-', '.'], '_', $uid) }}();
    }
})();
</script>
