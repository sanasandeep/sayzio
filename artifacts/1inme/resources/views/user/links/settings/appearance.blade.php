@extends('user.layouts.app')
@section('title', 'Appearance - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $activeSettingsTab = 'appearance';
    // Aliases UI is fully owned by partials/aliases-card.blade.php (computes
    // its own $extras/$usedExtras/$aliasHost/$canAddMore from $link). We only
    // forward $maxExtras since some controller paths still pass it explicitly.
    $maxExtras = $maxExtras ?? (auth()->user()?->getMaxAliasesPerLink($link->type) ?? 0);
    $bgType = $bs['background_type'] ?? 'color';
    $bgColor = $bs['background_color'] ?? '#0a0612';
    $fontColor = $bs['font_color'] ?? '#ffffff';
    $bgGradient = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
    $gradientColors = $bs['gradient_colors'] ?? [['color'=>'#0a0612','pos'=>0],['color'=>'#1a0533','pos'=>50],['color'=>'#0a0612','pos'=>100]];
    $gradientAngle = $bs['gradient_angle'] ?? 135;
    $gradientTypeVal = $bs['gradient_type'] ?? 'linear';
    $slideshowImages = $bs['slideshow_images'] ?? [];
    $slideshowInterval = $bs['slideshow_interval'] ?? 5;
    $videoUrl = $bs['video_url'] ?? '';
    $videoFile = $bs['video_file'] ?? '';
    $bgTemplateId = $bs['bg_template_id'] ?? null;
    $bgAttachment = $bs['bg_attachment'] ?? 'fixed';
    $bgFallbackColor = $bs['bg_fallback_color'] ?? '#0a0612';
    $bgFallbackImage = $bs['bg_fallback_image'] ?? '';
    $bgBlur = $bs['bg_blur'] ?? 0;
    $bgOverlayColor = $bs['bg_overlay_color'] ?? '#000000';
    $bgOverlayOpacity = $bs['bg_overlay_opacity'] ?? 0;
    $fontFamily = $bs['font_family'] ?? 'Space Grotesk';
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7" id="settings-tab-content">
            {{-- Aliases card lives OUTSIDE the page-settings form because it contains
                 its own <form> tags (add / promote / delete) — nesting forms is invalid HTML. --}}
            <div class="mb-6">
                @include('user.links.partials.aliases-card', ['link' => $link, 'domains' => $domains ?? collect()])
            </div>

            <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                @csrf

                <div class="space-y-6">

                    {{-- The legacy inline aliases block was deleted entirely.
                         It was hidden (display:none) but contained nested
                         <form> tags whose </form> closing tags would prematurely
                         close THIS outer page-settings form per the HTML spec
                         (browsers ignore nested <form> open tags but respect
                         the close tags). That kicked the bg-template radios
                         and other downstream inputs out of form scope, which
                         broke the live draft preview auto-binder. The visible
                         aliases card lives outside the form (line ~47) and is
                         unaffected. --}}

                    <div class="card-premium p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-palette text-pink-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Design</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page Title @if($link->is_verified)<span class="text-[10px] px-1.5 py-0.5 rounded ml-1" style="background: rgba(29,155,240,0.1); color: #1d9bf0;"><i class="fas fa-lock text-[8px]"></i> Verified</span>@endif</label>
                                    <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="theme-input w-full" placeholder="My Link in Bio" {{ $link->is_verified ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Family</label>
                                    @include('user.links.partials.font-picker', [
                                        'name' => 'font_family',
                                        'value' => $fontFamily,
                                        'pickerId' => 'pageFont',
                                        'allowInherit' => false,
                                    ])
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description</label>
                                <textarea name="biolink_description" rows="2" class="theme-input w-full" placeholder="A short description for your page">{{ $bs['biolink_description'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="font_color" value="{{ $fontColor }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $fontColor }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('user.links.partials.biolink-background-card', ['link' => $link, 'bgTemplates' => $bgTemplates])

                    @include('user.links.partials.page-stickers-card', ['link' => $link, 'bs' => $bs])
                    {{-- ── Floating text overlays (Task #5954) ─────────────────
                         Free-placed captions layered over the whole page.
                         Percent x/y so they land proportionally on every
                         screen. Design-locked pages keep their baked design:
                         the server ignores this field when locked. --}}
                    @php
                        $pageOverlaysSaved = [];
                        $bsOv = $link->settings['biolink']['text_overlays'] ?? null;
                        if (is_array($bsOv)) $pageOverlaysSaved = array_values($bsOv);
                        $pageOverlayMax = \App\Modules\User\Models\BiolinkBlock::PAGE_TEXT_OVERLAY_MAX;
                        $pageOverlayFonts = array_values(array_map(
                            fn ($e) => $e['family'],
                            array_filter(\App\Modules\User\Support\FontCatalog::all(), fn ($e) => in_array($e['category'], ['display', 'handwriting'], true))
                        ));
                        $pageDesignLocked = $link->isDesignLocked();
                    @endphp
                    <div class="card-premium p-6">
                        <div class="flex items-center gap-3 mb-1">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(245,158,11,0.1);"><i class="fas fa-font text-amber-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Floating Text</h3>
                        </div>
                        <p class="text-[11px] mb-4" style="color: var(--text-faint);">Layer up to {{ $pageOverlayMax }} short captions anywhere over your page: drag to place, tilt for a scrapbook feel.</p>

                        @if($pageDesignLocked)
                            <p class="text-xs rounded-lg p-3" style="background: rgba(245,158,11,0.08); color: var(--text-muted); border: 1px solid rgba(245,158,11,0.25);">
                                <i class="fas fa-lock mr-1 text-amber-400"></i>This page uses a design-locked template, so floating text is managed by the template and can't be edited here.
                            </p>
                        @else
                        <div x-data="{
                                overlays: @js($pageOverlaysSaved),
                                max: {{ $pageOverlayMax }},
                                error: '',
                                drag: null,
                                syncOverlays() {
                                    this.$nextTick(() => {
                                        const el = this.$refs.overlaysInput;
                                        el.value = this.overlays.length ? JSON.stringify(this.overlays) : '';
                                        el.dispatchEvent(new Event('input', { bubbles: true }));
                                    });
                                },
                                addOverlay() {
                                    if (this.overlays.length >= this.max) { this.error = 'Floating text limit reached ({{ $pageOverlayMax }} max).'; return; }
                                    this.error = '';
                                    this.overlays.push({ text: 'Your text', font: '', color: '#ffffff', size: 22, x: 50, y: 12, rotate: -6 });
                                    this.syncOverlays();
                                },
                                removeOverlay(i) { this.overlays.splice(i, 1); this.error = ''; this.syncOverlays(); },
                                overlayStyle(o) {
                                    const x = Math.max(0, Math.min(100, parseFloat(o.x) || 0));
                                    const y = Math.max(0, Math.min(100, parseFloat(o.y) || 0));
                                    const size = Math.max(10, Math.min(72, parseInt(o.size, 10) || 22));
                                    const rot = Math.max(-180, Math.min(180, parseInt(o.rotate, 10) || 0));
                                    let fam = String(o.font || '').replace(/[^a-zA-Z0-9 :_\-]/g, '');
                                    if (fam.indexOf('custom:') === 0) fam = fam.slice(7);
                                    const color = /^#[0-9a-fA-F]{3,8}$/.test(String(o.color || '')) ? o.color : '#ffffff';
                                    return 'left:' + x + '%;top:' + y + '%;'
                                        + (fam ? &quot;font-family:'&quot; + fam + &quot;';&quot; : '')
                                        + 'color:' + color + ';font-size:' + Math.round(size * 0.6) + 'px;line-height:1.15;white-space:nowrap;'
                                        + 'text-shadow:0 1px 6px rgba(0,0,0,0.45);'
                                        + 'transform:translate(-50%,-50%)' + (rot !== 0 ? ' rotate(' + rot + 'deg)' : '') + ';';
                                },
                                startOverlayDrag(i, ev) {
                                    const stage = this.$refs.overlayStage;
                                    if (!stage) return;
                                    this.drag = { i: i };
                                    try { stage.setPointerCapture(ev.pointerId); } catch (e) {}
                                },
                                onOverlayDrag(ev) {
                                    if (!this.drag) return;
                                    const stage = this.$refs.overlayStage;
                                    const rect = stage.getBoundingClientRect();
                                    const o = this.overlays[this.drag.i];
                                    o.x = Math.round(Math.max(0, Math.min(100, ((ev.clientX - rect.left) / rect.width) * 100)) * 100) / 100;
                                    o.y = Math.round(Math.max(0, Math.min(100, ((ev.clientY - rect.top) / rect.height) * 100)) * 100) / 100;
                                },
                                endOverlayDrag(ev) {
                                    if (!this.drag) return;
                                    this.drag = null;
                                    try { this.$refs.overlayStage.releasePointerCapture(ev.pointerId); } catch (e) {}
                                    this.syncOverlays();
                                },
                             }">
                            <input type="hidden" name="text_overlays" x-ref="overlaysInput"
                                   value="{{ $pageOverlaysSaved ? json_encode($pageOverlaysSaved) : '' }}">

                            {{-- Drag stage: a tall page-shaped canvas; positions
                                 are stored as percentages of the content column. --}}
                            <div class="mb-3" x-show="overlays.length" x-cloak>
                                <div x-ref="overlayStage" class="relative select-none rounded-xl mx-auto"
                                     style="touch-action:none; max-width: 260px; aspect-ratio: 9 / 16; background: linear-gradient(180deg, rgba(127,127,127,0.18), rgba(127,127,127,0.08)); border: 1px solid var(--border-subtle);"
                                     @pointermove="onOverlayDrag($event)" @pointerup="endOverlayDrag($event)" @pointercancel="endOverlayDrag($event)">
                                    <div class="absolute inset-x-6 top-5 space-y-2 pointer-events-none" aria-hidden="true">
                                        <div class="mx-auto rounded-full" style="width: 36px; height: 36px; background: rgba(127,127,127,0.35);"></div>
                                        <div class="rounded" style="height: 8px; background: rgba(127,127,127,0.3);"></div>
                                        <div class="rounded" style="height: 24px; background: rgba(127,127,127,0.22);"></div>
                                        <div class="rounded" style="height: 24px; background: rgba(127,127,127,0.22);"></div>
                                        <div class="rounded" style="height: 24px; background: rgba(127,127,127,0.22);"></div>
                                    </div>
                                    <template x-for="(o, i) in overlays" :key="'ov' + i">
                                        <span class="absolute z-10 font-bold"
                                              :class="drag && drag.i === i ? 'cursor-grabbing ring-2 ring-amber-400' : 'cursor-grab'"
                                              :style="overlayStyle(o)"
                                              x-text="(o.text || '').trim() || 'Your text'"
                                              @pointerdown.prevent="startOverlayDrag(i, $event)"></span>
                                    </template>
                                </div>
                                <p class="text-[10px] mt-1 text-center" style="color: var(--text-dimmed);"><i class="fas fa-hand-pointer mr-1"></i>Drag a caption anywhere on the mini page preview.</p>
                            </div>

                            <template x-for="(o, i) in overlays" :key="'ovr' + i">
                                <div class="rounded-lg p-2 mb-2" style="border: 1px solid var(--border-subtle); background: var(--bg-glass-input);">
                                    <div class="flex items-center gap-2 mb-2">
                                        <input type="text" maxlength="120" x-model="o.text" @input="syncOverlays()" placeholder="Caption text" class="theme-input flex-1 text-xs py-1.5">
                                        <input type="color" :value="/^#[0-9a-fA-F]{6}$/.test(o.color || '') ? o.color : '#ffffff'"
                                               @input="o.color = $event.target.value; syncOverlays()"
                                               class="w-9 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                        <button type="button" @click="removeOverlay(i)" class="text-red-400 hover:text-red-300 px-1.5" title="Remove"><i class="fas fa-trash-can text-xs"></i></button>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-1.5">
                                        <div class="col-span-2 sm:col-span-1">
                                            <label class="text-[10px] block" style="color: var(--text-dimmed);">Font</label>
                                            <select x-model="o.font" @change="syncOverlays()" class="theme-input w-full text-xs py-1.5">
                                                <option value="">Default</option>
                                                @foreach($pageOverlayFonts as $povF)
                                                <option value="{{ $povF }}">{{ $povF }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-[10px] block" style="color: var(--text-dimmed);">Size</label>
                                            <input type="number" min="10" max="72" x-model.number="o.size" @input="syncOverlays()" class="theme-input w-full text-xs py-1.5">
                                        </div>
                                        <div>
                                            <label class="text-[10px] block" style="color: var(--text-dimmed);">Rotate°</label>
                                            <input type="number" min="-180" max="180" x-model.number="o.rotate" @input="syncOverlays()" class="theme-input w-full text-xs py-1.5">
                                        </div>
                                        <div>
                                            <label class="text-[10px] block" style="color: var(--text-dimmed);">X %</label>
                                            <input type="number" min="0" max="100" step="0.5" x-model.number="o.x" @input="syncOverlays()" class="theme-input w-full text-xs py-1.5">
                                        </div>
                                        <div>
                                            <label class="text-[10px] block" style="color: var(--text-dimmed);">Y %</label>
                                            <input type="number" min="0" max="100" step="0.5" x-model.number="o.y" @input="syncOverlays()" class="theme-input w-full text-xs py-1.5">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <button type="button" @click="addOverlay()" x-show="overlays.length < max" class="w-full text-center text-xs py-2 rounded-lg" style="border: 1px dashed var(--border-subtle); color: var(--text-muted);">
                                <i class="fas fa-plus mr-1"></i>Add floating text
                            </button>
                            <p class="text-[10px] mt-1 text-red-400" x-show="error" x-text="error" x-cloak></p>
                        </div>
                        @endif
                    </div>

                </div>

                {{-- Inline save for the appearance/page-settings form. Non-sticky so it
                     doesn't stack with the link-settings form's sticky save bar below. --}}
                <div class="mt-6 py-4 flex items-center gap-3 flex-wrap border-t" style="border-color: var(--border-glass);">
                    <button type="submit" id="saveAppearanceBtn" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                        <i class="fas fa-save text-xs"></i> Save Appearance Settings
                    </button>
                    <span class="text-[11px]" style="color: var(--text-faint);">
                        Saves background, template, font and theme choices.
                    </span>
                </div>
            </form>

            {{-- ==================== LINK SETTINGS FORM (merged from /edit) ==================== --}}
            @php
                $lset = $link->settings ?? [];
                $expiryUrl = $lset['expiry_url'] ?? '';
                $maxClicks = (int) ($lset['max_clicks'] ?? 0);
                $startAt = $lset['start_at'] ?? null;
                $expireOnFirstClick = !empty($lset['expire_on_first_click']);
                $countryRestrictions = implode(',', $lset['country_restrictions'] ?? []);
                $deviceTargeting = $lset['device_targeting'] ?? [];
            @endphp
            <form method="POST" action="{{ route('user.links.update', $link) }}" enctype="multipart/form-data" class="space-y-6 mt-6"
                  x-data="{ pwd: {{ $link->is_password_protected ? 'true' : 'false' }}, expCondition: '{{ $link->expires_at ? 'date' : ($maxClicks ? 'clicks' : ($expireOnFirstClick ? 'first_click' : 'none')) }}' }">
                @csrf @method('PUT')

                {{-- LINK DETAILS --}}
                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.1);"><i class="fas fa-sliders-h text-emerald-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Link Details</h3>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Internal Title</label>
                            <input type="text" name="title" value="{{ old('title', $link->title) }}" class="theme-input w-full" placeholder="Internal label for this link">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Project</label>
                                <select name="project_id" class="theme-input w-full">
                                    <option value="">No project</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}" {{ old('project_id', $link->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Status</label>
                                <select name="is_active" class="theme-input w-full">
                                    <option value="1" {{ $link->is_active ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$link->is_active ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PROTECTION & EXPIRY --}}
                <div class="card-premium p-6">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(245,158,11,0.1);"><i class="fas fa-shield-alt text-amber-400 text-xs"></i></div>
                            <div>
                                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Protection & Expiry</h3>
                                <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Control who can open this link, and when it should stop working.</p>
                            </div>
                        </div>
                        <button type="button" @click="$refs.expHelp.classList.toggle('hidden')" class="text-[10px] px-2 py-1 rounded-md flex-shrink-0" style="background: var(--bg-glass-input); color: var(--text-faint);"><i class="fas fa-question-circle mr-1"></i> Help</button>
                    </div>
                    <div x-ref="expHelp" class="hidden mb-4 p-3 rounded-lg text-[11px] leading-relaxed" style="background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.2); color: var(--text-muted);">
                        <p class="mb-1.5"><strong style="color: var(--text-primary);">When would I use this?</strong></p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li><strong>Password</strong>, keep prying eyes out of a private page (a portfolio share, an early-access drop).</li>
                            <li><strong>Schedule</strong>, pre-publish a launch link today and have it go live exactly at sale time.</li>
                            <li><strong>Expire on date</strong>, promo or coupon page that should auto-close after a deadline.</li>
                            <li><strong>Click limit</strong>, limited offers ("first 100 visitors only"), invite caps for events.</li>
                            <li><strong>One-Time</strong>, secret reveals, single-use tickets, password reset URLs.</li>
                            <li><strong>Redirect after expired</strong>, instead of showing the default "expired" page, send leftover visitors to your homepage or sale page.</li>
                        </ul>
                    </div>
                    <div class="space-y-5">
                        {{-- PASSWORD --}}
                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input type="checkbox" name="is_password_protected" value="1" x-model="pwd" class="rounded text-blue-500 focus:ring-blue-500/40" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Password protect this link</span>
                            </label>
                            <div x-show="pwd" x-transition class="mt-2 ml-7">
                                @include('common.partials.password-field', [
                                    'name' => 'password',
                                    'placeholder' => $link->is_password_protected ? 'New password (leave empty to keep current)' : 'Enter a password',
                                    'autocomplete' => 'new-password',
                                    'wrapClass' => 'max-w-sm',
                                    'inputClass' => 'theme-input w-full',
                                ])
                                <p class="text-[10px] mt-1" style="color: var(--text-faint);">Visitors must enter this password to access the page.</p>
                            </div>
                        </div>

                        {{-- SCHEDULED START --}}
                        <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-primary);"><i class="fas fa-calendar-plus text-[10px] mr-1.5 text-emerald-400"></i> Schedule Activation</label>
                            <input type="datetime-local" name="start_at" value="{{ old('start_at', $startAt ? \Carbon\Carbon::parse($startAt)->format('Y-m-d\TH:i') : '') }}" class="theme-input w-full max-w-xs">
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);">Optional. Link returns 410 (or redirects to your fallback URL) before this time.</p>
                        </div>

                        {{-- EXPIRY CONDITIONS --}}
                        <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <label class="block text-xs font-semibold mb-2" style="color: var(--text-primary);"><i class="fas fa-hourglass-half text-[10px] mr-1.5 text-amber-400"></i> Expire When…</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                                <button type="button" @click="expCondition = 'none'" :class="expCondition === 'none' ? 'ring-2 ring-blue-500' : ''" class="px-3 py-2.5 rounded-lg text-[11px] font-semibold transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                    <i class="fas fa-infinity text-[10px] mb-1 block"></i> Never
                                </button>
                                <button type="button" @click="expCondition = 'date'" :class="expCondition === 'date' ? 'ring-2 ring-blue-500' : ''" class="px-3 py-2.5 rounded-lg text-[11px] font-semibold transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                    <i class="fas fa-calendar-times text-[10px] mb-1 block"></i> On Date
                                </button>
                                <button type="button" @click="expCondition = 'clicks'" :class="expCondition === 'clicks' ? 'ring-2 ring-blue-500' : ''" class="px-3 py-2.5 rounded-lg text-[11px] font-semibold transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                    <i class="fas fa-hashtag text-[10px] mb-1 block"></i> Click Limit
                                </button>
                                <button type="button" @click="expCondition = 'first_click'" :class="expCondition === 'first_click' ? 'ring-2 ring-blue-500' : ''" class="px-3 py-2.5 rounded-lg text-[11px] font-semibold transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                    <i class="fas fa-bolt text-[10px] mb-1 block"></i> One-Time
                                </button>
                            </div>

                            <div x-show="expCondition === 'date'" x-transition>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Expiration date & time</label>
                                <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $link->expires_at?->format('Y-m-d\TH:i')) }}" class="theme-input w-full max-w-xs">
                            </div>
                            <div x-show="expCondition === 'clicks'" x-transition>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Maximum total clicks</label>
                                <input type="number" name="max_clicks" min="1" max="1000000000" value="{{ old('max_clicks', $maxClicks ?: '') }}" placeholder="e.g. 100" class="theme-input w-full max-w-xs">
                                <p class="text-[10px] mt-1" style="color: var(--text-faint);">Currently used: <span class="font-mono">{{ number_format($link->total_clicks) }}</span> click(s).</p>
                            </div>
                            <div x-show="expCondition === 'first_click'" x-transition class="p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <p class="text-xs flex items-start gap-2" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle text-amber-400 text-[11px] mt-0.5"></i>
                                    <span>This link will <strong>self-destruct</strong> right after the first visit. Useful for one-time invites, secret reveals, or unique tickets.</span>
                                </p>
                            </div>

                            <input type="hidden" name="_exp_mode" :value="expCondition">
                            {{-- Server uses _exp_mode to clear unselected expiry fields. --}}
                        </div>

                        {{-- EXPIRY URL --}}
                        <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-primary);"><i class="fas fa-arrow-right-from-bracket text-[10px] mr-1.5 text-rose-400"></i> Redirect URL after expired</label>
                            <input type="url" name="expiry_url" value="{{ old('expiry_url', $expiryUrl) }}" placeholder="https://your-site.com/expired"
                                   class="theme-input w-full"
                                   pattern="https?://.+">
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);">When set, expired/unavailable visitors are redirected here. Otherwise the default expired page is shown. Must be a valid URL starting with <span class="font-mono">http://</span> or <span class="font-mono">https://</span>.</p>
                        </div>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="card-premium p-6" x-data="{ help: false }">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-search text-indigo-400 text-xs"></i></div>
                            <div>
                                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Search & Social Sharing</h3>
                                <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">How your link looks on Google, WhatsApp, Twitter, LinkedIn and other platforms when someone shares it.</p>
                            </div>
                        </div>
                        <button type="button" @click="help = !help" class="text-[10px] px-2 py-1 rounded-md flex-shrink-0" style="background: var(--bg-glass-input); color: var(--text-faint);"><i class="fas fa-question-circle mr-1"></i> Why this matters</button>
                    </div>
                    <div x-show="help" x-transition x-cloak class="mb-4 p-3 rounded-lg text-[11px] leading-relaxed" style="background: rgba(99,102,241,0.06); border: 1px solid rgba(99,102,241,0.2); color: var(--text-muted);">
                        When someone pastes your link in a chat or shares it on social media, those apps show a small preview card with a title, description and image. By default they grab whatever your page contains, which can look ugly or wrong. Filling these in lets you <strong style="color: var(--text-primary);">control exactly what people see before they click</strong>, which dramatically increases the number of people who actually open the link.
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page Title <span class="text-[10px]" style="color: var(--text-faint);"> - shown in browser tabs & Google results</span></label>
                            <input type="text" name="seo_title" value="{{ old('seo_title', $link->seo_title) }}" maxlength="60" placeholder="e.g. Sarah's Photography Portfolio" class="theme-input w-full">
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);"><i class="fas fa-lightbulb text-amber-400 mr-1"></i> Keep it under 60 characters. Lead with the most interesting word.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description <span class="text-[10px]" style="color: var(--text-faint);"> - the small grey text below the title</span></label>
                            <textarea name="seo_description" maxlength="160" placeholder="Two short sentences telling people why to click." rows="2" class="theme-input w-full">{{ old('seo_description', $link->seo_description) }}</textarea>
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);"><i class="fas fa-lightbulb text-amber-400 mr-1"></i> Aim for ~150 characters. Think of it as a one-line elevator pitch.</p>
                        </div>
                        @include('user.partials.dropzone-input', [
                            'name'        => 'seo_image',
                            'label'       => 'Share Preview Image',
                            'policy'      => \App\Services\UploadPolicy::for('link.seo_image', auth()->user()),
                            'currentUrl'  => $link->seo_image,
                            'currentName' => $link->seo_image ? 'Saved preview image' : null,
                            'hint'        => 'Best 1200×630, bold image with minimal text',
                            'compact'     => true,
                        ])
                        @include('user.partials.dropzone-input', [
                            'name'        => 'favicon',
                            'label'       => 'Browser Tab Icon (Favicon)',
                            'policy'      => \App\Services\UploadPolicy::for('link.favicon', auth()->user()),
                            'currentUrl'  => $link->favicon,
                            'currentName' => $link->favicon ? 'Saved favicon' : null,
                            'hint'        => 'Simple square logo · 32×32 or 64×64',
                            'compact'     => true,
                        ])
                    </div>
                </div>

                {{-- Audience Targeting card removed — Smart Redirect Rules now handles country/device gating. --}}

                {{-- UTM --}}
                <div class="card-premium p-6" x-data="{ help: false }">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-tags text-pink-400 text-xs"></i></div>
                            <div>
                                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Campaign Tags (UTM)</h3>
                                <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Optional labels that follow visitors into Google Analytics so you know which post / email / ad sent them.</p>
                            </div>
                        </div>
                        <button type="button" @click="help = !help" class="text-[10px] px-2 py-1 rounded-md flex-shrink-0" style="background: var(--bg-glass-input); color: var(--text-faint);"><i class="fas fa-question-circle mr-1"></i> Plain English</button>
                    </div>
                    <div x-show="help" x-transition x-cloak class="mb-4 p-3 rounded-lg text-[11px] leading-relaxed" style="background: rgba(236,72,153,0.06); border: 1px solid rgba(236,72,153,0.2); color: var(--text-muted);">
                        <p class="mb-2"><strong style="color: var(--text-primary);">In one sentence:</strong> these are sticky labels you add to your link so analytics tools (like Google Analytics on the destination site) can tell you "this visitor came from your Instagram bio" instead of just "this visitor came from somewhere".</p>
                        <p class="mb-1.5"><strong style="color: var(--text-primary);">Skip this if</strong> you don't run paid ads or use Google Analytics on the page you link to. The settings below only matter for tracking, they don't change what visitors see.</p>
                        <div class="mt-2 p-2 rounded" style="background: var(--bg-glass-input);">
                            <p class="text-[10px] mb-1" style="color: var(--text-faint);">Example for an Instagram Link in Bio sending people to your shop:</p>
                            <p class="font-mono text-[10px]"><strong>Source</strong>: instagram &nbsp; <strong>Medium</strong>: social &nbsp; <strong>Campaign</strong>: spring_sale</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @php
                            $utmFields = [
                                'utm_source'   => ['Source',   'instagram, newsletter, podcast', 'WHERE the click came from, the platform or website name.'],
                                'utm_medium'   => ['Medium',   'social, email, cpc, banner',     'HOW you reached them, the channel type. Use "social" for organic posts, "cpc" for paid ads, "email" for newsletters.'],
                                'utm_campaign' => ['Campaign', 'spring_sale, product_launch',    'WHICH campaign, a name only you understand. Use the same value across every link in one campaign so analytics groups them together.'],
                                'utm_term'     => ['Term',     'running shoes, vegan recipes',   'Optional. The keyword you bid on (mostly for Google Ads).'],
                                'utm_content'  => ['Content',  'header_button, footer_link',     'Optional. Tells you which version of an ad/email got the click when you have multiple links going to the same page.'],
                            ];
                        @endphp
                        @foreach($utmFields as $field => [$label, $eg, $explain])
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">{{ $label }} <span class="text-[10px] font-normal" style="color: var(--text-faint);"> - {{ $explain }}</span></label>
                            <input type="text" name="{{ $field }}" value="{{ old($field, $link->$field) }}" placeholder="e.g. {{ $eg }}" class="theme-input w-full">
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- TRACKING PIXELS --}}
                @if($pixels->count())
                <div class="card-premium p-6" x-data="{ help: false }">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(6,182,212,0.1);"><i class="fas fa-bullseye text-cyan-400 text-xs"></i></div>
                            <div>
                                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Tracking</h3>
                                <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Tell ad platforms (Facebook, Google, TikTok, …) "this person visited my page" so you can show them ads later.</p>
                            </div>
                        </div>
                        <button type="button" @click="help = !help" class="text-[10px] px-2 py-1 rounded-md flex-shrink-0" style="background: var(--bg-glass-input); color: var(--text-faint);"><i class="fas fa-question-circle mr-1"></i> What's a tracker?</button>
                    </div>
                    <div x-show="help" x-transition x-cloak class="mb-4 p-3 rounded-lg text-[11px] leading-relaxed" style="background: rgba(6,182,212,0.06); border: 1px solid rgba(6,182,212,0.2); color: var(--text-muted);">
                        <p class="mb-2">A <strong style="color: var(--text-primary);">tracker</strong> is a tiny invisible snippet from an advertising platform. When a visitor opens your page, the tracker fires and the ad platform remembers them. Later you can run an ad campaign that targets <em>only people who already visited this link</em>, that audience converts way better than strangers.</p>
                        <p class="mb-1.5"><strong style="color: var(--text-primary);">When to use:</strong> if you advertise on Facebook/Instagram/Google/TikTok and want to re-engage people who clicked your link but didn't buy. Skip this otherwise.</p>
                        <p class="text-[10px]"><i class="fas fa-cog mr-1"></i> Trackers are configured once in <a href="{{ route('user.dashboard') }}" class="underline">Account → Tracking</a>; here you just tick which ones to fire on this specific link.</p>
                    </div>
                    <p class="text-[10px] mb-2" style="color: var(--text-muted);">Tick the trackers you want to fire when someone opens this link:</p>
                    <div class="space-y-1.5">
                        @foreach($pixels as $pixel)
                        <label class="flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer select-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" {{ $link->pixels->contains($pixel->id) ? 'checked' : '' }} class="rounded text-blue-500">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $pixel->name }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded ml-auto" style="background: var(--bg-glass); color: var(--text-faint);">{{ ucfirst(str_replace('_', ' ', $pixel->type)) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="sticky bottom-0 mt-6 py-4 flex items-center gap-3 flex-wrap" style="background: var(--bg-body); z-index: 10;">
                    <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                        <i class="fas fa-save text-xs"></i> Save Link Settings
                    </button>
                    <span class="text-[11px]" style="color: var(--text-faint);">
                        Saves title, password, expiration, restrictions and trackers.
                    </span>
                    <a href="{{ route('user.links.show', $link) }}" class="text-xs px-4 py-2 rounded-lg ml-auto" style="color: var(--text-faint);">Cancel</a>
                </div>
            </form>

            {{-- bgSettings() Alpine helper now lives inside the
                 biolink-background-card partial via an @once block, so it's
                 defined exactly once regardless of how many editors include
                 the card on a single page. --}}
        </div>

        <div class="lg:col-span-5 hidden lg:block lg:self-stretch lg:h-full">
            @include('user.links.partials.device-preview', ['link' => $link])
        </div>
    </div>
</div>
@endsection
