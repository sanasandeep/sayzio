@php $chosenBiolink = old('primary_biolink_id', ($user->profile_showcase ?? [])['primary_biolink_id'] ?? ''); @endphp
<fieldset data-editor-section="settings" x-show="editorTab === 'settings'" class="cp-editor-panel" x-data="{ destinationMode: @js(old('profile_destination', ($user->profile_showcase ?? [])['profile_destination'] ?? 'profile')) }">
<legend>Profile destination</legend><p class="cp-editor-hint">Choose what visitors see when they open your handle.</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <label x-show="destinationMode === 'profile'" class="block text-sm font-semibold">Main biolink button
            <select name="primary_biolink_id" class="block w-full rounded-xl p-3 mt-2" style="background:var(--bg-input);border:1px solid var(--border-soft)">
                <option value="">Automatic · first active biolink</option>
                @foreach($pickerLinks->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY) as $option)<option value="{{ $option->id }}" @selected((string)$chosenBiolink === (string)$option->id)>{{ $option->title ?: $option->alias }} · /{{ $option->alias }}</option>@endforeach
            </select>
            <span class="block text-xs font-normal mt-2" style="color:var(--text-muted)">In creator profile mode, this controls its Open biolink button. Enable the Biolink section below to display it.</span>
        </label>
        <label class="block text-sm font-semibold">When someone opens /<span>@</span>{{ $user->handle }}
            <select name="profile_destination" x-model="destinationMode" class="block w-full rounded-xl p-3 mt-2" style="background:var(--bg-input);border:1px solid var(--border-soft)">
                <option value="profile" @selected(old('profile_destination', ($user->profile_showcase ?? [])['profile_destination'] ?? 'profile') === 'profile')>Show my creator profile</option>
                <option value="redirect" @selected(old('profile_destination', ($user->profile_showcase ?? [])['profile_destination'] ?? 'profile') === 'redirect')>Redirect directly to a link</option>
            </select>
            <span class="block text-xs font-normal mt-2" style="color:var(--text-muted)">Switch back to your creator profile at any time. Your profile design and content stay saved.</span>
        </label>
        <label x-show="destinationMode === 'redirect'" class="block text-sm font-semibold">Direct redirect link
            <select name="redirect_link_id" class="block w-full rounded-xl p-3 mt-2" style="background:var(--bg-input);border:1px solid var(--border-soft)">
                <option value="">Choose an active link</option>
                @foreach($pickerLinks as $option)<option value="{{ $option->id }}" @selected((string)old('redirect_link_id', ($user->profile_showcase ?? [])['redirect_link_id'] ?? '') === (string)$option->id)>{{ $option->title ?: $option->alias }} · /{{ $option->alias }}</option>@endforeach
            </select>
            <span class="block text-xs font-normal mt-2" style="color:var(--text-muted)">Used only in redirect mode. Choose a biolink or another link in this workspace. If it becomes unavailable, your creator profile is shown.</span>
            @error('redirect_link_id')<span class="block text-sm mt-2 text-red-600">{{ $message }}</span>@enderror
        </label>
</div></fieldset>