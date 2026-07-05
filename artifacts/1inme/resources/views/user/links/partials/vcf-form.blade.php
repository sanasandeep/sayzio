{{--
    Shared rich vCard editor used by create-vcf and edit-vcf.
    Expects:
      $vcf            (VcfData|null)
      $emailLabels, $phoneLabels, $urlLabels, $addrLabels, $socialServices
      $projects, $aliasLimits, optional $link, optional $prefillAlias
--}}
@php
    $v = $vcf ?? null;
    $emailsInit  = old('emails',  $v?->emailList()   ?: [['label' => 'Personal', 'value' => '']]);
    $phonesInit  = old('phones',  $v?->phoneList()   ?: [['label' => 'Mobile',   'value' => '']]);
    $urlsInit    = old('urls',    $v?->urlList()     ?: [['label' => 'Website',  'value' => '']]);
    $addrsInit   = old('addresses', $v?->addressList() ?: []);
    $socialsInit = old('social_profiles', $v?->socialList() ?: []);
    $birthday    = old('birthday',    $v?->birthday?->format('Y-m-d'));
    $anniversary = old('anniversary', $v?->anniversary?->format('Y-m-d'));
    $photoUrl    = $v?->photoUrl();
    $inputCls    = 'w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40';
    $miniBtn     = 'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs text-white/70 bg-white/5 hover:bg-white/10 border border-white/10 transition';

    // Task #3588: offer the user's own connected social accounts as
    // one-click autofill (handle + resolved URL) for the vCard's Social
    // Profiles section, mirroring the same picker on the biolink socials
    // block editor.
    $myConnections = auth()->check()
        ? \App\Modules\User\Models\SocialAccountConnection::where('user_id', auth()->id())
            ->orderBy('platform')->orderBy('handle')->get()
        : collect();
@endphp

