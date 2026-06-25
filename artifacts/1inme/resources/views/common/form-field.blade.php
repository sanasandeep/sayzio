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
            <select id="f_{{ $id }}" name="{{ $id }}" class="form-select" @if($required) required @endif @if($showPrices) data-priced @endif>
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
                    <label><input type="radio" name="{{ $id }}" value="{{ $opt }}" @checked($oldVal === $opt) @if($required) required @endif @if($showPrices && $oc > 0) data-price="{{ $oc }}" @endif> {{ $opt }}@if($showPrices && $oc > 0)<span class="form-price-tag">{{ $fmtPrice($oc) }}</span>@endif</label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
            <div class="form-check-group">
                @foreach(($field['options'] ?? []) as $opt)
                    @php $oc = (int) ($optPrices[$opt] ?? 0); @endphp
                    <label><input type="checkbox" name="{{ $id }}[]" value="{{ $opt }}" @checked(is_array($oldVal) && in_array($opt, $oldVal)) @if($showPrices && $oc > 0) data-price="{{ $oc }}" @endif> {{ $opt }}@if($showPrices && $oc > 0)<span class="form-price-tag">{{ $fmtPrice($oc) }}</span>@endif</label>
                @endforeach
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
                <input type="checkbox" name="{{ $id }}" value="1" @checked($oldVal) @if($required) required @endif @if($showPrices && $unitPrice > 0) data-price-addon="{{ $unitPrice }}" @endif style="margin-top: 0.2rem;">
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
            <input type="number" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}"
                   @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                   @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                   @if($showPrices && $unitPrice > 0) data-price-unit="{{ $unitPrice }}" @endif
                   @if($required) required @endif>
            @if($showPrices && $unitPrice > 0)<div class="form-help">{{ number_format($unitPrice / 100, 2) }} {{ $priceCur }} per unit</div>@endif
            @break

        @case('url')
            <input type="url" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder ?: 'https://' }}" value="{{ $oldVal }}" @if($required) required @endif>
            @break

        @default
            <input type="text" id="f_{{ $id }}" name="{{ $id }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $oldVal }}" @if($required) required @endif>
    @endswitch

    @if($help)<div class="form-help">{{ $help }}</div>@endif
    @if($hasError)<div class="form-error">{{ $errors->first($id) }}</div>@endif
</div>
@endif
