    <div class="mb-4 rounded-xl overflow-hidden glass-block">
        <video class="w-full rounded-xl" controls @if(!empty($s['autoplay'])) autoplay @endif @if(!empty($s['muted']) || !empty($s['autoplay'])) muted @endif @if(!empty($s['loop'])) loop @endif playsinline>
            <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
        </video>
    </div>
