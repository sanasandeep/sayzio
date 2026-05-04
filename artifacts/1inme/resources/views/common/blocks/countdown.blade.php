    <div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="countdown('{{ $s['target_date'] ?? '' }}')" x-init="start()">
        <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Coming Soon' }}</p>
        <div class="flex justify-center gap-4">
            <div><span class="text-2xl font-bold" x-text="days">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Days</p></div>
            <div><span class="text-2xl font-bold" x-text="hours">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Hours</p></div>
            <div><span class="text-2xl font-bold" x-text="minutes">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Min</p></div>
            <div><span class="text-2xl font-bold" x-text="seconds">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Sec</p></div>
        </div>
    </div>
