{{--
    Smart Redirect Rules editor — drop-in partial.

    Usage (must be inside a parent <form> so the hidden input posts with it):
        @include('user.links.partials.smart-rules', ['link' => $link])

    Saves to settings['smart_rules'] via the hidden `smart_rules_json` field.
    Self-contained Alpine component so it works inside any parent form.
--}}
@auth
    @include('user.partials._plan_lock', ['feature' => 'link_smart_rules', 'kind' => 'flag', 'label' => 'Smart redirect rules'])
@endauth
@php
    $smartRulesData = $link->settings['smart_rules'] ?? [];
    $tzList = ['UTC','Asia/Kolkata','Asia/Dubai','Asia/Singapore','Asia/Tokyo','Asia/Shanghai','Europe/London','Europe/Berlin','Europe/Paris','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Sao_Paulo','Australia/Sydney','Africa/Lagos','Africa/Cairo','Africa/Johannesburg'];
@endphp

@once
<script>
document.addEventListener('alpine:init', function () {
    window.Alpine.data('smartRulesEditor', function (initial) {
        return {
            smartRules: Array.isArray(initial) ? initial : [],
            _newId: function () {
                var a = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', s = '';
                for (var k = 0; k < 12; k++) s += a[Math.floor(Math.random() * a.length)];
                return s;
            },
            addRule: function (type) {
                var tpl = {
                    device:   { type: 'device',   match: ['mobile'], url: '' },
                    country:  { type: 'country',  match: ['US'],     url: '' },
                    language: { type: 'language', match: ['en'],     url: '' },
                    time:     { type: 'time',     from: '09:00', to: '17:00', tz: 'UTC', url: '' },
                    ab:       { type: 'ab',       variants: [
                        { id: this._newId(), url: '', weight: 50 },
                        { id: this._newId(), url: '', weight: 50 }
                    ] }
                };
                if (!tpl[type]) return;
                this.smartRules.push(JSON.parse(JSON.stringify(tpl[type])));
            },
            removeRule: function (i) { this.smartRules.splice(i, 1); },
            moveRule: function (i, dir) {
                var j = i + dir;
                if (j < 0 || j >= this.smartRules.length) return;
                var tmp = this.smartRules[i];
                this.smartRules[i] = this.smartRules[j];
                this.smartRules[j] = tmp;
            },
            addAbVariant: function (i) {
                this.smartRules[i].variants.push({ id: this._newId(), url: '', weight: 50 });
            },
            removeAbVariant: function (i, j) {
                if (this.smartRules[i].variants.length <= 2) return;
                this.smartRules[i].variants.splice(j, 1);
            },
            toggleMatch: function (rule, value) {
                var idx = rule.match.indexOf(value);
                if (idx >= 0) rule.match.splice(idx, 1);
                else rule.match.push(value);
            },
            ruleLabel: function (t) {
                var map = { device: 'Device', country: 'Country', language: 'Language', time: 'Time', ab: 'A/B Split' };
                return map[t] || t;
            }
        };
    });
});
</script>
@endonce

