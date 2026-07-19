@php
    $tjCreator     = $link->user ?? null;
    $tjConnection  = $tjCreator?->defaultPaymentConnection();
    $tjHasProvider = $tjConnection && $tjConnection->charges_enabled;
    $tjTitle       = $s['title'] ?? 'Send me a tip';
    $tjMessage     = $s['message'] ?? '';
    $tjBtnText     = $s['button_text'] ?? 'Send Tip';
    $tjAllowCustom = (bool) ($s['allow_custom'] ?? true);
    $tjAmounts     = is_array($s['amounts'] ?? null)
        ? array_values(array_filter(array_map('intval', $s['amounts']), fn($n) => $n > 0))
        : [3, 5, 10, 25];
    if (empty($tjAmounts)) {
        $tjAmounts = [3, 5, 10, 25];
    }
    $tjCurrency    = $tjCreator?->preferred_currency ?: 'USD';
    $tjCurrencySymbol = match (strtoupper($tjCurrency)) {
        'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'INR' => '₹',
        'CAD', 'AUD', 'USD' => '$',
        default => strtoupper($tjCurrency) . ' ',
    };
    $tjTipRoute    = $tjCreator ? url('/' . $link->alias . '/tip-jar') : '#';
    $tjShowThanks  = request()->query('tipped') === '1';
    $tjBlockId     = 'tj-' . $block->id;
@endphp
@if($tjHasProvider || !$tjCreator)
<div class="mb-3 rounded-2xl overflow-hidden"
     style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10);"
     x-data="{
         selected: null,
         custom: '',
         loading: false,
         done: {{ $tjShowThanks ? 'true' : 'false' }},
         get amount() { return this.selected || (parseInt(this.custom) || 0); },
         submit() {
             if (!this.amount || this.amount < 1) return;
             this.loading = true;
             this.$refs.tipForm.submit();
         }
     }">

    {{-- Thank-you state --}}
    <template x-if="done">
        <div class="px-5 py-8 text-center">
            <div class="text-4xl mb-3">🎉</div>
            <p class="font-semibold text-base" style="color:{{ $fontColor }}">Thank you for the tip!</p>
            <p class="text-xs mt-1" style="color:{{ $fontColor }}99">Your support goes straight to {{ $tjCreator?->name ?? 'the creator' }}.</p>
            <button type="button" @click="done=false; selected=null; custom=''"
                    class="mt-4 text-xs font-medium underline underline-offset-2"
                    style="color:{{ $fontColor }}88">
                Send another tip
            </button>
        </div>
    </template>

    {{-- Tip form --}}
    <template x-if="!done">
        <div>
            <div class="px-5 pt-5 pb-3">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-jar text-amber-400 text-lg"></i>
                    <p class="font-semibold text-base truncate" style="color:{{ $fontColor }}">{{ $tjTitle }}</p>
                </div>
                @if($tjMessage)
                    <p class="text-xs" style="color:{{ $fontColor }}99">{{ $tjMessage }}</p>
                @endif
            </div>

            <div class="px-5 pb-2 flex flex-wrap gap-2">
                @foreach($tjAmounts as $amt)
                    <button type="button"
                            @click="selected = (selected === {{ $amt }}) ? null : {{ $amt }}; custom = ''"
                            :class="selected === {{ $amt }} ? 'ring-2 ring-amber-400' : 'opacity-80 hover:opacity-100'"
                            class="px-4 py-2 rounded-full text-sm font-semibold transition"
                            style="background: rgba(255,255,255,0.10); color: {{ $fontColor }};">
                        {{ $tjCurrencySymbol }}{{ $amt }}
                    </button>
                @endforeach
            </div>

            @if($tjAllowCustom)
                <div class="px-5 pb-3">
                    <div class="flex items-center gap-2 rounded-xl px-3 py-2"
                         style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.10);">
                        <span class="text-sm font-medium" style="color:{{ $fontColor }}88">{{ $tjCurrencySymbol }}</span>
                        <input type="number" min="1" max="1000" step="1"
                               x-model="custom"
                               @input="selected = null"
                               placeholder="Custom amount"
                               class="flex-1 bg-transparent text-sm outline-none"
                               style="color:{{ $fontColor }}; min-width:0;">
                    </div>
                </div>
            @endif

            <div class="px-5 pb-5">
                <form x-ref="tipForm" method="POST" action="{{ $tjTipRoute }}">
                    @csrf
                    <input type="hidden" name="block_id" value="{{ $block->id }}">
                    <input type="hidden" name="amount" :value="amount">
                    <input type="hidden" name="return_url" value="{{ url('/' . $link->alias . '?tipped=1') }}">

                    <button type="button"
                            @click="submit()"
                            :disabled="loading || !amount"
                            class="bio-btn block w-full py-3 text-sm font-semibold text-center transition"
                            :class="(!amount) ? 'opacity-40 cursor-not-allowed' : ''">
                        <template x-if="loading">
                            <span><i class="fas fa-spinner fa-spin mr-1.5"></i> Redirecting…</span>
                        </template>
                        <template x-if="!loading">
                            <span>
                                <i class="fas fa-jar mr-1.5"></i>
                                <span x-text="amount ? '{{ $tjBtnText }}, {{ $tjCurrencySymbol }}' + amount : '{{ $tjBtnText }}'"></span>
                            </span>
                        </template>
                    </button>
                </form>
                <p class="text-[10px] text-center mt-2" style="color:{{ $fontColor }}55">0% platform fee · goes directly to the creator</p>
            </div>
        </div>
    </template>
</div>
@endif
