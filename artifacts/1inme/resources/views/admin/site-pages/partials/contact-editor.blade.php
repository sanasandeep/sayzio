@php
    $contactDefaults = \App\Modules\Common\Support\SitePagesContent::contactExtraDefault();
    $contactExtra = old('extra', is_array($page->extra) && !empty($page->extra)
        ? \App\Modules\Common\Support\SitePagesContent::normalizeContactExtra($page->extra)
        : $contactDefaults);
    $social = $contactExtra['social'] ?? $contactDefaults['social'];
    $map    = $contactExtra['map']    ?? $contactDefaults['map'];
@endphp

<div class="pt-2 border-t border-white/10 space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-white">Contact details</h3>
        <p class="text-xs text-white/50 mb-3">Shown in the "Contact details" card on /contact.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Address</label>
                <textarea name="extra[address]" rows="3" placeholder="Street, city, country" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactExtra['address'] ?? '' }}</textarea>
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Email</label>
                <input type="email" name="extra[email]" value="{{ $contactExtra['email'] ?? '' }}" placeholder="hello@example.com" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Phone</label>
                <input type="text" name="extra[phone]" value="{{ $contactExtra['phone'] ?? '' }}" placeholder="+91 …" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Hours</label>
                <textarea name="extra[hours]" rows="2" placeholder="Mon–Fri · 10:00 – 18:00" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactExtra['hours'] ?? '' }}</textarea>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-white">Social links</h3>
        <p class="text-xs text-white/50 mb-3">Leave blank to hide a network.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            @foreach (['twitter' => 'X (Twitter)', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'facebook' => 'Facebook'] as $key => $label)
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $label }}</label>
                    <input type="url" name="extra[social][{{ $key }}]" value="{{ $social[$key] ?? '' }}" placeholder="https://…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-white">Map (OpenStreetMap)</h3>
        <p class="text-xs text-white/50 mb-3">Defaults to our Hyderabad office. Change lat/lng to drop the marker elsewhere.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Latitude</label>
                <input type="number" step="any" min="-90" max="90" name="extra[map][lat]" value="{{ $map['lat'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lat')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Longitude</label>
                <input type="number" step="any" min="-180" max="180" name="extra[map][lng]" value="{{ $map['lng'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lng')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Zoom (1–19)</label>
                <input type="number" min="1" max="19" name="extra[map][zoom]" value="{{ $map['zoom'] ?? 14 }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.zoom')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Map caption</label>
                <input type="text" name="extra[map][label]" value="{{ $map['label'] ?? '' }}" placeholder="Our Hyderabad office" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
        </div>
    </div>
</div>
