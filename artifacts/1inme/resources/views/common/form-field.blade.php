@php
    $type = $field['type'] ?? 'text';
    $id = $field['id'] ?? '';
    $label = $field['label'] ?? '';
    $required = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? '';
    $help = $field['help'] ?? null;
    $oldVal = old($id);
    $hasError = $errors->has($id);
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
            <select id="f_{{ $id }}" name="{{ $id }}" class="form-select" @if($required) required @endif>
                <option value="">— Choose —</option>
                @foreach(($field['options'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected($oldVal === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div class="form-radio-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="radio" name="{{ $id }}" value="{{ $opt }}" @checked($oldVal === $opt) @if($required) required @endif> {{ $opt }}</label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
            <div class="form-check-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="checkbox" name="{{ $id }}[]" value="{{ $opt }}" @checked(is_array($oldVal) && in_array($opt, $oldVal))> {{ $opt }}</label>
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
            <input type="file" id="f_{{ $id }}" name="{{ $id }}" class="form-input" @if($required) required @endif>
            @break

        @case('consent')
            <label style="display: flex; gap: 0.6rem; align-items: flex-start; cursor: pointer; line-height: 1.4;">
                <input type="checkbox" name="{{ $id }}" value="1" @checked($oldVal) @if($required) required @endif style="margin-top: 0.2rem;">
                <span class="form-label" style="margin: 0;">{{ $label }}@if($required)<span class="form-required">*</span>@endif</span>
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
                   @if($required) required @endif>
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
