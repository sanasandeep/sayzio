@extends('user.layouts.app')
@section('title', 'Store - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
@php
    // Built here rather than inline in @json: that directive reads to the
    // first balancing ')', so a multi-line expression with array brackets
    // in it compiles to broken PHP.
    $menuColourFields = collect(\App\Modules\User\Support\MenuPresentation::COLOURS)
        ->map(fn ($c, $k) => ['key' => $k, 'label' => $c['label'], 'hint' => $c['hint']])
        ->values()
        ->all();
@endphp
<style>
    .rm-grid { display:grid; grid-template-columns: minmax(0,1fr) 320px; gap:20px; align-items:start; }
    @media (max-width:1100px){ .rm-grid { grid-template-columns: minmax(0,1fr); } }
    .rm-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:20px; margin-bottom:16px; backdrop-filter:blur(20px); }
    .rm-card h5 { color:var(--text-primary); font-weight:700; margin:0 0 14px; font-size:15px; display:flex; justify-content:space-between; align-items:center; }
    .rm-label { display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:6px; }
    .rm-input, .rm-select, .rm-textarea { width:100%; border:1px solid var(--border-glass); border-radius:.75rem; background:var(--bg-glass-input); color:var(--text-primary); padding:10px 12px; font-size:14px; outline:none; }
    .rm-textarea { resize:vertical; min-height:60px; }
    .rm-row { margin-bottom:14px; }
    .rm-btn { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border:0; border-radius:999px; font-weight:600; font-size:13.5px; color:#fff; cursor:pointer; background:linear-gradient(135deg,#5c83ff,#6366f1); }
    .rm-btn.sm { padding:6px 12px; font-size:12.5px; }
    .rm-btn.ghost { background:transparent; color:var(--text-muted); border:1px solid var(--border-glass); }
    .rm-btn.danger { background:transparent; color:#ef4444; border:1px solid rgba(239,68,68,.4); }
    /* A row is the height of the dish in it. The actions used to be five
       pill buttons stacked in a column, which set a floor of about 200px on
       every row no matter how little it held; they are icon buttons on one
       line now and the text decides the height. `align-items:center` keeps
       them beside the name rather than pinned to the top of a tall row. */
    /* One option chip, used by every picker on this panel.

       There were seven copies of this markup inline across four partials,
       each restating the same padding, radius and border. They drifted in
       the ways copies do: a fixed 3-column grid on a ~330px panel cut
       "Just the number" in half, and the border -- var(--border-glass),
       #e3e0da in light mode -- is invisible enough on white that the
       unselected options read as loose radio buttons with a stray blue
       rectangle behind whichever one was picked, rather than as a set of
       chips with one of them on. */
    .rm-opts { display:grid; grid-template-columns:repeat(auto-fit,minmax(92px,1fr)); gap:6px; }
    .rm-opts.two { grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); }
    .rm-opt {
        display:flex; align-items:center; gap:7px;
        padding:8px 10px; border-radius:10px; cursor:pointer;
        /* --border-strong, not --border-glass: the panel's own theme already
           carries a weight for "this is an edge you are meant to see", and
           --border-glass at #e3e0da on white is not it. */
        border:1px solid var(--border-strong);
        background:var(--bg-glass-input);
        transition:border-color .15s ease, background .15s ease;
        min-width:0;
    }
    .rm-opt:hover { border-color:#9db0ff; }
    .rm-opt input { flex:0 0 auto; margin:0; }
    /* Wraps instead of being clipped: a label is the only thing telling a
       creator what the option does. */
    .rm-opt span { font-size:12.5px; font-weight:600; line-height:1.25; min-width:0; }
    .rm-opt.on { border-color:#7f9cff; background:rgba(127,156,255,.12); }
    .rm-opt.on span { color:#3d6bff; }
    html:not(.light-mode) .rm-opt.on span { color:#9db0ff; }

    /* A colour row: swatch, what it is, and a way to clear it. Accent color
       was a full-width 42px bar two rows above four of these, which is the
       same control drawn two different ways within one card. */
    /* A heading for a second group of chips under one rm-label. */
    .rm-sublabel { font-size:11px; font-weight:600; color:var(--text-muted); opacity:.85; margin:9px 0 5px; }

    .rm-colour { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .rm-colour input[type=color] { height:34px; width:46px; padding:3px; flex:0 0 auto; border-radius:9px; border:1px solid var(--border-strong); background:var(--bg-glass-input); cursor:pointer; }
    .rm-colour .txt { flex:1; min-width:0; }
    .rm-colour .txt b { display:block; font-size:12px; font-weight:600; color:var(--text-primary); }
    .rm-colour .txt small { display:block; font-size:10.5px; opacity:.6; }

    .rm-item { display:flex; gap:12px; align-items:center; padding:10px 12px; border:1px solid var(--border-glass); border-radius:.85rem; margin-bottom:8px; background:var(--bg-glass-input); }
    .rm-item .meta { flex:1; min-width:0; }
    .rm-item .nm { font-weight:650; color:var(--text-primary); font-size:14.5px; }
    .rm-item .ds { font-size:12.5px; color:var(--text-muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rm-item .pr { font-size:13px; color:#5c83ff; font-weight:700; margin-top:3px; }
    /* Wraps rather than squeezing the name out of the row on a narrow column. */
    .rm-acts { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:4px; flex-shrink:0; }
    .rm-act { width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--border-glass); background:transparent; color:var(--text-muted); font-size:11px; cursor:pointer; transition:background .15s ease,color .15s ease; }
    .rm-act:hover:not(:disabled) { background:var(--bg-glass-hover,rgba(127,127,127,.12)); color:var(--text-primary); }
    .rm-act:disabled { opacity:.35; cursor:default; }
    .rm-act.danger { color:#ef4444; border-color:rgba(239,68,68,.35); }
    .rm-act.danger:hover { background:rgba(239,68,68,.12); color:#ef4444; }
    .rm-cat { border:1px solid var(--border-glass); border-radius:1rem; padding:16px; margin-bottom:16px; }
    .rm-cat-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .rm-cat-head .ct { font-weight:700; color:var(--text-primary); font-size:16px; }
    .rm-pill { font-size:11px; padding:3px 9px; border-radius:999px; background:rgba(239,68,68,.15); color:#ef4444; font-weight:600; }
    .rm-pill.off { background:rgba(148,163,184,.18); color:#94a3b8; }
    /* A sub-section sits inside its section, and says so by indent plus a
       rail -- the indent alone is ambiguous once a card is 16px padded. */
    .rm-sub { margin-left:22px; border-left:2px solid #5c83ff44; border-radius:0 1rem 1rem 0; }
    /* Hidden means hidden, not deleted: the row stays legible and editable,
       it just stops looking like something a visitor can see. */
    .rm-off { opacity:.5; }
    .rm-note { font-size:11.5px; color:var(--text-muted); margin-top:4px; }
    .rm-mode-toggle { display:flex; gap:8px; }
    .rm-mode-toggle label { flex:1; text-align:center; padding:10px; border:1px solid var(--border-strong); border-radius:.75rem; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-muted); }
    .rm-mode-toggle input { display:none; }
    .rm-mode-toggle input:checked + span { color:#5c83ff; }
    .rm-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:60; padding:16px; }
    .rm-modal { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:22px; width:100%; max-width:480px; max-height:90vh; overflow:auto; }
</style>

@php
    // Sana, 2026-10-05: "move settings column to another tab menu menu...
    // this way it will look uniform and all will be looking same layout
    // type". Same shell as the restaurant editor and as every Settings
    // screen -- hero, tabs, content left, live page right.
    $mePane = in_array(request()->query('pane'), ['items', 'design', 'ordering'], true)
        ? request()->query('pane')
        : 'items';
@endphp

<div class="w-full max-w-7xl mx-auto" x-data="Object.assign(storeEditor(), menuEditorPanes(@js($mePane)))" x-init="init()">
    @include('user.links.partials.editor-header', [
        'link' => $link,
        'activeMainTab' => 'store',
    ])
    @include('user.links.partials.menu-editor-panes', [
        'mepPane'  => $mePane,
        'mepItems' => 'Products',
    ])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7" style="padding-bottom:72px;">

        {{-- PRODUCTS ---------------------------------------------------- --}}
        <div x-show="pane === 'items'" x-cloak>
            @include('user.links.partials.menu-structure-editor', [
                'meNoun'      => 'product',
                'meNounTitle' => 'Product',
                'meSoldLabel' => 'Out of stock',
                'meSoldKey'   => 'is_out_of_stock',
                'meEmpty'     => 'No sections yet. Add one to start building your catalog.',
            ])
                </div>
            </template>
        </div>

        <!-- Settings -->
        {{-- The support bubble is fixed to the bottom-right, which is where
             this column ends. Without the gutter it sits on the last control
        </div>

        {{-- HOW IT LOOKS ------------------------------------------------ --}}
        <div x-show="pane === 'design'" x-cloak>
            <div class="rm-card">
                <h5>How the store looks</h5>
                {{-- The page background lives on the shared Appearance panel,
                     not here: it is the same picker every other page type uses,
                     and duplicating it per editor is how this app ended up with
                     six background pickers in the first place. --}}
                <a href="{{ route('user.links.settings.appearance', $link) }}"
                   class="no-underline"
                   style="display:flex;align-items:center;gap:10px;padding:11px 13px;margin:10px 0 14px;border-radius:12px;background:var(--bg-glass-input,rgba(127,127,127,.06));border:1px solid var(--border-glass,rgba(127,127,127,.18));color:inherit;">
                    <i class="fas fa-fill-drip text-[11px]" style="color:#7f9cff;"></i>
                    <span style="font-size:13px;font-weight:600;">Background &amp; fonts</span>
                    <span style="font-size:11px;opacity:.65;">Colour, gradient, 941 ready-made looks &mdash; and the page font</span>
                    <i class="fas fa-arrow-right text-[10px]" style="margin-left:auto;opacity:.5;"></i>
                </a>
                <div class="rm-row">
                    {{-- Drawn as a colour row, the same as the four below it.
                         It was a full-width 42px bar -- the same control, two
                         shapes, inside one card. --}}
                    <label class="rm-label">Accent color</label>
                    <div class="rm-colour">
                        <input type="color" x-model="menu.accent_color" @change="saveSettings()">
                        <span class="txt">
                            <b>Accent</b>
                            <small>Buttons, prices and highlights on the page.</small>
                        </span>
                    </div>
                </div>
                @include('user.links.partials.menu-hero-panel', ['hpBadgeLabel' => 'Order requests open'])
                <div class="rm-row">
                    {{-- Sana, 2026-09-23: "i cannot change colors of menu
                         items and all". He could not: the page read the
                         accent and hardcoded every other colour. Each of
                         these is optional, and clearing one puts that part
                         back to inheriting the page text colour. --}}
                    <label class="rm-label">Item colours</label>
                    <p style="font-size:11px;opacity:.6;margin:-2px 0 8px;">
                        Leave one blank to inherit the page text colour.
                    </p>
                    <template x-for="c in colourFields" :key="c.key">
                        <div class="rm-colour">
                            <input type="color"
                                   :value="menu[c.key] || '#888888'"
                                   @change="menu[c.key] = $event.target.value; saveSettings()">
                            <span class="txt">
                                <b x-text="c.label"></b>
                                <small x-text="c.hint"></small>
                            </span>
                            <button type="button" class="rm-act" title="Back to inheriting the page text colour"
                                    x-show="menu[c.key]"
                                    @click="menu[c.key] = ''; saveSettings()"><i class="fas fa-rotate-left"></i></button>
                        </div>
                    </template>
                </div>

                <div class="rm-row">
                    <label class="rm-label">Layout</label>
                    {{-- Five ways to draw the same items. The page had one
                         hardcoded column before this; the choice is saved on
                         the menu and read by the public template. --}}
                    <div class="rm-opts two">
                        @foreach(\App\Modules\User\Support\MenuPresentation::LAYOUTS as $lk => $lv)
                        <label class="rm-opt"
                               :class="{ 'on': menu.layout === '{{ $lk }}' }"
                               title="{{ $lv['hint'] }}">
                            <input type="radio" value="{{ $lk }}" x-model="menu.layout" @change="saveSettings()">
                            <span>{{ $lv['label'] }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="layoutHint"></p>
                </div>
                @include('user.links.partials.menu-section-nav-picker')
                @include('user.links.partials.menu-divider-picker')
                @include('user.links.partials.menu-card-design')
                <p class="text-xs" style="color:var(--text-faint)" x-text="savedMsg"></p>
            </div>
        </div>

        {{-- HOW ORDERING WORKS ------------------------------------------ --}}
        <div x-show="pane === 'ordering'" x-cloak>
            <div class="rm-card">
                <h5>How ordering works</h5>
                <div class="rm-row">
                    <label class="rm-label">Mode</label>
                    <div class="rm-mode-toggle">
                        <label><input type="radio" value="display" x-model="menu.mode" @change="saveSettings()"><span>Display only</span></label>
                        <label><input type="radio" value="order" x-model="menu.mode" @change="saveSettings()"><span>Order requests</span></label>
                    </div>
                    <p class="text-xs mt-2" style="color:var(--text-muted)">Order mode lets shoppers build a cart and send you an order request. No online payment, you arrange fulfilment and payment directly.</p>
                </div>
                <div class="rm-row" x-show="menu.mode === 'order'">
                    <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
                        <input type="checkbox" x-model="menu.accepting_orders" @change="saveSettings()"> Accepting order requests
                    </label>
                    <p class="text-xs mt-1" style="color:var(--text-muted)">Turn off to pause new requests without switching back to display only.</p>
                </div>
                <div class="rm-row">
                    {{-- Sana, 2026-09-28: "currency symbol, can u make it
                         dropdown?"

                         It was a three-character text box, so the only way
                         to learn whether a currency had a symbol on file was
                         to type it and watch the sample below. Each option
                         is labelled with the symbol it will print.

                         A creator already on a currency that is not listed
                         keeps it: "Other" drops back to the text box rather
                         than silently rewriting their setting. --}}
                    <label class="rm-label">Currency</label>
                    <select class="rm-input" x-show="!currencyIsOther" x-cloak
                            x-model="menu.currency" @change="onCurrencyPicked($event)">
                        @foreach(\App\Modules\User\Support\MenuMoney::options() as $c)
                        <option value="{{ $c['code'] }}">{{ $c['label'] }}</option>
                        @endforeach
                        <option value="__other">Other…</option>
                    </select>
                    <div x-show="currencyIsOther" x-cloak style="display:flex;gap:6px;align-items:center;">
                        <input class="rm-input" x-model="menu.currency" maxlength="3"
                               @change="saveSettings()" style="text-transform:uppercase" placeholder="e.g. GHS">
                        <button type="button" class="rm-act" title="Back to the list"
                                @click="currencyOther = false; if (!currencyKnown) { menu.currency = 'USD'; } saveSettings()"><i class="fas fa-list"></i></button>
                    </div>
                </div>
                @include('user.links.partials.menu-money-picker')
                @include('user.links.partials.menu-billing-company')
                @include('user.links.partials.menu-fulfilment-panel', ['fpIsRestaurant' => false])
                <div class="rm-row" x-show="menu.mode === 'order'">
                    <label class="rm-label">WhatsApp number (optional)</label>
                    <div @phone-changed="menu.whatsapp_number = $event.detail; saveSettings()">
                        @include('common.partials.phone-input', [
                            'phoneInputName' => 'whatsapp_number',
                            'phoneInputValue' => $menu->settings['whatsapp_number'] ?? '',
                            'phoneInputId' => 'menu-whatsapp',
                            'phoneInputSize' => 'sm',
                            'phoneInputAutoFormat' => true,
                        ])
                    </div>
                    <p class="text-xs mt-2" style="color:var(--text-muted)">Add your number with country code to let shoppers send their request to your WhatsApp. Requests still appear on your dashboard either way.</p>
                </div>
                <p class="text-xs" style="color:var(--text-faint)" x-text="savedMsg"></p>
            </div>


            @include('user.links.partials.menu-confirmation-panel', ['confirmHeadlinePlaceholder' => 'Request sent 🎉'])

            @include('user.links.partials.menu-tokens-panel', ['tkNoun' => 'request'])

            @include('user.links.partials.menu-timing-panel', ['tmNoun' => 'request'])

            @include('user.links.partials.menu-choices-panel', [
                'choiceBase'  => rtrim(url('/user/links/'.$link->id.'/store/option-groups'), '/'),
                'choiceItems' => $menu->products->map(fn ($i) => ['id' => (int) $i->id, 'name' => $i->name])->values(),
                'choiceNoun'  => 'product',
                'choiceNounPlural' => 'products',
            ])
        </div>

        </div>

        {{-- The page itself, beside whichever pane is open. --}}
        <div class="lg:col-span-5 hidden lg:block lg:self-stretch lg:h-full">
            @include('user.links.partials.device-preview', ['link' => $link])
        </div>
    </div>

    <!-- Category modal -->
    @include('user.links.partials.menu-section-modal', ['mmNoun' => 'store'])

    <!-- Product modal -->
    <div class="rm-modal-bg" x-show="productModal.open" x-cloak @click.self="productModal.open=false">
        <div class="rm-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px" x-text="productModal.id ? 'Edit product' : 'New product'"></h5>
            <div class="rm-row"><label class="rm-label">Name</label><input class="rm-input" x-model="productModal.name"></div>
            <div class="rm-row"><label class="rm-label">Description</label><textarea class="rm-textarea" x-model="productModal.description"></textarea></div>
            <div class="rm-row"><label class="rm-label">Price</label><input class="rm-input" type="number" step="0.01" min="0" x-model="productModal.price"></div>
            <div class="rm-row">
                <label class="rm-label">Photo</label>
                {{-- Mode tabs --}}
                <div style="display:flex;gap:6px;margin-bottom:8px">
                    <button type="button" class="rm-btn sm ghost" :class="photoMode==='url'?'active':''" @click="photoMode='url'"><i class="fas fa-link"></i> URL</button>
                    <button type="button" class="rm-btn sm ghost" :class="photoMode==='upload'?'active':''" @click="photoMode='upload'"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
                    <button type="button" class="rm-btn sm ghost" :class="photoMode==='vault'?'active':''" @click="photoMode='vault'; if(!vaultFiles.length) loadVault()"><i class="fas fa-folder-open"></i> My Files</button>
                </div>
                {{-- URL mode --}}
                <div x-show="photoMode==='url'" style="display:none;">
                    <input class="rm-input" x-model="productModal.photo_url" placeholder="https://…">
                </div>
                {{-- Upload mode --}}
                <div x-show="photoMode==='upload'" style="display:none;">
                    <div style="display:flex;gap:8px;align-items:center">
                        <button type="button" class="rm-btn sm ghost" @click="$refs.photoInput.click()" :disabled="photoUploading">
                            <i class="fas" :class="photoUploading ? 'fa-spinner fa-spin' : 'fa-cloud-upload-alt'"></i>
                            <span x-text="photoUploading ? ('Uploading… ' + photoProgress + '%') : 'Upload photo'"></span>
                        </button>
                        <input type="file" x-ref="photoInput" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="hidden" @change="uploadPhoto($event)">
                    </div>
                </div>
                {{-- My Files mode --}}
                <div x-show="photoMode==='vault'" style="display:none;border:1px solid var(--border-glass,#2a2a32);border-radius:10px;overflow:hidden">
                    <div style="padding:6px;border-bottom:1px solid var(--border-glass,#2a2a32);display:flex;gap:6px">
                        <input type="text" x-model="vaultSearch" placeholder="Search…" style="flex:1;font-size:11px;padding:4px 8px;border-radius:6px;background:var(--bg-glass-input,rgba(0,0,0,0.2));color:var(--text-primary,#fff);border:1px solid var(--border-glass,#2a2a32);outline:none">
                        <button type="button" @click="loadVault()" style="font-size:11px;color:var(--accent)"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div style="max-height:160px;overflow-y:auto;padding:6px">
                        <template x-if="vaultLoading"><div style="padding:20px;text-align:center"><i class="fas fa-spinner fa-spin" style="color:#60a5fa"></i></div></template>
                        <template x-if="!vaultLoading && vaultFiles.length===0"><div style="padding:20px;text-align:center;font-size:11px;color:var(--text-muted,#9ca3af)">No images yet, upload some to My Files first</div></template>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px">
                            <template x-for="f in filteredVault" :key="f.id">
                                <button type="button" @click="productModal.photo_url=f.url; photoMode='url'"
                                        style="border-radius:6px;overflow:hidden;aspect-ratio:1;cursor:pointer;padding:0;border:2px solid transparent;background:var(--bg-glass-input,rgba(0,0,0,0.2))"
                                        :style="productModal.photo_url===f.url?'border-color:#3b82f6':''">
                                    <img :src="f.url" :alt="f.original_name" style="width:100%;height:100%;object-fit:cover">
                                </button>
                            </template>
                        </div>
                        <template x-if="!vaultLoading && vaultHasMore">
                            <button type="button" @click="loadMoreVault()" style="width:100%;font-size:10px;color:#60a5fa;padding:6px;text-align:center">Load more…</button>
                        </template>
                    </div>
                </div>
                <p class="text-xs" style="color:var(--accent-danger,#f87171)" x-show="photoError" x-text="photoError"></p>
                <template x-if="productModal.photo_url && photoMode!=='vault'">
                    <div style="margin-top:8px;display:flex;align-items:center;gap:6px">
                        <img :src="productModal.photo_url" alt="Preview" style="max-height:80px;max-width:80px;border-radius:8px;object-fit:contain" x-on:error="$el.style.display='none'" x-on:load="$el.style.display='block'">
                        <button type="button" class="rm-btn sm ghost" @click="productModal.photo_url=''">Remove</button>
                    </div>
                </template>
            </div>
            <div class="rm-row"><label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)"><input type="checkbox" x-model="productModal.is_out_of_stock"> Out of stock</label></div>
            @include('user.links.partials.menu-marks-picker', [
                'mkModal' => 'productModal',
                'mkNoun'  => 'product',
            ])
            @include('user.links.partials.menu-quantity-picker', [
                'qpModal' => 'productModal',
                'qpNoun'  => 'product',
            ])

            {{-- The way to choices from here.

                 Choices shipped reachable only from a card down in the
                 settings column, and Sana looked for them on the item --
                 the eighth working feature this month with no way in from
                 where somebody would go looking. So the dialog says what
                 this product already asks and hands over to the panel,
                 which stays the one place that knows anything about it. --}}
            <div class="rm-row" x-show="productModal.id">
                <label class="rm-label">Choices</label>
                <p class="text-xs" style="color:var(--text-muted)" x-text="(window.menuChoicesOn || (() => 'None yet'))(productModal.id)"></p>
                <button class="rm-btn sm ghost mt-2" type="button"
                        @click="productModal.open = false; $dispatch('menu-choices-open', { itemId: productModal.id })">
                    <i class="fas fa-sliders-h"></i> Spice level, size, toppings…
                </button>
            </div>

            <div class="flex justify-end gap-2">
                <button class="rm-btn ghost" @click="productModal.open=false">Cancel</button>
                <button class="rm-btn" @click="saveProduct()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
@php
    // Price placement falls back differently per layout (Compact keeps
    // its leader dots), so the layout is resolved once and both read it.
    $menuLayoutKey = \App\Modules\User\Support\MenuPresentation::layout($menu->settings['layout'] ?? null);
    // The resolved price format, so the editor opens on what the page
    // actually prints rather than on a guess at the defaults.
    $menuMoney = \App\Modules\User\Support\MenuMoney::resolve($menu->currency, (array) ($menu->settings ?? []));
    // What this menu offers as a handover, and what each handover adds.
    $menuModes   = \App\Modules\User\Support\MenuFulfilment::modesFor((array) ($menu->settings ?? []), false);
    $menuCharges = \App\Modules\User\Support\MenuFulfilment::charges((array) ($menu->settings ?? []));
    $menuCategories = $menu->categories->map(fn($c)=>['id'=>$c->id,'parent_id'=>$c->parent_id,'name'=>$c->name,'description'=>$c->description,'hide_heading'=>(bool) $c->hide_heading,'hide_description'=>(bool) $c->hide_description,'is_active'=>(bool) $c->is_active,'sort_order'=>(int) $c->sort_order])->values();
    $menuProducts = $menu->products->map(fn($p)=>['id'=>$p->id,'category_id'=>$p->category_id,'name'=>$p->name,'description'=>$p->description,'price'=>$p->price,'photo_url'=>$p->photo_url,'is_out_of_stock'=>$p->is_out_of_stock,'marks'=>\App\Modules\User\Support\MenuItemMarks::sanitize($p->marks),'min_quantity'=>(int) ($p->min_quantity ?? 1),'max_quantity'=>$p->max_quantity,'bulk_price'=>$p->bulk_price,'coupon_from'=>$p->coupon_from,'is_active'=>(bool) $p->is_active,'sort_order'=>(int) $p->sort_order])->values();
    // The colours the owner has chosen, read back for the editor.
    //
    // Sana, 2026-10-04: "menu items color changed, updated live but not shown
    // changed value in settings" and "even default always grey". They saved,
    // and the PAGE read them -- which is why the preview changed -- but this
    // blob never carried them, so every reload handed the pickers '' and the
    // `|| '#888888'` fallback painted all five grey. Keyed off
    // MenuPresentation::COLOURS rather than written out, so a sixth colour
    // cannot be added to the panel and missed here again.
    $menuColours = [];
    foreach (array_keys(\App\Modules\User\Support\MenuPresentation::COLOURS) as $ck) {
        $menuColours[$ck] = \App\Modules\User\Support\MenuPresentation::hex($menu->settings[$ck] ?? null);
    }
    $menuColours += \App\Modules\User\Support\MenuSectionNav::colours((array) ($menu->settings ?? []));
    $menuColours['item_search_enabled'] = (bool) ($menu->settings['item_search_enabled'] ?? false);
    $menuColours['section_nav'] = \App\Modules\User\Support\MenuSectionNav::nav($menu->settings['section_nav'] ?? null);
    $menuColours['section_marker'] = \App\Modules\User\Support\MenuSectionNav::marker($menu->settings['section_marker'] ?? null);
    $menuHero = \App\Modules\User\Support\MenuHero::resolve((array) ($menu->settings ?? []));
    $menuState = $menuColours + $menuHero + [
        'mode' => $menu->mode,
        'currency' => $menu->currency,
        'accent_color' => $menu->accent_color,
        'whatsapp_number' => $menu->settings['whatsapp_number'] ?? '',
        'accepting_orders' => (bool) ($menu->settings['accepting_orders'] ?? true),
        'layout' => $menuLayoutKey,
        'divider' => \App\Modules\User\Support\MenuPresentation::divider($menu->settings['divider'] ?? null),
        'fulfilment_modes' => $menuModes, 'charges' => $menuCharges, 'price_display' => $menuMoney['display'], 'price_position' => $menuMoney['position'], 'price_decimals' => $menuMoney['decimals'] > 0,
        'heading_style' => \App\Modules\User\Support\MenuPresentation::heading($menu->settings['heading_style'] ?? null),
        'price_style' => \App\Modules\User\Support\MenuPresentation::price($menu->settings['price_style'] ?? null, $menuLayoutKey),
    ];
    $menuConfirm = \App\Modules\User\Support\MenuConfirmation::resolve((array) ($menu->settings ?? []));
    // The editor holds the mode as SAVED, not as resolved: someone who picks
    // "send them to my page" and saves before typing the URL should find
    // that choice still selected, rather than silently back on the default.
    $menuConfirm['mode'] = \App\Modules\User\Support\MenuConfirmation::mode(
        $menu->settings['confirmation']['mode'] ?? null
    );
    $storeBase = rtrim(url('/user/links/'.$link->id.'/store'), '/');
@endphp
function storeEditor() {
    return {
        billingCompanyId: @js($menu->settings['billing_company_id'] ?? ''),
        menu: @json($menuState),
        confirm: @json($menuConfirm),
        categories: @json($menuCategories),
        products: @json($menuProducts),
        tokens: @js(\App\Modules\User\Support\MenuOrderToken::resolve((array) ($menu->settings ?? []))),
        timing: @js(\App\Modules\User\Support\MenuHandoverTiming::resolve((array) ($menu->settings ?? []))),
        savedMsg: '',
        // The chosen layout's one-line description, so the panel explains
        // itself instead of making the creator click five radios to find out.
        // The optional item colours, with their labels, straight from the
        // one place that defines them. A field the creator has not set is
        // an empty string, which the save path reads as "clear it".
        colourFields: @json($menuColourFields),
        layoutHints: @json(collect(\App\Modules\User\Support\MenuPresentation::LAYOUTS)->map(fn ($l) => $l['hint'])),
        sectionNavHints: @json(collect(\App\Modules\User\Support\MenuSectionNav::NAVS)->map(fn ($n) => $n['hint'])),
        sectionMarkerHints: @json(collect(\App\Modules\User\Support\MenuSectionNav::MARKERS)->map(fn ($m) => $m['hint'])),
        get layoutHint(){ return this.layoutHints[this.menu.layout] || ''; },
        get sectionNavHint(){ return this.sectionNavHints[this.menu.section_nav || 'tabs'] || ''; },
        get sectionMarkerHint(){ return this.sectionMarkerHints[this.menu.section_marker || 'number'] || ''; },
        dividerHints: @json(collect(\App\Modules\User\Support\MenuPresentation::DIVIDERS)->map(fn ($d) => $d['hint'])),
        // ---- Handover and charges ---------------------------------------
        addCharge(){
            if (!Array.isArray(this.menu.charges)) this.menu.charges = [];
            if (this.menu.charges.length >= 8) return;
            this.menu.charges.push({ label:'', type:'fixed', amount:'', modes:[] });
        },
        removeCharge(i){ this.menu.charges.splice(i, 1); this.saveSettings(); },
        // ---- Price format ----------------------------------------------
        // Mirrors MenuMoney on the server so the sample below the controls
        // is the real thing rather than an approximation of it. The tables
        // come straight from that class, so a currency added there shows up
        // here without a second edit.
        moneySymbols: @json(\App\Modules\User\Support\MenuMoney::SYMBOLS),
        // The picker lists the currencies we carry a symbol for. A menu
        // already set to one we don't carry opens on the text box rather
        // than being quietly moved to USD.
        currencyOther: false,
        get currencyKnown(){ return Object.prototype.hasOwnProperty.call(this.moneySymbols, this.currencyCode); },
        get currencyIsOther(){ return this.currencyOther || !this.currencyKnown; },
        onCurrencyPicked(e){
            if (e.target.value === '__other') {
                this.currencyOther = true;
                // Leave the code alone so the box opens on what they had.
                this.menu.currency = this.currencyCode;
                return;
            }
            this.currencyOther = false;
            this.menu.currency = e.target.value;
            this.saveSettings();
        },
        moneyZeroDecimal: @json(\App\Modules\User\Support\MenuMoney::ZERO_DECIMAL),
        get currencyCode(){ return (this.menu.currency || 'USD').toUpperCase(); },
        get currencyHasSymbol(){ return !!this.moneySymbols[this.currencyCode]; },
        get zeroDecimalCurrency(){ return this.moneyZeroDecimal.includes(this.currencyCode); },
        // One price format for this screen. The sample under the controls
        // and every price in the list above go through it, because the list
        // used to print `currency + ' ' + toFixed(2)` on its own and so
        // ignored all three of these controls: pick "Rs." and the page said
        // Rs.120 while the row you picked it on still said INR 120.00.
        money(amount){
            const code = this.currencyCode;
            let token = this.menu.price_display === 'symbol'
                ? (this.moneySymbols[code] || code)
                : (this.menu.price_display === 'none' ? '' : code);
            token = token.trim();
            const decimals = (this.zeroDecimalCurrency || this.menu.price_decimals === false) ? 0 : 2;
            const n = (+amount || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            if (token === '') return n;
            // A token ending in a letter needs a gap or it runs into the
            // digits; one ending in punctuation or a glyph does not.
            const gap = /[A-Za-z]$/.test(token) ? ' ' : '';
            return this.menu.price_position === 'after' ? (n + ' ' + token) : (token + gap + n);
        },
        get priceSample(){ return this.money(1234.5); },
        get dividerHint(){ return this.dividerHints[this.menu.divider] || ''; },
        headingHints: @json(collect(\App\Modules\User\Support\MenuPresentation::HEADINGS)->map(fn ($h) => $h['hint'])),
        get headingHint(){ return this.headingHints[this.menu.heading_style] || ''; },
        priceHints: @json(collect(\App\Modules\User\Support\MenuPresentation::PRICES)->map(fn ($x) => $x['hint'])),
        get priceHint(){ return this.priceHints[this.menu.price_style] || ''; },
        catModal: { open:false, id:null, parent_id:null, name:'', description:'', hide_heading:false, hide_description:false },
        productModal: { open:false, id:null, category_id:null, name:'', description:'', price:'', photo_url:'', is_out_of_stock:false, marks:[], min_quantity:'', max_quantity:'', bulk_price:'',coupon_from:'' },
        base: @json($storeBase),
        uploadUrl: @json(route('user.files.upload')),
        csrf: @json(csrf_token()),
        photoUploading: false,
        photoProgress: 0,
        photoError: '',
        photoMode: 'url',
        vaultFiles: [], vaultLoading: false, vaultSearch: '', vaultPage: 1, vaultHasMore: false,
        init(){},
        uploadPhoto(e){
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file) return;
            this.photoUploading = true; this.photoProgress = 0; this.photoError = '';
            const fd = new FormData(); fd.append('file', file);
            const xhr = new XMLHttpRequest(); const self = this;
            xhr.upload.addEventListener('progress', function(ev){ if (ev.lengthComputable) self.photoProgress = Math.round((ev.loaded/ev.total)*100); });
            xhr.addEventListener('load', function(){
                self.photoUploading = false;
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && data.success && data.file) { self.productModal.photo_url = data.file.url; }
                    else { self.photoError = (data.error && data.error.message) || (typeof data.error === 'string' ? data.error : '') || data.message || ('Upload failed (' + xhr.status + ')'); }
                } catch (err) { self.photoError = 'Upload failed (' + xhr.status + ')'; }
            });
            xhr.addEventListener('error', function(){ self.photoUploading = false; self.photoError = 'Network error'; });
            xhr.open('POST', this.uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(fd);
        },
        productsFor(catId){ return this.products.filter(p => p.category_id === catId); },
        async api(method, path, body){
            const r = await fetch(this.base + path, {
                method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'},
                body: body ? JSON.stringify(body) : undefined
            });
            const j = await r.json().catch(()=>({}));
            if (!r.ok) { alert((j.error && j.error.message) || (j.message) || 'Request failed'); throw new Error('fail'); }
            return j.data;
        },
        /** Dine-in only? Then there is nothing to ask a time about. */
        timingApplies(){
            const modes = this.menu.fulfilment_modes || [];
            return modes.some(mode => ['dine_in', 'takeaway', 'delivery'].includes(mode));
        },

        /** The first time a customer could actually pick, right now. */
        timingPreview(){
            const step = Math.max(5, +this.timing.interval || 30);
            const at = new Date(Date.now() + (Math.max(0, +this.timing.prep_minutes || 0) * 60000));
            at.setSeconds(0, 0);
            const over = at.getMinutes() % step;
            if (over) { at.setMinutes(at.getMinutes() + (step - over)); }
            const t = at.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            return 'Right now, the earliest a customer could choose is ' + t + '.';
        },

        /** When the next run of numbers starts, in the owner's own day. */
        tokenHint(){
            const tz = window.MENU_TOKEN_TZ || undefined;
            const fmt = (d) => d.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', timeZone: tz });
            const next = new Date();
            if (this.tokens.reset === 'never') { return 'Numbers keep counting up for as long as this menu exists.'; }
            if (this.tokens.reset === 'day') {
                next.setDate(next.getDate() + 1);
                return 'Back to 1 at midnight, so ' + fmt(next) + ' starts again from 1.';
            }
            if (this.tokens.reset === 'week') {
                // ISO weeks, so a run always turns over on a Monday.
                const ahead = (8 - (next.getDay() || 7)) % 7 || 7;
                next.setDate(next.getDate() + ahead);
                return 'Back to 1 every Monday, so ' + fmt(next) + ' starts again from 1.';
            }
            next.setMonth(next.getMonth() + 1, 1);
            return 'Back to 1 on the 1st, so ' + fmt(next) + ' starts again from 1.';
        },

        async saveSettings(){
            await this.api('POST','/settings',{
                billing_company_id:this.billingCompanyId === '' ? null : Number(this.billingCompanyId),
                mode:this.menu.mode,
                currency:(this.menu.currency||'USD').toUpperCase(),
                accent_color:this.menu.accent_color,
                whatsapp_number:this.menu.whatsapp_number||'',
                timing_enabled:!!this.timing.enabled,
                timing_interval:+this.timing.interval||30,
                timing_open:this.timing.open||'10:00',
                timing_close:this.timing.close||'22:00',
                timing_prep:+this.timing.prep_minutes||0,
                tokens_enabled:!!this.tokens.enabled,
                tokens_reset:this.tokens.reset||'day',
                confirm_mode:this.confirm.mode||'bill',
                confirm_url:this.confirm.url||'',
                confirm_message:this.confirm.message||'',
                confirm_headline:this.confirm.headline||'',
                accepting_orders:!!this.menu.accepting_orders,
                item_search_enabled:!!this.menu.item_search_enabled,
                section_nav:this.menu.section_nav||'tabs',
                section_marker:this.menu.section_marker||'number',
                section_nav_text_color:this.menu.section_nav_text_color||'',
                section_nav_background_color:this.menu.section_nav_background_color||'',
                section_nav_border_color:this.menu.section_nav_border_color||'',
                layout:this.menu.layout||'list',
                divider:this.menu.divider||'line',
                heading_style:this.menu.heading_style||'plain',
                price_style:this.menu.price_style||'inline',
                price_display:this.menu.price_display||'code',
                price_position:this.menu.price_position||'before',
                price_decimals:this.menu.price_decimals !== false,
                fulfilment_modes:this.menu.fulfilment_modes || [],
                charges:this.menu.charges || [],
                heading_color:this.menu.heading_color||'',
                item_color:this.menu.item_color||'',
                desc_color:this.menu.desc_color||'',
                price_color:this.menu.price_color||'',
                divider_color:this.menu.divider_color||'',
                // The hero's own settings, sent on every save alongside the
                // item colours they sit above.
                hero_title_hidden:!!this.menu.hero_title_hidden,
                hero_badge_hidden:!!this.menu.hero_badge_hidden,
                hero_align:this.menu.hero_align||'left',
                hero_size:this.menu.hero_size||'medium',
                hero_title_color:this.menu.hero_title_color||'',
                hero_badge_color:this.menu.hero_badge_color||'',
            });
            this.savedMsg = 'Saved ✓'; setTimeout(()=>this.savedMsg='', 1500);
        },
        // ---- Structure -------------------------------------------------
        // Sections in order, each followed by its own sub-sections. Flat
        // rather than nested so the row markup exists once; `depth` is what
        // the template indents on. Mirrors MenuTree on the server, which is
        // what the public page and this screen must agree about.
        sections(){ return this.categories.filter(c => !c.parent_id).sort((a,b)=>(a.sort_order||0)-(b.sort_order||0)); },
        // Only what the save path will actually accept as a parent.
        parentChoices(){
            const self = this.catModal.id;
            if (self && this.subsFor(self).length > 0) return [];
            return this.sections().filter(c => c.id !== self);
        },
        subsFor(id){ return this.categories.filter(c => c.parent_id === id).sort((a,b)=>(a.sort_order||0)-(b.sort_order||0)); },
        groups(){
            const out = [];
            for (const cat of this.sections()) {
                const hidden = cat.is_active === false;
                out.push({ cat, depth:0, hidden, parentHidden:false });
                for (const sub of this.subsFor(cat.id)) {
                    out.push({ cat: sub, depth:1, hidden: hidden || sub.is_active === false, parentHidden: hidden });
                }
            }
            return out;
        },
        // A product in a hidden section is hidden whatever its own flag says.
        rowHidden(row, g){ return g.hidden || row.is_active === false; },
        rowsFor(catId){ return this.productsFor(catId); },
        openRow(catId, row){ return this.openProduct(catId, row); },
        deleteRow(row){ return this.deleteProduct(row); },
        moveRow(catId, row, dir){ return this.moveProduct(catId, row, dir); },
        async toggleCategory(cat){
            const next = !(cat.is_active !== false);
            const d = await this.api('PUT','/categories/'+cat.id, { is_active: next });
            const i = this.categories.findIndex(c=>c.id===cat.id); this.categories[i] = d.category;
        },
        async toggleRow(row){
            const next = !(row.is_active !== false);
            const d = await this.api('PUT','/products/'+row.id, { is_active: next });
            const i = this.products.findIndex(x=>x.id===row.id); this.products[i] = d.product;
        },
        openCategory(cat, parentId){
            this.catModal = cat
                ? {open:true,id:cat.id,parent_id:cat.parent_id||null,name:cat.name,description:cat.description||'',hide_heading:!!cat.hide_heading,hide_description:!!cat.hide_description}
                : {open:true,id:null,parent_id:parentId||null,name:'',description:'',hide_heading:false,hide_description:false};
        },
        async saveCategory(){
            if (!this.catModal.name.trim()) return;
            const payload = { name:this.catModal.name, description:this.catModal.description, hide_heading:!!this.catModal.hide_heading, hide_description:!!this.catModal.hide_description, parent_id:this.catModal.parent_id||null };
            try {
                if (this.catModal.id) { const d = await this.api('PUT','/categories/'+this.catModal.id, payload); const i=this.categories.findIndex(c=>c.id===this.catModal.id); this.categories[i]=d.category; }
                else { const d = await this.api('POST','/categories', payload); this.categories.push(d.category); }
            } catch (e) {
                // The server refuses a move that would nest three deep or
                // strand a sub-section, and api() has already shown the
                // reason it gave. Leave the dialog open on what they typed
                // rather than closing it over a change that did not happen.
                return;
            }
            this.catModal.open = false;
        },
        async deleteCategory(cat){
            const subs = this.subsFor(cat.id);
            const what = subs.length
                ? '"'+cat.name+'", its '+subs.length+' sub-section(s) and everything in them?'
                : '"'+cat.name+'" and its products?';
            if(!confirm('Delete '+what)) return;
            await this.api('DELETE','/categories/'+cat.id);
            const gone = [cat.id].concat(subs.map(s=>s.id));
            this.categories = this.categories.filter(c=>!gone.includes(c.id));
            this.products = this.products.filter(p=>!gone.includes(p.category_id));
        },
        async moveCategory(cat, dir){
            // Among its OWN siblings. A sub-section moving past the end of
            // its section would otherwise land between two unrelated ones.
            const arr = cat.parent_id ? this.subsFor(cat.parent_id) : this.sections();
            const idx = arr.findIndex(c=>c.id===cat.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=arr.length) return;
            arr.splice(to, 0, arr.splice(idx, 1)[0]);
            arr.forEach((c,i)=>{ const j=this.categories.findIndex(x=>x.id===c.id); this.categories[j].sort_order = i; });
            await this.api('POST','/categories/reorder', { order: arr.map(c=>c.id) });
        },
        async moveProduct(catId, product, dir){
            const list = this.productsFor(catId);
            const idx = list.findIndex(p=>p.id===product.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=list.length) return;
            list.splice(to, 0, list.splice(idx, 1)[0]);
            const ids = list.map(p=>p.id);
            const others = this.products.filter(p=>p.category_id!==catId);
            this.products = others.concat(list);
            await this.api('POST','/products/reorder', { order: ids });
        },
        async loadVault() {
            this.vaultLoading = true; this.vaultPage = 1;
            try {
                const r = await fetch(@json(route('user.files.index')) + '?type=image&page=1', { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.vaultFiles = d.files || [];
                this.vaultHasMore = d.pagination && d.pagination.current_page < d.pagination.last_page;
            } catch(e) { this.vaultFiles = []; }
            this.vaultLoading = false;
        },
        async loadMoreVault() {
            this.vaultPage++;
            try {
                const r = await fetch(@json(route('user.files.index')) + '?type=image&page=' + this.vaultPage, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.vaultFiles = this.vaultFiles.concat(d.files || []);
                this.vaultHasMore = d.pagination && d.pagination.current_page < d.pagination.last_page;
            } catch(e) {}
        },
        get filteredVault() {
            if (!this.vaultSearch) return this.vaultFiles;
            const s = this.vaultSearch.toLowerCase();
            return this.vaultFiles.filter(f => f.original_name.toLowerCase().includes(s));
        },
        openProduct(catId, product){
            // '' rather than 1 in the minimum box: see the restaurant's copy.
            this.productModal = product ? {open:true,id:product.id,category_id:catId,name:product.name,description:product.description||'',price:product.price,photo_url:product.photo_url||'',is_out_of_stock:!!product.is_out_of_stock,marks:(product.marks||[]),min_quantity:(product.min_quantity > 1 ? product.min_quantity : ''),max_quantity:(product.max_quantity ?? ''),bulk_price:(product.bulk_price ?? ''),coupon_from:(product.coupon_from ?? '')} : {open:true,id:null,category_id:catId,name:'',description:'',price:'',photo_url:'',is_out_of_stock:false,marks:[],min_quantity:'',max_quantity:'',bulk_price:'',coupon_from:''};
            this.photoMode = 'url'; this.photoError = '';
        },
        async saveProduct(){
            if (!this.productModal.name.trim()) return;
            const payload = { category_id:this.productModal.category_id, name:this.productModal.name, description:this.productModal.description, price:parseFloat(this.productModal.price||0), photo_url:this.productModal.photo_url||null, is_out_of_stock:this.productModal.is_out_of_stock, marks:(this.productModal.marks||[]),
                // Blank goes as null, not 0: see the restaurant's copy.
                min_quantity:menuQuantity.num(this.productModal.min_quantity), max_quantity:menuQuantity.num(this.productModal.max_quantity), bulk_price:this.productModal.bulk_price === '' ? null : Number(this.productModal.bulk_price), coupon_from:menuQuantity.num(this.productModal.coupon_from) };
            if (this.productModal.id) { const d = await this.api('PUT','/products/'+this.productModal.id, payload); const i=this.products.findIndex(x=>x.id===this.productModal.id); this.products[i]=d.product; }
            else { const d = await this.api('POST','/products', payload); this.products.push(d.product); }
            this.productModal.open = false;
        },
        async deleteProduct(product){ if(!confirm('Delete "'+product.name+'"?')) return; await this.api('DELETE','/products/'+product.id); this.products=this.products.filter(p=>p.id!==product.id); },
    };
}
</script>
@endsection
