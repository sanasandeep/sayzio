{{-- Shared FontAwesome icon picker.
     Include once on any admin page that needs it, then trigger via:
       $store.iconPicker.openFor(currentIconClass, (name) => { ...assign... })
--}}
<div x-data x-show="$store.iconPicker.open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
     @keydown.escape.window="$store.iconPicker.close()"
     @click.self="$store.iconPicker.close()">
    <div class="w-full max-w-2xl max-h-[80vh] flex flex-col bg-zinc-900 border border-white/10 rounded-2xl shadow-2xl">
        <div class="flex items-center justify-between p-4 border-b border-white/10">
            <h3 class="text-sm font-semibold text-white ak-strong">Pick an icon</h3>
            <button type="button" @click="$store.iconPicker.close()" class="text-white/60 hover:text-white text-sm ak-muted" title="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 border-b border-white/10">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-white/40 text-xs ak-note"></i>
                <input type="text" x-model="$store.iconPicker.query" placeholder="Search icons (e.g. share, link, chart)"
                       class="w-full pl-8 pr-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input"
                       x-ref="iconPickerSearch"
                       x-effect="if ($store.iconPicker.open) $nextTick(() => $refs.iconPickerSearch && $refs.iconPickerSearch.focus())">
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                <template x-for="ic in $store.iconPicker.filteredIcons()" :key="ic">
                    <button type="button" @click="$store.iconPicker.tapIcon(ic)"
                            @mouseenter="$store.iconPicker.hover = ic" @mouseleave="if ($store.iconPicker.hover === ic) $store.iconPicker.hover = null"
                            @focus="$store.iconPicker.hover = ic" @blur="if ($store.iconPicker.hover === ic) $store.iconPicker.hover = null"
                            :class="'group flex items-center justify-center aspect-square rounded-lg border text-white/80 hover:text-white hover:bg-blue-500/20 transition ' + ($store.iconPicker.hover === ic ? 'ring-2 ring-blue-400/60 ' : '') + ($store.iconPicker.current === ic ? 'bg-blue-500/30 border-blue-400/50' : 'bg-white/5 border-white/10') ak-strong"
                            :title="ic" :aria-label="ic">
                        <i :class="'fas ' + ic + ' text-base'"></i>
                    </button>
                </template>
            </div>
            <div x-show="$store.iconPicker.filteredIcons().length === 0" class="text-xs text-white/40 text-center py-6 ak-note">No icons match "<span x-text="$store.iconPicker.query"></span>".</div>
        </div>
        <div class="px-4 py-2 border-t border-white/10 bg-black/30 flex items-center justify-center gap-2 min-h-[36px]">
            <template x-if="$store.iconPicker.hover">
                <span class="flex items-center gap-2 text-xs text-white ak-strong">
                    <i :class="'fas ' + $store.iconPicker.hover + ' text-blue-300 ak-blue'"></i>
                    <code class="text-white/90 ak-strong" x-text="$store.iconPicker.hover"></code>
                    <span class="text-white/40 hidden sm:inline ak-note"> - tap again to select</span>
                </span>
            </template>
            <template x-if="!$store.iconPicker.hover">
                <span class="text-[11px] text-white/40 ak-note">Hover or tap an icon to see its name.</span>
            </template>
        </div>
        <div class="p-3 border-t border-white/10 text-[11px] text-white/40 text-center ak-note">
            You can also type a FontAwesome class name directly in the icon field.
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.FA_ICON_GALLERY = window.FA_ICON_GALLERY || [
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
        { name: 'fa-user-plus', keywords: 'invite add person' },
        { name: 'fa-user-tie', keywords: 'business professional' },
        { name: 'fa-user-graduate', keywords: 'student education' },
        { name: 'fa-id-card', keywords: 'profile card identity' },
        { name: 'fa-id-badge', keywords: 'badge identity' },
        { name: 'fa-address-card', keywords: 'profile contact card' },
        { name: 'fa-envelope', keywords: 'mail email message' },
        { name: 'fa-bell', keywords: 'notification alert' },
        { name: 'fa-comment', keywords: 'chat message talk' },
        { name: 'fa-comments', keywords: 'chat conversation' },
        { name: 'fa-comment-dots', keywords: 'chat dm message' },
        { name: 'fa-message', keywords: 'chat dm' },
        { name: 'fa-phone', keywords: 'call contact phone' },
        { name: 'fa-heart', keywords: 'like favorite love' },
        { name: 'fa-star', keywords: 'favorite rating' },
        { name: 'fa-bookmark', keywords: 'save mark' },
        { name: 'fa-tag', keywords: 'label price category' },
        { name: 'fa-tags', keywords: 'labels categories' },
        { name: 'fa-flag', keywords: 'mark report' },
        { name: 'fa-thumbtack', keywords: 'pin attach' },
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
        { name: 'fa-pen-nib', keywords: 'write author' },
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
        { name: 'fa-cog', keywords: 'settings config' },
        { name: 'fa-gear', keywords: 'settings config' },
        { name: 'fa-gears', keywords: 'settings automation' },
        { name: 'fa-sliders', keywords: 'controls settings adjust' },
        { name: 'fa-toggle-on', keywords: 'switch toggle' },
        { name: 'fa-wrench', keywords: 'tools fix' },
        { name: 'fa-screwdriver-wrench', keywords: 'tools maintenance' },
        { name: 'fa-toolbox', keywords: 'tools fix' },
        { name: 'fa-hammer', keywords: 'tools build' },
        { name: 'fa-puzzle-piece', keywords: 'integration extension addon' },
        { name: 'fa-plug', keywords: 'integration connect addon' },
        { name: 'fa-code', keywords: 'developer api programming' },
        { name: 'fa-terminal', keywords: 'developer cli' },
        { name: 'fa-laptop-code', keywords: 'developer programming' },
        { name: 'fa-database', keywords: 'storage data' },
        { name: 'fa-server', keywords: 'hosting backend' },
        { name: 'fa-cloud', keywords: 'cloud storage sync weather' },
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
        { name: 'fa-money-bill-wave', keywords: 'money cash payment' },
        { name: 'fa-piggy-bank', keywords: 'savings money' },
        { name: 'fa-wallet', keywords: 'money wallet payment' },
        { name: 'fa-credit-card', keywords: 'payment card billing' },
        { name: 'fa-cash-register', keywords: 'pos sales checkout' },
        { name: 'fa-percent', keywords: 'discount percent sale' },
        { name: 'fa-cart-shopping', keywords: 'shop store ecommerce' },
        { name: 'fa-bag-shopping', keywords: 'shop store ecommerce' },
        { name: 'fa-store', keywords: 'shop ecommerce' },
        { name: 'fa-shop', keywords: 'shop ecommerce' },
        { name: 'fa-receipt', keywords: 'invoice bill payment' },
        { name: 'fa-truck', keywords: 'shipping delivery' },
        { name: 'fa-box', keywords: 'package product' },
        { name: 'fa-gift', keywords: 'reward present' },
        { name: 'fa-calendar', keywords: 'date schedule' },
        { name: 'fa-calendar-day', keywords: 'date day schedule' },
        { name: 'fa-calendar-days', keywords: 'date schedule month' },
        { name: 'fa-calendar-check', keywords: 'date scheduled confirmed' },
        { name: 'fa-clock', keywords: 'time schedule' },
        { name: 'fa-hourglass', keywords: 'time waiting' },
        { name: 'fa-hourglass-half', keywords: 'time waiting half' },
        { name: 'fa-bullhorn', keywords: 'announce marketing promote' },
        { name: 'fa-megaphone', keywords: 'announce marketing' },
        { name: 'fa-newspaper', keywords: 'news article blog' },
        { name: 'fa-book', keywords: 'docs guide read' },
        { name: 'fa-book-open', keywords: 'docs guide read' },
        { name: 'fa-graduation-cap', keywords: 'learn course education' },
        { name: 'fa-school', keywords: 'school education' },
        { name: 'fa-chalkboard-user', keywords: 'teach education classroom' },
        { name: 'fa-lightbulb', keywords: 'idea tip insight' },
        { name: 'fa-flask', keywords: 'experiment science lab' },
        { name: 'fa-brain', keywords: 'ai think smart' },
        { name: 'fa-robot', keywords: 'ai bot automation' },
        { name: 'fa-language', keywords: 'translate language' },
        { name: 'fa-earth-americas', keywords: 'world global region' },
        { name: 'fa-map', keywords: 'location map' },
        { name: 'fa-map-location-dot', keywords: 'pin location map' },
        { name: 'fa-location-dot', keywords: 'pin location place' },
        { name: 'fa-compass', keywords: 'navigate direction' },
        { name: 'fa-route', keywords: 'path journey' },
        { name: 'fa-sitemap', keywords: 'structure tree map' },
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
        { name: 'fa-tree', keywords: 'nature outdoors' },
        { name: 'fa-paw', keywords: 'pet animal' },
        { name: 'fa-dog', keywords: 'pet animal dog' },
        { name: 'fa-cat', keywords: 'pet animal cat' },
        { name: 'fa-utensils', keywords: 'food restaurant dining' },
        { name: 'fa-mug-hot', keywords: 'coffee tea drink' },
        { name: 'fa-cake-candles', keywords: 'birthday cake celebrate' },
        { name: 'fa-pizza-slice', keywords: 'food pizza restaurant' },
        { name: 'fa-plane', keywords: 'travel flight' },
        { name: 'fa-car', keywords: 'travel auto vehicle' },
        { name: 'fa-bicycle', keywords: 'bike travel transport' },
        { name: 'fa-ship', keywords: 'travel boat ocean' },
        { name: 'fa-train', keywords: 'travel rail transport' },
        { name: 'fa-bed', keywords: 'sleep hotel rest' },
        { name: 'fa-couch', keywords: 'home furniture lounge' },
        { name: 'fa-briefcase', keywords: 'work business job' },
        { name: 'fa-building', keywords: 'company office building' },
        { name: 'fa-building-user', keywords: 'company team office' },
        { name: 'fa-suitcase', keywords: 'travel work luggage' },
        { name: 'fa-dumbbell', keywords: 'fitness gym workout' },
        { name: 'fa-heart-pulse', keywords: 'health fitness vitals' },
        { name: 'fa-spa', keywords: 'wellness relax spa' },
        { name: 'fa-stethoscope', keywords: 'health medical doctor' },
        { name: 'fa-pills', keywords: 'medicine health pharmacy' },
        { name: 'fa-droplet', keywords: 'water drop liquid' },
        { name: 'fa-sun', keywords: 'weather day light' },
        { name: 'fa-moon', keywords: 'weather night dark' },
        { name: 'fa-cube', keywords: '3d shape block' },
        { name: 'fa-cubes', keywords: '3d shapes blocks' },
        { name: 'fa-layer-group', keywords: 'stack layers group' },
        { name: 'fa-shapes', keywords: 'shapes design' },
        { name: 'fa-infinity', keywords: 'unlimited infinite forever' },
        { name: 'fa-circle-dot', keywords: 'dot point default selected' },
        { name: 'fa-circle', keywords: 'dot point default' },
    ];

    document.addEventListener('alpine:init', () => {
        if (window.Alpine && Alpine.store && !Alpine.store('iconPicker')) {
            Alpine.store('iconPicker', {
                open: false,
                query: '',
                current: '',
                hover: null,
                _onSelect: null,
                openFor(currentValue, onSelect) {
                    this.current = currentValue || '';
                    this.query = '';
                    this.hover = null;
                    this._onSelect = typeof onSelect === 'function' ? onSelect : null;
                    this.open = true;
                },
                close() {
                    this.open = false;
                    this.query = '';
                    this.current = '';
                    this.hover = null;
                    this._onSelect = null;
                },
                selectIcon(name) {
                    if (this._onSelect) this._onSelect(name);
                    this.close();
                },
                tapIcon(name) {
                    // On hover-capable devices, the icon is already "hovered"
                    // before click, so the first tap selects. On touch devices,
                    // the first tap reveals the name and a second tap on the
                    // same icon selects.
                    if (this.hover === name) {
                        this.selectIcon(name);
                    } else {
                        this.hover = name;
                    }
                },
                filteredIcons() {
                    const q = (this.query || '').trim().toLowerCase();
                    const list = window.FA_ICON_GALLERY || [];
                    if (!q) return list.map(i => i.name);
                    return list
                        .filter(i => i.name.toLowerCase().includes(q) || (i.keywords || '').toLowerCase().includes(q))
                        .map(i => i.name);
                },
            });
        }
    });
</script>
@endpush
