<div class="card-premium p-6">
    <h3 class="text-sm font-bold mb-2" style="color:var(--text-primary)">Supporting links &amp; additional information</h3>
    <p class="text-xs mb-4" style="color:var(--text-muted)">Help the admin confirm your identity or organization. These details are included with your request for review.</p>
    <div class="space-y-3">
        @for($urlIndex=0;$urlIndex<5;$urlIndex++)
        <label class="block text-xs font-medium" style="color:var(--text-primary)">Supporting URL {{ $urlIndex+1 }}
            <input type="url" name="supporting_urls[]" value="{{ old('supporting_urls.'.$urlIndex) }}" maxlength="2048" placeholder="https://…" class="theme-input w-full text-sm mt-1" aria-describedby="verification-url-hint">
        </label>
        @endfor
        <p id="verification-url-hint" class="text-xs" style="color:var(--text-muted)">Up to five HTTP/HTTPS links: official website, social profiles, press coverage, or organization registry.</p>
        <label class="block text-xs font-medium" style="color:var(--text-primary)">Additional information
            <textarea name="additional_info" maxlength="4000" rows="4" class="theme-input w-full text-sm mt-1" placeholder="Your role, organization, public identity, and how the evidence relates to you…">{{ old('additional_info') }}</textarea>
        </label>
    </div>
</div>
