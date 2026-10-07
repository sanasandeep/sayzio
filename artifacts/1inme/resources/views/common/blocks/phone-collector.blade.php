<div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="{ submitted: false, loading: false, error: '' }">
    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Call Us' }}</p>
    <form x-show="!submitted" class="flex gap-2" @submit.prevent="
        loading = true; error = '';
        fetch('/{{ $link->alias }}/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ block_id: {{ (int) $block->id }}, type: 'phone', phone: $refs.phone.value, _hp: $refs.honeypot.value })
        }).then(async r => {
            const d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.message || 'Could not submit. Please try again.');
            submitted = true;
        }).catch(e => { error = e.message || 'Could not submit. Please try again.'; }).finally(() => { loading = false; });
    ">
        <input x-ref="phone" type="tel" required maxlength="30" placeholder="{{ $s['placeholder'] ?? 'Your phone' }}" class="flex-1 min-w-0 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm" style="color:{{ $fontColor }}" aria-label="Phone number">
        <input x-ref="honeypot" type="text" name="_hp" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <button type="submit" :disabled="loading" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Submit' }}</button>
    </form>
    <p x-show="submitted" x-cloak class="text-sm" role="status">Thanks! Your number has been submitted.</p>
    <p x-show="error" x-cloak x-text="error" class="text-sm mt-2" role="alert"></p>
</div>
