    @if(!empty($s['url']))
        @php
            $u = $s['url'];
            $params = [];
            if (!empty($s['hide_event_details'])) $params['hide_event_type_details'] = 1;
            if (!empty($s['hide_cookie_banner'])) $params['hide_gdpr_banner'] = 1;
            $sep = str_contains($u, '?') ? '&' : '?';
            if (!empty($params)) $u .= $sep . http_build_query($params);
            $h = (int)($s['height'] ?? 700);
        @endphp
        <div class="mb-4 glass-block rounded-xl overflow-hidden">
            <iframe src="{{ $u }}" frameborder="0" class="w-full" style="height: {{ $h }}px;" loading="lazy"></iframe>
        </div>
    @else
        <div class="mb-4 glass-block rounded-xl p-4 text-center text-xs text-white/40">Add your Calendly URL</div>
    @endif
