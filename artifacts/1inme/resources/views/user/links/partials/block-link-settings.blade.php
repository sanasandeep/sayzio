@php $lk = $imgLink ?? []; @endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showLink: {{ !empty($lk['url']) ? 'true' : 'false' }}, showUtm: false }">
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

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);">
            <button type="button" @click="showUtm = !showUtm"
                    class="flex items-center gap-2 text-xs font-medium py-1" style="color: var(--text-muted);">
                <i class="fas fa-tags text-purple-400"></i>
                <span>UTM Parameters</span>
                <i :class="showUtm ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[9px] ml-1"></i>
            </button>

            <div x-show="showUtm" x-cloak x-transition class="mt-2 space-y-2">
                <div>
                    <label class="{{ $labelClass }}">utm_source</label>
                    <input type="text" name="settings[_link][utm_source]" value="{{ $lk['utm_source'] ?? '' }}" placeholder="e.g. biolink" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">utm_medium</label>
                    <input type="text" name="settings[_link][utm_medium]" value="{{ $lk['utm_medium'] ?? '' }}" placeholder="e.g. social" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">utm_campaign</label>
                    <input type="text" name="settings[_link][utm_campaign]" value="{{ $lk['utm_campaign'] ?? '' }}" placeholder="e.g. spring-sale" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">utm_term</label>
                    <input type="text" name="settings[_link][utm_term]" value="{{ $lk['utm_term'] ?? '' }}" placeholder="e.g. keyword" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">utm_content</label>
                    <input type="text" name="settings[_link][utm_content]" value="{{ $lk['utm_content'] ?? '' }}" placeholder="e.g. banner-top" class="{{ $inputClass }}">
                </div>
            </div>
        </div>

    </div>
</div>
