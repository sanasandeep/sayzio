@php
    $contactDefaults = \App\Modules\Common\Support\SitePagesContent::contactExtraDefault();
    $contactExtra = old('extra', is_array($page->extra) && !empty($page->extra)
        ? \App\Modules\Common\Support\SitePagesContent::normalizeContactExtra($page->extra)
        : $contactDefaults);
    $social = $contactExtra['social'] ?? $contactDefaults['social'];
    $map    = $contactExtra['map']    ?? $contactDefaults['map'];

    $contactHero    = array_replace_recursive(
        $contactDefaults['hero'],
        (array) ($contactExtra['hero'] ?? [])
    );
    $contactDetailsHeading = (string) ($contactExtra['details_heading'] ?? $contactDefaults['details_heading']);
    // For the feature-cards repeater, prefer admin-saved values; if the admin
    // explicitly wiped them all (key present, empty array) keep it empty so the
    // section can be hidden on the public page.
    $contactFeatureCards = array_key_exists('feature_cards', $contactExtra) && is_array($contactExtra['feature_cards'])
        ? array_values($contactExtra['feature_cards'])
        : array_values($contactDefaults['feature_cards']);
    $contactOfficeImage = array_replace(
        $contactDefaults['office_image'],
        array_intersect_key((array) ($contactExtra['office_image'] ?? []), $contactDefaults['office_image'])
    );
    $contactForm = array_replace($contactDefaults['form'], (array) ($contactExtra['form'] ?? []));
    $contactMessages = array_replace(
        $contactDefaults['messages'],
        (array) ($contactExtra['messages'] ?? [])
    );
@endphp

{{--
    Photo uploader Alpine helper (window.aboutPhotoUploader) and crop modal —
    shared with the about-editor, so it works the same way here.
--}}
@include('admin.site-pages.partials.photo-uploader')

