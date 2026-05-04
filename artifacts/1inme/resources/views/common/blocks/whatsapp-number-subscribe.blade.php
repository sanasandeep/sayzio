    <div class="mb-4 glass-block rounded-2xl p-5" x-data="{ submitted: false, loading: false, error: '', phone: '' }">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe via WhatsApp' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <template x-if="!submitted">
            <div class="space-y-3">
                <input x-ref="hpInput" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                @if($s['collect_phone'] ?? true)
                <input x-model="phone" type="tel" placeholder="Your WhatsApp number" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-green-500/40 transition" style="color:{{ $fontColor }}">
                @endif
                <button @click="
                    loading = true; error = '';
                    fetch('/{{ $link->alias }}/subscribe', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                        body: JSON.stringify({
                            block_id: {{ $block->id }},
                            type: 'whatsapp_number',
                            phone: phone,
                            _hp: $refs.hpInput ? $refs.hpInput.value : ''
                        })
                    }).then(r => r.json()).then(d => {
                        loading = false;
                        if(d.success) {
                            submitted = true;
                            window.open('https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['default_message'] ?? 'Hi! I want to subscribe.') }}', '_blank');
                        } else error = d.message || 'Something went wrong';
                    }).catch(() => { loading = false; error = 'Network error'; })
                " :disabled="loading"
                   class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 cursor-pointer"
                   style="background: #25D366; color: #fff;">
                    <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <i class="fab fa-whatsapp text-lg" x-show="!loading"></i>
                    <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe on WhatsApp' }}'"></span>
                </button>
                <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
            </div>
        </template>
        <template x-if="submitted">
            <div class="text-center py-3">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                    <i class="fas fa-check text-green-400 text-xl"></i>
                </div>
                <p class="text-sm font-semibold text-green-400">Subscribed! Check WhatsApp.</p>
            </div>
        </template>
    </div>
