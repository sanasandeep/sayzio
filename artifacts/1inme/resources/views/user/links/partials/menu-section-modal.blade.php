{{--
    The new/edit dialog for a menu section, shared by both menu editors.

    The one field that is not obvious is the parent. A section can be moved
    into another section, or pulled back out, from the same dropdown that
    created it -- because a creator who built "Idli" as a top-level heading
    and then wanted it under "Tiffins" would otherwise have to delete it and
    retype every item.

    The dropdown lists only what is a LEGAL parent, which is the same rule
    MenuTree::rejectParent enforces on the way in: a top-level section, not
    this one, and not offered at all when this section already has
    sub-sections of its own (that move would strand them). Offering a choice
    the save path refuses is the bug this week has been about.

    Parameters:
      $mmNoun  'menu' | 'store'  -- only used in the explanatory line
--}}
<div class="rm-modal-bg" x-show="catModal.open" x-cloak @click.self="catModal.open=false">
    <div class="rm-modal">
        <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px"
            x-text="catModal.id ? 'Edit section' : (catModal.parent_id ? 'New sub-section' : 'New section')"></h5>
        <div class="rm-row"><label class="rm-label">Name</label><input class="rm-input" x-model="catModal.name"></div>
        <div class="rm-row"><label class="rm-label">Description</label><textarea class="rm-textarea" x-model="catModal.description"></textarea></div>
        <div class="rm-row">
            <label class="rm-label">Inside</label>
            <select class="rm-input" x-model="catModal.parent_id">
                <option :value="null">Nothing &mdash; this is a section of its own</option>
                <template x-for="p in parentChoices()" :key="'p'+p.id">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
            </select>
            <p class="rm-note" x-show="catModal.id && subsFor(catModal.id).length > 0">
                This section holds sub-sections, so it cannot go inside another one.
            </p>
            <p class="rm-note" x-show="!catModal.id || subsFor(catModal.id).length === 0">
                A sub-section prints under its section, the way a printed {{ $mmNoun }} card groups
                its dishes. One level deep.
            </p>
        </div>
        <div class="flex justify-end gap-2">
            <button class="rm-btn ghost" @click="catModal.open=false">Cancel</button>
            <button class="rm-btn" @click="saveCategory()">Save</button>
        </div>
    </div>
</div>
