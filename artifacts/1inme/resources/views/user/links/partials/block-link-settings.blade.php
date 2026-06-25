@php
    $lk = $imgLink ?? [];
    $autoUtm = is_array($lk['auto_utm'] ?? null) ? $lk['auto_utm'] : [];
    $autoUtmOverrides = is_array($autoUtm['overrides'] ?? null) ? $autoUtm['overrides'] : [];
    $autoUtmEnabled = $autoUtm['enabled'] ?? 'inherit';
    if (!in_array($autoUtmEnabled, ['inherit', 'on', 'off'], true)) $autoUtmEnabled = 'inherit';

    // Snapshot of biolink-wide Auto-UTM settings + this block's identity so
    // the JS preview can resolve {slug}/{block} tokens client-side without
    // a round trip on every keystroke. We let the server-side AutoUtmBuilder
    // own the token-resolution logic (field fallback + slugify) so the
    // preview is byte-identical to what the redirect controller emits.
    $bsAutoUtm = $link->settings['biolink']['auto_utm'] ?? [];
    $autoUtmTokens = app(\App\Modules\Common\Services\AutoUtmBuilder::class)
        ->tokensFor($link, $block);
    $autoUtmContext = [
        'biolink_enabled'  => !empty($bsAutoUtm['enabled']),
        'biolink_defaults' => is_array($bsAutoUtm['defaults'] ?? null) ? $bsAutoUtm['defaults'] : [],
        'tokens'           => $autoUtmTokens,
    ];
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showLink: {{ !empty($lk['url']) ? 'true' : 'false' }}, showUtm: false, showPreview: false, previewUrl: '', previewLoading: false, previewError: '' }">
    <button type="button" @click="showLink = !showLink"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-arrow-up-right-from-square mr-2 text-emerald-400"></i>Trackable Link</span>
        <i :class="showLink ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showLink" x-cloak x-transition class="mt-3 space-y-3">

        <p class="text-[10px] px-2 py-1.5 rounded-lg" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.12); color: var(--text-dimmed);">
            <i class="fas fa-chart-line mr-1 text-emerald-400"></i>Clicks on this link are tracked in your analytics
        </p>

        <div>
            <label class="{{ $labelClass }}">Destination URL</label>
            <input type="url" name="settings[_link][url]" value="{{ $lk['url'] ?? '' }}" placeholder="https://example.com" class="{{ $inputClass }}">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $labelClass }}">Target</label>
                <select name="settings[_link][target]" class="{{ $inputClass }}">
                    <option value="_blank" {{ ($lk['target'] ?? '_blank') === '_blank' ? 'selected' : '' }}>New Tab (_blank)</option>
                    <option value="_self" {{ ($lk['target'] ?? '') === '_self' ? 'selected' : '' }}>Same Tab (_self)</option>
                </select>
            </div>
            <div>
                <label class="{{ $labelClass }}">Rel Attribute</label>
                <select name="settings[_link][rel]" class="{{ $inputClass }}">
                    <option value="noopener" {{ ($lk['rel'] ?? 'noopener') === 'noopener' ? 'selected' : '' }}>noopener</option>
                    <option value="noopener nofollow" {{ ($lk['rel'] ?? '') === 'noopener nofollow' ? 'selected' : '' }}>noopener nofollow</option>
                    <option value="noopener noreferrer" {{ ($lk['rel'] ?? '') === 'noopener noreferrer' ? 'selected' : '' }}>noopener noreferrer</option>
                    <option value="noopener noreferrer nofollow" {{ ($lk['rel'] ?? '') === 'noopener noreferrer nofollow' ? 'selected' : '' }}>noopener noreferrer nofollow</option>
                    <option value="sponsored" {{ ($lk['rel'] ?? '') === 'sponsored' ? 'selected' : '' }}>sponsored</option>
                    <option value="ugc" {{ ($lk['rel'] ?? '') === 'ugc' ? 'selected' : '' }}>ugc</option>
                </select>
            </div>
        </div>

        <div>
            <label class="{{ $labelClass }}">Title / Tooltip</label>
            <input type="text" name="settings[_link][title]" value="{{ $lk['title'] ?? '' }}" placeholder="Hover tooltip text" class="{{ $inputClass }}">
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);"
             x-data='autoUtmBlock(@json($autoUtmContext), @json($autoUtmOverrides), @json($autoUtmEnabled))'>
            <button type="button" @click="showUtm = !showUtm"
                    class="flex items-center gap-2 text-xs font-medium py-1" style="color: var(--text-muted);">
                <i class="fas fa-tags text-violet-400"></i>
                <span>Auto-UTM &amp; Overrides</span>
                <span x-show="biolinkEnabled" class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: rgba(139,92,246,0.15); color: #8b5cf6;">ON</span>
                <i :class="showUtm ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[9px] ml-auto"></i>
            </button>

            <div x-show="showUtm" x-cloak x-transition class="mt-2 space-y-3">
                <div>
                    <label class="{{ $labelClass }}">Auto-UTM for this block</label>
                    <select x-model="enabled" name="settings[_link][auto_utm][enabled]" class="{{ $inputClass }}">
                        {{-- Resolve the biolink-wide state server-side so the
                             label is plain text (nested elements inside
                             <option> are invalid HTML and don't render
                             reliably across browsers). --}}
                        <option value="inherit">Inherit from Link in Bio ({{ !empty($bsAutoUtm['enabled']) ? 'on' : 'off' }})</option>
                        <option value="on">Always on</option>
                        <option value="off">Off</option>
                    </select>
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">
                        Existing UTM params on the destination URL are always preserved.
                    </p>
                </div>

                <div class="space-y-2" x-show="effectiveOn()">
                    <p class="text-[10px]" style="color: var(--text-dimmed);">Override individual params (leave blank to use the biolink default):</p>
                    @foreach([
                        'utm_source'   => '1inme',
                        'utm_medium'   => 'biolink',
                        'utm_campaign' => '{slug}',
                        'utm_term'     => '',
                        'utm_content'  => '{block}',
                    ] as $k => $defaultPlaceholder)
                        <div>
                            <label class="{{ $labelClass }}">{{ $k }}</label>
                            <input type="text"
                                   name="settings[_link][auto_utm][overrides][{{ $k }}]"
                                   x-model="overrides['{{ $k }}']"
                                   placeholder="{{ $defaultPlaceholder ?: 'optional' }}"
                                   class="{{ $inputClass }}">
                        </div>
                    @endforeach
                </div>

                <div class="pt-2" style="border-top: 1px dashed var(--border-subtle);">
                    <label class="{{ $labelClass }}">Resolved URL preview</label>
                    <div class="px-2 py-2 rounded-lg text-[11px] break-all font-mono"
                         style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary); min-height: 36px;"
                         x-text="resolvedUrl(document.querySelector('input[name=&quot;settings[_link][url]&quot;]')?.value || '')"></div>
                </div>
            </div>
        </div>

    </div>
