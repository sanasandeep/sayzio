{{--
   Renders provider-defined fields. Variables:
   - $providerSchema: provider definition from the registry
   - $config (optional): the IntegrationConfig being edited (for prefilling meta)
   - $masked  (optional): masked credentials map for placeholder hints when editing
--}}
@php
    $editing = isset($config) && $config !== null;
    $meta    = $editing ? (array) $config->meta : [];
    $__providerKey = $provider ?? ($config->provider ?? null);
@endphp
@if($__providerKey && isset($kind))
    <x-how-to-get-this guide-key="integrations.{{ $kind }}.{{ $__providerKey }}" class="mb-4" />
@endif
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @foreach($providerSchema['fields'] as $f)
        @php
            $key       = $f['key'];
            $label     = $f['label'];
            $type      = $f['type'] ?? 'text';
            $required  = (bool) ($f['required'] ?? false);
            $isCred    = ($f['group'] ?? 'meta') === 'credentials';
            $oldVal    = old("fields.$key");
            $existing  = $isCred ? '' : ($meta[$key] ?? '');
            $val       = $oldVal !== null ? $oldVal : $existing;
            $placeholder = $f['placeholder'] ?? '';
            if ($isCred && $editing && isset($masked[$key]) && ! $oldVal) {
                $placeholder = 'Saved · leave blank to keep · (' . $masked[$key] . ')';
            }
            $colSpan = ($type === 'textarea') ? 'sm:col-span-2' : '';
        @endphp
        <div class="{{ $colSpan }}">
            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-primary);">
                {{ $label }}
                @if($required && ! ($editing && $isCred))
                    <span class="text-red-400">*</span>
                @endif
                @if($isCred)
                    <span class="ml-1 inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded"
                          style="background: rgba(16,185,129,0.12); color: #10b981;">
                        <i class="fas fa-lock text-[8px]"></i> Encrypted
                    </span>
                @endif
            </label>

            @if($type === 'select')
                <select name="fields[{{ $key }}]" class="theme-input w-full" @if($required && ! $editing) required @endif>
                    <option value="">select</option>
                    @foreach(($f['options'] ?? []) as $optVal => $optLabel)
                        <option value="{{ $optVal }}" @selected((string) $val === (string) $optVal)>{{ $optLabel }}</option>
                    @endforeach
                </select>
            @elseif($type === 'textarea')
                <textarea name="fields[{{ $key }}]" rows="4" class="theme-input w-full" placeholder="{{ $placeholder }}">{{ $val }}</textarea>
            @else
                <input type="{{ in_array($type, ['email','password','url']) ? $type : 'text' }}"
                       name="fields[{{ $key }}]"
                       value="{{ $isCred ? $oldVal : $val }}"
                       placeholder="{{ $placeholder }}"
                       class="theme-input w-full"
                       autocomplete="off"
                       @if($required && ! ($editing && $isCred)) required @endif>
            @endif

            @if(! empty($f['help']))
                <p class="text-[11px] mt-1" style="color: var(--text-faint);">{{ $f['help'] }}</p>
            @endif
            @error("fields.$key")
                <p class="text-[11px] mt-1 text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
