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
        <p class="text-xs text-white/50 mb-3">Defaults to our Hyderabad office. Change lat/lng to drop the marker elsewhere, or click on the preview to set the location.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Latitude</label>
                <input type="number" step="any" min="-90" max="90" name="extra[map][lat]" id="contact-map-lat" value="{{ $map['lat'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lat')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Longitude</label>
                <input type="number" step="any" min="-180" max="180" name="extra[map][lng]" id="contact-map-lng" value="{{ $map['lng'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lng')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Zoom (1–19)</label>
                <input type="number" min="1" max="19" name="extra[map][zoom]" id="contact-map-zoom" value="{{ $map['zoom'] ?? 14 }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.zoom')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Map caption</label>
                <input type="text" name="extra[map][label]" id="contact-map-label" value="{{ $map['label'] ?? '' }}" placeholder="Our Hyderabad office" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Live preview</label>
                <div class="aspect-[16/9] w-full rounded-lg overflow-hidden border border-white/10 bg-white/5">
                    <div id="contact-map-preview" style="width:100%; height:100%;"></div>
                </div>
                <p class="mt-1.5 text-[11px] text-white/40">Click anywhere on the map to update the latitude and longitude. Use the +/− controls (or scroll while hovering) to change zoom.</p>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
      integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    #contact-map-preview { background:#1e2330; }
    #contact-map-preview .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    #contact-map-preview .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    #contact-map-preview .leaflet-control-attribution a { color:#a78bfa !important; }
    #contact-map-preview .leaflet-control-zoom a {
        background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important;
    }
    #contact-map-preview .leaflet-control-zoom a:hover { background:#7c3aed !important; }
    .admin-brand-marker {
        width:34px; height:44px; position:relative;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45));
    }
    .admin-brand-marker svg { width:100%; height:100%; display:block; }
    .admin-brand-marker .pulse {
        position:absolute; left:50%; bottom:-4px; width:14px; height:14px;
        margin-left:-7px; border-radius:9999px;
        background:rgba(124,58,237,0.55);
        animation: admin-brand-marker-pulse 1.8s ease-out infinite;
    }
    @keyframes admin-brand-marker-pulse {
        0% { transform:scale(0.6); opacity:0.9; }
        100% { transform:scale(2.2); opacity:0; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
        integrity="sha512-BB3hKbKWOc9Ez/TAwyWxNXeoV9c1v6FIeYiBieIWkpLjauysF18NzgR1MBNBXf8/KABdlkX68nAhlwcDFLGPCQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script>
(function(){
    function init(){
        var el = document.getElementById('contact-map-preview');
        var latI = document.getElementById('contact-map-lat');
        var lngI = document.getElementById('contact-map-lng');
        var zoomI = document.getElementById('contact-map-zoom');
        var labelI = document.getElementById('contact-map-label');
        if (!el || !latI || !lngI || !zoomI || typeof L === 'undefined') return;

        function readLat(){ var v = parseFloat(latI.value); return isFinite(v) ? v : 17.3850; }
        function readLng(){ var v = parseFloat(lngI.value); return isFinite(v) ? v : 78.4867; }
        function readZoom(){ var v = parseInt(zoomI.value, 10); if(!isFinite(v)) v = 12; return Math.max(1, Math.min(19, v)); }
        function readLabel(){ return labelI ? (labelI.value || '') : ''; }

        var map = L.map(el, {
            center: [readLat(), readLng()],
            zoom: readZoom(),
            scrollWheelZoom: true,
            zoomControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var pinSvg = '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<defs><linearGradient id="bm-admin-g" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
            '</linearGradient></defs>' +
            '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#bm-admin-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
            '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
            '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#7c3aed">1</text>' +
            '</svg>';

        var icon = L.divIcon({
            className: '',
            html: '<div class="admin-brand-marker"><span class="pulse"></span>' + pinSvg + '</div>',
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -40]
        });

        var marker = L.marker([readLat(), readLng()], { icon: icon, draggable: true, title: readLabel() || '1INME' }).addTo(map);

        var suppressInputSync = false;
        var suppressMapSync = false;

        function syncMapFromInputs(recenter){
            if (suppressMapSync) return;
            var lat = readLat(), lng = readLng(), z = readZoom();
            suppressInputSync = true;
            marker.setLatLng([lat, lng]);
            if (recenter) {
                map.setView([lat, lng], z, { animate: false });
            } else if (map.getZoom() !== z) {
                map.setZoom(z, { animate: false });
            }
            suppressInputSync = false;
        }

        function setInputs(lat, lng){
            suppressMapSync = true;
            latI.value = (Math.round(lat * 1e6) / 1e6).toString();
            lngI.value = (Math.round(lng * 1e6) / 1e6).toString();
            try {
                latI.dispatchEvent(new Event('input', { bubbles: true }));
                lngI.dispatchEvent(new Event('input', { bubbles: true }));
            } catch (e) {}
            suppressMapSync = false;
        }

        ['input', 'change'].forEach(function(evt){
            latI.addEventListener(evt, function(){ syncMapFromInputs(true); });
            lngI.addEventListener(evt, function(){ syncMapFromInputs(true); });
            zoomI.addEventListener(evt, function(){ syncMapFromInputs(false); });
        });
        if (labelI) {
            labelI.addEventListener('input', function(){
                var t = readLabel() || '1INME';
                try {
                    var el2 = marker.getElement();
                    if (el2) el2.setAttribute('title', t);
                } catch (e) {}
            });
        }

        map.on('click', function(e){
            setInputs(e.latlng.lat, e.latlng.lng);
            marker.setLatLng(e.latlng);
        });
        marker.on('dragend', function(){
            var p = marker.getLatLng();
            setInputs(p.lat, p.lng);
        });
        map.on('zoomend', function(){
            if (suppressInputSync) return;
            var z = map.getZoom();
            if (parseInt(zoomI.value, 10) !== z) {
                suppressMapSync = true;
                zoomI.value = String(z);
                try { zoomI.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
                suppressMapSync = false;
            }
        });

        setTimeout(function(){ map.invalidateSize(); }, 100);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
