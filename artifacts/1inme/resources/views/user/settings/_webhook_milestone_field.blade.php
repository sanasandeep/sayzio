{{--
    Click-milestone configuration partial.
    Included inside webhook destination create/edit forms.

    Variables:
      $dest — ?InboxForwardDestination (null when creating)
--}}
@php
    $milestones = $dest?->clickMilestoneThresholds() ?? [];
    $milestonesStr = old('click_milestones_raw', implode(', ', $milestones));
@endphp

<div class="md:col-span-2" x-data="{ show: {{ empty($milestones) && !old('click_milestones_raw') ? 'false' : 'true' }} }">
    <button type="button" @click="show = !show"
            class="flex items-center gap-2 text-xs font-semibold mb-2" style="color: var(--text-muted);">
        <i class="fas fa-chevron-right text-[10px] transition-transform" :class="{ 'rotate-90': show }"></i>
        Click milestone thresholds
        <span style="color: var(--text-faint); font-weight:normal;">(optional, e.g. 100, 1000, 10000)</span>
    </button>

    <div x-show="show" x-transition>
        <p class="text-[11px] mb-2" style="color: var(--text-faint);">
            Enter comma-separated click counts. A <strong style="color:var(--text-secondary);">click_milestone</strong> event fires
            once per threshold when a link crosses it.
            Requires the <strong style="color:var(--text-secondary);">click_milestone</strong> source to be selected above
            (or leave all sources unchecked to receive all events).
        </p>
        <input type="text" name="click_milestones_raw" maxlength="200"
               value="{{ $milestonesStr }}"
               placeholder="100, 1000, 10000"
               class="w-full px-3 py-2 rounded-lg text-sm outline-none"
               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
               @input="
                   const raw = $event.target.value;
                   document.querySelectorAll('[name=\'click_milestones[]\']').forEach(el => el.remove());
                   raw.split(',').map(v => parseInt(v.trim())).filter(v => v > 0).forEach(v => {
                       const inp = document.createElement('input');
                       inp.type = 'hidden';
                       inp.name = 'click_milestones[]';
                       inp.value = v;
                       $event.target.parentElement.appendChild(inp);
                   });
               ">
        {{-- Pre-populate hidden inputs from existing thresholds (server-side) --}}
        @foreach($milestones as $ms)
            <input type="hidden" name="click_milestones[]" value="{{ $ms }}">
        @endforeach
    </div>
</div>