<div x-data="vcfForm({
    emails:   @js($emailsInit),
    phones:   @js($phonesInit),
    urls:     @js($urlsInit),
    addrs:    @js($addrsInit),
    socials:  @js($socialsInit),
})" class="space-y-4">

    @php /* photo handled by the dropzone-input partial below; vcfForm() no longer
            tracks the photo preview itself. */ @endphp

    {{-- AVATAR --}}
    <div class="glass rounded-2xl p-6" x-data="{ removePhoto: false }">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-image text-blue-400 mr-2"></i>Avatar</h2>
        @include('user.partials.dropzone-input', [
            'name'        => 'photo',
            'policy'      => \App\Services\UploadPolicy::for('vcf.photo', auth()->user()),
            'currentUrl'  => $photoUrl,
            'currentName' => $photoUrl ? 'Saved avatar' : null,
            'hint'        => 'Square photo, embedded in the .vcf so contacts work offline',
            'previewKind' => 'image',
        ])
        @if($photoUrl)
            <label class="inline-flex items-center gap-2 mt-3 text-xs text-white/50 hover:text-red-400 cursor-pointer">
                <input type="checkbox" name="remove_photo" value="1" x-model="removePhoto" class="rounded border-white/20 bg-white/5 text-red-500 focus:ring-red-500/40">
                <span x-text="removePhoto ? 'Saved avatar will be removed on save' : 'Remove saved avatar'"></span>
            </label>
        @else
            <input type="hidden" name="remove_photo" value="0">
        @endif
        @error('photo') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- NAME --}}
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-user text-blue-400 mr-2"></i>Name</h2>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Prefix</label>
                <input type="text" name="prefix" value="{{ old('prefix', $v?->prefix) }}" placeholder="Mr., Dr." class="{{ $inputCls }}">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1">First <span class="text-red-400">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $v?->first_name) }}" required class="{{ $inputCls }}">
                @error('first_name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Middle</label>
                <input type="text" name="middle_name" value="{{ old('middle_name', $v?->middle_name) }}" class="{{ $inputCls }}">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1">Last</label>
                <input type="text" name="last_name" value="{{ old('last_name', $v?->last_name) }}" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Suffix</label>
                <input type="text" name="suffix" value="{{ old('suffix', $v?->suffix) }}" placeholder="Jr., PhD" class="{{ $inputCls }}">
            </div>
            <div class="col-span-2 md:col-span-3">
                <label class="block text-xs font-medium text-white/60 mb-1">Nickname</label>
                <input type="text" name="nickname" value="{{ old('nickname', $v?->nickname) }}" class="{{ $inputCls }}">
            </div>
        </div>
    </div>

    {{-- ORG --}}
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-building text-blue-400 mr-2"></i>Work</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Organization</label>
                <input type="text" name="organization" value="{{ old('organization', $v?->organization) }}" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Department</label>
                <input type="text" name="department" value="{{ old('department', $v?->department) }}" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Job Title</label>
                <input type="text" name="title" value="{{ old('title', $v?->title) }}" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Role</label>
                <input type="text" name="role" value="{{ old('role', $v?->role) }}" placeholder="e.g. Project Manager" class="{{ $inputCls }}">
            </div>
        </div>
    </div>

    {{-- EMAILS --}}
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-envelope text-blue-400 mr-2"></i>Emails</h2>
            <button type="button" @click="addEmail()" class="{{ $miniBtn }}"><i class="fas fa-plus text-[9px]"></i> Add</button>
        </div>
        <template x-for="(e, i) in emails" :key="'em-'+i">
            <div class="flex gap-2 mb-2">
                <select :name="`emails[${i}][label]`" x-model="e.label" class="{{ $inputCls }}" style="max-width:130px;">
                    @foreach($emailLabels as $lab)<option value="{{ $lab }}" class="bg-[#0a0612]">{{ $lab }}</option>@endforeach
                </select>
                <input type="email" :name="`emails[${i}][value]`" x-model="e.value" placeholder="name@example.com" class="{{ $inputCls }} flex-1">
                <button type="button" @click="emails.splice(i,1)" class="{{ $miniBtn }}" style="color:#f87171;"><i class="fas fa-times text-[10px]"></i></button>
            </div>
        </template>
    </div>

    {{-- PHONES --}}
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-phone text-blue-400 mr-2"></i>Phones</h2>
            <button type="button" @click="addPhone()" class="{{ $miniBtn }}"><i class="fas fa-plus text-[9px]"></i> Add</button>
        </div>
        <template x-for="(p, i) in phones" :key="'ph-'+i">
            <div class="flex gap-2 mb-2">
                <select :name="`phones[${i}][label]`" x-model="p.label" class="{{ $inputCls }}" style="max-width:130px;">
                    @foreach($phoneLabels as $lab)<option value="{{ $lab }}" class="bg-[#0a0612]">{{ $lab }}</option>@endforeach
                </select>
                <input type="tel" :name="`phones[${i}][value]`" x-model="p.value" placeholder="+1 555 123 4567" class="{{ $inputCls }} flex-1">
                <button type="button" @click="phones.splice(i,1)" class="{{ $miniBtn }}" style="color:#f87171;"><i class="fas fa-times text-[10px]"></i></button>
            </div>
        </template>
    </div>

    {{-- URLs --}}
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-globe text-blue-400 mr-2"></i>Websites</h2>
            <button type="button" @click="addUrl()" class="{{ $miniBtn }}"><i class="fas fa-plus text-[9px]"></i> Add</button>
        </div>
        <template x-for="(u, i) in urls" :key="'u-'+i">
            <div class="flex gap-2 mb-2">
                <select :name="`urls[${i}][label]`" x-model="u.label" class="{{ $inputCls }}" style="max-width:130px;">
                    @foreach($urlLabels as $lab)<option value="{{ $lab }}" class="bg-[#0a0612]">{{ $lab }}</option>@endforeach
                </select>
                <input type="url" :name="`urls[${i}][value]`" x-model="u.value" placeholder="https://example.com" class="{{ $inputCls }} flex-1">
                <button type="button" @click="urls.splice(i,1)" class="{{ $miniBtn }}" style="color:#f87171;"><i class="fas fa-times text-[10px]"></i></button>
            </div>
        </template>
    </div>

    {{-- SOCIAL --}}
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-hashtag text-blue-400 mr-2"></i>Social Profiles</h2>
            <button type="button" @click="addSocial()" class="{{ $miniBtn }}"><i class="fas fa-plus text-[9px]"></i> Add</button>
        </div>
        @if($myConnections->isNotEmpty())
            <div class="mb-3">
                @include('user.partials.social-autofill-picker', [
                    'connections' => $myConnections,
                    'onSelect'    => "socials.push({ service: opt.dataset.label, value: opt.dataset.handle })",
                    'buttonLabel' => 'Autofill from connected account',
                    'selectClass' => $inputCls . ' text-xs',
                ])
            </div>
        @endif
        <template x-for="(s, i) in socials" :key="'s-'+i">
            <div class="flex gap-2 mb-2">
                <select :name="`social_profiles[${i}][service]`" x-model="s.service" class="{{ $inputCls }}" style="max-width:150px;">
                    @foreach($socialServices as $svc)<option value="{{ $svc }}" class="bg-[#0a0612]">{{ $svc }}</option>@endforeach
                </select>
                <input type="text" :name="`social_profiles[${i}][value]`" x-model="s.value" placeholder="@handle or full URL" class="{{ $inputCls }} flex-1">
                <button type="button" @click="socials.splice(i,1)" class="{{ $miniBtn }}" style="color:#f87171;"><i class="fas fa-times text-[10px]"></i></button>
            </div>
        </template>
        <p x-show="socials.length === 0" class="text-xs text-white/40">No social profiles. Add Twitter, LinkedIn, Instagram, GitHub, and more.</p>
    </div>

    {{-- ADDRESSES --}}
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>Addresses</h2>
            <button type="button" @click="addAddress()" class="{{ $miniBtn }}"><i class="fas fa-plus text-[9px]"></i> Add</button>
        </div>
        <template x-for="(a, i) in addrs" :key="'a-'+i">
            <div class="rounded-xl p-3 mb-3 bg-white/5 border border-white/10">
                <div class="flex items-center justify-between mb-2">
                    <select :name="`addresses[${i}][label]`" x-model="a.label" class="{{ $inputCls }}" style="max-width:130px;">
                        @foreach($addrLabels as $lab)<option value="{{ $lab }}" class="bg-[#0a0612]">{{ $lab }}</option>@endforeach
                    </select>
                    <button type="button" @click="addrs.splice(i,1)" class="{{ $miniBtn }}" style="color:#f87171;"><i class="fas fa-times text-[10px]"></i></button>
                </div>
                <input type="text" :name="`addresses[${i}][street]`" x-model="a.street" placeholder="Street" class="{{ $inputCls }} mb-2">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <input type="text" :name="`addresses[${i}][city]`" x-model="a.city" placeholder="City" class="{{ $inputCls }}">
                    <input type="text" :name="`addresses[${i}][state]`" x-model="a.state" placeholder="State" class="{{ $inputCls }}">
                    <input type="text" :name="`addresses[${i}][zip]`" x-model="a.zip" placeholder="ZIP" class="{{ $inputCls }}">
                    <input type="text" :name="`addresses[${i}][country]`" x-model="a.country" placeholder="Country" class="{{ $inputCls }}">
                </div>
            </div>
        </template>
        <p x-show="addrs.length === 0" class="text-xs text-white/40">No addresses added.</p>
    </div>

    {{-- DATES + NOTES --}}
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-calendar text-blue-400 mr-2"></i>Dates &amp; Notes</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Birthday</label>
                <input type="date" name="birthday" value="{{ $birthday }}" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Anniversary</label>
                <input type="date" name="anniversary" value="{{ $anniversary }}" class="{{ $inputCls }}">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-white/60 mb-1">Notes</label>
            <textarea name="note" rows="3" class="{{ $inputCls }}">{{ old('note', $v?->note) }}</textarea>
        </div>
    </div>

    {{-- LINK SETTINGS --}}
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-link text-blue-400 mr-2"></i>Link Settings</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
            <div>
                @if(empty($v))
                    @include('user.links.partials.alias-field')
                @else
                    <label class="block text-xs font-medium text-white/60 mb-1">Custom Alias</label>
                    <input type="text" name="alias" value="{{ old('alias', $link->alias ?? ($prefillAlias ?? '')) }}"
                           placeholder="auto-generated"
                           minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
                           maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
                           pattern="[A-Za-z0-9_\-]+"
                           class="{{ $inputCls }}">
                    @error('alias') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1">Project</label>
                <select name="project_id" class="{{ $inputCls }}">
                    <option value="" class="bg-[#0a0612]">No project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" class="bg-[#0a0612]"
                            {{ old('project_id', $link->project_id ?? null) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                @include('user.links.partials.visibility-field', ['visInputClass' => $inputCls, 'link' => $link ?? null])
            </div>
        </div>
        @include('user.links.partials.preview-toggle', [
            'previewChecked' => old('show_preview_page', $link->settings['show_preview_page'] ?? false),
            'previewTitle' => 'Show preview page before download',
            'previewDesc' => 'Renders a contact preview that fires marketing pixels and tracks visitor dwell time before the .vcf is delivered.',
        ])
    </div>

    @isset($link)
        @include('user.links.partials.protection-scheduling', ['link' => $link])
        @include('user.links.partials.smart-rules', ['link' => $link])
    @endisset
</div>

<script>
function vcfForm({ emails, phones, urls, addrs, socials }) {
    return {
        emails: emails.length ? emails : [{ label: 'Personal', value: '' }],
        phones: phones.length ? phones : [{ label: 'Mobile', value: '' }],
        urls:   urls.length   ? urls   : [{ label: 'Website', value: '' }],
        addrs,
        socials,
        addEmail()   { this.emails.push({ label: 'Personal', value: '' }); },
        addPhone()   { this.phones.push({ label: 'Mobile', value: '' }); },
        addUrl()     { this.urls.push({ label: 'Website', value: '' }); },
        addAddress() { this.addrs.push({ label: 'Home', street: '', city: '', state: '', zip: '', country: '' }); },
        addSocial()  { this.socials.push({ service: 'Twitter', value: '' }); },
    };
}
</script>
