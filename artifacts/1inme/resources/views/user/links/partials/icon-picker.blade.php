@php
$fieldName = $fieldName ?? 'settings[icon]';
$currentValue = $currentValue ?? '';
$pickerId = 'iconpicker_' . md5($fieldName . uniqid());
@endphp

<div x-data="{{ $pickerId }}()" class="icon-picker-field relative">
    <label class="{{ $labelClass ?? 'block text-xs mb-1' }}">{{ $labelText ?? 'Icon' }}</label>
    <div class="relative">
        <button type="button" @click="open = !open" class="{{ $inputClass ?? 'theme-input w-full' }} flex items-center gap-2 text-left cursor-pointer" style="padding-right: 2rem;">
            <template x-if="value">
                <span class="flex items-center gap-2">
                    <i :class="value" class="text-sm w-5 text-center" style="color: var(--text-primary);"></i>
                    <span class="text-xs truncate" x-text="value" style="color: var(--text-muted);"></span>
                </span>
            </template>
            <template x-if="!value">
                <span class="text-xs" style="color: var(--text-faint);">Choose icon...</span>
            </template>
        </button>
        <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] transition-transform" :class="open ? 'rotate-180' : ''" style="color: var(--text-faint);"></i>
        <button x-show="value" type="button" @click.stop="value = ''; open = false" class="absolute right-8 top-1/2 -translate-y-1/2 text-[10px] text-red-400/60 hover:text-red-400">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <input type="hidden" :name="'{{ $fieldName }}'" x-model="value">

    <div x-show="open" x-cloak @click.away="open = false"
         class="absolute z-50 mt-1 w-full rounded-xl overflow-hidden shadow-2xl"
         style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); backdrop-filter: blur(20px);">
        <div class="p-2" style="border-bottom: 1px solid var(--border-subtle);">
            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px]" style="color: var(--text-faint);"></i>
                <input type="text" x-model="search" x-ref="iconSearch" placeholder="Search icons..." @keydown.escape="open = false"
                       class="w-full text-xs pl-7 pr-3 py-2 rounded-lg outline-none" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
            </div>
            <div class="flex gap-1 mt-1.5 overflow-x-auto pb-1" style="scrollbar-width: none;">
                <template x-for="cat in categories" :key="cat">
                    <button type="button" @click="activeCategory = cat"
                            class="text-[9px] px-2 py-1 rounded-md whitespace-nowrap transition-all font-medium flex-shrink-0"
                            :class="activeCategory === cat ? 'bg-violet-500/20 text-violet-300 ring-1 ring-violet-500/30' : 'text-white/30 hover:text-white/50'"
                            style="background: var(--bg-glass);" x-text="cat">
                    </button>
                </template>
            </div>
        </div>
        <div class="grid grid-cols-6 gap-0.5 p-2 max-h-56 overflow-y-auto" style="scrollbar-width: thin;">
            <template x-for="ic in filteredIcons" :key="ic.c">
                <button type="button" @click="selectIcon(ic.c)"
                        class="w-full aspect-square rounded-lg flex items-center justify-center transition-all group relative"
                        :class="value === ic.c ? 'ring-2 ring-violet-500 bg-violet-500/20' : 'hover:bg-white/5'"
                        :title="ic.n">
                    <i :class="ic.c" class="text-sm" :style="value === ic.c ? 'color: #a78bfa' : 'color: var(--text-muted)'"></i>
                </button>
            </template>
            <template x-if="filteredIcons.length === 0">
                <div class="col-span-6 py-6 text-center text-xs" style="color: var(--text-faint);">No icons found</div>
            </template>
        </div>
    </div>
</div>

