    <div class="mb-4 glass-block rounded-xl p-5 flex justify-center">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size={{ $s['size'] ?? 200 }}x{{ $s['size'] ?? 200 }}&data={{ urlencode($s['url'] ?? request()->url()) }}&bgcolor=0f0a1a&color=ffffff" alt="QR Code" class="rounded-lg">
    </div>
