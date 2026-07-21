@extends('user.layouts.settings')
@section('title', 'Profile')

@section('settings-content')
<div>
    <h1 class="text-2xl font-bold mb-6" style="color: var(--text-strong);">Profile Settings</h1>

    @if(session('force_handle_rename'))
        <div class="rounded-xl px-4 py-3 mb-6 bg-amber-500/10 border border-amber-500/40 text-amber-100 text-sm">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <div>
                    <div class="font-semibold">Pick a new handle to continue</div>
                    <div class="mt-1 text-amber-100/80">
                        An admin has reserved <span class="font-mono">{{ session('force_handle_rename') }}</span>,
                        which matches your current handle. Please choose a different one before continuing.
                    </div>
                    <form method="POST" action="{{ route('user.creator-profile.handle.claim') }}" class="mt-3 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input type="text" name="handle" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+"
                               value="{{ old('handle') }}" placeholder="new_handle"
                               class="flex-1 px-3 py-2 rounded-lg bg-white/10 border border-amber-400/40 text-amber-50 placeholder-amber-100/40 text-sm font-mono outline-none focus:border-amber-300/70">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-amber-950 text-sm font-semibold transition whitespace-nowrap">Save new handle</button>
                    </form>
                    @error('handle')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror
                    @if(!empty($handleSuggestions))
                        <div class="mt-3">
                            <div class="text-xs uppercase tracking-wider text-amber-200/70 mb-1.5">Available suggestions, click to use</div>
                            <div class="flex flex-wrap gap-2" data-handle-suggestions>
                                @foreach($handleSuggestions as $suggestion)
                                    <button type="button"
                                            data-handle-suggestion="{{ $suggestion }}"
                                            class="px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-400/40 text-amber-50 hover:bg-amber-500/25 hover:border-amber-300/60 text-sm font-mono transition-colors">
                                        {{ '@' . $suggestion }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('user.profile.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left column: Personal Information + Notifications --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Personal Information --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-base font-semibold mb-4" style="color: var(--text-strong);">Personal Information</h2>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Name</label>
                                @if($user->isNameAvatarLocked())
                                    <div class="flex items-center gap-2 w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl" title="Name is locked by profile verification. Submit a re-verification request to change it.">
                                        <span class="flex-1 text-white">{{ $user->profile_verified_name ?: $user->name }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background: rgba(59,130,246,0.15); color: #60a5fa;">{!! $user->verificationTickHtml() !!} Locked</span>
                                    </div>
                                    <p class="mt-1 text-xs" style="color: var(--text-subtle);">Your verified name is locked. <a href="{{ route('user.profile-verification.index') }}" class="underline" style="color: var(--color-primary);">Request a name change</a> via re-verification.</p>
                                    <input type="hidden" name="name" value="{{ $user->profile_verified_name ?: $user->name }}">
                                @else
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 outline-none transition-all">
                                @endif
                                @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Email</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 outline-none transition-all">
                                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Phone</label>
                                @include('common.partials.phone-input', [
                                    'phoneInputName'  => 'phone',
                                    'phoneInputValue' => old('phone', $user->phone ?? ''),
                                    'phoneInputId'    => 'profile-phone',
                                    'phoneInputSize'  => 'lg',
                                    'phoneInputClass' => 'w-full',
                                ])
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Billing country</label>
                                @php
                                    $countries = \App\Support\BillingCountries::options();
                                @endphp
                                <select name="country" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                                    @foreach($countries as $code => $label)
                                        @if($code === 'OTHER')
                                            @continue
                                        @endif
                                        <option value="{{ $code }}" {{ old('country', $user->country) === $code ? 'selected' : '' }} class="bg-[#0d0818]">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] mt-1" style="color: var(--text-subtle, rgba(255,255,255,0.30));">Used to determine your billing currency.</p>
                                @error('country')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Timezone</label>
                                <select name="timezone" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                                    @foreach($timezones as $tz)
                                        <option value="{{ $tz }}" {{ \App\Support\PlatformTimezone::resolve($user->timezone) === $tz ? 'selected' : '' }} class="bg-[#0d0818]">{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Language</label>
                                <select name="language" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                                    <option value="en" {{ $user->language == 'en' ? 'selected' : '' }} class="bg-[#0d0818]">English</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Billing Address & Tax ID --}}
                @php
                    $billingCountryInit = old('billing_country', $billing->country ?? $user->country) ?? '';
                    $taxKindInit = old('tax_id_kind', $billing->tax_id_kind ?: 'NONE');
                    $billingCityInit   = old('billing_city',        $billing->city        ?? '') ?? '';
                    $billingRegionInit = old('billing_region',      $billing->region      ?? '') ?? '';
                    $billingPostalInit = old('billing_postal_code', $billing->postal_code ?? '') ?? '';
                @endphp
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-base font-semibold mb-1" style="color: var(--text-strong);">Billing Address &amp; Tax ID</h2>
                    <p class="text-xs mb-4" style="color: var(--text-muted);">Used to calculate tax on your invoices and to print on your tax invoice PDF. GSTIN is for Indian businesses; VATIN is for EU/UK businesses claiming reverse-charge.</p>
                    <div class="space-y-3" data-billing-address
                         @country-picked="onCountryInput($event.detail)"
                         x-data="{
                             billingCountry: @js($billingCountryInit),
                             taxKind: @js($taxKindInit),
                             cityVal: @js($billingCityInit),
                             regionVal: @js($billingRegionInit),
                             cityEdited: false,
                             regionEdited: false,
                             lookupTimer: null,
                             lookupUrl: @js(route('user.profile.postal.lookup')),
                             onCountryInput(val) {
                                 this.billingCountry = val;
                                 this.scheduleLookup();
                             },
                             scheduleLookup() {
                                 clearTimeout(this.lookupTimer);
                                 this.lookupTimer = setTimeout(() => this.doLookup(), 600);
                             },
                             async doLookup() {
                                 const country = this.billingCountry.trim();
                                 const postalEl = this.$el.querySelector('[name=billing_postal_code]');
                                 const postal = postalEl ? postalEl.value.trim() : '';
                                 if (country.length !== 2 || !postal) return;
                                 try {
                                     const r = await fetch(this.lookupUrl + '?country=' + encodeURIComponent(country) + '&postal_code=' + encodeURIComponent(postal), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                     if (!r.ok) return;
                                     const d = await r.json();
                                     if (d.city && !this.cityEdited) this.cityVal = d.city;
                                     if (!this.regionEdited) {
                                         if ((country === 'IN' || country === 'US') && d.region_code) {
                                             this.regionVal = d.region_code;
                                         } else if (d.region && country !== 'IN' && country !== 'US') {
                                             this.regionVal = d.region;
                                         }
                                     }
                                 } catch (e) {}
                             }
                         }">

                        {{-- 1. Country --}}
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--text-muted);">Country</label>
                            @include('common.partials.country-select', [
                                'csName'        => 'billing_country',
                                'csValue'       => $billingCountryInit,
                                'csId'          => 'billing-country',
                                'csPlaceholder' => 'Select billing country',
                            ])
                            @error('billing_country')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>

                        {{-- 2. Postal / ZIP code — triggers the city/state auto-fill --}}
                        <input type="text" name="billing_postal_code" placeholder="Postal / ZIP code"
                               value="{{ $billingPostalInit }}" maxlength="16"
                               @input="scheduleLookup()"
                               @blur="doLookup()"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">

                        {{-- 3. State / region (auto-filled; user may override) --}}
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--text-muted);">State / region</label>
                            {{-- Dropdown for India / US; free-text for every other country --}}
                            <template x-if="billingCountry === 'IN' || billingCountry === 'US'">
                                <select name="billing_region" x-model="regionVal" @change="regionEdited = true"
                                        class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                                    <option value="" class="bg-[#0d0818]">None / N/A</option>
                                    <optgroup label="India" class="bg-[#0d0818]">
                                        @foreach($inStates as $code => $label)
                                            <option value="{{ $code }}" class="bg-[#0d0818]">IN-{{ $code }} · {{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="United States" class="bg-[#0d0818]">
                                        @foreach($usStates as $code => $label)
                                            <option value="{{ $code }}" class="bg-[#0d0818]">US-{{ $code }} · {{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </template>
                            <template x-if="billingCountry !== 'IN' && billingCountry !== 'US'">
                                <input type="text" name="billing_region" maxlength="100"
                                       placeholder="State / region (optional)"
                                       x-model="regionVal"
                                       @input="regionEdited = true"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                            </template>
                        </div>

                        {{-- 4. City (auto-filled; user may override) --}}
                        <input type="text" name="billing_city" placeholder="City"
                               x-model="cityVal"
                               @input="cityEdited = true"
                               maxlength="100"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">

                        {{-- 5 & 6. Address lines --}}
                        <input type="text" name="billing_line1" placeholder="Address line 1" value="{{ old('billing_line1', $billing->line1) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                        <input type="text" name="billing_line2" placeholder="Address line 2 (optional)" value="{{ old('billing_line2', $billing->line2) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">

                        {{-- 7. Business name --}}
                        <input type="text" name="business_name" placeholder="Registered business name (optional)" value="{{ old('business_name', $billing->business_name) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                        <div class="grid grid-cols-3 gap-3">
                            @php $currentKind = old('tax_id_kind', $billing->tax_id_kind ?: 'NONE'); @endphp
                            <select name="tax_id_kind" data-tax-kind x-model="taxKind"
                                    class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                                <option value="NONE"  {{ $currentKind === 'NONE'  ? 'selected' : '' }} class="bg-[#0d0818]">No tax ID</option>
                                <option value="GSTIN" {{ $currentKind === 'GSTIN' ? 'selected' : '' }} class="bg-[#0d0818]">GSTIN (India)</option>
                                <option value="VATIN" {{ $currentKind === 'VATIN' ? 'selected' : '' }} class="bg-[#0d0818]">VATIN (EU / UK)</option>
                                <option value="OTHER" {{ $currentKind === 'OTHER' ? 'selected' : '' }} class="bg-[#0d0818]">Other</option>
                            </select>
                            <input type="text" name="tax_id" data-tax-id placeholder="Tax ID number" value="{{ old('tax_id', $billing->tax_id) }}" maxlength="32"
                                   class="col-span-2 px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white uppercase outline-none focus:ring-2 focus:ring-blue-500/40">
                        </div>
                        {{-- Free-text label for "Other" tax ID type --}}
                        <template x-if="taxKind === 'OTHER'">
                            <input type="text" name="tax_id_label"
                                   placeholder="Tax ID type name (e.g. ABN, EIN, PAN…)"
                                   value="{{ old('tax_id_label', $billing->tax_id_label ?? '') }}"
                                   maxlength="100"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-blue-500/40">
                        </template>
                        <p class="text-[11px]" data-tax-feedback></p>
                        @error('tax_id')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        @error('tax_id_kind')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Notifications --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-base font-semibold mb-4" style="color: var(--text-strong);">Notifications</h2>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-white/70">
                            <input type="checkbox" name="notify_new_follower" value="1" {{ $user->notify_new_follower ? 'checked' : '' }} class="w-4 h-4">
                            Email me when someone follows me
                        </label>
                        <div>
                            <p class="text-sm text-white/70 mb-2">When creators I follow post updates:</p>
                            @php
                                $mode = old('follower_updates_mode', $user->follower_updates_mode ?: ($user->notify_follower_updates ? 'digest' : 'off'));
                            @endphp
                            <div class="space-y-2 pl-1">
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="instant" {{ $mode === 'instant' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Instant</span>, email me as soon as something happens</span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="digest" {{ $mode === 'digest' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Daily digest</span>, one email per day with everything new (recommended)</span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="off" {{ $mode === 'off' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Off</span>, don't email me about creator updates</span>
                                </label>
                            </div>

                            @php
                                $prefHour = (int) old('digest_preferred_hour', $user->digest_preferred_hour ?? 9);
                            @endphp
                            <div class="mt-4 pl-1">
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">
                                    Send my daily digest at
                                </label>
                                <div class="flex items-center gap-3">
                                    <select name="digest_preferred_hour" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                                        @for($h = 0; $h < 24; $h++)
                                            @php
                                                $suffix = $h < 12 ? 'am' : 'pm';
                                                $disp = $h % 12; if ($disp === 0) $disp = 12;
                                            @endphp
                                            <option value="{{ $h }}" {{ $prefHour === $h ? 'selected' : '' }} class="bg-[#0d0818]">
                                                {{ $disp }}:00 {{ $suffix }}
                                            </option>
                                        @endfor
                                    </select>
                                    <span class="text-xs text-white/50">
                                        in your timezone ({{ \App\Support\PlatformTimezone::resolve($user->timezone) }})
                                    </span>
                                </div>
                                <p class="text-xs text-white/40 mt-1.5">Only applies when "Daily digest" is selected above.</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-white/10 bg-white/5 overflow-hidden"
                             data-digest-preview
                             data-preview-url="{{ route('user.profile.digest.preview') }}">
                            <div class="flex items-center justify-between px-4 py-2 border-b border-white/10 bg-white/5">
                                <div class="flex items-center gap-2 text-xs text-white/70">
                                    <i class="fas fa-envelope text-blue-400"></i>
                                    <span>Daily digest preview</span>
                                </div>
                                <span data-digest-badge-slot>
                                    @if($digestPreviewIsReal)
                                        <span class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30" title="This preview uses your real pending notifications.">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span>
                                            </span>
                                            Live preview · showing your {{ $digestPreviewCount }} pending update{{ $digestPreviewCount === 1 ? '' : 's' }}
                                        </span>
                                    @else
                                        <span class="text-[10px] uppercase tracking-wider text-blue-300/80 px-2 py-0.5 rounded-full bg-blue-500/15 border border-blue-500/30" title="You don't have any pending updates yet, so this is a placeholder.">
                                            Example preview · not your real queue
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <iframe
                                data-digest-iframe
                                title="Digest email preview"
                                sandbox=""
                                class="block w-full bg-white"
                                style="height: 480px; border: 0;"
                                srcdoc="{{ $digestPreviewHtml }}"
                            ></iframe>
                        </div>
                    </div>
                </div>

            </div>{{-- /left column --}}

            {{-- Right column: Avatar + Public Profile --}}
            <div class="space-y-6">

                {{-- Avatar Upload --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-base font-semibold mb-4" style="color: var(--text-strong);">Photo</h2>
                    @php
                        $avatarPolicy = \App\Services\UploadPolicy::for('user.avatar', auth()->user());
                        $avatarCurrentUrl = $user->avatar ? \App\Support\PublicStorageUrl::resolve($user->avatar) : null;
                    @endphp
                    @include('user.partials.dropzone-input', [
                        'name'        => 'avatar',
                        'policy'      => $avatarPolicy,
                        'currentUrl'  => $avatarCurrentUrl,
                        'currentName' => $user->avatar ? basename($user->avatar) : null,
                        'label'       => null,
                        'previewKind' => 'image',
                        'compact'     => true,
                    ])
                </div>

                {{-- Public Profile — the actual editor (handle, bio, tagline,
                     socials…) lives on the Creator Profile tab; this card just
                     points there and keeps the two account-level visibility
                     toggles that save through this form. --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-base font-semibold mb-4" style="color: var(--text-strong);">Public Profile</h2>
                    <div class="space-y-4">
                        <div class="rounded-xl px-4 py-3 flex items-start gap-3" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.20);">
                            <i class="fas fa-id-badge text-blue-400 mt-0.5"></i>
                            <div class="text-sm text-white/70">
                                Your handle, bio, avatar, cover and everything else people see at
                                <span class="font-mono">/@{{ $user->handle ?: 'handle' }}</span> is edited on the
                                <a href="{{ route('user.creator-profile.edit') }}" class="font-semibold underline" style="color: var(--color-primary-400, #60a5fa);">Creator Profile</a> tab.
                            </div>
                        </div>
                        <a href="{{ route('user.creator-profile.edit') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 border border-white/15 text-white text-sm font-medium hover:bg-white/10 transition-all">
                            Edit Creator Profile <i class="fas fa-arrow-right text-xs"></i>
                        </a>

                        <div class="border-t border-white/10 pt-4 space-y-3">
                            @php $canPublicProfile = $user->planFeatureEnabled('creator_profile_public'); @endphp
                            <label class="flex items-start gap-2 text-sm {{ $canPublicProfile ? 'text-white/70' : 'text-white/40' }}">
                                <input type="checkbox" name="discoverable" value="1"
                                    {{ $user->discoverable ? 'checked' : '' }}
                                    @if(!$canPublicProfile) disabled @endif
                                    class="w-4 h-4 mt-0.5 shrink-0">
                                <span>
                                    Show me in the public Creators directory at /creators
                                    @if(!$canPublicProfile)
                                        <a href="{{ route('user.upgrade') }}" class="ml-1 text-[11px] uppercase tracking-wider text-blue-400 hover:underline">Upgrade</a>
                                    @endif
                                </span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-white/70">
                                <input type="checkbox" name="allow_followers" value="1" {{ ($user->allow_followers ?? true) ? 'checked' : '' }} class="w-4 h-4">
                                Allow other people to follow me
                            </label>
                        </div>
                    </div>
                </div>

            </div>{{-- /right column --}}

        </div>{{-- /grid --}}

        {{-- Save button --}}
        <div class="mt-6">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-all hover:shadow-lg hover:shadow-blue-500/20">
                Save Changes
            </button>
        </div>

    </form>

    {{-- Secondary cards: WhatsApp number + sample digest + sign-in security --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

        {{-- WhatsApp number — add or change the verified number used for
             OTP sign-in and WhatsApp alerts. Reuses the shared onboarding
             send/verify endpoints (same throttles); a hidden from=settings
             flag brings the flow back to this page, and replace=1 swaps
             out the old verified number once the new one is confirmed.
             Kept OUTSIDE the main profile <form> — nested forms break. --}}
        @php
            $waCurrent = $user->whatsappNumber();
            $waPending = session('whatsapp_connect_pending');
            // When the connected number is the primary sign-in identifier and
            // another verified contact exists, removal auto-promotes that
            // contact — surface it in the remove confirmation so the sign-in
            // switch is never a surprise (mirrors the mobile remove dialog).
            $waIdentifier = $waCurrent
                ? $user->linkedIdentifiers()->where('kind', 'phone')->whereNotNull('verified_at')->first()
                : null;
            $waPromotesTo = null;
            $waPromotesToKind = null;
            if ($waIdentifier && $waIdentifier->is_primary) {
                $waFallback = $user->verifiedIdentifiers()
                    ->where('id', '!=', $waIdentifier->id)
                    ->whereIn('kind', ['email', 'phone'])
                    ->first();
                if ($waFallback) {
                    $waPromotesTo = $waFallback->value;
                    $waPromotesToKind = $waFallback->kind;
                }
            }
        @endphp
        <div class="glass rounded-2xl p-6 lg:col-span-2"
             x-data="{ phase: '{{ $waPending ? 'code' : 'number' }}' }">
            <div class="flex items-start gap-3 mb-1">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                    <i class="fab fa-whatsapp text-emerald-300 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-base font-semibold" style="color: var(--text-strong);">WhatsApp number</h2>
                    @if($waCurrent)
                        <p class="text-sm text-white/50 mt-0.5">
                            Connected:
                            <span class="font-mono" style="color: var(--text-primary);">{{ $waCurrent }}</span>
                            <span class="ml-1.5 text-[10px] uppercase tracking-wider text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30">Verified</span>
                        </p>
                        <p class="text-xs text-white/40 mt-1">Verify a different number below to replace it. Your current number stays active until the new one is confirmed.</p>
                    @else
                        <p class="text-sm text-white/50 mt-0.5">Verify a WhatsApp number to sign in faster with a one-time code and receive WhatsApp alerts.</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @if(session('status'))
                    <div class="px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-xs">{{ session('status') }}</div>
                @endif
                @if(session('otp_demo_reveal'))
                    <div class="px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-400/30 text-amber-200 text-xs"><i class="fas fa-flask text-[10px] mr-1"></i> {{ session('otp_demo_reveal') }}</div>
                @endif
                @if(session('error'))
                    <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-xs">{{ session('error') }}</div>
                @endif

                {{-- Phase 1: number --}}
                <form method="POST" action="{{ route('user.onboarding.whatsapp.send') }}" x-show="phase === 'number'" class="flex flex-col sm:flex-row sm:items-stretch gap-2">
                    @csrf
                    <input type="hidden" name="from" value="settings">
                    @if($waCurrent)
                        <input type="hidden" name="replace" value="1">
                    @endif
                    <div class="flex-1 min-w-0">
                        @include('common.partials.phone-input', [
                            'phoneInputName'  => 'mobile',
                            'phoneInputValue' => old('mobile', $waPending ?? ''),
                            'phoneInputId'    => 'wa-settings',
                            'phoneInputSize'  => 'sm',
                        ])
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition whitespace-nowrap">
                        {{ $waCurrent ? 'Send code to new number' : 'Send code' }}
                    </button>
                </form>

                {{-- Phase 2: code --}}
                <form method="POST" action="{{ route('user.onboarding.whatsapp.verify') }}" x-show="phase === 'code'" x-cloak class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required placeholder="123456" autocomplete="one-time-code"
                           class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm tracking-[0.3em] text-center font-mono focus:border-emerald-400/50 focus:outline-none" style="color: var(--text-primary);">
                    <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition whitespace-nowrap">Verify &amp; connect</button>
                    <button type="button" @click="phase = 'number'" class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold transition whitespace-nowrap" style="color: var(--text-muted);">Use a different number</button>
                </form>
                @if($waPending)
                    <p class="text-[11px] text-white/40">We sent a code to <span class="font-mono">{{ $waPending }}</span> on WhatsApp.</p>
                @endif

                {{-- Remove (disconnect) the connected number entirely. The
                     server promotes another verified email/phone to primary
                     first when needed, and refuses to leave the account with
                     no verified email or phone. --}}
                @if($waCurrent)
                    <div class="pt-3 mt-1 border-t border-white/10 flex items-center justify-between gap-3">
                        <p class="text-[11px] text-white/40">No longer want WhatsApp sign-in codes or alerts? You can remove this number entirely.</p>
                        <form method="POST" action="{{ route('user.onboarding.whatsapp.remove') }}"
                              onsubmit="return confirm('Remove your WhatsApp number {{ $waCurrent }}? You will no longer receive sign-in codes or alerts on WhatsApp.{{ $waPromotesTo ? ' This number is your primary sign-in contact; after removal, your ' . ($waPromotesToKind === 'phone' ? 'phone number' : 'email') . ' ' . $waPromotesTo . ' will become your primary sign-in contact.' : '' }}');">
                            @csrf
                            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 border border-red-400/30 text-red-300 text-xs font-semibold transition whitespace-nowrap">
                                <i class="fas fa-unlink text-[10px] mr-1"></i> Remove number
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="glass rounded-2xl p-6">
            <h2 class="text-base font-semibold mb-1" style="color: var(--text-strong);">Preview your daily digest</h2>
            <p class="text-sm text-white/50 mb-4">Send yourself a sample email using your current pending updates. Nothing in your real digest queue is changed.</p>
            <form method="POST" action="{{ route('user.profile.digest.sample') }}">
                @csrf
                <button type="submit" class="px-5 py-2 bg-white/5 border border-white/15 text-white rounded-xl font-medium hover:bg-white/10 transition-all">
                    <i class="fas fa-paper-plane mr-1.5 text-blue-300"></i>
                    Send sample digest
                </button>
            </form>
        </div>

        <div class="glass rounded-2xl p-6">
            <h2 class="text-base font-semibold mb-1" style="color: var(--text-strong);">Sign-in security</h2>
            <p class="text-sm text-white/50 mb-4">Your account is protected by one-time codes, there's no password to manage.</p>
            <div class="flex items-start gap-3 rounded-xl px-4 py-3" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.20);">
                <i class="fas fa-shield-alt text-blue-400 mt-0.5"></i>
                <div class="text-sm text-white/70">
                    Each time you sign in, we send a fresh 6-digit code to your email{{ auth()->user()->mobile ? ' or mobile number' : '' }}. Keep your contact details up to date above so you can always receive it.
                </div>
            </div>
        </div>

    </div>

</div>

@push('styles')
@endpush

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-digest-preview]');
    if (!root) return;
    const url = root.dataset.previewUrl;
    const badgeSlot = root.querySelector('[data-digest-badge-slot]');
    const iframe = root.querySelector('[data-digest-iframe]');
    if (!url || !badgeSlot || !iframe) return;

    let lastCount = {!! json_encode((int) $digestPreviewCount) !!};
    let lastIsReal = {!! json_encode((bool) $digestPreviewIsReal) !!};
    let lastHtml = iframe.getAttribute('srcdoc') || '';
    let inFlight = false;
    let timer = null;

    function renderBadge(isReal, count) {
        if (isReal) {
            const plural = count === 1 ? '' : 's';
            badgeSlot.innerHTML =
                '<span class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30" title="This preview uses your real pending notifications.">' +
                  '<span class="relative flex h-1.5 w-1.5">' +
                    '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>' +
                    '<span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span>' +
                  '</span>' +
                  'Live preview · showing your ' + count + ' pending update' + plural +
                '</span>';
        } else {
            badgeSlot.innerHTML =
                '<span class="text-[10px] uppercase tracking-wider text-blue-300/80 px-2 py-0.5 rounded-full bg-blue-500/15 border border-blue-500/30" title="You don\'t have any pending updates yet, so this is a placeholder.">' +
                  'Example preview · not your real queue' +
                '</span>';
        }
    }

    async function poll() {
        if (inFlight || document.hidden) return;
        inFlight = true;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            const count = Number(data.count) || 0;
            const isReal = !!data.isReal;
            const html = typeof data.html === 'string' ? data.html : '';
            if (count !== lastCount || isReal !== lastIsReal) {
                renderBadge(isReal, count);
                lastCount = count;
                lastIsReal = isReal;
            }
            if (html && html !== lastHtml) {
                iframe.setAttribute('srcdoc', html);
                lastHtml = html;
            }
        } catch (e) {
            // silent — next tick will retry
        } finally {
            inFlight = false;
        }
    }

    function start() { if (!timer) timer = setInterval(poll, 20000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop(); else { start(); poll(); }
    });
    start();
})();

(function () {
    // Live tax-id format feedback (PHP-only validation also runs on submit).
    const root = document.querySelector('[data-billing-address]');
    if (root) {
        const kindEl = root.querySelector('[data-tax-kind]');
        const idEl   = root.querySelector('[data-tax-id]');
        const fb     = root.querySelector('[data-tax-feedback]');
        function check() {
            const kind = kindEl.value;
            const v = (idEl.value || '').toUpperCase().trim();
            if (!v || kind === 'NONE' || kind === 'OTHER') { fb.textContent = ''; fb.className = 'text-[11px]'; return; }
            let ok = false, msg = '';
            if (kind === 'GSTIN') {
                ok = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/.test(v);
                msg = ok ? 'GSTIN format looks valid (server will verify checksum on save).' : 'Expected 15 chars: 2 digits + 5 letters + 4 digits + 1 letter + 1 alphanum + Z + 1 alphanum.';
            } else if (kind === 'VATIN') {
                ok = /^[A-Z]{2}[A-Z0-9]{2,}$/.test(v);
                msg = ok ? 'VAT number format looks valid (server checks per-country pattern on save).' : 'Expected 2-letter country prefix followed by the national number.';
            }
            fb.textContent = msg;
            fb.className = 'text-[11px] ' + (ok ? 'text-emerald-300' : 'text-amber-300');
        }
        kindEl.addEventListener('change', check);
        idEl.addEventListener('input', check);
        check();
    }
})();

(function () {
    document.querySelectorAll('[data-handle-suggestion]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const value = btn.getAttribute('data-handle-suggestion') || '';
            const input = document.querySelector('input[name="handle"]');
            if (!input) return;
            input.value = value;
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
})();
</script>
@endpush
@endsection
