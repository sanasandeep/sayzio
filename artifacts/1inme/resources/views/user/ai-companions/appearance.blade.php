<details class="rounded-xl border theme-border p-4">
    <summary class="cursor-pointer text-sm font-semibold">Customize backgrounds &amp; bubbles</summary>
    <p class="text-xs theme-text-muted mt-2">Keep defaults for a quick setup, or customize each surface. Choose readable text colors over images and gradients.</p>
    @foreach(['accent'=>'Header & launcher','chat'=>'Chat background','user'=>'Visitor bubbles','assistant'=>'Assistant bubbles'] as $part=>$label)
    <div class="mt-4 space-y-3" x-data="surfaceContrast(@js($config[$part.'_mode'] ?? 'default'), @js($config[$part.'_color'] ?? ($part==='accent' ? ($config['accent'] ?? '#7c3aed') : '#f1f4f9')), @js($config[$part.'_text'] ?? ($part==='accent' ? '#ffffff' : '#17213a')), @js($config[$part.'_color_end'] ?? '#6366f1'))">
        <h4 class="text-sm font-semibold">{{ $label }}</h4>
        <select name="config[{{ $part }}_mode]" x-model="mode" class="theme-input w-full" aria-label="{{ $label }} style">
            @foreach(['default'=>'Default','solid'=>'Solid color','gradient'=>'Gradient','image'=>'Image'] as $value=>$text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
        </select>
        <div x-show="mode!=='default'" class="grid grid-cols-2 gap-3">
            <label class="text-xs">Base color<input aria-label="{{ $label }} base color" type="color" name="config[{{ $part }}_color]" x-model="base" value="{{ $config[$part.'_color'] ?? ($part==='accent' ? ($config['accent'] ?? '#7c3aed') : '#f1f4f9') }}" class="w-full h-10"></label>
            <label class="text-xs">Text color<input aria-label="{{ $label }} text color" type="color" name="config[{{ $part }}_text]" x-model="text" value="{{ $config[$part.'_text'] ?? ($part==='accent' ? '#ffffff' : '#17213a') }}" class="w-full h-10"></label>
        </div>
        <div x-show="mode==='gradient'"><label class="text-xs">Gradient end<input type="color" name="config[{{ $part }}_color_end]" x-model="end" value="{{ $config[$part.'_color_end'] ?? '#6366f1' }}" class="w-full h-10"></label></div>
        {{-- Advisory, never blocking: it is the owner's widget and they may
             have a reason. But "choose readable colors" as a sentence of
             advice is not a check, and a gradient that ends dark under dark
             text is exactly what it failed to catch. --}}
        <p x-show="mode!=='default' && mode!=='image' && worst() < 4.5" x-cloak
           class="text-[11px] rounded-lg px-3 py-2 bg-amber-500/10 text-amber-500 border border-amber-500/20">
            <strong>Hard to read.</strong>
            <span x-show="mode==='gradient'">Your text colour contrasts <span x-text="ratio(text, end).toFixed(1)"></span>:1 against the gradient's end.</span>
            <span x-show="mode!=='gradient'">Your text colour contrasts <span x-text="ratio(text, base).toFixed(1)"></span>:1 against the background.</span>
            4.5:1 is the readable minimum. It will save either way.
        </p>
        <div x-show="mode==='image'">@include('user.links.partials.file-upload-field',['fieldName'=>'config['.$part.'_image]','currentValue'=>$config[$part.'_image']??'','acceptTypes'=>'image','labelText'=>$label.' image'])</div>
    </div>
    @endforeach
    <label class="block text-xs mt-4">Bubble corner radius (4–28 px)<input type="range" name="config[bubble_radius]" min="4" max="28" value="{{ $config['bubble_radius'] ?? 14 }}" class="w-full"></label>
</details>

<script>
    // WCAG 2.1 relative luminance and contrast ratio. Small enough to keep
    // here rather than reach for a library the editor does not otherwise need.
    function surfaceContrast(mode, base, text, end) {
        return {
            mode, base, text, end,
            channel(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); },
            luminance(hex) {
                const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || ''));
                if (! m) { return 0; }
                const n = parseInt(m[1], 16);
                return 0.2126 * this.channel((n >> 16) & 255)
                     + 0.7152 * this.channel((n >> 8) & 255)
                     + 0.0722 * this.channel(n & 255);
            },
            ratio(a, b) {
                const x = this.luminance(a), y = this.luminance(b);
                return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
            },
            // A gradient has to stay readable at BOTH ends, so the worst of
            // the two is the one that decides.
            worst() {
                const against = this.mode === 'gradient' ? [this.base, this.end] : [this.base];
                return Math.min(...against.map((c) => this.ratio(this.text, c)));
            },
        };
    }
</script>
