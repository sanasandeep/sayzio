<form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
      x-data="featuresEditor({{ json_encode(array_values($categories)) }})"
      class="glass rounded-2xl p-6 space-y-5">
    @csrf
    @method('PUT')
    <div>
        <h2 class="text-lg font-semibold text-white">{{ $page->title }} <span class="text-xs text-white/40 ml-2">/{{ $page->slug }}</span></h2>
        <p class="text-xs text-white/50 mt-1">Edit every category and feature row shown on the public Features page. Categories stay in the order listed below — nothing is collapsed or merged automatically.</p>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Page title</label>
        <input type="text" name="title" required value="{{ old('title', $page->title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        @error('title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Meta description</label>
        <textarea name="meta_description" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('meta_description', $page->meta_description) }}</textarea>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Feature categories</label>
            <button type="button" @click="addCategory()" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add category
            </button>
        </div>

        <template x-for="(cat, ci) in categories" :key="ci">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Category <span x-text="ci+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveCategory(ci, -1)" :disabled="ci===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveCategory(ci, 1)" :disabled="ci===categories.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="removeCategory(ci)" class="text-xs text-red-400 hover:text-red-300 px-2 py-1" title="Delete category"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Anchor id</label>
                        <input type="text" :name="'categories['+ci+'][id]'" x-model="cat.id" placeholder="biolink"
                               pattern="[a-z0-9\-]*"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">FontAwesome icon</label>
                        <div class="flex gap-1">
                            <input type="text" :name="'categories['+ci+'][icon]'" x-model="cat.icon" placeholder="fa-square-share-nodes"
                                   class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <button type="button" @click="openPicker(ci)"
                                    class="shrink-0 px-2.5 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white/70 hover:text-white text-sm"
                                    title="Pick from gallery">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-1 flex items-end">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20 border border-violet-400/30 flex items-center justify-center">
                            <i :class="'fas ' + (cat.icon || 'fa-circle') + ' text-violet-300 text-lg'"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Heading</label>
                    <input type="text" :name="'categories['+ci+'][heading]'" x-model="cat.heading" placeholder="Category heading"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Intro</label>
                    <textarea :name="'categories['+ci+'][intro]'" x-model="cat.intro" rows="2" placeholder="One-paragraph intro shown below the heading."
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                </div>

                <div class="border-t border-white/10 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[10px] uppercase tracking-wider text-white/40">Features in this category</label>
                        <button type="button" @click="addFeature(ci)" class="text-[11px] px-2 py-1 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white">
                            <i class="fas fa-plus mr-1"></i> Add feature
                        </button>
                    </div>
                    <template x-for="(feat, fi) in cat.features" :key="fi">
                        <div class="bg-black/20 border border-white/5 rounded-lg p-3 mb-2 space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <input type="text" :name="'categories['+ci+'][features]['+fi+'][name]'" x-model="feat.name" placeholder="Feature name"
                                           class="md:col-span-1 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white">
                                    <textarea :name="'categories['+ci+'][features]['+fi+'][description]'" x-model="feat.description" rows="2" placeholder="Short description"
                                              class="md:col-span-2 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white"></textarea>
                                </div>
                                <div class="flex flex-col gap-1 shrink-0">
                                    <button type="button" @click="moveFeature(ci, fi, -1)" :disabled="fi===0" class="text-[11px] text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                    <button type="button" @click="moveFeature(ci, fi, 1)" :disabled="fi===cat.features.length-1" class="text-[11px] text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                    <button type="button" @click="removeFeature(ci, fi)" class="text-[11px] text-red-400 hover:text-red-300 px-1.5" title="Delete feature"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="cat.features.length===0" class="text-[11px] text-white/40 text-center py-2">No features in this category yet.</div>
                </div>
            </div>
        </template>

        <div x-show="categories.length===0" class="text-xs text-white/40 text-center py-4">No categories yet — click "Add category".</div>
    </div>

    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
        <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-violet-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
        <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save changes</button>
    </div>

    <div x-show="picker.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
         @keydown.escape.window="closePicker()"
         @click.self="closePicker()">
        <div class="w-full max-w-2xl max-h-[80vh] flex flex-col bg-zinc-900 border border-white/10 rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between p-4 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">Pick an icon</h3>
                <button type="button" @click="closePicker()" class="text-white/60 hover:text-white text-sm" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 border-b border-white/10">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-white/40 text-xs"></i>
                    <input type="text" x-model="picker.query" placeholder="Search icons (e.g. share, link, chart)"
                           class="w-full pl-8 pr-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"
                           x-ref="pickerSearch">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                    <template x-for="ic in filteredIcons()" :key="ic">
                        <button type="button" @click="selectIcon(ic)"
                                :class="'group flex items-center justify-center aspect-square rounded-lg border text-white/80 hover:text-white hover:bg-violet-500/20 transition ' + (picker.ci !== null && categories[picker.ci] && categories[picker.ci].icon === ic ? 'bg-violet-500/30 border-violet-400/50' : 'bg-white/5 border-white/10')"
                                :title="ic">
                            <i :class="'fas ' + ic + ' text-base'"></i>
                        </button>
                    </template>
                </div>
                <div x-show="filteredIcons().length === 0" class="text-xs text-white/40 text-center py-6">No icons match "<span x-text="picker.query"></span>".</div>
            </div>
            <div class="p-3 border-t border-white/10 text-[11px] text-white/40 text-center">
                You can also type a FontAwesome class name directly in the icon field.
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const FA_ICON_GALLERY = [
        { name: 'fa-link', keywords: 'link url chain' },
        { name: 'fa-square-share-nodes', keywords: 'share social network' },
        { name: 'fa-share-nodes', keywords: 'share social network' },
        { name: 'fa-share', keywords: 'share arrow' },
        { name: 'fa-paper-plane', keywords: 'send share message' },
        { name: 'fa-globe', keywords: 'world web internet' },
        { name: 'fa-house', keywords: 'home main' },
        { name: 'fa-user', keywords: 'profile account person' },
        { name: 'fa-users', keywords: 'people team audience' },
        { name: 'fa-user-group', keywords: 'people team audience' },
        { name: 'fa-id-card', keywords: 'profile card identity' },
        { name: 'fa-address-card', keywords: 'profile contact card' },
        { name: 'fa-envelope', keywords: 'mail email message' },
        { name: 'fa-bell', keywords: 'notification alert' },
        { name: 'fa-comment', keywords: 'chat message talk' },
        { name: 'fa-comments', keywords: 'chat conversation' },
        { name: 'fa-message', keywords: 'chat dm' },
        { name: 'fa-heart', keywords: 'like favorite love' },
        { name: 'fa-star', keywords: 'favorite rating' },
        { name: 'fa-bookmark', keywords: 'save mark' },
        { name: 'fa-tag', keywords: 'label price category' },
        { name: 'fa-tags', keywords: 'labels categories' },
        { name: 'fa-flag', keywords: 'mark report' },
        { name: 'fa-fire', keywords: 'hot trending popular' },
        { name: 'fa-bolt', keywords: 'fast power energy lightning' },
        { name: 'fa-rocket', keywords: 'launch fast startup' },
        { name: 'fa-magic-wand-sparkles', keywords: 'magic ai effects' },
        { name: 'fa-wand-magic-sparkles', keywords: 'magic ai effects' },
        { name: 'fa-sparkles', keywords: 'shine new ai' },
        { name: 'fa-palette', keywords: 'design color art' },
        { name: 'fa-paintbrush', keywords: 'design art paint' },
        { name: 'fa-brush', keywords: 'paint design' },
        { name: 'fa-pen', keywords: 'edit write' },
        { name: 'fa-pen-to-square', keywords: 'edit write compose' },
        { name: 'fa-pencil', keywords: 'edit write' },
        { name: 'fa-image', keywords: 'photo picture media' },
        { name: 'fa-images', keywords: 'gallery photos' },
        { name: 'fa-camera', keywords: 'photo capture' },
        { name: 'fa-video', keywords: 'movie clip' },
        { name: 'fa-film', keywords: 'movie video' },
        { name: 'fa-music', keywords: 'audio sound' },
        { name: 'fa-headphones', keywords: 'audio music podcast' },
        { name: 'fa-microphone', keywords: 'voice audio podcast record' },
        { name: 'fa-podcast', keywords: 'audio show broadcast' },
        { name: 'fa-play', keywords: 'media start' },
        { name: 'fa-circle-play', keywords: 'media start' },
        { name: 'fa-chart-line', keywords: 'analytics graph trend stats' },
        { name: 'fa-chart-bar', keywords: 'analytics graph stats' },
        { name: 'fa-chart-pie', keywords: 'analytics breakdown stats' },
        { name: 'fa-chart-column', keywords: 'analytics graph stats' },
        { name: 'fa-chart-simple', keywords: 'analytics stats' },
        { name: 'fa-arrow-trend-up', keywords: 'growth analytics up' },
        { name: 'fa-square-poll-vertical', keywords: 'analytics poll stats' },
        { name: 'fa-magnifying-glass', keywords: 'search find' },
        { name: 'fa-magnifying-glass-chart', keywords: 'search analytics insights' },
        { name: 'fa-eye', keywords: 'view visibility see' },
        { name: 'fa-bullseye', keywords: 'target goal aim' },
        { name: 'fa-crosshairs', keywords: 'target aim' },
        { name: 'fa-gauge', keywords: 'speed performance dashboard' },
        { name: 'fa-gauge-high', keywords: 'speed performance fast' },
        { name: 'fa-shield', keywords: 'security protection' },
        { name: 'fa-shield-halved', keywords: 'security protection' },
        { name: 'fa-lock', keywords: 'secure private' },
        { name: 'fa-key', keywords: 'access password' },
        { name: 'fa-fingerprint', keywords: 'identity biometric secure' },
        { name: 'fa-user-shield', keywords: 'privacy protection' },
        { name: 'fa-gear', keywords: 'settings config' },
        { name: 'fa-gears', keywords: 'settings automation' },
        { name: 'fa-sliders', keywords: 'controls settings adjust' },
        { name: 'fa-toggle-on', keywords: 'switch toggle' },
        { name: 'fa-wrench', keywords: 'tools fix' },
        { name: 'fa-screwdriver-wrench', keywords: 'tools maintenance' },
        { name: 'fa-puzzle-piece', keywords: 'integration extension addon' },
        { name: 'fa-plug', keywords: 'integration connect addon' },
        { name: 'fa-code', keywords: 'developer api programming' },
        { name: 'fa-terminal', keywords: 'developer cli' },
        { name: 'fa-laptop-code', keywords: 'developer programming' },
        { name: 'fa-database', keywords: 'storage data' },
        { name: 'fa-server', keywords: 'hosting backend' },
        { name: 'fa-cloud', keywords: 'cloud storage sync' },
        { name: 'fa-cloud-arrow-up', keywords: 'upload sync' },
        { name: 'fa-cloud-arrow-down', keywords: 'download sync' },
        { name: 'fa-upload', keywords: 'upload import' },
        { name: 'fa-download', keywords: 'download export' },
        { name: 'fa-file', keywords: 'document' },
        { name: 'fa-file-lines', keywords: 'document text' },
        { name: 'fa-folder', keywords: 'directory files' },
        { name: 'fa-folder-open', keywords: 'directory files' },
        { name: 'fa-clipboard', keywords: 'copy paste notes' },
        { name: 'fa-clipboard-list', keywords: 'tasks todo notes' },
        { name: 'fa-list', keywords: 'list items' },
        { name: 'fa-list-check', keywords: 'todo tasks done' },
        { name: 'fa-square-check', keywords: 'done complete' },
        { name: 'fa-circle-check', keywords: 'done complete success' },
        { name: 'fa-check', keywords: 'done complete' },
        { name: 'fa-circle-info', keywords: 'info help' },
        { name: 'fa-circle-question', keywords: 'help support faq' },
        { name: 'fa-life-ring', keywords: 'support help rescue' },
        { name: 'fa-headset', keywords: 'support help service' },
        { name: 'fa-handshake', keywords: 'partner deal agreement' },
        { name: 'fa-people-group', keywords: 'community team' },
        { name: 'fa-thumbs-up', keywords: 'like approve' },
        { name: 'fa-trophy', keywords: 'award win achievement' },
        { name: 'fa-medal', keywords: 'award badge achievement' },
        { name: 'fa-award', keywords: 'badge achievement' },
        { name: 'fa-crown', keywords: 'premium pro vip' },
        { name: 'fa-gem', keywords: 'premium diamond pro' },
        { name: 'fa-circle-dollar-to-slot', keywords: 'payment money tip' },
        { name: 'fa-dollar-sign', keywords: 'money price payment' },
        { name: 'fa-coins', keywords: 'money payment' },
        { name: 'fa-money-bill', keywords: 'money cash payment' },
        { name: 'fa-credit-card', keywords: 'payment card billing' },
        { name: 'fa-cart-shopping', keywords: 'shop store ecommerce' },
        { name: 'fa-bag-shopping', keywords: 'shop store ecommerce' },
        { name: 'fa-store', keywords: 'shop ecommerce' },
        { name: 'fa-receipt', keywords: 'invoice bill payment' },
        { name: 'fa-truck', keywords: 'shipping delivery' },
        { name: 'fa-box', keywords: 'package product' },
        { name: 'fa-gift', keywords: 'reward present' },
        { name: 'fa-calendar', keywords: 'date schedule' },
        { name: 'fa-calendar-days', keywords: 'date schedule month' },
        { name: 'fa-clock', keywords: 'time schedule' },
        { name: 'fa-hourglass', keywords: 'time waiting' },
        { name: 'fa-bullhorn', keywords: 'announce marketing promote' },
        { name: 'fa-megaphone', keywords: 'announce marketing' },
        { name: 'fa-newspaper', keywords: 'news article blog' },
        { name: 'fa-book', keywords: 'docs guide read' },
        { name: 'fa-book-open', keywords: 'docs guide read' },
        { name: 'fa-graduation-cap', keywords: 'learn course education' },
        { name: 'fa-lightbulb', keywords: 'idea tip insight' },
        { name: 'fa-brain', keywords: 'ai think smart' },
        { name: 'fa-robot', keywords: 'ai bot automation' },
        { name: 'fa-language', keywords: 'translate language' },
        { name: 'fa-earth-americas', keywords: 'world global region' },
        { name: 'fa-map', keywords: 'location map' },
        { name: 'fa-location-dot', keywords: 'pin location place' },
        { name: 'fa-compass', keywords: 'navigate direction' },
        { name: 'fa-route', keywords: 'path journey' },
        { name: 'fa-mobile-screen', keywords: 'mobile phone device' },
        { name: 'fa-mobile', keywords: 'mobile phone' },
        { name: 'fa-tablet', keywords: 'tablet device' },
        { name: 'fa-desktop', keywords: 'computer monitor' },
        { name: 'fa-qrcode', keywords: 'qr code scan' },
        { name: 'fa-barcode', keywords: 'code scan' },
        { name: 'fa-circle-nodes', keywords: 'network nodes graph' },
        { name: 'fa-diagram-project', keywords: 'workflow flow diagram' },
        { name: 'fa-network-wired', keywords: 'network connect' },
        { name: 'fa-recycle', keywords: 'sustainable refresh' },
        { name: 'fa-leaf', keywords: 'eco green nature' },
        { name: 'fa-seedling', keywords: 'growth start' },
        { name: 'fa-circle', keywords: 'dot point default' },
    ];

    function featuresEditor(initial) {
        return {
            categories: (initial || []).map(c => ({
                id: c.id || '',
                icon: c.icon || 'fa-circle',
                heading: c.heading || '',
                intro: c.intro || '',
                features: (c.features || []).map(f => ({
                    name: f.name || '',
                    description: f.description || '',
                })),
            })),
            picker: { open: false, ci: null, query: '' },
            addCategory() {
                this.categories.push({ id: '', icon: 'fa-circle', heading: '', intro: '', features: [] });
            },
            removeCategory(i) {
                if (!confirm('Delete this category and all its features?')) return;
                this.categories.splice(i, 1);
            },
            moveCategory(i, dir) {
                const j = i + dir;
                if (j < 0 || j >= this.categories.length) return;
                const tmp = this.categories[i];
                this.categories[i] = this.categories[j];
                this.categories[j] = tmp;
            },
            addFeature(ci) {
                this.categories[ci].features.push({ name: '', description: '' });
            },
            removeFeature(ci, fi) {
                this.categories[ci].features.splice(fi, 1);
            },
            moveFeature(ci, fi, dir) {
                const list = this.categories[ci].features;
                const j = fi + dir;
                if (j < 0 || j >= list.length) return;
                const tmp = list[fi];
                list[fi] = list[j];
                list[j] = tmp;
            },
            openPicker(ci) {
                this.picker.open = true;
                this.picker.ci = ci;
                this.picker.query = '';
                this.$nextTick(() => {
                    if (this.$refs.pickerSearch) this.$refs.pickerSearch.focus();
                });
            },
            closePicker() {
                this.picker.open = false;
                this.picker.ci = null;
                this.picker.query = '';
            },
            selectIcon(name) {
                if (this.picker.ci !== null && this.categories[this.picker.ci]) {
                    this.categories[this.picker.ci].icon = name;
                }
                this.closePicker();
            },
            filteredIcons() {
                const q = (this.picker.query || '').trim().toLowerCase();
                if (!q) return FA_ICON_GALLERY.map(i => i.name);
                return FA_ICON_GALLERY
                    .filter(i => i.name.toLowerCase().includes(q) || i.keywords.toLowerCase().includes(q))
                    .map(i => i.name);
            },
        };
    }
</script>
@endpush
