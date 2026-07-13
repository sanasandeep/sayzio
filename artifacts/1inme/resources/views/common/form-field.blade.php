@php
    $type = $field['type'] ?? 'text';
    $id = $field['id'] ?? '';
    $label = $field['label'] ?? '';
    $required = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? '';
    $help = $field['help'] ?? null;
    $oldVal = old($id);
    $hasError = $errors->has($id);

    // Per-field pricing (Task #2321). Only surfaced when the form is in
    // per_field paid mode (caller passes $showPrices + $priceCurrency).
    $showPrices = $showPrices ?? false;
    $priceCur   = $priceCurrency ?? 'USD';
    $optPrices  = (array) ($field['option_prices'] ?? []);
    $unitPrice  = (int) ($field['price_cents'] ?? 0);
    $fmtPrice   = fn ($cents) => '+' . number_format(((int) $cents) / 100, 2) . ' ' . $priceCur;
@endphp

@if($type === 'heading')
    <h3 class="form-heading">{{ $label }}</h3>
@elseif($type === 'paragraph')
    <p class="form-paragraph">{{ $label }}</p>
@elseif($type === 'divider')
    <div class="form-divider"></div>
@elseif($type === 'hidden')
    <input type="hidden" name="{{ $id }}" value="{{ $field['value'] ?? '' }}">
