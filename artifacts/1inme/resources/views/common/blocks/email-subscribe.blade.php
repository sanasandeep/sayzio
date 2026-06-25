    <div class="mb-4 glass-block rounded-2xl p-6" x-data="{ submitted: false, loading: false, error: '' }">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: linear-gradient(135deg, rgba(61,107,255,0.3), rgba(110,97,255,0.2));">
                <i class="fas fa-envelope text-indigo-400 text-lg"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <template x-if="!submitted">
            <form @submit.prevent="
                loading = true; error = '';
                fetch('/{{ $link->alias }}/subscribe', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify({
                        block_id: {{ $block->id }},
                        type: 'email',
                        email: $refs.emailInput.value,
                        name: $refs.nameInput ? $refs.nameInput.value : '',
                        _hp: $refs.hpInput ? $refs.hpInput.value : ''
                    })
                }).then(r => r.json()).then(d => {
                    loading = false;
                    if(d.success) submitted = true;
                    else error = d.message || 'Something went wrong';
                }).catch(() => { loading = false; error = 'Network error'; })
            " class="space-y-3">
                <input x-ref="hpInput" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                @if($s['name_field'] ?? false)
                <input x-ref="nameInput" type="text" placeholder="Your name" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500/40 transition" style="color:{{ $fontColor }}">
                @endif
                <input x-ref="emailInput" type="email" required placeholder="{{ $s['placeholder'] ?? 'Enter your email' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500/40 transition" style="color:{{ $fontColor }}">
                <button type="submit" :disabled="loading" class="bio-btn w-full px-5 py-3 text-sm font-semibold rounded-xl flex items-center justify-center gap-2 transition-all">
                    <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe' }}'"></span>
                </button>
                <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
            </form>
        </template>
        <template x-if="submitted">
            <div class="text-center py-3">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                    <i class="fas fa-check text-green-400 text-xl"></i>
                </div>
                <p class="text-sm font-semibold text-green-400">{{ $s['success_message'] ?? 'Thanks for subscribing!' }}</p>
            </div>
        </template>
    </div>