<script>
function {{ $pickerId }}() {
    var _allIcons = [
        {c:'fas fa-globe',n:'Globe',t:'general'},
        {c:'fas fa-link',n:'Link',t:'general'},
        {c:'fas fa-external-link-alt',n:'External Link',t:'general'},
        {c:'fas fa-home',n:'Home',t:'general'},
        {c:'fas fa-star',n:'Star',t:'general'},
        {c:'fas fa-heart',n:'Heart',t:'general'},
        {c:'fas fa-fire',n:'Fire',t:'general'},
        {c:'fas fa-bolt',n:'Bolt',t:'general'},
        {c:'fas fa-crown',n:'Crown',t:'general'},
        {c:'fas fa-gem',n:'Gem',t:'general'},
        {c:'fas fa-trophy',n:'Trophy',t:'general'},
        {c:'fas fa-medal',n:'Medal',t:'general'},
        {c:'fas fa-award',n:'Award',t:'general'},
        {c:'fas fa-certificate',n:'Certificate',t:'general'},
        {c:'fas fa-check',n:'Check',t:'general'},
        {c:'fas fa-check-circle',n:'Check Circle',t:'general'},
        {c:'fas fa-times',n:'Times',t:'general'},
        {c:'fas fa-plus',n:'Plus',t:'general'},
        {c:'fas fa-minus',n:'Minus',t:'general'},
        {c:'fas fa-info-circle',n:'Info',t:'general'},
        {c:'fas fa-exclamation-circle',n:'Exclamation',t:'general'},
        {c:'fas fa-question-circle',n:'Question',t:'general'},
        {c:'fas fa-bell',n:'Bell',t:'general'},
        {c:'fas fa-bookmark',n:'Bookmark',t:'general'},
        {c:'fas fa-flag',n:'Flag',t:'general'},
        {c:'fas fa-tag',n:'Tag',t:'general'},
        {c:'fas fa-tags',n:'Tags',t:'general'},
        {c:'fas fa-thumbs-up',n:'Thumbs Up',t:'general'},
        {c:'fas fa-thumbs-down',n:'Thumbs Down',t:'general'},
        {c:'fas fa-smile',n:'Smile',t:'general'},
        {c:'fas fa-laugh',n:'Laugh',t:'general'},
        {c:'fas fa-grin-stars',n:'Star Eyes',t:'general'},
        {c:'fas fa-rocket',n:'Rocket',t:'general'},
        {c:'fas fa-paper-plane',n:'Paper Plane',t:'general'},
        {c:'fas fa-lightbulb',n:'Lightbulb',t:'general'},
        {c:'fas fa-magic',n:'Magic',t:'general'},
        {c:'fas fa-sparkles',n:'Sparkles',t:'general'},
        {c:'fas fa-sun',n:'Sun',t:'general'},
        {c:'fas fa-moon',n:'Moon',t:'general'},
        {c:'fas fa-cloud',n:'Cloud',t:'general'},
        {c:'fas fa-snowflake',n:'Snowflake',t:'general'},
        {c:'fas fa-leaf',n:'Leaf',t:'general'},
        {c:'fas fa-seedling',n:'Seedling',t:'general'},
        {c:'fas fa-tree',n:'Tree',t:'general'},
        {c:'fas fa-paw',n:'Paw',t:'general'},
        {c:'fas fa-feather',n:'Feather',t:'general'},
        {c:'fas fa-user',n:'User',t:'people'},
        {c:'fas fa-users',n:'Users',t:'people'},
        {c:'fas fa-user-circle',n:'User Circle',t:'people'},
        {c:'fas fa-user-plus',n:'User Plus',t:'people'},
        {c:'fas fa-user-tie',n:'User Tie',t:'people'},
        {c:'fas fa-people-group',n:'Group',t:'people'},
        {c:'fas fa-handshake',n:'Handshake',t:'people'},
        {c:'fas fa-hand-holding-heart',n:'Holding Heart',t:'people'},
        {c:'fas fa-envelope',n:'Email',t:'communication'},
        {c:'fas fa-envelope-open',n:'Email Open',t:'communication'},
        {c:'fas fa-phone',n:'Phone',t:'communication'},
        {c:'fas fa-phone-alt',n:'Phone Alt',t:'communication'},
        {c:'fas fa-mobile-alt',n:'Mobile',t:'communication'},
        {c:'fas fa-comment',n:'Comment',t:'communication'},
        {c:'fas fa-comments',n:'Comments',t:'communication'},
        {c:'fas fa-comment-dots',n:'Chat',t:'communication'},
        {c:'fas fa-inbox',n:'Inbox',t:'communication'},
        {c:'fas fa-at',n:'At',t:'communication'},
        {c:'fas fa-share-alt',n:'Share',t:'communication'},
        {c:'fas fa-share',n:'Share Arrow',t:'communication'},
        {c:'fab fa-instagram',n:'Instagram',t:'social'},
        {c:'fab fa-x-twitter',n:'X/Twitter',t:'social'},
        {c:'fab fa-facebook-f',n:'Facebook',t:'social'},
        {c:'fab fa-tiktok',n:'TikTok',t:'social'},
        {c:'fab fa-youtube',n:'YouTube',t:'social'},
        {c:'fab fa-linkedin-in',n:'LinkedIn',t:'social'},
        {c:'fab fa-github',n:'GitHub',t:'social'},
        {c:'fab fa-discord',n:'Discord',t:'social'},
        {c:'fab fa-telegram',n:'Telegram',t:'social'},
        {c:'fab fa-whatsapp',n:'WhatsApp',t:'social'},
        {c:'fab fa-snapchat-ghost',n:'Snapchat',t:'social'},
        {c:'fab fa-pinterest',n:'Pinterest',t:'social'},
        {c:'fab fa-reddit',n:'Reddit',t:'social'},
        {c:'fab fa-twitch',n:'Twitch',t:'social'},
        {c:'fab fa-spotify',n:'Spotify',t:'social'},
        {c:'fab fa-apple',n:'Apple',t:'social'},
        {c:'fab fa-google',n:'Google',t:'social'},
        {c:'fab fa-amazon',n:'Amazon',t:'social'},
        {c:'fab fa-dribbble',n:'Dribbble',t:'social'},
        {c:'fab fa-behance',n:'Behance',t:'social'},
        {c:'fab fa-figma',n:'Figma',t:'social'},
        {c:'fab fa-slack',n:'Slack',t:'social'},
        {c:'fab fa-medium',n:'Medium',t:'social'},
        {c:'fab fa-patreon',n:'Patreon',t:'social'},
        {c:'fab fa-paypal',n:'PayPal',t:'social'},
        {c:'fab fa-stripe',n:'Stripe',t:'social'},
        {c:'fab fa-etsy',n:'Etsy',t:'social'},
        {c:'fab fa-shopify',n:'Shopify',t:'social'},
        {c:'fab fa-soundcloud',n:'SoundCloud',t:'social'},
        {c:'fab fa-vimeo-v',n:'Vimeo',t:'social'},
        {c:'fab fa-steam',n:'Steam',t:'social'},
        {c:'fab fa-xbox',n:'Xbox',t:'social'},
        {c:'fab fa-playstation',n:'PlayStation',t:'social'},
        {c:'fas fa-shopping-cart',n:'Cart',t:'commerce'},
        {c:'fas fa-shopping-bag',n:'Bag',t:'commerce'},
        {c:'fas fa-store',n:'Store',t:'commerce'},
        {c:'fas fa-credit-card',n:'Card',t:'commerce'},
        {c:'fas fa-wallet',n:'Wallet',t:'commerce'},
        {c:'fas fa-money-bill-wave',n:'Money',t:'commerce'},
        {c:'fas fa-coins',n:'Coins',t:'commerce'},
        {c:'fas fa-dollar-sign',n:'Dollar',t:'commerce'},
        {c:'fas fa-percent',n:'Percent',t:'commerce'},
        {c:'fas fa-receipt',n:'Receipt',t:'commerce'},
        {c:'fas fa-gift',n:'Gift',t:'commerce'},
        {c:'fas fa-box',n:'Box',t:'commerce'},
        {c:'fas fa-truck',n:'Truck',t:'commerce'},
        {c:'fas fa-barcode',n:'Barcode',t:'commerce'},
        {c:'fas fa-camera',n:'Camera',t:'media'},
        {c:'fas fa-image',n:'Image',t:'media'},
        {c:'fas fa-images',n:'Images',t:'media'},
        {c:'fas fa-video',n:'Video',t:'media'},
        {c:'fas fa-film',n:'Film',t:'media'},
        {c:'fas fa-music',n:'Music',t:'media'},
        {c:'fas fa-headphones',n:'Headphones',t:'media'},
        {c:'fas fa-microphone',n:'Microphone',t:'media'},
        {c:'fas fa-podcast',n:'Podcast',t:'media'},
        {c:'fas fa-play',n:'Play',t:'media'},
        {c:'fas fa-play-circle',n:'Play Circle',t:'media'},
        {c:'fas fa-pause',n:'Pause',t:'media'},
        {c:'fas fa-volume-up',n:'Volume',t:'media'},
        {c:'fas fa-palette',n:'Palette',t:'media'},
        {c:'fas fa-paint-brush',n:'Brush',t:'media'},
        {c:'fas fa-pen',n:'Pen',t:'media'},
        {c:'fas fa-pencil-alt',n:'Pencil',t:'media'},
        {c:'fas fa-file',n:'File',t:'content'},
        {c:'fas fa-file-alt',n:'File Text',t:'content'},
        {c:'fas fa-file-pdf',n:'PDF',t:'content'},
        {c:'fas fa-file-image',n:'File Image',t:'content'},
        {c:'fas fa-file-video',n:'File Video',t:'content'},
        {c:'fas fa-file-audio',n:'File Audio',t:'content'},
        {c:'fas fa-file-code',n:'File Code',t:'content'},
        {c:'fas fa-file-download',n:'Download',t:'content'},
        {c:'fas fa-folder',n:'Folder',t:'content'},
        {c:'fas fa-folder-open',n:'Folder Open',t:'content'},
        {c:'fas fa-clipboard',n:'Clipboard',t:'content'},
        {c:'fas fa-copy',n:'Copy',t:'content'},
        {c:'fas fa-book',n:'Book',t:'content'},
        {c:'fas fa-book-open',n:'Book Open',t:'content'},
        {c:'fas fa-newspaper',n:'Newspaper',t:'content'},
        {c:'fas fa-blog',n:'Blog',t:'content'},
        {c:'fas fa-quote-left',n:'Quote',t:'content'},
        {c:'fas fa-align-left',n:'Align',t:'content'},
        {c:'fas fa-list',n:'List',t:'content'},
        {c:'fas fa-table',n:'Table',t:'content'},
        {c:'fas fa-calendar',n:'Calendar',t:'content'},
        {c:'fas fa-calendar-alt',n:'Calendar Alt',t:'content'},
        {c:'fas fa-clock',n:'Clock',t:'content'},
        {c:'fas fa-hourglass-half',n:'Hourglass',t:'content'},
        {c:'fas fa-map-marker-alt',n:'Location',t:'misc'},
        {c:'fas fa-map',n:'Map',t:'misc'},
        {c:'fas fa-compass',n:'Compass',t:'misc'},
        {c:'fas fa-directions',n:'Directions',t:'misc'},
        {c:'fas fa-car',n:'Car',t:'misc'},
        {c:'fas fa-plane',n:'Plane',t:'misc'},
        {c:'fas fa-train',n:'Train',t:'misc'},
        {c:'fas fa-bicycle',n:'Bicycle',t:'misc'},
        {c:'fas fa-utensils',n:'Food',t:'misc'},
        {c:'fas fa-coffee',n:'Coffee',t:'misc'},
        {c:'fas fa-glass-cheers',n:'Cheers',t:'misc'},
        {c:'fas fa-birthday-cake',n:'Cake',t:'misc'},
        {c:'fas fa-graduation-cap',n:'Education',t:'misc'},
        {c:'fas fa-university',n:'University',t:'misc'},
        {c:'fas fa-flask',n:'Flask',t:'misc'},
        {c:'fas fa-microscope',n:'Microscope',t:'misc'},
        {c:'fas fa-dumbbell',n:'Fitness',t:'misc'},
        {c:'fas fa-heartbeat',n:'Health',t:'misc'},
        {c:'fas fa-stethoscope',n:'Medical',t:'misc'},
        {c:'fas fa-pills',n:'Pills',t:'misc'},
        {c:'fas fa-pray',n:'Pray',t:'misc'},
        {c:'fas fa-church',n:'Church',t:'misc'},
        {c:'fas fa-gavel',n:'Law',t:'misc'},
        {c:'fas fa-shield-alt',n:'Shield',t:'misc'},
        {c:'fas fa-lock',n:'Lock',t:'misc'},
        {c:'fas fa-unlock',n:'Unlock',t:'misc'},
        {c:'fas fa-key',n:'Key',t:'misc'},
        {c:'fas fa-eye',n:'Eye',t:'misc'},
        {c:'fas fa-search',n:'Search',t:'misc'},
        {c:'fas fa-cog',n:'Settings',t:'misc'},
        {c:'fas fa-cogs',n:'Gears',t:'misc'},
        {c:'fas fa-wrench',n:'Wrench',t:'misc'},
        {c:'fas fa-tools',n:'Tools',t:'misc'},
        {c:'fas fa-code',n:'Code',t:'misc'},
        {c:'fas fa-terminal',n:'Terminal',t:'misc'},
        {c:'fas fa-database',n:'Database',t:'misc'},
        {c:'fas fa-server',n:'Server',t:'misc'},
        {c:'fas fa-wifi',n:'WiFi',t:'misc'},
        {c:'fas fa-signal',n:'Signal',t:'misc'},
        {c:'fas fa-chart-bar',n:'Bar Chart',t:'misc'},
        {c:'fas fa-chart-line',n:'Line Chart',t:'misc'},
        {c:'fas fa-chart-pie',n:'Pie Chart',t:'misc'},
        {c:'fas fa-download',n:'Download',t:'misc'},
        {c:'fas fa-upload',n:'Upload',t:'misc'},
        {c:'fas fa-arrow-right',n:'Arrow Right',t:'misc'},
        {c:'fas fa-arrow-left',n:'Arrow Left',t:'misc'},
        {c:'fas fa-arrow-up',n:'Arrow Up',t:'misc'},
        {c:'fas fa-arrow-down',n:'Arrow Down',t:'misc'},
        {c:'fas fa-angle-right',n:'Angle Right',t:'misc'},
        {c:'fas fa-angle-double-right',n:'Double Right',t:'misc'},
        {c:'fas fa-chevron-right',n:'Chevron Right',t:'misc'},
        {c:'fas fa-long-arrow-alt-right',n:'Long Arrow',t:'misc'},
        {c:'fas fa-hand-point-right',n:'Point Right',t:'misc'},
        {c:'fas fa-mouse-pointer',n:'Cursor',t:'misc'},
        {c:'fas fa-bullhorn',n:'Bullhorn',t:'misc'},
        {c:'fas fa-megaphone',n:'Megaphone',t:'misc'},
        {c:'fas fa-bullseye',n:'Target',t:'misc'},
        {c:'fas fa-crosshairs',n:'Crosshairs',t:'misc'},
        {c:'fas fa-fingerprint',n:'Fingerprint',t:'misc'},
        {c:'fas fa-qrcode',n:'QR Code',t:'misc'},
        {c:'fas fa-hashtag',n:'Hashtag',t:'misc'},
        {c:'fas fa-puzzle-piece',n:'Puzzle',t:'misc'},
        {c:'fas fa-dice',n:'Dice',t:'misc'},
        {c:'fas fa-gamepad',n:'Gamepad',t:'misc'}
    ];
    return {
        open: false,
        value: '{{ $currentValue }}',
        search: '',
        activeCategory: 'All',
        categories: ['All','General','Social','Commerce','Media','People','Communication','Content','Misc'],
        get filteredIcons() {
            var s = this.search.toLowerCase();
            var cat = this.activeCategory.toLowerCase();
            return _allIcons.filter(function(ic) {
                if (cat !== 'all' && ic.t !== cat) return false;
                if (s && ic.n.toLowerCase().indexOf(s) === -1 && ic.c.toLowerCase().indexOf(s) === -1) return false;
                return true;
            });
        },
        selectIcon: function(cls) {
            this.value = cls;
            this.open = false;
            this.search = '';
        }
    };
}
</script>
