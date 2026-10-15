<details class="rounded-xl border border-white/10 p-4">
    <summary class="cursor-pointer text-sm font-semibold">Customize backgrounds &amp; bubbles</summary>
    <p class="text-xs text-white/50 mt-2">Keep defaults for a quick setup, or customize each surface. Choose readable text colors over images and gradients.</p>
    @foreach(['accent'=>'Header & launcher','chat'=>'Chat background','user'=>'Visitor bubbles','assistant'=>'Assistant bubbles'] as $part=>$label)
    <div class="mt-4 space-y-3" x-data="{ mode: @js($config[$part.'_mode'] ?? 'default') }">
        <h4 class="text-sm font-semibold">{{ $label }}</h4>
        <select name="config[{{ $part }}_mode]" x-model="mode" class="theme-input w-full" aria-label="{{ $label }} style">
            @foreach(['default'=>'Default','solid'=>'Solid color','gradient'=>'Gradient','image'=>'Image'] as $value=>$text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
        </select>
        <div x-show="mode!=='default'" class="grid grid-cols-2 gap-3">
            <label class="text-xs">Base color<input aria-label="{{ $label }} base color" type="color" name="config[{{ $part }}_color]" value="{{ $config[$part.'_color'] ?? ($part==='accent' ? ($config['accent'] ?? '#7c3aed') : '#f1f4f9') }}" class="w-full h-10"></label>
            <label class="text-xs">Text color<input aria-label="{{ $label }} text color" type="color" name="config[{{ $part }}_text]" value="{{ $config[$part.'_text'] ?? ($part==='accent' ? '#ffffff' : '#17213a') }}" class="w-full h-10"></label>
        </div>
        <div x-show="mode==='gradient'"><label class="text-xs">Gradient end<input type="color" name="config[{{ $part }}_color_end]" value="{{ $config[$part.'_color_end'] ?? '#6366f1' }}" class="w-full h-10"></label></div>
        <div x-show="mode==='image'">@include('user.links.partials.file-upload-field',['fieldName'=>'config['.$part.'_image]','currentValue'=>$config[$part.'_image']??'','acceptTypes'=>'image','labelText'=>$label.' image'])</div>
    </div>
    @endforeach
    <label class="block text-xs mt-4">Bubble corner radius (4–28 px)<input type="range" name="config[bubble_radius]" min="4" max="28" value="{{ $config['bubble_radius'] ?? 14 }}" class="w-full"></label>
</details>
