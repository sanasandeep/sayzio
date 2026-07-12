@php
    /**
     * Simplified field renderer for repeatable-group children.
     *
     * Variables expected from caller:
     *   $field       — field definition array
     *   $repName     — full HTML name attribute value (e.g. "rep_sec1[0][email]")
     *   $repCopyIdx  — integer copy index (0-based) for error key lookup
     *   $sectionId   — parent section ID
     *
     * Excluded types (file, signature, pricing, hidden) must be filtered by
     * the caller; they will not render meaningfully here.
     */
    $type        = $field['type'] ?? 'text';
    $id          = $field['id'] ?? '';
    $label       = $field['label'] ?? '';
    $required    = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? '';
    $help        = $field['help'] ?? null;
    $repCopyIdx  = $repCopyIdx ?? 0;
    $sectionId   = $sectionId ?? '';

    // Error key: rep_sectionId.copyIdx.fieldId  (dot notation for $errors->has)
    $errKey  = "rep_{$sectionId}.{$repCopyIdx}.{$id}";
    $hasError = $errors->has($errKey);
@endphp

@if(in_array($type, ['heading', 'paragraph']))
    @if($type === 'heading') <h3 class="form-heading">{{ $label }}</h3>
    @else <p class="form-paragraph">{{ $label }}</p> @endif
@elseif(!in_array($type, ['file', 'signature', 'pricing', 'hidden', 'page_break', 'divider', 'section']))
<div class="form-field">
    @unless(in_array($type, ['consent', 'checkbox']))
        <label class="form-label" for="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}">
            {{ $label }}@if($required)<span class="form-required">*</span>@endif
        </label>
    @endunless

    @switch($type)
        @case('textarea')
            <textarea id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                      class="form-textarea {{ $hasError ? 'is-invalid' : '' }}"
                      rows="{{ $field['rows'] ?? 4 }}" placeholder="{{ $placeholder }}"
                      @if($required) required @endif></textarea>
            @break

        @case('select')
            <select id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                    class="form-select {{ $hasError ? 'is-invalid' : '' }}" @if($required) required @endif>
                <option value="">— Choose —</option>
                @foreach(($field['options'] ?? []) as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div class="form-radio-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="radio" name="{{ $repName }}" value="{{ $opt }}" @if($required) required @endif> {{ $opt }}</label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
            <div class="form-check-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="checkbox" name="{{ $repName }}[]" value="{{ $opt }}"> {{ $opt }}</label>
                @endforeach
            </div>
            @break

        @case('rating')
            @php $rMax = (int) ($field['max'] ?? 5); @endphp
            <div class="rating-stars" x-data="{ value: 0, hover: 0 }">
                @for($i = 1; $i <= $rMax; $i++)
                    <label :class="{ active: value >= {{ $i }}, hover: hover >= {{ $i }} }"
                           @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" @click="value = {{ $i }}">
                        <input type="radio" name="{{ $repName }}" value="{{ $i }}" @if($required && $i === 1) required @endif x-bind:checked="value === {{ $i }}">
                        <i class="fas fa-star"></i>
                    </label>
                @endfor
            </div>
            @break

        @case('scale')
            @php $scMin = (int) ($field['min'] ?? 0); $scMax = (int) ($field['max'] ?? 10); @endphp
            <div class="scale-row">
                @for($i = $scMin; $i <= $scMax; $i++)
                    <input type="radio" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}_{{ $i }}" name="{{ $repName }}" value="{{ $i }}">
                    <label for="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}_{{ $i }}">{{ $i }}</label>
                @endfor
            </div>
            @break

        @case('consent')
            <label style="display: flex; gap: 0.6rem; align-items: flex-start; cursor: pointer; line-height: 1.4;">
                <input type="checkbox" name="{{ $repName }}" value="1" @if($required) required @endif style="margin-top: 0.2rem;">
                <span class="form-label" style="margin: 0;">{{ $label }}@if($required)<span class="form-required">*</span>@endif</span>
            </label>
            @break

        @case('date')
            <input type="date" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" @if($required) required @endif>
            @break

        @case('time')
            <input type="time" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" @if($required) required @endif>
            @break

        @case('email')
            <input type="email" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   @if($required) required @endif>
            @break

        @case('phone')
            <input type="tel" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   @if($required) required @endif>
            @break

        @case('number')
            <input type="number" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                   @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                   @if($required) required @endif>
            @break

        @case('url')
            <input type="url" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder ?: 'https://' }}"
                   @if($required) required @endif>
            @break

        @default
            <input type="text" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   @if($required) required @endif>
    @endswitch

    @if($help)<div class="form-help">{{ $help }}</div>@endif
    @if($hasError)<div class="form-error">{{ $errors->first($errKey) }}</div>@endif
</div>
@endif
