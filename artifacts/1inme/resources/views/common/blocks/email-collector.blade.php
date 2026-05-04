    <div class="mb-4 glass-block rounded-xl p-5 text-center">
        <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Subscribe' }}</p>
        <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Done!'; this.querySelector('button').disabled=true;">
            <input type="email" required placeholder="{{ $s['placeholder'] ?? 'Your email' }}" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20" style="color:{{ $fontColor }}">
            <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Subscribe' }}</button>
        </form>
    </div>
