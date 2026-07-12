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

    // Old input repopulation (Task #4640): on a 422 re-render, restore the value
    // this copy carried. old() supports the same dot-notation key as the error key,
    // so copies beyond index 0 survive validation failures and page reloads.
    $oldVal = old($errKey);
@endphp

@if(in_array($type, ['heading', 'paragraph']))
    @if($type === 'heading') <h3 class="form-heading">{{ $label }}</h3>
    @else <p class="form-paragraph">{{ $label }}</p> @endif
@elseif($type === 'file')
    @php
        // File children of repeatable groups. Mirror the flat file field's
        // plan-driven upload policy, resolved against the form OWNER so public
        // (unauthenticated) submissions still honour the owner's limits.
        $ffOwner  = $fieldOwner ?? auth()->user();
        $ffPolicy = (!empty($field['file_types']) || !empty($field['file_max_kb']) || !$ffOwner)
            ? null
            : \App\Services\UploadPolicy::for('form_field.file', $ffOwner);
        $ffAccept = !empty($field['file_types'])
            ? collect(explode(',', preg_replace('/[^a-zA-Z0-9,]/', '', (string) $field['file_types'])))->map(fn ($e) => '.' . $e)->implode(',')
            : ($ffPolicy['accept'] ?? '*/*');
        $ffMaxMb  = !empty($field['file_max_kb'])
            ? round(((int) $field['file_max_kb']) / 1024, 1)
            : ($ffPolicy['max_mb'] ?? null);
        $ffHint   = !empty($field['file_types']) ? strtoupper(str_replace(',', ', ', $field['file_types'])) : null;

        // Uploaded files can't be flashed back by old() after a 422 (the browser
        // never resubmits a file input's value). If THIS copy carried a file on
        // the failed submit, surface a precise "please re-attach" prompt so the
        // file isn't silently dropped.
        $repPending  = session('_rep_file_pending', []);
        $pendingName = $repPending[$sectionId][$repCopyIdx][$id] ?? null;
    @endphp
    <div class="form-field">
        <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
        @if($pendingName)
            <div role="status" style="display:flex;gap:0.5rem;align-items:flex-start;margin-bottom:0.5rem;padding:0.55rem 0.7rem;border-radius:8px;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.4);color:#b45309;font-size:0.8rem;line-height:1.35;">
                <i class="fas fa-triangle-exclamation" style="margin-top:0.12rem;"></i>
                <span>Please re-attach <strong>{{ $pendingName }}</strong> — uploaded files can’t be saved when the form is returned with errors, so this one needs to be selected again.</span>
            </div>
        @endif
        @include('user.partials.dropzone-input', [
            'name'     => $repName,
            'accept'   => $ffAccept,
            'required' => $required,
            'maxMb'    => $ffMaxMb,
            'hint'     => $ffHint,
            'compact'  => true,
            // Explicitly null the `form` param: this partial lives inside the
            // public form.blade.php scope where `$form` is the Form model, and
            // the dropzone would otherwise inherit it and render a bogus
            // form="<model>" attribute that detaches the file input from the
            // real <form>, so the upload is silently never submitted.
            'form'     => null,
        ])
        @if($help)<div class="form-help">{{ $help }}</div>@endif
        @if($hasError)<div class="form-error">{{ $errors->first($errKey) }}</div>@endif
    </div>
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
                      @if($required) required @endif>{{ is_scalar($oldVal) ? $oldVal : '' }}</textarea>
            @break

        @case('select')
            <select id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                    class="form-select {{ $hasError ? 'is-invalid' : '' }}" @if($required) required @endif>
                <option value="">— Choose —</option>
                @foreach(($field['options'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected((string) $oldVal === (string) $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div class="form-radio-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="radio" name="{{ $repName }}" value="{{ $opt }}" @checked((string) $oldVal === (string) $opt) @if($required) required @endif> {{ $opt }}</label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            @php $oldChecks = is_array($oldVal) ? array_map('strval', $oldVal) : []; @endphp
            <label class="form-label">{{ $label }}@if($required)<span class="form-required">*</span>@endif</label>
            <div class="form-check-group">
                @foreach(($field['options'] ?? []) as $opt)
                    <label><input type="checkbox" name="{{ $repName }}[]" value="{{ $opt }}" @checked(in_array((string) $opt, $oldChecks, true))> {{ $opt }}</label>
                @endforeach
            </div>
            @break

        @case('rating')
            @php $rMax = (int) ($field['max'] ?? 5); $rOld = (int) (is_scalar($oldVal) ? $oldVal : 0); @endphp
            <div class="rating-stars" x-data="{ value: {{ $rOld }}, hover: 0 }">
                @for($i = 1; $i <= $rMax; $i++)
                    <label :class="{ active: value >= {{ $i }}, hover: hover >= {{ $i }} }"
                           @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" @click="value = {{ $i }}">
                        <input type="radio" name="{{ $repName }}" value="{{ $i }}" @checked($rOld === $i) @if($required && $i === 1) required @endif x-bind:checked="value === {{ $i }}">
                        <i class="fas fa-star"></i>
                    </label>
                @endfor
            </div>
            @break

        @case('scale')
            @php $scMin = (int) ($field['min'] ?? 0); $scMax = (int) ($field['max'] ?? 10); @endphp
            <div class="scale-row">
                @for($i = $scMin; $i <= $scMax; $i++)
                    <input type="radio" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}_{{ $i }}" name="{{ $repName }}" value="{{ $i }}" @checked((string) $oldVal === (string) $i)>
                    <label for="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}_{{ $i }}">{{ $i }}</label>
                @endfor
            </div>
            @break

        @case('consent')
            <label style="display: flex; gap: 0.6rem; align-items: flex-start; cursor: pointer; line-height: 1.4;">
                <input type="checkbox" name="{{ $repName }}" value="1" @checked(filter_var($oldVal, FILTER_VALIDATE_BOOL)) @if($required) required @endif style="margin-top: 0.2rem;">
                <span class="form-label" style="margin: 0;">{{ $label }}@if($required)<span class="form-required">*</span>@endif</span>
            </label>
            @break

        @case('date')
            <input type="date" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
            @break

        @case('time')
            <input type="time" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
            @break

        @case('email')
            <input type="email" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
            @break

        @case('phone')
            <input type="tel" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
            @break

        @case('number')
            <input type="number" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   value="{{ is_scalar($oldVal) ? $oldVal : '' }}"
                   @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                   @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                   @if($required) required @endif>
            @break

        @case('url')
            <input type="url" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder ?: 'https://' }}"
                   value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
            @break

        @default
            <input type="text" id="frep_{{ $sectionId }}_{{ $repCopyIdx }}_{{ $id }}" name="{{ $repName }}"
                   class="form-input {{ $hasError ? 'is-invalid' : '' }}" placeholder="{{ $placeholder }}"
                   value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($required) required @endif>
    @endswitch

    @if($help)<div class="form-help">{{ $help }}</div>@endif
    @if($hasError)<div class="form-error">{{ $errors->first($errKey) }}</div>@endif
</div>
@endif
