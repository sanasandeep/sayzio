{{-- Mind picker — multi-select for the user's own Minds plus an
     opt-in checkbox for the platform default. Used by Persona and
     Coach so generations can be grounded in selected knowledge bases.

     Inputs:
       $mineMinds       Collection of AiMind (id, name)
       $platformMind    AiMind|null
       $selectedIds     int[] previously selected own-mind ids
       $platformOptIn   bool previously checked
--}}
@php
    $selectedIds   = collect($selectedIds ?? [])->map(fn($v) => (int) $v)->all();
    $platformOptIn = (bool) ($platformOptIn ?? false);
@endphp
<div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">Minds it can use</p>
            <p class="text-xs text-white/40 mt-0.5">
                Pick which knowledge bases to ground the answer in.
                <a href="{{ route('user.minds.index') }}" class="text-violet-300 hover:underline">Manage Minds →</a>
            </p>
        </div>
    </div>

    @if($mineMinds->isEmpty())
        <p class="text-xs text-white/40 italic">
            You don't have any Minds yet. Create one to ground answers in your own content.
        </p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($mineMinds as $m)
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 border border-white/10 hover:border-violet-400/40 cursor-pointer">
                    <input type="checkbox" name="mind_ids[]" value="{{ $m->id }}"
                           {{ in_array((int) $m->id, $selectedIds, true) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/10 text-violet-500 focus:ring-violet-500">
                    <span class="text-sm text-white/90 truncate">{{ $m->name }}</span>
                </label>
            @endforeach
        </div>
    @endif

    @if($platformMind)
        <label class="flex items-center gap-2 pt-1">
            <input type="hidden" name="include_platform" value="0">
            <input type="checkbox" name="include_platform" value="1"
                   {{ $platformOptIn ? 'checked' : '' }}
                   class="rounded border-white/20 bg-white/10 text-violet-500 focus:ring-violet-500">
            <span class="text-sm text-white/80">
                Also use the platform default Mind
                <span class="text-xs text-white/40">({{ $platformMind->name }})</span>
            </span>
        </label>
    @endif

    @isset($defaultFeature)
        {{-- Save / clear the user's default Mind selection for this
             feature (Persona, Coach). The save/clear forms sit outside
             this <form> via the `form` attribute, but the buttons are
             rendered inline so the affordance is right next to the
             checkboxes. --}}
        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-white/10">
            <p class="text-xs text-white/40">
                @if(!empty($hasDefault))
                    Loaded from your default selection.
                @else
                    Tip: save this selection as your default to skip re-picking next time.
                @endif
            </p>
            <div class="flex items-center gap-2">
                <button type="submit"
                        formaction="{{ route('user.ai.' . $defaultFeature . '.defaults.save') }}"
                        formmethod="POST"
                        formnovalidate
                        class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-white/80 text-xs hover:bg-white/20">
                    Use as default
                </button>
                @if(!empty($hasDefault))
                    {{-- Submitted from inside the parent <form>. Setting
                         the button's name/value to `_method=DELETE`
                         only adds that pair when this button is the
                         submitter, so Laravel routes it to the DELETE
                         clearDefaults action without affecting the
                         "Use as default" POST. --}}
                    <button type="submit"
                            formaction="{{ route('user.ai.' . $defaultFeature . '.defaults.clear') }}"
                            formmethod="POST"
                            formnovalidate
                            name="_method" value="DELETE"
                            class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-white/60 text-xs hover:bg-white/10">
                        Clear default
                    </button>
                @endif
            </div>
        </div>
    @endisset
</div>
