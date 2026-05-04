    <div class="mb-4 rounded-xl overflow-hidden glass-block">
        <video class="w-full rounded-xl" controls {{ ($s['autoplay'] ?? false) ? 'autoplay muted' : '' }}>
            <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
        </video>
    </div>
