@foreach($fields as $fieldKey => $field)
    @php
        $fieldAccess = $access.'.'.$fieldKey;
        $fieldName = $nameExpression." + '[".$fieldKey."]'";
        $fieldLabel = ucwords(str_replace('_', ' ', $fieldKey));
    @endphp
    <div class="mt-3" data-editor-field="{{ $fieldKey }}">
        <label class="{{ $labelClass }}">{{ $fieldLabel }}</label>
        @if($field['kind'] === 'list')
            @php
                $indexName = 'rowIndex'.$depth;
                $rowAccess = $fieldAccess.'['.$indexName.']';
                $rowName = $fieldName." + '[' + ".$indexName." + ']'";
                $blankRow = $field['scalar'] ? '' : \App\Modules\User\Support\BlockEditorFields::emptyObject($field['fields']);
            @endphp
            <input type="hidden" :name="{{ $fieldName }}" value="" :disabled="{{ $fieldAccess }}.length > 0">
            <template x-for="(row, {{ $indexName }}) in {{ $fieldAccess }}" :key="{{ $indexName }}">
                <div class="rounded-xl border p-3 mb-3" style="border-color:var(--border-glass)">
                    @if($field['scalar'])
                        <input type="text" :name="{{ $rowName }}" x-model="{{ $rowAccess }}" class="{{ $inputClass }}" aria-label="{{ $fieldLabel }} item">
                    @else
                        @include('user.links.partials.block-structured-fields', ['fields' => $field['fields'], 'access' => $rowAccess, 'nameExpression' => $rowName, 'depth' => $depth + 1])
                    @endif
                    <button type="button" class="text-xs mt-3 text-red-400" @click="{{ $fieldAccess }}.splice({{ $indexName }}, 1)">Remove item</button>
                </div>
            </template>
            <button type="button" class="text-xs font-semibold mt-2" @click="{{ $fieldAccess }}.push(@js($blankRow))">+ Add {{ strtolower($fieldLabel) }} item</button>
        @elseif($field['kind'] === 'object')
            @include('user.links.partials.block-structured-fields', ['fields' => $field['fields'], 'access' => $fieldAccess, 'nameExpression' => $fieldName, 'depth' => $depth + 1])
        @elseif($field['kind'] === 'boolean')
            <input type="hidden" :name="{{ $fieldName }}" :value="{{ $fieldAccess }} ? '1' : '0'">
            <input type="checkbox" x-model="{{ $fieldAccess }}" aria-label="{{ $fieldLabel }}">
        @else
            @if(str_contains($fieldKey, 'color'))
                <input type="color" aria-label="{{ $fieldLabel }} picker" :value="/^#[0-9a-f]{6}$/i.test({{ $fieldAccess }}) ? {{ $fieldAccess }} : '#3d6bff'" @input="{{ $fieldAccess }} = $event.target.value" class="w-10 h-9 rounded">
            @endif
            @if(in_array($fieldKey, ['text', 'description', 'content', 'html', 'caption', 'bio', 'body'], true))
                <textarea :name="{{ $fieldName }}" x-model="{{ $fieldAccess }}" aria-label="{{ $fieldLabel }}" rows="3" class="{{ $inputClass }}"></textarea>
            @else
            <input type="{{ $field['kind'] === 'number' ? 'number' : 'text' }}" :name="{{ $fieldName }}" x-model="{{ $fieldAccess }}" aria-label="{{ $fieldLabel }}" class="{{ $inputClass }}" @if(str_contains($fieldKey, 'color')) placeholder="Auto" @endif>
            @endif
        @endif
    </div>
@endforeach
