{{--
    The left column of both menu editors: sections, sub-sections, the rows
    inside them, and the show/hide control on every one of those.

    Sana, 2026-09-23: "also i should able to hide/unhide items, sub cat, and
    cat also" -- and, separately, "cats and sub cats".

    ---- Hiding was already built, minus the button -----------------------

    `is_active` has been on these tables since they were created. The public
    pages filter on it. The API accepts it. This screen never sent it and
    offered no control, so in the whole life of these two page types no
    creator has ever been able to hide anything. Deleting was the only way,
    and deleting a seasonal dish to bring it back in October is not the same
    operation at all.

    ---- One copy ---------------------------------------------------------

    The restaurant and store editors carried the same forty lines of markup
    twice, which is how the store spent a week missing what the restaurant
    got. This is that markup, once. The two editors differ in what they call
    a row -- an item or a product -- and in what "unavailable" means for
    one, so those are parameters; every method below is a name each editor
    already binds to its own implementation.

    The list is FLAT on purpose. `groups()` returns each section followed by
    its own sub-sections, each carrying its depth, so the row markup exists
    once instead of once per level. Nesting the templates would have meant
    two copies of the row again, inside the change that exists to remove
    the second copy.

    Parameters:
      $meNoun       'item' | 'product'   -- what a row is called, lowercase
      $meNounTitle  'Item' | 'Product'   -- button label
      $meSoldLabel  'Sold out' | 'Out of stock'
      $meSoldKey    'is_sold_out' | 'is_out_of_stock'
      $meEmpty      what to say when there are no sections at all

    Alpine contract, bound by each editor:
      groups()                -> [{cat, depth, hidden, parentHidden}]
      rowsFor(catId)          -> rows in that section, in order
      rowHidden(row, group)   -> bool
      openCategory(cat, parentId)
      toggleCategory(cat), deleteCategory(cat), moveCategory(cat, dir)
      openRow(catId, row), toggleRow(row), deleteRow(row), moveRow(catId, row, dir)
--}}
<div class="flex justify-between items-center mb-3">
    <h2 class="font-bold text-lg" style="color:var(--text-primary)">Menu</h2>
    <button class="rm-btn sm" @click="openCategory()"><i class="fas fa-plus"></i> Section</button>
</div>

<template x-if="categories.length === 0">
    <div class="rm-card" style="text-align:center;color:var(--text-muted)">
        {{ $meEmpty }}
    </div>
</template>

<template x-for="g in groups()" :key="'g'+g.cat.id">
    <div class="rm-cat" :class="{ 'rm-sub': g.depth > 0, 'rm-off': g.hidden }">
        <div class="rm-cat-head">
            <div style="min-width:0">
                <div class="ct" :style="g.depth ? 'font-size:14px' : ''" x-text="g.cat.name"></div>
                {{-- A sub-section inside a hidden section is hidden too,
                     whatever its own switch says. Saying so here is the
                     whole point: the creator turned this one ON and it is
                     still not on the page. --}}
                <div x-show="g.parentHidden" class="rm-note">
                    Hidden with the section above it.
                </div>
            </div>
            <div class="flex gap-2 items-center">
                <button class="rm-btn sm ghost" title="Move up" @click="moveCategory(g.cat,-1)"><i class="fas fa-arrow-up"></i></button>
                <button class="rm-btn sm ghost" title="Move down" @click="moveCategory(g.cat,1)"><i class="fas fa-arrow-down"></i></button>
                <button x-show="g.depth === 0" class="rm-btn sm ghost" title="Add a sub-section inside this one"
                        @click="openCategory(null, g.cat.id)"><i class="fas fa-folder-plus"></i></button>
                <button class="rm-btn sm" @click="openRow(g.cat.id)"><i class="fas fa-plus"></i> {{ $meNounTitle }}</button>
                <button class="rm-btn sm ghost" :title="g.cat.is_active === false ? 'Show on the page' : 'Hide from the page'"
                        @click="toggleCategory(g.cat)">
                    <i class="fas" :class="g.cat.is_active === false ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
                <button class="rm-btn sm ghost" @click="openCategory(g.cat)"><i class="fas fa-pen"></i></button>
                <button class="rm-btn sm danger" @click="deleteCategory(g.cat)"><i class="fas fa-trash"></i></button>
            </div>
        </div>

        <template x-for="(row, ri) in rowsFor(g.cat.id)" :key="row.id">
            <div class="rm-item" :class="{ 'rm-off': rowHidden(row, g) }">
                <img x-show="row.photo_url" :src="row.photo_url" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;">
                <div class="meta">
                    <div class="nm" x-text="row.name"></div>
                    <div class="ds" x-show="row.description" x-text="row.description"></div>
                    <div class="pr"><span x-text="menu.currency"></span> <span x-text="(+row.price).toFixed(2)"></span>
                        <span x-show="row.{{ $meSoldKey }}" class="rm-pill" style="margin-left:6px">{{ $meSoldLabel }}</span>
                        <span x-show="row.is_active === false" class="rm-pill off" style="margin-left:6px">Hidden</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <button class="rm-btn sm ghost" title="Move up" :disabled="ri===0" @click="moveRow(g.cat.id,row,-1)"><i class="fas fa-arrow-up"></i></button>
                    <button class="rm-btn sm ghost" title="Move down" :disabled="ri===rowsFor(g.cat.id).length-1" @click="moveRow(g.cat.id,row,1)"><i class="fas fa-arrow-down"></i></button>
                    <button class="rm-btn sm ghost" :title="row.is_active === false ? 'Show on the page' : 'Hide from the page'" @click="toggleRow(row)">
                        <i class="fas" :class="row.is_active === false ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                    <button class="rm-btn sm ghost" @click="openRow(g.cat.id, row)"><i class="fas fa-pen"></i></button>
                    <button class="rm-btn sm danger" @click="deleteRow(row)"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </template>

        <template x-if="rowsFor(g.cat.id).length === 0">
            <p class="text-sm" style="color:var(--text-muted)">No {{ $meNoun }}s here yet.</p>
        </template>
    </div>
</template>