<div class="glass rounded-2xl p-6 mb-6" x-data="smartRulesEditor(@js($smartRulesData))">
    <div class="flex items-start gap-3 mb-4 pb-4 border-b border-white/5">
        <div class="w-9 h-9 rounded-xl bg-blue-500/15 text-blue-300 flex items-center justify-center text-base shrink-0">
            <i class="fas fa-route"></i>
        </div>
        <div>
            <h2 class="text-base font-semibold text-white leading-tight">Smart Redirect Rules</h2>
            <p class="text-xs text-white/50 mt-0.5">Send different visitors to a different URL based on their device, country, language, time of day, or A/B split. Rules check top-to-bottom, the first match wins. If nothing matches, the link works normally.</p>
        </div>
    </div>

    <input type="hidden" name="smart_rules_json" :value="JSON.stringify(smartRules)">

    <template x-for="(rule, i) in smartRules" :key="i">
        <div class="border border-white/10 rounded-xl p-4 mb-3 bg-white/[0.02]">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500/20 text-blue-300 text-xs font-semibold" x-text="i + 1"></span>
                    <span class="text-sm font-medium text-white" x-text="ruleLabel(rule.type)"></span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" @click="moveRule(i, -1)" :disabled="i === 0" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Move up"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" @click="moveRule(i, 1)" :disabled="i === smartRules.length - 1" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Move down"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" @click="removeRule(i)" class="text-xs text-rose-400 hover:text-rose-300 px-2 py-1 rounded hover:bg-rose-500/10" title="Remove rule"><i class="fas fa-trash"></i></button>
                </div>
            </div>

            {{-- Device --}}
            <div x-show="rule.type === 'device'" class="space-y-3">
                <div>
                    <label class="block text-xs text-white/50 mb-1">When the visitor is on</label>
                    <div class="flex gap-2 flex-wrap">
                        <template x-for="d in ['mobile','tablet','desktop']" :key="d">
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 cursor-pointer hover:bg-white/5" :class="rule.match.includes(d) ? 'bg-blue-500/15 border-blue-500/40' : ''">
                                <input type="checkbox" :checked="rule.match.includes(d)" @change="toggleMatch(rule, d)" class="rounded text-blue-400">
                                <span class="text-sm text-white/80 capitalize" x-text="d"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Country --}}
            <div x-show="rule.type === 'country'" class="space-y-3">
                <div>
                    <label class="block text-xs text-white/50 mb-1">When visitor is in country (2-letter codes, comma-separated, e.g. <span class="text-white/70">IN, US, GB</span>)</label>
                    <input type="text" :value="rule.match.join(', ')" @input="rule.match = $event.target.value.split(',').map(s => s.trim().toUpperCase()).filter(s => /^[A-Z]{2}$/.test(s))" placeholder="IN, US" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                </div>
            </div>

            {{-- Language --}}
            <div x-show="rule.type === 'language'" class="space-y-3">
                <div>
                    <label class="block text-xs text-white/50 mb-1">When browser language is (codes, comma-separated, e.g. <span class="text-white/70">hi, en, es</span>)</label>
                    <input type="text" :value="rule.match.join(', ')" @input="rule.match = $event.target.value.split(',').map(s => s.trim().toLowerCase()).filter(s => /^[a-z]{2,3}$/.test(s))" placeholder="hi, en" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                </div>
            </div>

            {{-- Time --}}
            <div x-show="rule.type === 'time'" class="space-y-3">
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs text-white/50 mb-1">From</label>
                        <input type="time" x-model="rule.from" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">To</label>
                        <input type="time" x-model="rule.to" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Time zone</label>
                        <select x-model="rule.tz" class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                            @foreach($tzList as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-white/40">Overnight ranges work too (e.g. 22:00 → 06:00 wraps midnight).</p>
            </div>

            {{-- Destination URL — common to non-AB rules --}}
            <div x-show="rule.type !== 'ab'" class="mt-3">
                <label class="block text-xs text-white/50 mb-1">Send them to</label>
                <input type="url" x-model="rule.url" placeholder="https://example.com/destination" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
            </div>

            {{-- A/B Variants --}}
            <div x-show="rule.type === 'ab'" class="space-y-2">
                <p class="text-xs text-white/50">Each visitor is randomly assigned a variant on first visit (weighted) and remembered for 30 days.</p>
                <template x-for="(v, j) in rule.variants" :key="j">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-white/40 w-6 text-right" x-text="String.fromCharCode(65 + j)"></span>
                        <input type="url" x-model="v.url" placeholder="https://variant.example" class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40">
                        <input type="number" min="1" max="100" x-model.number="v.weight" class="w-20 bg-white/5 border border-white/10 rounded-lg px-2 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500/40" title="Weight">
                        <span class="text-xs text-white/40">%</span>
                        <button type="button" @click="removeAbVariant(i, j)" :disabled="rule.variants.length <= 2" class="text-rose-400 hover:text-rose-300 px-2 py-1 rounded hover:bg-rose-500/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Remove variant"><i class="fas fa-times"></i></button>
                    </div>
                </template>
                <button type="button" @click="addAbVariant(i)" :disabled="rule.variants.length >= 10" class="text-xs text-blue-300 hover:text-blue-200 disabled:opacity-30 disabled:cursor-not-allowed"><i class="fas fa-plus mr-1"></i>Add variant</button>
            </div>
        </div>
    </template>

    <div x-show="smartRules.length === 0" class="text-center py-6 text-sm text-white/40 border border-dashed border-white/10 rounded-xl mb-3">
        <i class="fas fa-route text-2xl text-white/20 block mb-2"></i>
        No smart rules yet, every visitor sees this link normally.
    </div>

    <div class="flex flex-wrap gap-2 pt-2 border-t border-white/5">
        <span class="text-xs text-white/50 self-center mr-1">Add rule:</span>
        <button type="button" @click="addRule('device')"   class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/40 text-white/80"><i class="fas fa-mobile-alt mr-1.5"></i>Device</button>
        <button type="button" @click="addRule('country')"  class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/40 text-white/80"><i class="fas fa-globe mr-1.5"></i>Country</button>
        <button type="button" @click="addRule('language')" class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/40 text-white/80"><i class="fas fa-language mr-1.5"></i>Language</button>
        <button type="button" @click="addRule('time')"     class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/40 text-white/80"><i class="fas fa-clock mr-1.5"></i>Time</button>
        <button type="button" @click="addRule('ab')"       class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/40 text-white/80"><i class="fas fa-random mr-1.5"></i>A/B Split</button>
    </div>
</div>
