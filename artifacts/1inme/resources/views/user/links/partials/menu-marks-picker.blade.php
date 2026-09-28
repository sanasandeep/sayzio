{{--
    The marks an owner puts on one dish: veg, spicy, served hot, no garlic.

    Sana, 2026-09-28: "only customers will see in menu if its too spicy or
    less... hot or cold... gravy or dry or semi gravy... no garlic.. no
    onions... not all item need to have these configs".

    That last clause is the whole interaction design: nothing here is
    required, nothing is preselected, and a dish with no marks looks
    exactly as it does today. So this is a row of toggles, not a form.

    ---- Why a graded mark cycles rather than having a stepper ------------

    Spice is one flame, two or three. A separate 1/2/3 control beside it
    would be three more targets on a dialog that is already long, and the
    owner would have to look somewhere other than the icon to see what
    they set. Tapping the mark walks 1, 2, 3, off, and the icon itself
    repeats as they go -- the control and the preview are the same object.

    Parameters:
      $mkModal  the Alpine property holding the open item, e.g. 'itemModal'
      $mkNoun   'dish' or 'product', for the copy
--}}
@php $mkModal = $mkModal ?? 'itemModal'; @endphp
<div class="rm-row">
    <label class="rm-label">Marks</label>
    <p class="text-xs mb-2" style="color:var(--text-muted)">
        What a diner reads on the menu beside the name. Nothing here is required, and
        a {{ $mkNoun ?? 'dish' }} with none looks exactly as it does now.
        Tap a graded mark again to raise it.
    </p>

    <template x-for="mkGroup in window.MENU_MARK_GROUPS" :key="mkGroup.label">
        <div class="mkp-group">
            <div class="mkp-head" x-text="mkGroup.label"></div>
            <div class="mkp-row">
                <template x-for="mk in mkGroup.marks" :key="mk.key">
                    <button type="button" class="mkp"
                            :class="{ 'on': menuMarks.gradeOf({{ $mkModal }}.marks, mk.key) > 0 }"
                            :style="menuMarks.gradeOf({{ $mkModal }}.marks, mk.key) > 0 && mk.color ? ('border-color:' + mk.color) : ''"
                            :aria-pressed="menuMarks.gradeOf({{ $mkModal }}.marks, mk.key) > 0"
                            :title="mk.is_graded ? (mk.label + ': tap again to raise it') : mk.label"
                            @click="{{ $mkModal }}.marks = menuMarks.toggle({{ $mkModal }}.marks, mk)">
                        <template x-if="mk.path">
                            <span class="mkp-ico"
                                  :style="mk.color ? ('color:' + mk.color) : ''"
                                  x-html="menuMarks.markup(mk, Math.max(1, menuMarks.gradeOf({{ $mkModal }}.marks, mk.key)))"></span>
                        </template>
                        <span x-text="mk.label"></span>
                    </button>
                </template>
            </div>
        </div>
    </template>

    <p class="text-xs mt-1" style="color:var(--text-faint)"
       x-show="({{ $mkModal }}.marks || []).length >= {{ \App\Modules\User\Models\MenuItemMark::MAX_PER_ITEM }}">
        That is as many marks as one row can carry without becoming a wall.
    </p>
</div>

<style>
    .mkp-group { margin-top: 10px; }
    .mkp-head {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-faint);
        margin-bottom: 5px;
    }
    .mkp-row { display: flex; flex-wrap: wrap; gap: 6px; }
    .mkp {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        border: 1px solid var(--border-subtle, rgba(128,128,128,.3));
        background: transparent;
        color: var(--text-primary);
        font-size: 12.5px;
        cursor: pointer;
    }
    .mkp:hover { border-color: var(--text-muted); }
    /* A mark that is ON borrows its own colour for the border, so the row
       reads as the menu will rather than as a list of identical pills. */
    .mkp.on {
        background: var(--bg-subtle, rgba(128,128,128,.12));
        font-weight: 600;
        border-width: 1.5px;
    }
    .mkp-ico { display: inline-flex; align-items: center; gap: 1px; }
    .mkp-ico svg { display: block; }
</style>

<script>
@once
{{-- The vocabulary is the same on every menu, so it is written out once
     per page no matter how many pickers include this partial. --}}
window.MENU_MARK_GROUPS = @json(\App\Modules\User\Support\MenuItemMarks::forPicker());
window.MENU_MARKS_MAX = {{ \App\Modules\User\Models\MenuItemMark::MAX_PER_ITEM }};

/**
 * The marks an item carries, as the editor manipulates them.
 *
 * Every function returns a NEW array rather than editing in place: Alpine
 * tracks the property, and mutating the array it already holds is how a
 * picker ends up saving correctly while showing nothing.
 */
window.menuMarks = {
    list(marks) { return Array.isArray(marks) ? marks : []; },

    /** 0 when the mark is not on the item, otherwise 1 or more. */
    gradeOf(marks, key) {
        const found = this.list(marks).find(m => m.key === key);
        return found ? Math.max(1, +found.grade || 1) : 0;
    },

    /**
     * Off -> 1 -> 2 -> 3 -> off for a graded mark; on -> off for the rest.
     * The ceiling comes from the mark itself, so a mark admin capped at 2
     * cycles at 2 without this knowing anything about spice.
     */
    toggle(marks, mark) {
        const list = this.list(marks);
        const at = list.findIndex(m => m.key === mark.key);

        if (at === -1) {
            if (list.length >= window.MENU_MARKS_MAX) { return list; }
            return [...list, mark.is_graded ? { key: mark.key, grade: 1 } : { key: mark.key }];
        }

        const next = (+list[at].grade || 1) + 1;
        if (mark.is_graded && next <= Math.max(1, mark.grades || 1)) {
            const copy = [...list];
            copy[at] = { key: mark.key, grade: next };
            return copy;
        }

        return list.filter(m => m.key !== mark.key);
    },

    /** Only ever catalogue path data, never anything anyone typed. */
    markup(mark, times) {
        if (!mark.path) { return ''; }
        const solid = mark.solid
            ? '<path d="' + mark.solid + '" fill="currentColor" stroke="none"/>'
            : '';
        let out = '';
        for (let i = 0; i < Math.max(1, times || 1); i++) {
            out += '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"'
                + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="'
                + mark.path + '"/>' + solid + '</svg>';
        }
        return out;
    },
};
@endonce
</script>