@else
<div class="form-field">
    @unless(in_array($type, ['consent', 'checkbox']))
        <label class="form-label" for="f_{{ $id }}">
            {{ $label }}@if($required)<span class="form-required">*</span>@endif
        </label>
    @endunless

    @switch($type)
        @case('textarea')
            <textarea id="f_{{ $id }}" name="{{ $id }}" class="form-textarea" rows="{{ $field['rows'] ?? 4 }}"
                      placeholder="{{ $placeholder }}" @if($required) required @endif>{{ $oldVal }}</textarea>
            @break

        @case('select')
            <select id="f_{{ $id }}" name="{{ $id }}" class="form-select" @if($required) required @endif @if($showPrices) data-priced data-price-label="{{ $label }}" @endif>
                <option value="">— Choose —</option>
                @foreach(($field['options'] ?? []) as $opt)
                    @php $oc = (int) ($optPrices[$opt] ?? 0); @endphp
                    <option value="{{ $opt }}" @selected($oldVal === $opt) @if($showPrices && $oc > 0) data-price="{{ $oc }}" @endif>{{ $opt }}@if($showPrices && $oc > 0) ({{ $fmtPrice($oc) }})@endif</option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div class="form-radio-group">
                @foreach(($field['options'] ?? []) as $opt)
                    @php $oc = (int) ($optPrices[$opt] ?? 0); @endphp
                    <label><input type="radio" name="{{ $id }}" value="{{ $opt }}" @checked($oldVal === $opt) @if($required) required @endif @if($showPrices && $oc > 0) data-price="{{ $oc }}" data-price-label="{{ $label }}" @endif> {{ $opt }}@if($showPrices && $oc > 0)<span class="form-price-tag">{{ $fmtPrice($oc) }}</span>@endif</label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
            <div class="form-check-group">
                @foreach(($field['options'] ?? []) as $opt)
                    @php $oc = (int) ($optPrices[$opt] ?? 0); @endphp
                    <label><input type="checkbox" name="{{ $id }}[]" value="{{ $opt }}" @checked(is_array($oldVal) && in_array($opt, $oldVal)) @if($showPrices && $oc > 0) data-price="{{ $oc }}" data-price-label="{{ $label }}" @endif> {{ $opt }}@if($showPrices && $oc > 0)<span class="form-price-tag">{{ $fmtPrice($oc) }}</span>@endif</label>
                @endforeach
            </div>
            @break

        @case('pricing')
            @php
                $cur  = $formCurrency ?? 'USD';
                $opts = array_values($field['price_options'] ?? []);
                $adds = array_values($field['addons'] ?? []);
                $moneyFmt = function ($cents) use ($cur) {
                    $amount  = number_format($cents / 100, 2);
                    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'AUD' => 'A$', 'CAD' => 'C$', 'JPY' => '¥', 'BRL' => 'R$'];
                    $sym = $symbols[$cur] ?? null;
                    return $sym ? $sym . $amount : $cur . ' ' . $amount;
                };
                $pricingCfg = [
                    'id'             => $id,
                    'currency'       => $cur,
                    'opts'           => array_map(fn ($o) => ['cents' => \App\Modules\User\Models\Form::priceToCents($o['price'] ?? 0)], $opts),
                    'addons'         => array_map(fn ($a) => ['cents' => \App\Modules\User\Models\Form::priceToCents($a['price'] ?? 0)], $adds),
                    'selected'       => ($oldVal !== null && $oldVal !== '') ? (int) $oldVal : null,
                    'selectedAddons' => array_map('intval', (array) old($id . '_addons', [])),
                ];
            @endphp
            <div class="form-pricing" x-data="pricingField(@js($pricingCfg))" x-init="init()">
                <div class="form-pricing-options">
                    @foreach($opts as $oi => $opt)
                        <label class="form-pricing-option" :class="{ 'is-selected': selected === {{ $oi }} }">
                            <span class="form-pricing-option-main">
                                <input type="radio" name="{{ $id }}" value="{{ $oi }}" x-model.number="selected" @if($required && $oi === 0) required @endif>
                                <span class="form-pricing-option-label">{{ $opt['label'] ?? '' }}</span>
                            </span>
                            <span class="form-pricing-option-price">{{ $moneyFmt($pricingCfg['opts'][$oi]['cents']) }}</span>
                        </label>
                    @endforeach
                </div>
                @if(count($adds))
                    <div class="form-pricing-addons">
                        <div class="form-pricing-addons-title">Add-ons</div>
                        @foreach($adds as $ai => $ad)
                            <label class="form-pricing-addon">
                                <span class="form-pricing-addon-main">
                                    <input type="checkbox" name="{{ $id }}_addons[]" value="{{ $ai }}" x-model.number="addons">
                                    <span class="form-pricing-addon-label">{{ $ad['label'] ?? '' }}</span>
                                </span>
                                <span class="form-pricing-addon-price">+{{ $moneyFmt($pricingCfg['addons'][$ai]['cents']) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <div class="form-pricing-subtotal">
                    <span>Subtotal</span>
                    <strong x-text="fmt(subtotal)"></strong>
                </div>
            </div>
            @break

        @case('rating')
            @php $max = (int) ($field['max'] ?? 5); @endphp
            <div class="rating-stars" x-data="{ value: parseInt(@js($oldVal ?: 0)), hover: 0 }">
                @for($i = 1; $i <= $max; $i++)
                    <label :class="{ active: value >= {{ $i }}, hover: hover >= {{ $i }} }"
                           @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" @click="value = {{ $i }}">
                        <input type="radio" name="{{ $id }}" value="{{ $i }}" @if($required && $i === 1) required @endif x-bind:checked="value === {{ $i }}">
                        <i class="fas fa-star"></i>
                    </label>
                @endfor
            </div>
            @break

        @case('scale')
            @php $min = (int) ($field['min'] ?? 0); $max = (int) ($field['max'] ?? 10); @endphp
            <div class="scale-row">
                @for($i = $min; $i <= $max; $i++)
                    <input type="radio" id="f_{{ $id }}_{{ $i }}" name="{{ $id }}" value="{{ $i }}" @checked((string) $oldVal === (string) $i)>
                    <label for="f_{{ $id }}_{{ $i }}">{{ $i }}</label>
                @endfor
            </div>
            @break

        @case('file')
            @php
                // Form-builder file field: per-field configuration takes priority,
                // otherwise fall back to the form_field.file plan policy if a user is logged in.
                // Resolve plan policy against the form OWNER (passed in as
                // $fieldOwner from common/form.blade.php) so that public,
                // unauthenticated submissions still honour the form-owner's
                // plan-level upload limits. Falls back to the current viewer.
                $ffOwner = $fieldOwner ?? auth()->user();
                $ffPolicy = (!empty($field['file_types']) || !empty($field['file_max_kb']) || !$ffOwner)
                    ? null
                    : \App\Services\UploadPolicy::for('form_field.file', $ffOwner);
                $ffAccept  = !empty($field['file_types'])
                    ? collect(explode(',', preg_replace('/[^a-zA-Z0-9,]/', '', (string)$field['file_types'])))->map(fn($e) => '.' . $e)->implode(',')
                    : ($ffPolicy['accept'] ?? '*/*');
                $ffMaxMb   = !empty($field['file_max_kb'])
                    ? round(((int) $field['file_max_kb']) / 1024, 1)
                    : ($ffPolicy['max_mb'] ?? null);
                $ffHint    = !empty($field['file_types']) ? strtoupper(str_replace(',', ', ', $field['file_types'])) : null;
            @endphp
            @include('user.partials.dropzone-input', [
                'name'     => $id,
                'accept'   => $ffAccept,
                'required' => $required,
                'maxMb'    => $ffMaxMb,
                'hint'     => $ffHint,
                'compact'  => true,
            ])
            @break

        @case('signature')
            <div class="form-signature" x-data="signaturePad('{{ $id }}', @if($required) true @else false @endif)" x-init="init()">
                <canvas x-ref="pad" width="600" height="180"
                        style="display:block; width:100%; height:180px; touch-action:none; background:#fff; border:1px dashed #cbd5e1; border-radius: var(--form-radius-sm); cursor:crosshair;"
                        @mousedown.prevent="startStroke($event)" @mousemove.prevent="moveStroke($event)" @mouseup="endStroke()" @mouseleave="endStroke()"
                        @touchstart.prevent="startStroke($event)" @touchmove.prevent="moveStroke($event)" @touchend.prevent="endStroke()"></canvas>
                <input type="hidden" id="f_{{ $id }}" name="{{ $id }}" :value="dataUrl" @if($required) required @endif>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:0.5rem; font-size:0.74rem; opacity:0.7;">
                    <span>Sign with mouse or finger</span>
                    <button type="button" @click="clearPad()" style="background:transparent; border:0; color:var(--form-accent); font-weight:600; cursor:pointer; font-size:0.78rem;">
                        <i class="fas fa-eraser"></i> Clear
                    </button>
                </div>
            </div>
            @break

        @case('consent')
            <label style="display: flex; gap: 0.6rem; align-items: flex-start; cursor: pointer; line-height: 1.4;">
                <input type="checkbox" name="{{ $id }}" value="1" @checked($oldVal) @if($required) required @endif @if($showPrices && $unitPrice > 0) data-price-addon="{{ $unitPrice }}" data-price-label="{{ $label }}" @endif style="margin-top: 0.2rem;">
                <span class="form-label" style="margin: 0;">{{ $label }}@if($required)<span class="form-required">*</span>@endif @if($showPrices && $unitPrice > 0)<span class="form-price-tag">{{ $fmtPrice($unitPrice) }}</span>@endif</span>
            </label>
            @break

        @case('date')
            <input type="date" id="f_{{ $id }}" name="{{ $id }}" class="form-input" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @case('time')
            <input type="time" id="f_{{ $id }}" name="{{ $id }}" class="form-input" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @case('email')
            <input type="email" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @case('phone')
            <input type="tel" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @case('number')
            @if($showPrices && $unitPrice > 0)
                {{-- Priced number = unit-priced quantity → render a +/- stepper (Task #2337). --}}
                <div class="form-stepper" x-data="formStepper()">
                    <button type="button" class="form-stepper-btn" @click="step(-1)" aria-label="Decrease quantity" tabindex="-1">&minus;</button>
                    <input type="number" id="f_{{ $id }}" name="{{ $id }}" class="form-input form-stepper-input" x-ref="input"
                           inputmode="numeric" value="{{ $oldVal }}" placeholder="{{ $placeholder ?: '0' }}"
                           @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                           @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                           data-price-unit="{{ $unitPrice }}" data-price-label="{{ $label }}"
                           @if($required) required @endif>
                    <button type="button" class="form-stepper-btn" @click="step(1)" aria-label="Increase quantity" tabindex="-1">+</button>
                </div>
                <div class="form-help">{{ number_format($unitPrice / 100, 2) }} {{ $priceCur }} per unit</div>
            @else
                <input type="number" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}"
                       @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                       @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                       @if($required) required @endif>
            @endif
            @break

        @case('url')
            <input type="url" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder ?: 'https://' }}" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @case('full_name')
            @php
                $fnFirst = old($id . '_first', '');
                $fnLast  = old($id . '_last', '');
                $firstLbl = $field['first_label'] ?? 'First Name';
                $lastLbl  = $field['last_label']  ?? 'Last Name';
            @endphp
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div>
                    <label class="form-label" for="f_{{ $id }}_first" style="font-size:0.78rem; margin-bottom:0.25rem;">{{ $firstLbl }}</label>
                    <input type="text" id="f_{{ $id }}_first" name="{{ $id }}_first" class="form-input" placeholder="{{ $firstLbl }}" value="{{ $fnFirst }}" @if($required) required @endif>
                </div>
                <div>
                    <label class="form-label" for="f_{{ $id }}_last" style="font-size:0.78rem; margin-bottom:0.25rem;">{{ $lastLbl }}</label>
                    <input type="text" id="f_{{ $id }}_last" name="{{ $id }}_last" class="form-input" placeholder="{{ $lastLbl }}" value="{{ $fnLast }}" @if($required) required @endif>
                </div>
            </div>
            @break

        @case('address')
            @php
                $addrOld = old($id, []);
                if (!is_array($addrOld)) $addrOld = [];
            @endphp
            <div class="space-y-2">
                <input type="text" name="{{ $id }}[street]" class="form-input" placeholder="Street address" value="{{ $addrOld['street'] ?? '' }}" @if($required) required @endif>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <input type="text" name="{{ $id }}[city]" class="form-input" placeholder="City" value="{{ $addrOld['city'] ?? '' }}" @if($required) required @endif>
                    <input type="text" name="{{ $id }}[state]" class="form-input" placeholder="State / Province" value="{{ $addrOld['state'] ?? '' }}">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <input type="text" name="{{ $id }}[postal]" class="form-input" placeholder="Postal / ZIP code" value="{{ $addrOld['postal'] ?? '' }}">
                    <input type="text" name="{{ $id }}[country]" class="form-input" placeholder="Country" value="{{ $addrOld['country'] ?? '' }}" list="f_{{ $id }}_countries">
                    <datalist id="f_{{ $id }}_countries">
                        @foreach(['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Bolivia','Brazil','Bulgaria','Cambodia','Canada','Chile','China','Colombia','Croatia','Czech Republic','Denmark','Egypt','Ethiopia','Finland','France','Germany','Ghana','Greece','Guatemala','Hungary','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Malaysia','Mexico','Morocco','Myanmar','Nepal','Netherlands','New Zealand','Nigeria','Norway','Pakistan','Peru','Philippines','Poland','Portugal','Romania','Russia','Saudi Arabia','Serbia','Singapore','Slovakia','South Africa','South Korea','Spain','Sri Lanka','Sweden','Switzerland','Taiwan','Tanzania','Thailand','Turkey','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Venezuela','Vietnam','Zimbabwe'] as $cn)
                            <option value="{{ $cn }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
            @break

        @case('country')
            @php
                $countries = ['Afghanistan','Albania','Algeria','Argentina','Armenia','Australia','Austria','Azerbaijan','Bangladesh','Belarus','Belgium','Bolivia','Brazil','Bulgaria','Cambodia','Canada','Chile','China','Colombia','Croatia','Cuba','Czech Republic','Denmark','Dominican Republic','Ecuador','Egypt','El Salvador','Ethiopia','Finland','France','Georgia','Germany','Ghana','Greece','Guatemala','Honduras','Hungary','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kuwait','Kyrgyzstan','Lebanon','Libya','Malaysia','Mexico','Moldova','Mongolia','Morocco','Mozambique','Myanmar','Nepal','Netherlands','New Zealand','Nicaragua','Nigeria','North Korea','Norway','Pakistan','Panama','Paraguay','Peru','Philippines','Poland','Portugal','Puerto Rico','Qatar','Romania','Russia','Saudi Arabia','Senegal','Serbia','Singapore','Slovakia','Slovenia','Somalia','South Africa','South Korea','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Tunisia','Turkey','Turkmenistan','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Venezuela','Vietnam','Yemen','Zimbabwe'];
                $countryOld = old($id, '');
            @endphp
            {{-- Searchable country typeahead --}}
            <div x-data="{
                    open: false,
                    search: @js($countryOld),
                    value: @js($countryOld),
                    countries: @js($countries),
                    get filtered() {
                        if (!this.search) return this.countries;
                        var s = this.search.toLowerCase();
                        return this.countries.filter(function(c){ return c.toLowerCase().indexOf(s) !== -1; });
                    },
                    select(c) { this.value = c; this.search = c; this.open = false; },
                    clear() { if (!this.countries.includes(this.search)) { this.search = this.value; } }
                }"
                style="position:relative;">
                <input type="text"
                       x-model="search"
                       @focus="open = true"
                       @blur="setTimeout(() => { open = false; clear(); }, 180)"
                       @input="open = true; value = countries.includes(search) ? search : ''"
                       class="form-input"
                       id="f_{{ $id }}"
                       autocomplete="off"
                       placeholder="{{ $placeholder ?: 'Search country…' }}"
                       @if($required) x-bind:required="!value" @endif>
                <input type="hidden" name="{{ $id }}" :value="value">
                <div x-show="open && filtered.length > 0"
                     style="position:absolute;z-index:999;left:0;right:0;top:100%;max-height:200px;overflow-y:auto;background:var(--card-bg,#1e1e2e);border:1px solid var(--border-color,rgba(255,255,255,0.1));border-radius:0.375rem;box-shadow:0 4px 12px rgba(0,0,0,0.3);">
                    <template x-for="c in filtered" :key="c">
                        <div @mousedown.prevent="select(c)"
                             :class="c === value ? 'bg-blue-600/20 text-blue-300' : 'hover:bg-white/5'"
                             style="padding:0.45rem 0.75rem;cursor:pointer;font-size:0.875rem;"
                             x-text="c"></div>
                    </template>
                </div>
            </div>
            @break

        @case('currency')
            @php
                $curCode = $field['currency_code'] ?? 'USD';
                $curSymbols = ['USD'=>'$','EUR'=>'€','GBP'=>'£','INR'=>'₹','AUD'=>'A$','CAD'=>'C$','JPY'=>'¥','BRL'=>'R$','CHF'=>'Fr','CNY'=>'¥','SGD'=>'S$','AED'=>'د.إ','SAR'=>'﷼','MXN'=>'$','HKD'=>'HK$','SEK'=>'kr','NOK'=>'kr','DKK'=>'kr','NZD'=>'NZ$','ZAR'=>'R'];
                $curSym = $curSymbols[$curCode] ?? $curCode;
            @endphp
            <div style="display:flex; align-items:center; gap:0;">
                <span style="display:flex; align-items:center; padding:0 0.75rem; border:1px solid var(--form-accent,#3b82f6); border-right:0; border-radius:var(--form-radius-sm,6px) 0 0 var(--form-radius-sm,6px); font-weight:600; font-size:0.9rem; background:rgba(59,130,246,0.08); color:var(--form-accent,#3b82f6); height:2.75rem;">{{ $curSym }}</span>
                <input type="number" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder ?: '0.00' }}" value="{{ $oldVal }}"
                       min="0" step="0.01"
                       style="border-radius:0 var(--form-radius-sm,6px) var(--form-radius-sm,6px) 0 !important; flex:1;"
                       @if($required) required @endif>
            </div>
            @break

        @case('yes_no')
            <div class="form-radio-group" style="display:flex; gap:0.75rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; flex:1; padding:0.6rem 1rem; border:1px solid var(--form-accent,#3b82f6); border-radius:var(--form-radius-sm,6px); cursor:pointer; transition:background 0.15s;"
                       :style="f_{{ $id }}_val === 'yes' ? 'background:rgba(59,130,246,0.12)' : ''"
                       x-data x-on:click="document.getElementById('f_{{ $id }}_yes').checked=true; window.f_{{ $id }}_val='yes'">
                    <input type="radio" id="f_{{ $id }}_yes" name="{{ $id }}" value="yes" @checked(old($id)==='yes') @if($required) required @endif style="accent-color:var(--form-accent,#3b82f6);">
                    <span style="font-weight:600;">✓ Yes</span>
                </label>
                <label style="display:flex; align-items:center; gap:0.5rem; flex:1; padding:0.6rem 1rem; border:1px solid var(--form-accent,#3b82f6); border-radius:var(--form-radius-sm,6px); cursor:pointer; transition:background 0.15s;"
                       :style="f_{{ $id }}_val === 'no' ? 'background:rgba(59,130,246,0.12)' : ''"
                       x-data x-on:click="document.getElementById('f_{{ $id }}_no').checked=true; window.f_{{ $id }}_val='no'">
                    <input type="radio" id="f_{{ $id }}_no" name="{{ $id }}" value="no" @checked(old($id)==='no') @if($required) required @endif style="accent-color:var(--form-accent,#3b82f6);">
                    <span style="font-weight:600;">✗ No</span>
                </label>
            </div>
            @break

        @case('image_choice')
            @php
                $imgOpts = $field['image_options'] ?? [];
            @endphp
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:0.75rem;">
                @forelse($imgOpts as $io)
                    @php $ioLabel = $io['label'] ?? ''; $ioUrl = $io['url'] ?? ''; @endphp
                    <label style="position:relative; cursor:pointer; border-radius:var(--form-radius-sm,6px); overflow:hidden; border:2px solid transparent; transition:border-color 0.15s;"
                           x-data="{ checked: @js(old($id) === $ioLabel) }"
                           :style="checked ? 'border-color:var(--form-accent,#3b82f6)' : 'border:2px solid rgba(0,0,0,0.12)'">
                        <input type="radio" name="{{ $id }}" value="{{ $ioLabel }}" @checked(old($id) === $ioLabel) @if($required) required @endif
                               x-model="checked" style="position:absolute; opacity:0; width:0; height:0;" @change="checked=!checked">
                        @if($ioUrl)
                            <img src="{{ $ioUrl }}" alt="{{ $ioLabel }}" style="width:100%; height:100px; object-fit:cover; display:block;">
                        @else
                            <div style="width:100%; height:100px; background:rgba(59,130,246,0.08); display:flex; align-items:center; justify-content:center;">
                                <i class="far fa-image" style="font-size:1.5rem; opacity:0.4;"></i>
                            </div>
                        @endif
                        <div style="padding:0.4rem 0.5rem; font-size:0.78rem; font-weight:500; text-align:center;">{{ $ioLabel }}</div>
                        <div x-show="checked" style="position:absolute; top:0.3rem; right:0.3rem; width:1.1rem; height:1.1rem; border-radius:50%; background:var(--form-accent,#3b82f6); display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-check" style="font-size:0.5rem; color:#fff;"></i>
                        </div>
                    </label>
                @empty
                    <p style="color:inherit; opacity:0.5; font-size:0.82rem; grid-column:1/-1;">No image options configured.</p>
                @endforelse
            </div>
            @break

        @case('ranking')
            @php
                $rankOpts = $field['options'] ?? [];
                $rankOld  = old($id);
                if (is_string($rankOld) && $rankOld !== '') $rankOld = explode(',', $rankOld);
                elseif (!is_array($rankOld)) $rankOld = $rankOpts;
            @endphp
            <div x-data="rankingField(@js($rankOld ?: $rankOpts), @js($id))" x-init="init()">
                <ul id="rank_{{ $id }}" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.4rem;">
                    <template x-for="(item, idx) in items" :key="item">
                        <li style="display:flex; align-items:center; gap:0.6rem; padding:0.5rem 0.75rem; border:1px solid var(--form-accent,#3b82f6); border-radius:var(--form-radius-sm,6px); cursor:grab; background:var(--form-bg,#fff); user-select:none;"
                            :draggable="true"
                            @dragstart="dragStart(idx)"
                            @dragover.prevent="dragOver(idx)"
                            @dragend="dragEnd()">
                            <i class="fas fa-grip-vertical" style="opacity:0.4; font-size:0.7rem;"></i>
                            <span style="flex:1; font-size:0.88rem;" x-text="item"></span>
                            <span style="font-size:0.7rem; opacity:0.4; font-weight:600;" x-text="idx+1"></span>
                        </li>
                    </template>
                </ul>
                <input type="hidden" name="{{ $id }}" :value="items.join(',')">
            </div>
            @break

        @case('slider')
            @php
                $slMin = $field['min'] ?? 0;
                $slMax = $field['max'] ?? 100;
                $slStep = $field['step'] ?? 1;
                $slUnit = $field['unit'] ?? '';
                $slDefault = $field['default_val'] ?? $slMin;
                $slOld = old($id, $slDefault);
            @endphp
            <div x-data="{ val: {{ (float) $slOld }} }" style="padding:0.25rem 0;">
                <div style="display:flex; justify-content:space-between; font-size:0.78rem; opacity:0.6; margin-bottom:0.4rem;">
                    <span>{{ $slMin }}{{ $slUnit }}</span>
                    <span style="font-weight:700; color:var(--form-accent,#3b82f6);" x-text="val + '{{ $slUnit }}'"></span>
                    <span>{{ $slMax }}{{ $slUnit }}</span>
                </div>
                <input type="range" id="f_{{ $id }}" name="{{ $id }}" class="form-range"
                       min="{{ $slMin }}" max="{{ $slMax }}" step="{{ $slStep }}"
                       x-model.number="val"
                       style="width:100%; accent-color:var(--form-accent,#3b82f6);"
                       @if($required) required @endif>
            </div>
            @break

        @case('time_range')
            @php
                $trOld = old($id, []);
                if (!is_array($trOld)) $trOld = [];
            @endphp
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:end;">
                <div>
                    <label class="form-label" for="f_{{ $id }}_start" style="font-size:0.78rem; margin-bottom:0.25rem;">From</label>
                    <input type="time" id="f_{{ $id }}_start" name="{{ $id }}[start]" class="form-input" value="{{ $trOld['start'] ?? '' }}" @if($required) required @endif>
                </div>
                <div>
                    <label class="form-label" for="f_{{ $id }}_end" style="font-size:0.78rem; margin-bottom:0.25rem;">To</label>
                    <input type="time" id="f_{{ $id }}_end" name="{{ $id }}[end]" class="form-input" value="{{ $trOld['end'] ?? '' }}" @if($required) required @endif
                           @if($required) data-time-range-end="f_{{ $id }}_start" @endif>
                </div>
            </div>
            @break

        @case('date_range')
            @php
                $drOld = old($id, []);
                if (!is_array($drOld)) $drOld = [];
                $drMin = $field['min_date'] ?? null;
                $drMax = $field['max_date'] ?? null;
            @endphp
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:end;">
                <div>
                    <label class="form-label" for="f_{{ $id }}_start" style="font-size:0.78rem; margin-bottom:0.25rem;">Start date</label>
                    <input type="date" id="f_{{ $id }}_start" name="{{ $id }}[start]" class="form-input"
                           value="{{ $drOld['start'] ?? '' }}"
                           @if($drMin) min="{{ $drMin }}" @endif @if($drMax) max="{{ $drMax }}" @endif
                           @if($required) required @endif
                           data-date-range-start="f_{{ $id }}_end">
                </div>
                <div>
                    <label class="form-label" for="f_{{ $id }}_end" style="font-size:0.78rem; margin-bottom:0.25rem;">End date</label>
                    <input type="date" id="f_{{ $id }}_end" name="{{ $id }}[end]" class="form-input"
                           value="{{ $drOld['end'] ?? '' }}"
                           @if($drMin) min="{{ $drMin }}" @endif @if($drMax) max="{{ $drMax }}" @endif
                           @if($required) required @endif
                           data-date-range-end="f_{{ $id }}_start">
                </div>
            </div>
            @break

        @default
            <input type="text" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}" @if($required) required @endif>
    @endswitch

    @if($help)<div class="form-help">{{ $help }}</div>@endif
    @if($hasError)<div class="form-error">{{ $errors->first($id) }}</div>@endif
</div>
@endif
