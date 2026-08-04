@php
    /**
     * Reusable "Embed on your site" settings panel (task #2617).
     * Shared by the biolink settings tab and every non-biolink edit screen.
     *
     * @var \App\Modules\User\Models\Link $link
     */
    $isCard       = $link->isEmbedCard();
    $scriptSnippet = $link->embedScriptSnippet();
    $iframeSnippet = $link->embedIframeSnippet();
    // The live preview renders inside the editor, so load it same-origin
    // (relative path) — it always resolves against whatever host the editor
    // is currently served on. Only the copyable snippets above need the
    // real public host, since those get pasted onto external sites.
    $previewSrc   = '/embed/link/' . $link->alias . '/iframe';
    $kindLabel    = $isCard ? 'compact action card' : 'responsive page';
@endphp

{{-- Theme-aware colors (task #6684): the panel used dark-mode-hardcoded
     white/black opacity classes that were illegible in light mode. Neutral
     text uses the shared --text-* vars; the few accent/surface colors that
     need a different light value get paired html.light-mode rules here
     (per-element, no blanket overrides). Colors only — no layout changes. --}}
<style>
    .embp-badge { background: rgba(59,130,246,0.10); color: #93c5fd; border: 1px solid rgba(59,130,246,0.20); }
    html.light-mode .embp-badge { color: #1d4ed8; border-color: rgba(29,78,216,0.25); }
    .embp-accent { color: #93c5fd; }
    html.light-mode .embp-accent { color: #1d4ed8; }
    .embp-copy { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.70); }
    .embp-copy:hover { background: rgba(255,255,255,0.10); color: #fff; }
    html.light-mode .embp-copy { background: rgba(15,23,42,0.06); color: #334155; }
    html.light-mode .embp-copy:hover { background: rgba(15,23,42,0.10); color: #0f172a; }
    .embp-copied { color: #6ee7b7; }
    html.light-mode .embp-copied { color: #047857; }
    .embp-pre { background: rgba(0,0,0,0.30); border: 1px solid rgba(255,255,255,0.10); color: rgba(255,255,255,0.80); }
    html.light-mode .embp-pre { background: #f1f5f9; border-color: rgba(15,23,42,0.12); color: #1e293b; }
    .embp-preview-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.10); }
    html.light-mode .embp-preview-box { background: rgba(15,23,42,0.03); border-color: rgba(15,23,42,0.10); }
</style>
<div class="glass rounded-2xl p-6 mb-6"
     x-data="{
        copied: '',
        copy(which, text) {
            const done = () => { this.copied = which; setTimeout(() => { if (this.copied === which) this.copied = ''; }, 1800); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(() => this.fallback(text, done));
            } else { this.fallback(text, done); }
        },
        fallback(text, done) {
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
     }">
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Embed on your site</h2>
            <p class="text-xs mt-1" style="color: var(--text-muted);">
                Drop this link onto any external website, blog, or docs page. It renders as a {{ $kindLabel }} and
                every view &amp; click still counts in your analytics. Private links show a &ldquo;view on site&rdquo; prompt instead.
            </p>
        </div>
        <span class="text-[10px] px-2 py-1 rounded-md flex-shrink-0 embp-badge">
            <i class="fas fa-code mr-1"></i>{{ $isCard ? 'Card' : 'Iframe' }}
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-4 items-start">
        {{-- Snippets --}}
        <div class="space-y-5">
            {{-- Script (auto-render) --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-medium" style="color: var(--text-secondary);">
                        <i class="fas fa-bolt embp-accent mr-1"></i>Auto-render script <span class="font-normal" style="color: var(--text-muted);">(recommended)</span>
                    </label>
                    <button type="button"
                            @click="copy('script', @js($scriptSnippet))"
                            class="text-[11px] px-2.5 py-1 rounded-md embp-copy transition">
                        <span x-show="copied !== 'script'"><i class="far fa-copy mr-1"></i>Copy</span>
                        <span x-show="copied === 'script'" x-cloak class="embp-copied"><i class="fas fa-check mr-1"></i>Copied</span>
                    </button>
                </div>
                <pre class="text-[11px] leading-relaxed embp-pre rounded-xl p-3 overflow-x-auto whitespace-pre-wrap break-all"><code>{{ $scriptSnippet }}</code></pre>
                <p class="text-[11px] mt-1" style="color: var(--text-muted);">Resizes itself to fit the content automatically.</p>
            </div>

            {{-- Static iframe --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-medium" style="color: var(--text-secondary);">
                        <i class="fas fa-window-maximize mr-1" style="color: var(--text-muted);"></i>Static iframe <span class="font-normal" style="color: var(--text-muted);">(no JavaScript)</span>
                    </label>
                    <button type="button"
                            @click="copy('iframe', @js($iframeSnippet))"
                            class="text-[11px] px-2.5 py-1 rounded-md embp-copy transition">
                        <span x-show="copied !== 'iframe'"><i class="far fa-copy mr-1"></i>Copy</span>
                        <span x-show="copied === 'iframe'" x-cloak class="embp-copied"><i class="fas fa-check mr-1"></i>Copied</span>
                    </button>
                </div>
                <pre class="text-[11px] leading-relaxed embp-pre rounded-xl p-3 overflow-x-auto whitespace-pre-wrap break-all"><code>{{ $iframeSnippet }}</code></pre>
                <p class="text-[11px] mt-1" style="color: var(--text-muted);">Paste straight into your page&rsquo;s HTML. Fixed height &mdash; prefer the auto-render script if you can run JavaScript.</p>
            </div>
        </div>

        {{-- Live preview --}}
        <div>
            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                <i class="fas fa-eye mr-1" style="color: var(--text-muted);"></i>Live preview
            </label>
            <div class="rounded-xl embp-preview-box p-3 overflow-hidden">
                <iframe src="{{ $previewSrc }}"
                        title="Embed preview"
                        loading="lazy"
                        data-embed-preview="{{ $link->alias }}"
                        class="w-full rounded-lg border-0 bg-transparent"
                        style="{{ $isCard ? 'height:200px;' : 'height:460px;' }} width:100%;"></iframe>
            </div>
            <p class="text-[11px] mt-1" style="color: var(--text-muted);">This is exactly what visitors see when embedded.</p>
            {{-- Auto-fit the preview to its content (task #6712): the card
                 document posts a `1inme-embed-resize` message (same channel the
                 public auto-render loader listens on); resize the preview iframe
                 to match so short embeds don't leave a blank area. Full-page
                 previews never post, so they keep the tall fallback height. --}}
            <script>
                (function () {
                    var alias = @js($link->alias);
                    window.addEventListener('message', function (e) {
                        var d = e.data;
                        if (!d || d.type !== '1inme-embed-resize' || d.alias !== alias) return;
                        if (!(d.height > 0)) return;
                        var frames = document.querySelectorAll('iframe[data-embed-preview="' + alias.replace(/"/g, '') + '"]');
                        for (var i = 0; i < frames.length; i++) {
                            frames[i].style.height = Math.ceil(d.height) + 'px';
                        }
                    });
                })();
            </script>
        </div>
    </div>
</div>
