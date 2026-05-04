    <div class="mb-4 glass-block rounded-2xl p-5">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Follow our Channel' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <input id="hp_{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
        <a href="{{ $s['channel_url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-lg"
           style="background: #25D366; color: #fff;"
           onclick="fetch('/{{ $link->alias }}/subscribe', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({block_id:{{ $block->id }},type:'whatsapp_channel',channel_url:'{{ $s['channel_url'] ?? '' }}',_hp:(document.getElementById('hp_{{ $block->id }}')||{}).value||''})})">
            <i class="fab fa-whatsapp text-lg"></i>
            <span>{{ $s['button_text'] ?? 'Follow Channel' }}</span>
        </a>
    </div>
