{{--
    Choices on items: spice levels, sizes, toppings, add-ons.

    Sana, 2026-09-28: "like spicy levels, served like hot cold.. variations
    like sizes and toppings options with limitions settings (like select 2)
    ... options like addons with variable quatity".

    Those read like four features and they are one object: a group of
    choices with a floor, a ceiling, and a cap on how many times one choice
    can be taken. So there is one panel, not four.

    Shared by the restaurant and store editors. Expects:
      $choiceBase   the option-groups endpoint for this menu
      $choiceItems  [{id, name}] -- the items a group can be put on
      $choiceNoun   "dish" or "product", for the copy
      $choiceNounPlural  its plural. Not noun + "s": that gives "dishs",
                         which is what shipped and what this fixes.
--}}
@php
    // The icon catalogue is the same on every menu, so it is read here
    // rather than passed in by each editor -- one fewer thing for the next
    // editor that includes this partial to forget.
    $choiceIcons = [
        'list'  => \App\Modules\User\Support\MenuOptionIcon::forPicker(),
        'paths' => \App\Modules\User\Support\MenuOptionIcon::paths(),
        'max'   => \App\Modules\User\Support\MenuOptionIcon::MAX_REPEAT,
    ];
@endphp
<div class="rm-card"
     x-data="menuChoices(@js($choiceBase), @js($choiceItems), @js($choiceNoun ?? 'item'), @js($choiceNounPlural ?? (($choiceNoun ?? 'item').'s')), @js($choiceIcons))"
     x-init="load()"
     {{-- The way in from the item dialog: it dispatches this rather than
          reaching into another component, so the dialog needs to know
          nothing about how choices work. --}}
     @menu-choices-open.window="openFor($event.detail && $event.detail.itemId)">
    <h5 style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <span>Choices <button class="rm-btn sm" type="button" @click="newGroup()"><i class="fas fa-plus"></i></button></span>
    </h5>
    <p class="text-xs mb-3" style="color:var(--text-muted)">
        Questions a customer answers when they add something: spice level, size, toppings, add-ons. Define a set once and put it on as many <span x-text="nouns"></span> as it applies to.
    </p>

    <template x-if="!loaded">
        <p class="text-xs" style="color:var(--text-faint)">Loading…</p>
    </template>

    <template x-if="loaded && groups.length === 0">
        <p class="text-xs" style="color:var(--text-faint)">
            None yet. "Spice level" with Mild, Medium and Hot is the usual first one.
        </p>
    </template>

    <template x-for="g in groups" :key="g.id">
        <div class="rm-table-row">
            <span style="color:var(--text-primary);min-width:0">
                <strong x-text="g.name"></strong>
                <span class="mc-rule" x-text="g.rule_label"></span>
                <em class="mc-on" x-text="onLabel(g)"></em>
            </span>
            <span style="display:flex;gap:4px;flex:none">
                <button class="rm-btn sm" type="button" @click="editGroup(g)" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="rm-btn sm danger" type="button" @click="removeGroup(g)" title="Delete"><i class="fas fa-trash"></i></button>
            </span>
        </div>
    </template>

    <p class="text-xs mt-2" style="color:var(--text-faint)" x-text="msg"></p>

    {{-- The editor for one group. Everything about it is here rather than
         spread across the card, so a half-finished group is never saved in
         pieces.

         TELEPORTED TO BODY, and it has to be. `position: fixed` resolves
         against the nearest ancestor with a transform, filter or contain --
         and the settings column has one -- so left where it was written the
         sheet laid itself out INSIDE the column, overlapping the cards above
         and below it and clipped at the right edge. Same trap as the public
         pages' pinned overlays, different cause, same symptom. --}}
    <template x-teleport="body">
    <div>
    <template x-if="draft">
        <div class="mc-modal" x-cloak @click.self="draft = null" @keydown.escape.window="draft = null">
            <div class="mc-sheet">
                <h5 x-text="draft.id ? 'Edit choices' : 'New choices'"></h5>

                <div class="rm-row">
                    <label class="rm-label">Name</label>
                    <input class="rm-input" x-model="draft.name" maxlength="80" placeholder="Spice level">
                </div>

                <div class="rm-row">
                    <label class="rm-label">Note under the name (optional)</label>
                    <input class="rm-input" x-model="draft.hint" maxlength="160" placeholder="How hot would you like it?">
                </div>

                <div class="rm-row">
                    <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
                        <input type="checkbox" x-model="draft.is_required"> They have to choose
                    </label>
                </div>

                <div class="mc-nums">
                    <div>
                        <label class="rm-label">Choose at least</label>
                        <input class="rm-input" type="number" min="0" max="20" x-model.number="draft.min_select">
                    </div>
                    <div>
                        <label class="rm-label">At most</label>
                        <input class="rm-input" type="number" min="1" max="20" x-model.number="draft.max_select" placeholder="any">
                    </div>
                    <div>
                        <label class="rm-label">Each, up to</label>
                        <input class="rm-input" type="number" min="1" max="20" x-model.number="draft.max_per_option">
                    </div>
                </div>
                <p class="text-xs mt-1" style="color:var(--text-muted)">
                    Leave "at most" empty for no limit. "Each, up to" is how many times one choice can be added: 1 is a tick box, more is a quantity.
                </p>
                <p class="text-xs mt-2 mc-preview" x-text="'Customers will see: ' + preview()"></p>

                {{-- Choices live only once the group exists, because each one
                     is its own row against a group id. --}}
                <template x-if="draft.id">
                    <div class="mc-options">
                        <label class="rm-label">The choices</label>
                        <template x-for="(o, i) in draft.options" :key="o.id">
                            <div class="mc-optwrap">
                                <div class="mc-opt">
                                    <button class="rm-btn sm mc-icobtn" type="button"
                                            :class="{ 'on': o.icon }"
                                            @click="iconPicker = (iconPicker === o.id ? null : o.id)"
                                            :title="o.icon ? 'Change the icon' : 'Add an icon'"
                                            :aria-label="o.icon ? 'Change the icon on ' + (o.name || 'this choice') : 'Add an icon to ' + (o.name || 'this choice')"
                                            x-html="iconMarkup(o.icon, o.icon_repeat)"></button>
                                    <input class="rm-input" x-model="o.name" maxlength="80" placeholder="Mild" @change="saveOption(o)">
                                    <input class="rm-input mc-delta" type="number" step="0.01" x-model.number="o.price_delta" @change="saveOption(o)" title="Added to the price. Negative takes money off.">
                                    <button class="rm-btn sm" type="button" :class="{ 'on': o.is_sold_out }" @click="o.is_sold_out = !o.is_sold_out; saveOption(o)" title="Sold out today"><i class="fas fa-ban"></i></button>
                                    <button class="rm-btn sm danger" type="button" @click="removeOption(o, i)" title="Remove"><i class="fas fa-trash"></i></button>
                                </div>

                                {{-- Open under the choice it belongs to, so
                                     there is never a question of which row is
                                     being changed. --}}
                                <template x-if="iconPicker === o.id">
                                    <div class="mc-icopick">
                                        <button type="button" class="mc-icoopt none" :class="{ 'on': !o.icon }" @click="setIcon(o, null)" title="No icon">None</button>
                                        <template x-for="ic in icons" :key="ic.key">
                                            <button type="button" class="mc-icoopt"
                                                    :class="{ 'on': o.icon === ic.key }"
                                                    @click="setIcon(o, ic.key)"
                                                    :title="ic.label + ': ' + ic.hint"
                                                    :aria-label="ic.label"
                                                    x-html="iconMarkup(ic.key, 1)"></button>
                                        </template>

                                        {{-- "Mild / Medium / Hot" is this one
                                             drawing at one, two and three. --}}
                                        <template x-if="o.icon">
                                            <span class="mc-icorep">
                                                <span>Draw it</span>
                                                <template x-for="n in maxRepeat" :key="n">
                                                    <button type="button" class="mc-icoopt tiny"
                                                            :class="{ 'on': (o.icon_repeat || 1) === n }"
                                                            @click="setRepeat(o, n)"
                                                            :aria-label="n === 1 ? 'once' : n + ' times'"
                                                            x-text="n + '×'"></button>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <button class="rm-btn sm mt-2" type="button" @click="addOption()"><i class="fas fa-plus"></i> Add a choice</button>
                        <p class="text-xs mt-1" style="color:var(--text-muted)">
                            The number is what the choice adds to the price. Leave it 0 for a spice level; use it for sizes and add-ons, and a negative number to take money off. The icon is optional. A flame drawn once, twice and three times is how "Mild, Medium, Hot" usually reads.
                        </p>
                    </div>
                </template>

                <template x-if="draft.id">
                    <div class="mc-items">
                        <label class="rm-label" x-text="'Which ' + nouns + ' is this on?'"></label>
                        <div class="mc-item-list">
                            <template x-for="it in items" :key="it.id">
                                <label class="mc-item">
                                    <input type="checkbox" :value="it.id" x-model.number="draft.item_ids">
                                    <span x-text="it.name"></span>
                                </label>
                            </template>
                        </div>
                        <template x-if="items.length === 0">
                            <p class="text-xs" style="color:var(--text-faint)" x-text="'Add a ' + noun + ' first, then come back and tick it here.'"></p>
                        </template>
                    </div>
                </template>

                <div class="mc-actions">
                    <button class="rm-btn primary" type="button" @click="saveGroup()" x-text="draft.id ? 'Save' : 'Create'"></button>
                    <button class="rm-btn" type="button" @click="draft = null">Close</button>
                </div>
                <p class="text-xs mt-1" style="color:var(--text-faint)" x-text="draftMsg"></p>
            </div>
        </div>
    </template>
    </div>
    </template>
</div>

<style>
    .mc-rule {
        margin-left: 8px;
        font-size: 12px;
        color: var(--text-muted);
    }
    .mc-on {
        display: block;
        margin-top: 2px;
        font-style: normal;
        font-size: 11.5px;
        color: var(--text-faint);
    }
    .mc-modal {
        position: fixed;
        inset: 0;
        /* Above the support widget: a sheet the owner just opened is the
           thing they are looking at. */
        z-index: 9999;
        background: rgba(0,0,0,.45);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .mc-sheet {
        background: var(--bg-card, #fff);
        color: var(--text-primary);
        width: 100%;
        max-width: 520px;
        max-height: 88vh;
        overflow: auto;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 24px 60px -20px rgba(0,0,0,.5);
    }
    .mc-sheet h5 { margin: 0 0 14px; font-size: 16px; }
    .mc-nums {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .mc-preview {
        padding: 7px 10px;
        border-radius: 8px;
        background: var(--bg-subtle, rgba(128,128,128,.1));
        color: var(--text-primary);
    }
    .mc-options { margin-top: 16px; }
    .mc-optwrap { margin-top: 6px; }
    .mc-opt {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .mc-opt .rm-input { margin-top: 0; }
    .mc-opt .mc-delta { max-width: 96px; flex: none; }
    /* The icon button shows what is on the choice right now, repeats and
       all, so the row itself is the preview. */
    .mc-icobtn {
        flex: none;
        min-width: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 1px;
    }
    .mc-icobtn svg { display: block; }
    .mc-ico-none { font-size: 12px; opacity: .5; }
    .mc-icopick {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        margin: 6px 0 2px 40px;
        padding: 7px 8px;
        border-radius: 10px;
        background: var(--bg-subtle, rgba(128,128,128,.1));
    }
    .mc-icoopt {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-primary);
        cursor: pointer;
        padding: 0;
    }
    .mc-icoopt svg { display: block; }
    .mc-icoopt:hover { border-color: var(--border-subtle, rgba(128,128,128,.4)); }
    .mc-icoopt.on {
        border-color: var(--accent, #3b82f6);
        background: var(--bg-card, rgba(255,255,255,.06));
    }
    .mc-icoopt.none, .mc-icoopt.tiny { width: auto; padding: 0 8px; font-size: 11.5px; }
    .mc-icorep {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-left: auto;
        font-size: 11.5px;
        color: var(--text-muted);
    }
    .mc-items { margin-top: 16px; }
    .mc-item-list {
        max-height: 200px;
        overflow: auto;
        border: 1px solid var(--border-subtle, rgba(128,128,128,.28));
        border-radius: 10px;
        padding: 8px 10px;
        margin-top: 4px;
    }
    .mc-item {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 3px 0;
        font-size: 13.5px;
        color: var(--text-primary);
        cursor: pointer;
    }
    .mc-actions {
        display: flex;
        gap: 8px;
        margin-top: 18px;
    }
    @media (max-width: 520px) {
        .mc-nums { grid-template-columns: 1fr; }
    }
</style>

<script>
/**
 * Which choice groups are on an item, for the item dialog to show.
 *
 * The panel is the only thing that knows; the dialog is a different Alpine
 * component in a different card and must not reach into it. So the panel
 * publishes here after every load, and the dialog reads it.
 */
window.menuChoiceIndex = window.menuChoiceIndex || {};
window.menuChoicesOn = function (itemId) {
    var names = window.menuChoiceIndex[itemId] || [];
    return names.length ? names.join(', ') : 'None yet';
};

function menuChoices(base, items, noun, nouns, iconset) {
    return {
        base, items, noun, nouns,
        icons: (iconset && iconset.list) || [],
        iconPaths: (iconset && iconset.paths) || {},
        maxRepeat: (iconset && iconset.max) || 3,
        iconPicker: null,
        groups: [],
        loaded: false,
        draft: null,
        confirmDelete: null,
        msg: '',
        draftMsg: '',

        async api(method, path, body) {
            const r = await fetch(this.base + path, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            let j = null;
            try { j = await r.json(); } catch (e) { j = null; }
            if (!r.ok) {
                throw new Error((j && j.error && j.error.message) || 'Could not save that.');
            }
            return j ? j.data : null;
        },

        async load() {
            try {
                const d = await this.api('GET', '');
                this.groups = d.groups;
                this.publishIndex();
            } catch (e) {
                this.msg = e.message;
            }
            this.loaded = true;
        },

        /** What the item dialog reads to say which choices an item has. */
        publishIndex() {
            const by = {};
            this.groups.forEach(g => {
                (g.item_ids || []).forEach(id => {
                    (by[id] = by[id] || []).push(g.name);
                });
            });
            window.menuChoiceIndex = by;
        },

        /**
         * Opened from the item dialog's "Manage choices".
         *
         * This is the whole reason that button exists: choices were built,
         * shipped and reachable only from a card in the settings column,
         * and Sana went looking for them on the item -- which is the eighth
         * time this month a working feature had no way in. If the item
         * already has a group, edit that one; otherwise start a new one
         * with the item already ticked, so the obvious next step is not a
         * scroll away.
         */
        openFor(itemId) {
            const id = Number(itemId) || null;
            this.$el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (!id) { return; }

            const existing = this.groups.find(g => (g.item_ids || []).map(Number).includes(id));
            if (existing) { this.editGroup(existing); return; }

            this.newGroup();
            this.draft.item_ids = [id];
        },

        iconMarkup(key, times) {
            const path = this.iconPaths[key];
            if (!path) { return '<span class="mc-ico-none">+</span>'; }

            const n = Math.max(1, Math.min(this.maxRepeat, +times || 1));
            let out = '';
            for (let i = 0; i < n; i++) {
                // Stroked, not filled: an outline holds its shape at the
                // 15px the customer sees it at.
                out += '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"'
                    + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="'
                    + path + '"/></svg>';
            }
            return out;
        },

        setIcon(o, key) {
            o.icon = key;
            // A count for an icon that is not there is a number nobody can
            // see the effect of.
            if (!key) { o.icon_repeat = 1; }
            this.saveOption(o);
        },

        setRepeat(o, n) {
            o.icon_repeat = Math.max(1, Math.min(this.maxRepeat, +n || 1));
            this.saveOption(o);
        },

        onLabel(g) {
            const n = (g.item_ids || []).length;
            if (n === 0) { return 'Not on anything yet'; }
            return 'On ' + n + ' ' + (n === 1 ? this.noun : this.nouns);
        },

        blank() {
            return {
                id: null, name: '', hint: '',
                is_required: false, min_select: 0, max_select: null, max_per_option: 1,
                options: [], item_ids: [],
            };
        },

        newGroup() { this.draftMsg = ''; this.iconPicker = null; this.draft = this.blank(); },

        editGroup(g) {
            this.draftMsg = '';
            this.iconPicker = null;
            // A copy, so closing without saving changes nothing.
            this.draft = JSON.parse(JSON.stringify(g));
        },

        // Mirrors MenuOptionSelection::ruleLabel on the server, so the
        // sentence the owner is shown here is the sentence their customers
        // will read rather than an approximation of it.
        preview() {
            const min = Math.max(0, +this.draft.min_select || 0) || (this.draft.is_required ? 1 : 0);
            let max = this.draft.max_select === null || this.draft.max_select === '' ? null : Math.max(1, +this.draft.max_select);
            if (max !== null && max < min) { max = min; }

            let label;
            if (min > 0 && max === min) {
                label = 'Choose ' + min;
            } else {
                const parts = [];
                if (min > 0) { parts.push('choose at least ' + min); }
                if (max !== null) { parts.push((parts.length ? 'up to ' : 'choose up to ') + max); }
                if (!parts.length) { parts.push('optional'); }
                label = parts.join(', ');
                label = label.charAt(0).toUpperCase() + label.slice(1);
            }
            const cap = Math.max(1, +this.draft.max_per_option || 1);
            if (cap > 1) { label += ' · each up to ' + cap + 'x'; }
            return label;
        },

        payload() {
            const max = this.draft.max_select === null || this.draft.max_select === '' ? null : +this.draft.max_select;
            return {
                name: (this.draft.name || '').trim(),
                hint: (this.draft.hint || '').trim(),
                is_required: !!this.draft.is_required,
                min_select: Math.max(0, +this.draft.min_select || 0),
                max_select: max,
                max_per_option: Math.max(1, +this.draft.max_per_option || 1),
            };
        },

        async saveGroup() {
            if (!this.payload().name) { this.draftMsg = 'Give it a name first.'; return; }
            this.draftMsg = 'Saving…';
            try {
                if (this.draft.id) {
                    await this.api('PUT', '/' + this.draft.id, this.payload());
                    await this.api('POST', '/' + this.draft.id + '/items', {
                        item_ids: (this.draft.item_ids || []).map(Number),
                    });
                } else {
                    const d = await this.api('POST', '', this.payload());
                    // Stay open on the new group: choices and items can only
                    // be attached once it has an id, and closing here would
                    // make creating one a two-step job with no sign of it.
                    this.draft.id = d.group.id;
                }
                await this.load();
                this.draftMsg = this.draft.id ? 'Saved. Add the choices below.' : 'Saved.';
                const fresh = this.groups.find(g => g.id === this.draft.id);
                if (fresh) { this.draft.options = JSON.parse(JSON.stringify(fresh.options)); }
            } catch (e) {
                this.draftMsg = e.message;
            }
        },

        async removeGroup(g) {
            if (this.confirmDelete !== g.id) {
                this.confirmDelete = g.id;
                this.msg = 'Tap delete again to remove "' + g.name + '".';
                return;
            }
            this.confirmDelete = null;
            try {
                await this.api('DELETE', '/' + g.id);
                this.msg = 'Removed "' + g.name + '".';
                await this.load();
            } catch (e) {
                this.msg = e.message;
            }
        },

        async addOption() {
            try {
                const d = await this.api('POST', '/' + this.draft.id + '/options', { name: 'New choice', price_delta: 0 });
                this.draft.options.push(d.option);
                this.draftMsg = '';
            } catch (e) {
                this.draftMsg = e.message;
            }
        },

        async saveOption(o) {
            try {
                await this.api('PUT', '/' + this.draft.id + '/options/' + o.id, {
                    name: (o.name || '').trim() || 'Choice',
                    price_delta: +o.price_delta || 0,
                    icon: o.icon || null,
                    icon_repeat: Math.max(1, Math.min(this.maxRepeat, +o.icon_repeat || 1)),
                    is_sold_out: !!o.is_sold_out,
                });
                this.draftMsg = 'Saved.';
                await this.load();
            } catch (e) {
                this.draftMsg = e.message;
            }
        },

        async removeOption(o, i) {
            if (this.iconPicker === o.id) { this.iconPicker = null; }
            try {
                await this.api('DELETE', '/' + this.draft.id + '/options/' + o.id);
                this.draft.options.splice(i, 1);
                await this.load();
            } catch (e) {
                this.draftMsg = e.message;
            }
        },
    };
}
</script>