</div>

@once
<script>
// Mirrors AutoUtmBuilder.php exactly so the editor preview is the same
// URL the redirect controller will emit. `tokens` (slug/alias/block/
// block_id/link_id) are pre-resolved server-side via the same buildTokens
// + slugify path so we don't drift from the backend.
window.autoUtmBlock = function (ctx, overrides, enabled) {
    const KEYS = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'];
    const BUILTIN_DEFAULTS = { utm_source: '1inme', utm_medium: 'biolink', utm_campaign: '{slug}', utm_content: '{block}' };
    return {
        biolinkEnabled: !!ctx.biolink_enabled,
        biolinkDefaults: ctx.biolink_defaults || {},
        tokens: ctx.tokens || {},
        enabled: enabled || 'inherit',
        overrides: KEYS.reduce((a, k) => (a[k] = (overrides && overrides[k]) || '', a), {}),
        showUtm: false,
        effectiveOn() {
            if (this.enabled === 'on') return true;
            if (this.enabled === 'off') return false;
            return this.biolinkEnabled;
        },
        resolveTokens(v) {
            if (!v) return v;
            return String(v).replace(/\{([a-z_]+)\}/g, (_, name) => this.tokens[name] ?? '');
        },
        resolvedUrl(rawUrl) {
            if (!rawUrl) return '(set a destination URL above)';
            // Mirror the PHP split: separate fragment + query so we
            // never drop them and "creator-set wins" by exact key
            // presence (an explicit `?utm_source=` sticks).
            const hashAt = rawUrl.indexOf('#');
            const fragment = hashAt !== -1 ? rawUrl.slice(hashAt) : '';
            const base = hashAt !== -1 ? rawUrl.slice(0, hashAt) : rawUrl;
            const queryAt = base.indexOf('?');
            const pathPart = queryAt !== -1 ? base.slice(0, queryAt) : base;
            const qsRaw = queryAt !== -1 ? base.slice(queryAt + 1) : '';
            const params = new URLSearchParams(qsRaw);
            if (this.effectiveOn()) {
                for (const k of KEYS) {
                    let v = (this.overrides[k] || '').trim();
                    if (!v) {
                        const tpl = (this.biolinkDefaults[k] || '').trim()
                            || BUILTIN_DEFAULTS[k] || '';
                        if (tpl) v = this.resolveTokens(tpl).trim();
                    } else {
                        v = this.resolveTokens(v).trim();
                    }
                    if (v === '' || params.has(k)) continue;
                    // Per-block override always wins over biolink default
                    // even if it ultimately renders to empty — but we still
                    // skip empty so we don't pollute the URL with `?utm_x=`.
                    params.set(k, v);
                }
            }
            const qs = params.toString();
            return pathPart + (qs ? '?' + qs : '') + fragment;
        },
    };
};
</script>
@endonce
