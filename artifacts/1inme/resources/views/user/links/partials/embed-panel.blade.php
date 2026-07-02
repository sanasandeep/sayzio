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
            <h2 class="text-lg font-semibold text-white">Embed on your site</h2>
            <p class="text-xs text-white/40 mt-1">
                Drop this link onto any external website, blog, or docs page. It renders as a {{ $kindLabel }} and
                every view &amp; click still counts in your analytics. Private links show a &ldquo;view on site&rdquo; prompt instead.
            </p>
        </div>
        <span class="text-[10px] px-2 py-1 rounded-md flex-shrink-0 bg-blue-500/10 text-blue-300 border border-blue-500/20">
            <i class="fas fa-code mr-1"></i>{{ $isCard ? 'Card' : 'Iframe' }}
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-4">
        {{-- Snippets --}}
        <div class="space-y-5">
            {{-- Script (auto-render) --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-medium text-white/70">
                        <i class="fas fa-bolt text-blue-300 mr-1"></i>Auto-render script <span class="text-white/30 font-normal">(recommended)</span>
                    </label>
                    <button type="button"
                            @click="copy('script', @js($scriptSnippet))"
                            class="text-[11px] px-2.5 py-1 rounded-md bg-white/5 hover:bg-white/10 text-white/70 hover:text-white transition">
                        <span x-show="copied !== 'script'"><i class="far fa-copy mr-1"></i>Copy</span>
                        <span x-show="copied === 'script'" x-cloak class="text-emerald-300"><i class="fas fa-check mr-1"></i>Copied</span>
                    </button>
                </div>
                <pre class="text-[11px] leading-relaxed bg-black/30 border border-white/10 rounded-xl p-3 overflow-x-auto text-white/80 whitespace-pre-wrap break-all"><code>{{ $scriptSnippet }}</code></pre>
                <p class="text-[11px] text-white/30 mt-1">Resizes itself to fit the content automatically.</p>
            </div>

            {{-- Static iframe --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-medium text-white/70">
                        <i class="fas fa-window-maximize text-white/40 mr-1"></i>Static iframe <span class="text-white/30 font-normal">(no JavaScript)</span>
                    </label>
                    <button type="button"
                            @click="copy('iframe', @js($iframeSnippet))"
                            class="text-[11px] px-2.5 py-1 rounded-md bg-white/5 hover:bg-white/10 text-white/70 hover:text-white transition">
                        <span x-show="copied !== 'iframe'"><i class="far fa-copy mr-1"></i>Copy</span>
                        <span x-show="copied === 'iframe'" x-cloak class="text-emerald-300"><i class="fas fa-check mr-1"></i>Copied</span>
                    </button>
                </div>
                <pre class="text-[11px] leading-relaxed bg-black/30 border border-white/10 rounded-xl p-3 overflow-x-auto text-white/80 whitespace-pre-wrap break-all"><code>{{ $iframeSnippet }}</code></pre>
                <p class="text-[11px] text-white/30 mt-1">Paste straight into your page&rsquo;s HTML.</p>
            </div>
        </div>

        {{-- Live preview --}}
        <div>
            <label class="block text-xs font-medium text-white/70 mb-1.5">
                <i class="fas fa-eye text-white/40 mr-1"></i>Live preview
            </label>
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 overflow-hidden">
                <iframe src="{{ $previewSrc }}"
                        title="Embed preview"
                        loading="lazy"
                        class="w-full rounded-lg border-0 bg-transparent"
                        style="{{ $isCard ? 'height:200px;' : 'height:460px;' }} width:100%;"></iframe>
            </div>
            <p class="text-[11px] text-white/30 mt-1">This is exactly what visitors see when embedded.</p>
        </div>
    </div>
</div>