<div class="pt-2 border-t border-white/10 space-y-6">

    {{-- ========== HERO ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Hero</h3>
        <p class="text-xs text-white/50 mb-3">Top of /contact — badge pill, availability/language line, side image and the small "Friendly humans" floating card.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Badge label</label>
                    <input type="text" name="extra[hero][badge_label]" value="{{ $contactHero['badge_label'] }}" maxlength="60" placeholder="Contact" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.badge_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Badge icon (FontAwesome class)</label>
                    <input type="text" name="extra[hero][badge_icon]" value="{{ $contactHero['badge_icon'] }}" maxlength="60" placeholder="fa-envelope" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('extra.hero.badge_icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-[1fr_180px] gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Availability line <span class="normal-case tracking-normal text-white/40">(blank = hide)</span></label>
                    <input type="text" name="extra[hero][availability_text]" value="{{ $contactHero['availability_text'] }}" maxlength="200" placeholder="Replies within 1 business day" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.availability_text')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Availability icon</label>
                    <input type="text" name="extra[hero][availability_icon]" value="{{ $contactHero['availability_icon'] }}" maxlength="60" placeholder="fa-circle (blank = pulse dot)" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('extra.hero.availability_icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Language list <span class="normal-case tracking-normal text-white/40">(blank = hide)</span></label>
                <input type="text" name="extra[hero][languages]" value="{{ $contactHero['languages'] }}" maxlength="200" placeholder="EN · हिन्दी" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.hero.languages')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Side image (16:10) with the same uploader/cropper as the about page. --}}
            <div x-data="{ url: @js((string) $contactHero['side_image']) }">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Hero side image <span class="normal-case tracking-normal text-white/40">(16:10)</span></label>
                <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: {{ 16/10 }}, outputSize: 1200, isCircle: false })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="url">
                                <img :src="url" alt="" class="w-40 object-cover rounded-md border border-white/10 bg-white/5" style="height:100px" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!url">
                                <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2" style="height:100px">Falls back to bundled image</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" name="extra[hero][side_image]" x-model="url" placeholder="https://… or /storage/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.hero.side_image')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="url" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                            <input type="text" name="extra[hero][side_image_alt]" value="{{ $contactHero['side_image_alt'] }}" maxlength="200" placeholder="Alt text" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.hero.side_image_alt')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card title</label>
                    <input type="text" name="extra[hero][floating_card][title]" value="{{ $contactHero['floating_card']['title'] }}" maxlength="120" placeholder="Friendly humans" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.floating_card.title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card subtitle</label>
                    <input type="text" name="extra[hero][floating_card][subtitle]" value="{{ $contactHero['floating_card']['subtitle'] }}" maxlength="120" placeholder="Behind every reply" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.floating_card.subtitle')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card icon</label>
                    <input type="text" name="extra[hero][floating_card][icon]" value="{{ $contactHero['floating_card']['icon'] }}" maxlength="60" placeholder="fa-headset" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('extra.hero.floating_card.icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-[11px] text-white/40">Both the title and subtitle must be blank to hide the floating card entirely.</p>
        </div>
    </div>

    {{-- ========== CONTACT DETAILS ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Contact details</h3>
        <p class="text-xs text-white/50 mb-3">Heading and the address / email / phone / hours shown in the "Contact details" card on /contact.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Section heading <span class="normal-case tracking-normal text-white/40">(blank = hide)</span></label>
                <input type="text" name="extra[details_heading]" value="{{ $contactDetailsHeading }}" maxlength="200" placeholder="Contact details" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.details_heading')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Address</label>
                    <textarea name="extra[address]" rows="3" placeholder="Street, city, country" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactExtra['address'] ?? '' }}</textarea>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Email</label>
                    <input type="email" name="extra[email]" value="{{ $contactExtra['email'] ?? '' }}" placeholder="hello@example.com" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Phone</label>
                    <input type="text" name="extra[phone]" value="{{ $contactExtra['phone'] ?? '' }}" placeholder="+91 …" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Hours</label>
                    <textarea name="extra[hours]" rows="2" placeholder="Mon–Fri · 10:00 – 18:00" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactExtra['hours'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-white">Social links</h3>
        <p class="text-xs text-white/50 mb-3">Leave blank to hide a network.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            @foreach (['twitter' => 'X (Twitter)', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'facebook' => 'Facebook'] as $key => $label)
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $label }}</label>
                    <input type="url" name="extra[social][{{ $key }}]" value="{{ $social[$key] ?? '' }}" placeholder="https://…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-white">Map (OpenStreetMap)</h3>
        <p class="text-xs text-white/50 mb-3">Defaults to our Hyderabad office. Change lat/lng to drop the marker elsewhere, or click on the preview to set the location.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Latitude</label>
                <input type="number" step="any" min="-90" max="90" name="extra[map][lat]" id="contact-map-lat" value="{{ $map['lat'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lat')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Longitude</label>
                <input type="number" step="any" min="-180" max="180" name="extra[map][lng]" id="contact-map-lng" value="{{ $map['lng'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.lng')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Zoom (1–19)</label>
                <input type="number" min="1" max="19" name="extra[map][zoom]" id="contact-map-zoom" value="{{ $map['zoom'] ?? 14 }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                @error('extra.map.zoom')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Map caption</label>
                <input type="text" name="extra[map][label]" id="contact-map-label" value="{{ $map['label'] ?? '' }}" placeholder="Our Hyderabad office" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Live preview</label>
                <div class="aspect-[16/9] w-full rounded-lg overflow-hidden border border-white/10 bg-white/5">
                    <div id="contact-map-preview" style="width:100%; height:100%;"></div>
                </div>
                <p class="mt-1.5 text-[11px] text-white/40">Click anywhere on the map to update the latitude and longitude. Use the +/− controls (or scroll while hovering) to change zoom.</p>
            </div>
        </div>
    </div>

    {{-- ========== FEATURE CARDS ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Feature cards</h3>
        <p class="text-xs text-white/50 mb-3">The three small cards rendered between the map and the contact form. Removing every card hides the entire row.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4">
            <div x-data="{ rows: {{ json_encode($contactFeatureCards) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-[10px] uppercase tracking-wider text-white/40">Cards <span class="normal-case tracking-normal text-white/40">(max 6)</span></label>
                    <button type="button" @click="if(rows.length<6) rows.push({icon:'fa-circle-dot',title:'',desc:''})" :disabled="rows.length>=6" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add card</button>
                </div>
                <template x-for="(c, i) in rows" :key="i">
                    <div class="bg-white/[0.04] border border-white/10 rounded-lg p-3 mb-2 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Card <span x-text="i+1"></span></span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-down"></i></button>
                                <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-[140px_1fr] gap-2">
                            <input type="text" :name="'extra[feature_cards]['+i+'][icon]'" x-model="c.icon" maxlength="60" placeholder="fa-bolt" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white font-mono">
                            <input type="text" :name="'extra[feature_cards]['+i+'][title]'" x-model="c.title" maxlength="200" placeholder="Title" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white">
                        </div>
                        <textarea :name="'extra[feature_cards]['+i+'][desc]'" x-model="c.desc" rows="2" maxlength="500" placeholder="Short description shown under the title." class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white"></textarea>
                    </div>
                </template>
                <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-3">No feature cards — the row will be hidden on /contact.</div>
            </div>
        </div>
    </div>

    {{-- ========== OFFICE IMAGE ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Office image</h3>
        <p class="text-xs text-white/50 mb-3">Image rendered next to the contact form on desktop (hidden on mobile).</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4">
            <div x-data="{ url: @js((string) $contactOfficeImage['url']) }">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Office image <span class="normal-case tracking-normal text-white/40">(4:3)</span></label>
                <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: {{ 4/3 }}, outputSize: 1000, isCircle: false })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="url">
                                <img :src="url" alt="" class="w-40 object-cover rounded-md border border-white/10 bg-white/5" style="height:120px" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!url">
                                <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2" style="height:120px">Falls back to bundled image</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" name="extra[office_image][url]" x-model="url" placeholder="https://… or /storage/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.office_image.url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="url" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                            <input type="text" name="extra[office_image][alt]" value="{{ $contactOfficeImage['alt'] }}" maxlength="200" placeholder="Alt text" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.office_image.alt')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
            </div>
        </div>
    </div>

    {{-- ========== CONTACT FORM COPY ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Contact form copy</h3>
        <p class="text-xs text-white/50 mb-3">Heading, optional intro line, field labels and submit button text. Form behaviour and field names stay the same.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Heading</label>
                <input type="text" name="extra[form][heading]" value="{{ $contactForm['heading'] }}" maxlength="200" placeholder="Send us a message" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.form.heading')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Intro line <span class="normal-case tracking-normal text-white/40">(optional)</span></label>
                <textarea name="extra[form][intro]" rows="2" maxlength="500" placeholder="Optional sentence under the heading — e.g. Tell us a bit about what you need." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactForm['intro'] }}</textarea>
                @error('extra.form.intro')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            @foreach([
                ['key' => 'name',    'label' => 'Name',    'phPlaceholder' => 'Optional placeholder for the Name field'],
                ['key' => 'email',   'label' => 'Email',   'phPlaceholder' => 'Optional placeholder for the Email field'],
                ['key' => 'subject', 'label' => 'Subject', 'phPlaceholder' => 'Optional placeholder for the Subject field'],
                ['key' => 'message', 'label' => 'Message', 'phPlaceholder' => 'Optional placeholder for the Message field'],
            ] as $field)
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $field['label'] }} label</label>
                        <input type="text" name="extra[form][{{ $field['key'] }}_label]" value="{{ $contactForm[$field['key'].'_label'] }}" maxlength="80" placeholder="{{ $field['label'] }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        @error('extra.form.'.$field['key'].'_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $field['label'] }} placeholder</label>
                        <input type="text" name="extra[form][{{ $field['key'] }}_placeholder]" value="{{ $contactForm[$field['key'].'_placeholder'] }}" maxlength="200" placeholder="{{ $field['phPlaceholder'] }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        @error('extra.form.'.$field['key'].'_placeholder')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach

            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Submit button label</label>
                <input type="text" name="extra[form][submit_label]" value="{{ $contactForm['submit_label'] }}" maxlength="80" placeholder="Send message" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.form.submit_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ========== POST-SUBMIT MESSAGES ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Post-submit messages</h3>
        <p class="text-xs text-white/50 mb-3">The green flash shown after a successful submission and the (optional) wording of the required-field validation errors. Leave a field blank to keep the built-in default.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Success message</label>
                <textarea name="extra[messages][success]" rows="2" maxlength="500" placeholder="Thanks! Your message has been sent. We will reply within one business day." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $contactMessages['success'] }}</textarea>
                @error('extra.messages.success')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                <p class="mt-1 text-[11px] text-white/40">Shown in the green banner above the form after the visitor's message is delivered.</p>
            </div>

            <div class="pt-2 border-t border-white/10">
                <p class="text-[10px] uppercase tracking-wider text-white/40 mb-2">Required-field error wording</p>
                <p class="text-[11px] text-white/40 mb-3">Leave a row blank to keep Laravel's default phrasing (e.g. "The email field is required.").</p>
                <div class="space-y-3">
                    @foreach([
                        ['key' => 'name_required',    'label' => 'Name required',    'placeholder' => 'The name field is required.'],
                        ['key' => 'email_required',   'label' => 'Email required',   'placeholder' => 'The email field is required.'],
                        ['key' => 'subject_required', 'label' => 'Subject required', 'placeholder' => 'The subject field is required.'],
                        ['key' => 'message_required', 'label' => 'Message required', 'placeholder' => 'The message field is required.'],
                    ] as $row)
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $row['label'] }}</label>
                            <input type="text" name="extra[messages][{{ $row['key'] }}]" value="{{ $contactMessages[$row['key']] }}" maxlength="200" placeholder="{{ $row['placeholder'] }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.messages.'.$row['key'])<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-2 border-t border-white/10">
                <p class="text-[10px] uppercase tracking-wider text-white/40 mb-2">Other validator messages</p>
                <p class="text-[11px] text-white/40 mb-3">Leave a row blank to keep the built-in default wording.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Invalid email format</label>
                        <input type="text" name="extra[messages][email_invalid]" value="{{ $contactMessages['email_invalid'] }}" maxlength="200" placeholder="The email must be a valid email address." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        @error('extra.messages.email_invalid')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-white/40">Shown when a visitor submits something like <code class="text-white/60">john@</code> in the email field.</p>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Rate-limit banner</label>
                        <input type="text" name="extra[messages][rate_limited]" value="{{ $contactMessages['rate_limited'] }}" maxlength="200" placeholder="Too many submissions — please try again in a few minutes." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        @error('extra.messages.rate_limited')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-white/40">Shown above the form after more than 3 submissions in 10 minutes from the same visitor.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
      integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    #contact-map-preview { background:#1e2330; }
    #contact-map-preview .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    #contact-map-preview .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    #contact-map-preview .leaflet-control-attribution a { color:#a78bfa !important; }
    #contact-map-preview .leaflet-control-zoom a {
        background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important;
    }
    #contact-map-preview .leaflet-control-zoom a:hover { background:#7c3aed !important; }
    .admin-brand-marker {
        width:34px; height:44px; position:relative;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45));
    }
    .admin-brand-marker svg { width:100%; height:100%; display:block; }
    .admin-brand-marker .pulse {
        position:absolute; left:50%; bottom:-4px; width:14px; height:14px;
        margin-left:-7px; border-radius:9999px;
        background:rgba(124,58,237,0.55);
        animation: admin-brand-marker-pulse 1.8s ease-out infinite;
    }
    @keyframes admin-brand-marker-pulse {
        0% { transform:scale(0.6); opacity:0.9; }
        100% { transform:scale(2.2); opacity:0; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
        integrity="sha512-BB3hKbKWOc9Ez/TAwyWxNXeoV9c1v6FIeYiBieIWkpLjauysF18NzgR1MBNBXf8/KABdlkX68nAhlwcDFLGPCQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script>
(function(){
    function init(){
        var el = document.getElementById('contact-map-preview');
        var latI = document.getElementById('contact-map-lat');
        var lngI = document.getElementById('contact-map-lng');
        var zoomI = document.getElementById('contact-map-zoom');
        var labelI = document.getElementById('contact-map-label');
        if (!el || !latI || !lngI || !zoomI || typeof L === 'undefined') return;

        function readLat(){ var v = parseFloat(latI.value); return isFinite(v) ? v : 17.3850; }
        function readLng(){ var v = parseFloat(lngI.value); return isFinite(v) ? v : 78.4867; }
        function readZoom(){ var v = parseInt(zoomI.value, 10); if(!isFinite(v)) v = 12; return Math.max(1, Math.min(19, v)); }
        function readLabel(){ return labelI ? (labelI.value || '') : ''; }

        var map = L.map(el, {
            center: [readLat(), readLng()],
            zoom: readZoom(),
            scrollWheelZoom: true,
            zoomControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var pinSvg = '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<defs><linearGradient id="bm-admin-g" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
            '</linearGradient></defs>' +
            '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#bm-admin-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
            '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
            '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#7c3aed">1</text>' +
            '</svg>';

        var icon = L.divIcon({
            className: '',
            html: '<div class="admin-brand-marker"><span class="pulse"></span>' + pinSvg + '</div>',
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -40]
        });

        var marker = L.marker([readLat(), readLng()], { icon: icon, draggable: true, title: readLabel() || '1INME' }).addTo(map);

        var suppressInputSync = false;
        var suppressMapSync = false;

        function syncMapFromInputs(recenter){
            if (suppressMapSync) return;
            var lat = readLat(), lng = readLng(), z = readZoom();
            suppressInputSync = true;
            marker.setLatLng([lat, lng]);
            if (recenter) {
                map.setView([lat, lng], z, { animate: false });
            } else if (map.getZoom() !== z) {
                map.setZoom(z, { animate: false });
            }
            suppressInputSync = false;
        }

        function setInputs(lat, lng){
            suppressMapSync = true;
            latI.value = (Math.round(lat * 1e6) / 1e6).toString();
            lngI.value = (Math.round(lng * 1e6) / 1e6).toString();
            try {
                latI.dispatchEvent(new Event('input', { bubbles: true }));
                lngI.dispatchEvent(new Event('input', { bubbles: true }));
            } catch (e) {}
            suppressMapSync = false;
        }

        ['input', 'change'].forEach(function(evt){
            latI.addEventListener(evt, function(){ syncMapFromInputs(true); });
            lngI.addEventListener(evt, function(){ syncMapFromInputs(true); });
            zoomI.addEventListener(evt, function(){ syncMapFromInputs(false); });
        });
        if (labelI) {
            labelI.addEventListener('input', function(){
                var t = readLabel() || '1INME';
                try {
                    var el2 = marker.getElement();
                    if (el2) el2.setAttribute('title', t);
                } catch (e) {}
            });
        }

        map.on('click', function(e){
            setInputs(e.latlng.lat, e.latlng.lng);
            marker.setLatLng(e.latlng);
        });
        marker.on('dragend', function(){
            var p = marker.getLatLng();
            setInputs(p.lat, p.lng);
        });
        map.on('zoomend', function(){
            if (suppressInputSync) return;
            var z = map.getZoom();
            if (parseInt(zoomI.value, 10) !== z) {
                suppressMapSync = true;
                zoomI.value = String(z);
                try { zoomI.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
                suppressMapSync = false;
            }
        });

        setTimeout(function(){ map.invalidateSize(); }, 100);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
