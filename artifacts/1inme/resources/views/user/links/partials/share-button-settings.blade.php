{{--
    Share button and QR: the settings card.

    Sana, 2026-09-23: "share button should be visible or not,
    customizable options should be there in settings on that link.
    default active".

    This card lived inline in the Link in Bio advanced-settings screen.
    Every other page type has its own settings screen, so a card that
    only exists in one of them is a control the other types cannot offer
    -- which is what "for all link in bio or any other pages" asks to
    change. It is a partial now, and each page type includes it.

    Parameter
      $shareBtn  (array) the link's stored settings['biolink']['share_button']

    Note the toggle's default. An ABSENT `enabled` key means "never
    configured", which is now on; an explicit false means "turned off on
    purpose" and stays off. Collapsing the two would switch the button
    back on for every creator who had already said no.

    The card must sit inside the page-settings <form>: its inputs post
    as share_button[...] alongside everything else on that screen.
--}}
@php
    use App\Modules\User\Support\ShareButton;

    $sbResolved = ShareButton::resolve(['share_button' => $shareBtn ?? []]);
@endphp
<div class="card-premium p-6" x-data="{ enabled: {{ $sbResolved['enabled'] ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between mb-1">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(61,107,255,0.1);"><i class="fas fa-share-nodes text-blue-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Share Button & QR Code</h3>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="hidden" name="share_button[enabled]" value="0">
            <input type="checkbox" name="share_button[enabled]" value="1" x-model="enabled" class="sr-only peer">
            <div class="w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-blue-500/40 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:rounded-full after:h-4 after:w-4 after:transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="enabled ? 'background: #3d6bff' : ''">
                <div class="absolute top-[2px] start-[2px] rounded-full h-4 w-4 transition-all bg-white" :class="enabled ? 'translate-x-4' : ''"></div>
            </div>
        </label>
    </div>
    <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Add a floating share button so visitors can share your profile or scan a QR code.</p>

    <div x-show="enabled" x-transition class="space-y-4">
        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
            <input type="hidden" name="share_button[show_qr]" value="0">
            <input type="checkbox" name="share_button[show_qr]" value="1" {{ $sbResolved['show_qr'] ? 'checked' : '' }} class="rounded text-blue-500 focus:ring-blue-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
            <div>
                <span class="text-xs font-semibold" style="color: var(--text-primary);">Show QR Code</span>
                <p class="text-[10px]" style="color: var(--text-dimmed);">Display a scannable QR code in the share popup</p>
            </div>
        </label>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Style</label>
                <select name="share_button[style]" class="theme-input w-full text-xs">
                    @foreach(['fab' => 'Floating (FAB)', 'bar' => 'Share Bar', 'icon' => 'Icon Only'] as $val => $label)
                    <option value="{{ $val }}" {{ $sbResolved['style'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position</label>
                <select name="share_button[position]" class="theme-input w-full text-xs">
                    @foreach(['bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left', 'bottom-center' => 'Bottom Center', 'top-right' => 'Top Right', 'top-left' => 'Top Left'] as $val => $label)
                    <option value="{{ $val }}" {{ $sbResolved['position'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Size</label>
                <select name="share_button[size]" class="theme-input w-full text-xs">
                    @foreach(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $val => $label)
                    <option value="{{ $val }}" {{ $sbResolved['size'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Label</label>
                <input type="text" name="share_button[label]" value="{{ $sbResolved['label'] }}" placeholder="Share" class="theme-input w-full" maxlength="30">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Color</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="share_button[color]" value="{{ $sbResolved['color'] }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                    <input type="text" value="{{ $sbResolved['color'] }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text Color</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="share_button[text_color]" value="{{ $sbResolved['text_color'] }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                    <input type="text" value="{{ $sbResolved['text_color'] }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                </div>
            </div>
        </div>


        <div>
            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Where visitors can share to</label>
            <p class="text-[10px] mb-2" style="color: var(--text-dimmed);">Untick any you do not want offered. Each one is tagged, so your stats can tell a WhatsApp forward from a QR scan.</p>
            <div class="grid grid-cols-2 gap-2">
                <input type="hidden" name="share_button[networks][]" value="">
                @foreach (ShareButton::NETWORKS as $net)
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                    <input type="checkbox" name="share_button[networks][]" value="{{ $net['key'] }}"
                           {{ in_array($net['key'], $sbResolved['networks'], true) ? 'checked' : '' }}
                           class="rounded text-blue-500 focus:ring-blue-500/40 w-3.5 h-3.5"
                           style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                    <i class="{{ $net['icon'] }} text-[11px]" style="color: var(--text-muted);"></i>
                    <span class="text-[11px] font-medium" style="color: var(--text-primary);">{{ $net['label'] }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-qrcode text-blue-400 text-[10px]"></i>
                <span class="text-xs font-semibold" style="color: var(--text-primary);">QR Code Appearance</span>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Size (px)</label>
                    <input type="number" name="share_button[qr_size]" value="{{ $sbResolved['qr_size'] }}" min="100" max="400" step="10" class="theme-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">FG Color</label>
                    <div class="flex items-center gap-1">
                        <input type="color" name="share_button[qr_fg_color]" value="{{ $sbResolved['qr_fg_color'] }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                        <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $sbResolved['qr_fg_color'] }}</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">BG Color</label>
                    <div class="flex items-center gap-1">
                        <input type="color" name="share_button[qr_bg_color]" value="{{ $sbResolved['qr_bg_color'] }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                        <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $sbResolved['qr_bg_color'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
