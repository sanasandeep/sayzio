@php
    /**
     * Renders a single wizard question field.
     * Expects: $q (question array), $answers (map), $draft.
     * Shared by the Basic profile & Additional content steps so the two
     * surfaces render identical inputs.
     */
    $key   = $q['key'];
    $type  = $q['type'] ?? 'text';
    $label = $q['label'];
    $val   = $answers[$key] ?? '';
    $req   = !empty($q['required']);
    $name  = "a[{$key}]";
    $id    = 'fld_' . $key;

    $byKey = [
        'instagram' => 'fa-hashtag', 'tiktok' => 'fa-hashtag', 'twitter' => 'fa-at',
        'whatsapp' => 'fa-comment-dots', 'phone' => 'fa-phone', 'address' => 'fa-location-dot',
        'hours' => 'fa-clock', 'discount_code' => 'fa-ticket',
    ];
    $icon = $byKey[$key] ?? match ($type) {
        'textarea' => 'fa-align-left',
        'select'   => 'fa-list-ul',
        'color'    => 'fa-palette',
        'image'    => 'fa-image',
        'url'      => 'fa-link',
        'email'    => 'fa-envelope',
        'phone'    => 'fa-phone',
        default    => 'fa-pen',
    };
    $wide = in_array($type, ['textarea'], true);
@endphp

<div class="{{ $wide ? 'sm:col-span-2' : '' }}">
    <label for="{{ $id }}" class="flex items-center gap-2 text-sm font-medium text-white/80 mb-1.5">
        <i class="fas {{ $icon }} text-violet-300/70 text-xs w-4 text-center"></i>
        <span>{{ $label }}</span>
        @if($req) <span class="text-violet-400">*</span> @endif
    </label>

    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
            placeholder="{{ $q['placeholder'] ?? '' }}"
            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">{{ $val }}</textarea>

    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}"
            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
            <option value="" class="bg-[#0d0818]">— pick one —</option>
            @foreach(($q['options'] ?? []) as $opt)
                <option value="{{ $opt['v'] }}" class="bg-[#0d0818]" @selected($val === $opt['v'])>{{ $opt['l'] }}</option>
            @endforeach
        </select>

    @elseif($type === 'color')
        <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-3 py-2">
            <input id="{{ $id }}" name="{{ $name }}" type="color"
                value="{{ $val ?: \App\Modules\User\Services\BiolinkWizardQuestions::defaultBrandColor($draft->category) }}"
                class="w-10 h-9 rounded-lg bg-transparent border-0 cursor-pointer">
            <span class="text-xs text-white/40">Used for buttons &amp; accents</span>
        </div>

    @elseif($type === 'image')
        <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-3 py-2">
            @if(!empty($val) && is_string($val))
                <img src="{{ $val }}" class="w-10 h-10 rounded-lg object-cover border border-white/10 flex-shrink-0" alt="">
            @else
                <span class="w-10 h-10 rounded-lg bg-violet-500/15 text-violet-300 flex items-center justify-center flex-shrink-0"><i class="fas fa-image"></i></span>
            @endif
            <input id="{{ $id }}" name="a_files[{{ $key }}]" type="file" accept="image/*"
                class="block text-xs text-white/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-violet-600 file:text-white file:cursor-pointer hover:file:bg-violet-700">
        </div>

    @elseif(in_array($type, ['url','email','phone'], true))
        <div class="relative">
            <i class="fas {{ $icon }} absolute left-3.5 top-1/2 -translate-y-1/2 text-white/25 text-xs pointer-events-none"></i>
            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'phone' ? 'tel' : $type }}"
                value="{{ $val }}" placeholder="{{ $q['placeholder'] ?? '' }}"
                class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
        </div>

    @else
        <input id="{{ $id }}" name="{{ $name }}" type="text" value="{{ $val }}"
            placeholder="{{ $q['placeholder'] ?? '' }}"
            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
    @endif

    @if(!empty($q['help']))
        <p class="text-xs text-white/30 mt-1">{{ $q['help'] }}</p>
    @endif
</div>
