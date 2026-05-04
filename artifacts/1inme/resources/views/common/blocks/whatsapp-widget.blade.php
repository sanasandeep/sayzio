    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['message'] ?? '') }}" target="_blank" rel="noopener"
       class="block w-full mb-3 rounded-2xl py-4 text-center font-semibold transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-3"
       style="background: #25D366; color: #fff; border-radius: {{ $btnRadius ?? '12px' }};">
        <i class="fab fa-whatsapp text-xl"></i><span>{{ $s['button_text'] ?? 'Chat on WhatsApp' }}</span>
    </a>
