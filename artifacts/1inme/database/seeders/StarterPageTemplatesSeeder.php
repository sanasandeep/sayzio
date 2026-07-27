<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds a small, broadly-useful set of starter Page Templates so the
 * page-template picker (`user/links/templates/picker`) is never empty
 * on a fresh install. These complement the persona-tied templates from
 * ExpandedPageTemplateLibrarySeeder: they're not gated on PersonaCatalog
 * (which can be empty on day-one or in a stripped-down install) and
 * cover several legacy "shape" categories so the picker's category
 * chips, blueprint thumbnails, and "what's inside" chips look
 * meaningfully different across tiles.
 *
 * Idempotent: every row is keyed by a stable `starter-*` slug and
 * inserted with `firstOrCreate` so re-running the seeder never
 * duplicates rows or clobbers admin edits to the same row.
 *
 * Auto-refresh: like the persona seeder, each snapshot carries a
 * `meta.seed_version` stamp. When the starter blueprints are redesigned
 * the version is bumped and `autoRefreshStale()` drops untouched seeded
 * rows whose stored version is older, so the firstOrCreate loop below
 * recreates them with the new design on the next deploy. Admin-edited
 * rows (updated after creation) and unknown `starter-*` slugs are left
 * alone.
 *
 * Slug namespace `starter-*` is intentionally distinct from the
 * `persona-*` namespace owned by ExpandedPageTemplateLibrarySeeder so
 * its auto-refresh / outdated-blueprint logic leaves these alone.
 */
class StarterPageTemplatesSeeder extends Seeder
{
    /**
     * Bump when the starter blueprints below are redesigned.
     *
     * v5 (2026-06): Added 5 media-forward showcase pages (photo
     * portfolio, music artist, content-creator embeds, knowledge hub,
     * press/media kit) exercising image grids/sliders, social embeds,
     * audio/music, documents and advanced UI (tabs/accordion/ticker/
     * stats/reviews wall/testimonial carousel).
     */
    public const SEED_VERSION = 6;

    /** Tolerance (seconds) for treating updated_at == created_at. */
    private const EDIT_DRIFT_TOLERANCE = 2;

    public function run(): void
    {
        // Drop untouched seeded rows from an older blueprint version so
        // the firstOrCreate loop below recreates them with the new design.
        $this->autoRefreshStale();

        foreach ($this->templates() as $i => $tpl) {
            PageTemplate::firstOrCreate(
                ['slug' => $tpl['slug']],
                [
                    'name'                 => $tpl['name'],
                    'category'             => $tpl['category'],
                    'description'          => $tpl['description'],
                    'thumbnail_url'        => null,
                    'plan_tier'            => null,
                    'is_active'            => true,
                    'sort_order'           => 10 + $i,
                    'recommended_personas' => $tpl['recommended_personas'],
                    'snapshot'             => $tpl['snapshot'],
                ]
            );
        }
    }

    /**
     * Delete untouched `starter-*` rows whose stored seed_version is
     * older than the current SEED_VERSION. Mirrors the persona seeder's
     * auto-refresh: unknown slugs (admin-added) and admin-edited rows
     * (updated after creation) are preserved.
     */
    private function autoRefreshStale(): void
    {
        $knownSlugs = array_column($this->templates(), 'slug');

        $rows = PageTemplate::query()
            ->where('slug', 'like', 'starter-%')
            ->get(['id', 'slug', 'snapshot', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            if (!in_array($row->slug, $knownSlugs, true)) {
                continue; // unknown slug — admin-added, leave alone.
            }
            if ($row->updated_at && $row->created_at
                && $row->updated_at->getTimestamp() - $row->created_at->getTimestamp() > self::EDIT_DRIFT_TOLERANCE) {
                continue; // admin edited through the panel — preserve.
            }
            $stored = (int) (((array) $row->snapshot)['meta']['seed_version'] ?? 0);
            if ($stored >= self::SEED_VERSION) {
                continue;
            }

            PageTemplate::whereKey($row->id)->delete();
        }
    }

    /**
     * @return array<int, array{slug:string,name:string,category:string,description:string,recommended_personas:array<int,string>,snapshot:array}>
     */
    private function templates(): array
    {
        // All legacy starter blueprints were retired (task: remove all
        // seeded page templates). New blueprint designs will be added here
        // later. Returning an empty list makes run() a no-op while keeping
        // the seeder scaffolding (SEED_VERSION, auto-refresh, block
        // helpers below) in place for the next template generation.
        return [];
    }

    /* ──────────────────── snapshot + block helpers ──────────────────── */

    private function snapshot(array $blocks, array $biolink = []): array
    {
        // `meta.seed_version` lets a future seeder run detect rows
        // generated by an older blueprint version and auto-refresh them
        // (see `autoRefreshStale()`).
        return [
            'biolink' => $biolink,
            'blocks'  => $blocks,
            'meta'    => ['seed_version' => self::SEED_VERSION],
        ];
    }

    private function block(string $type, array $settings): array
    {
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    /**
     * Per-template "variant kit": the profile block type + identity
     * variant (which carries the `_profile_layout`) plus the link design
     * variant key. Keyed by template slug-fragment for readability.
     *
     * @return array<string, array{ptype:string,pvar:string,link:string}>
     */
    private function variantKits(): array
    {
        return [
            'personal'   => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_glass',        'link' => 'frosted_pill'],
            'linkbio'    => ['ptype' => 'profile_card_v2', 'pvar' => 'identity_cover_hero',   'link' => 'pill_solid'],
            'restaurant' => ['ptype' => 'profile_card_v4', 'pvar' => 'identity_minimal_dark', 'link' => 'corporate_row'],
            'event'      => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_classic',      'link' => 'card_lifted'],
            'portfolio'  => ['ptype' => 'profile_card_v3', 'pvar' => 'identity_founder',      'link' => 'outline_pill'],
        ];
    }

    /**
     * Resolve a curated design variant into a full baked `_style` payload
     * (STYLE_DEFAULTS + the catalog variant's style + the current catalog
     * VERSION stamp) so BOTH the applied block and the no-DB template
     * preview render the variant identically.
     *
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function variantStyle(string $type, string $key, array $extra = []): array
    {
        $variant = BlockVariantCatalog::find($type, $key);
        $style = is_array($variant['style'] ?? null) ? $variant['style'] : [];
        $merged = array_merge(BiolinkBlock::STYLE_DEFAULTS, $style, $extra);
        if ($variant !== null) {
            $merged['_variant'] = $key;
            $merged['_variant_version'] = BlockVariantCatalog::VERSION;
        }
        return $merged;
    }

    /** @return array<int, array{name:string,url:string}> */
    private function profileSocials(): array
    {
        return [
            ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
            ['name' => 'tiktok',    'url' => 'https://tiktok.com/@yourhandle'],
            ['name' => 'youtube',   'url' => 'https://youtube.com/@yourhandle'],
            ['name' => 'linkedin',  'url' => 'https://linkedin.com/in/yourhandle'],
        ];
    }

    /**
     * Rich profile card. The `$kit` selects the profile block type
     * (profile_card_v1..v4) and the `identity_*` design variant. v3
     * (Stats) and v4 (Badges) get placeholder stats/badges so they
     * render full unless overridden via `$extra`.
     *
     * @param  array{ptype:string,pvar:string,link:string}  $kit
     * @param  array<string,mixed>  $extra
     */
    private function profile(string $name, string $bio, string $avatar, array $kit, string $cover = '', array $extra = []): array
    {
        $type = $kit['ptype'];
        $settings = array_merge([
            'name'      => $name,
            'title'     => '',
            'avatar'    => $avatar,
            'cover'     => $cover,
            'bio'       => $bio,
            'verified'  => true,
            'location'  => 'Your City, Country',
            'website'   => 'https://example.com',
            'cta_label' => 'Get in touch',
            'cta_url'   => 'https://example.com',
            'socials'   => $this->profileSocials(),
            '_style'    => $this->variantStyle($type, $kit['pvar']),
        ], $extra);

        if ($type === 'profile_card_v3' && !isset($settings['stats'])) {
            $settings['stats'] = [
                ['label' => 'Followers', 'value' => '12.4K'],
                ['label' => 'Projects',  'value' => '87'],
                ['label' => 'Years',     'value' => '6'],
            ];
        }
        if ($type === 'profile_card_v4' && !isset($settings['badges'])) {
            $settings['badges'] = [
                ['label' => 'Top Rated'],
                ['label' => 'Verified'],
            ];
        }

        return $this->block($type, $settings);
    }

    private function heading(string $text, string $size = 'h3'): array
    {
        return $this->block('heading', ['text' => $text, 'size' => $size, 'align' => 'center']);
    }

    private function paragraph(string $text): array
    {
        return $this->block('paragraph', ['text' => $text, 'align' => 'center']);
    }

    /** @param  array{ptype:string,pvar:string,link:string}|null  $kit */
    private function link(string $text, string $url, string $icon = '', ?array $kit = null): array
    {
        $settings = ['text' => $text, 'url' => $url, 'icon' => $icon];
        if ($kit !== null) {
            $settings['_style'] = $this->variantStyle('link', $kit['link']);
        }
        return $this->block('link', $settings);
    }

    /** @param  array{ptype:string,pvar:string,link:string}|null  $kit */
    private function linkBig(string $text, string $url, string $icon = '', ?array $kit = null): array
    {
        $settings = ['text' => $text, 'url' => $url, 'icon' => $icon];
        if ($kit !== null) {
            $settings['_style'] = $this->variantStyle('link_big', $kit['link']);
        }
        return $this->block('link_big', $settings);
    }

    private function divider(): array
    {
        return $this->block('divider', []);
    }

    private function image(string $url): array
    {
        return $this->block('image', ['url' => $url, 'alt' => '']);
    }

    private function imageGrid(array $images, int $columns = 3): array
    {
        return $this->block('image_grid', [
            'columns' => $columns,
            'gap'     => 2,
            'images'  => array_map(fn($u) => ['url' => $u], $images),
        ]);
    }

    private function list(array $items): array
    {
        return $this->block('list', ['items' => $items]);
    }

    private function countdown(string $title, string $relative = '+30 days'): array
    {
        $target = now()->modify($relative)->toIso8601String();
        return $this->block('countdown', ['title' => $title, 'target_date' => $target]);
    }

    /** @param  array<int, array{question:string,answer:string}>  $items */
    private function faq(array $items): array
    {
        return $this->block('faq', ['items' => array_values($items)]);
    }

    /** @param  array<int, array{title:string,description:string,date:string}>  $items */
    private function timeline(array $items): array
    {
        return $this->block('timeline', ['items' => array_values($items)]);
    }

    private function testimonials(array $items): array
    {
        return $this->block('testimonials', ['items' => array_values($items)]);
    }

    private function review(string $name, int $rating, string $text, string $avatar = ''): array
    {
        return $this->block('review', [
            'name'   => $name,
            'avatar' => $avatar,
            'rating' => $rating,
            'text'   => $text,
        ]);
    }

    private function poll(string $question, array $options): array
    {
        return $this->block('poll', ['question' => $question, 'options' => array_values($options)]);
    }

    private function coupon(string $code, string $description, string $expires): array
    {
        return $this->block('coupon', ['code' => $code, 'description' => $description, 'expires' => $expires]);
    }

    private function product(string $name, string $description, string $price, string $image, string $url, string $badge = ''): array
    {
        return $this->block('product', [
            'name'            => $name,
            'description'     => $description,
            'price'          => $price,
            'image'          => $image,
            'url'            => $url,
            'badge'          => $badge,
            'native_checkout' => false,
        ]);
    }

    private function ctaButton(string $text, string $url, string $color = '#3d6bff', string $textColor = '#ffffff', string $size = 'lg'): array
    {
        return $this->block('cta_button', [
            'text'       => $text,
            'url'        => $url,
            'color'      => $color,
            'text_color' => $textColor,
            'size'       => $size,
        ]);
    }

    private function socials(): array
    {
        return $this->block('socials_multi', [
            'groups' => [[
                'label'     => 'Personal',
                'platforms' => [
                    ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle',    'display' => 'icon'],
                    ['name' => 'tiktok',    'url' => 'https://tiktok.com/@yourhandle',      'display' => 'icon'],
                    ['name' => 'youtube',   'url' => 'https://youtube.com/@yourhandle',     'display' => 'icon'],
                    ['name' => 'twitter',   'url' => 'https://x.com/yourhandle',            'display' => 'icon'],
                    ['name' => 'linkedin',  'url' => 'https://linkedin.com/in/yourhandle',  'display' => 'icon'],
                ],
            ]],
            'size'  => 'md',
            'style' => 'rounded',
        ]);
    }

    /* ──────────────────── realistic demo imagery ──────────────────── */

    /** Map a starter image key to an on-topic photo keyword. */
    private function starterKeyword(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'restaurant') => str_contains($key, 'hero') ? 'gourmet,food' : 'restaurant,interior',
            str_starts_with($key, 'event')      => 'concert,event',
            str_starts_with($key, 'portfolio')  => str_contains($key, 'print') ? 'art,print' : 'photography,art',
            str_starts_with($key, 'linkbio')    => 'creative,lifestyle',
            str_starts_with($key, 'personal')   => 'lifestyle,portrait',
            default                              => 'minimal,abstract',
        };
    }

    /**
     * Self-hosted placeholder image bundled with the app
     * (public/block-placeholders/*.svg). External photo CDNs (loremflickr)
     * can rate-limit, change, or disappear — which would make seeded template
     * previews look broken over time. Picked by aspect ratio so square slots
     * get the square art and wide banners get the cover art.
     */
    private function photo(string $keywords, int $w, int $h, string $seed): string
    {
        if ($w === $h) {
            return asset('block-placeholders/image-square.svg');
        }
        if ($h > 0 && $w / $h >= 2) {
            return asset('block-placeholders/cover.svg');
        }
        return asset('block-placeholders/image.svg');
    }

    /** Self-hosted avatar placeholder bundled with the app. */
    private function face(string $seed, int $size = 200): string
    {
        return asset('block-placeholders/avatar.svg');
    }

    /* ──────────────────── newer block helpers ──────────────────── */

    /** @param  array<int, array{question:string,answer:string,icon?:string}>  $items */
    private function faqV2(array $items): array
    {
        return $this->block('faq_v2', ['items' => array_values($items)]);
    }

    private function badge(string $text, string $color = '#3d6bff', string $textColor = '#ffffff'): array
    {
        return $this->block('badge', ['text' => $text, 'color' => $color, 'text_color' => $textColor]);
    }

    private function alert(string $text, string $type = 'info', string $icon = 'fa-info-circle'): array
    {
        return $this->block('alert', ['text' => $text, 'type' => $type, 'icon' => $icon]);
    }

    /** @param  array<int, array{label:string,value:int,color?:string}>  $items */
    private function progress(array $items): array
    {
        return $this->block('progress', ['items' => array_values($items)]);
    }

    /** @param  array<int, array{name:string,price:string,included?:bool}>  $items */
    private function listPricing(array $items): array
    {
        return $this->block('list_pricing', ['items' => array_values($items)]);
    }

    private function oneTimeOffer(string $title, string $description, string $price, string $originalPrice, string $url): array
    {
        return $this->block('one_time_offer', [
            'title'          => $title,
            'description'    => $description,
            'price'          => $price,
            'original_price' => $originalPrice,
            'url'            => $url,
        ]);
    }

    /** @param  array<int,string>  $images */
    private function imageSlider(array $images, int $interval = 3500): array
    {
        return $this->block('image_slider', [
            'images'   => array_map(fn($u) => ['url' => $u], array_values($images)),
            'interval' => $interval,
            'effect'   => 'fade',
        ]);
    }

    private function whatsapp(string $phone, string $buttonText = 'Chat on WhatsApp', string $message = ''): array
    {
        return $this->block('whatsapp_widget', [
            'phone'       => $phone,
            'button_text' => $buttonText,
            'message'     => $message,
        ]);
    }

    /** @param  array<int,int>  $amounts */
    private function donation(string $title, string $description, array $amounts, string $url): array
    {
        return $this->block('donation', [
            'title'       => $title,
            'description' => $description,
            'amounts'     => array_values($amounts),
            'url'         => $url,
        ]);
    }

    /** @param  array<int,int>  $amounts */
    private function buyMeCoffee(string $username, string $text, string $description, array $amounts): array
    {
        return $this->block('buy_me_coffee', [
            'username'    => $username,
            'text'        => $text,
            'description' => $description,
            'amounts'     => array_values($amounts),
        ]);
    }

    /* ──────────────── rich-media block helpers (v5) ──────────────── */

    private function spotify(string $url = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT', string $type = 'track'): array
    {
        return $this->block('spotify', ['url' => $url, 'type' => $type]);
    }

    private function appleMusic(string $url = 'https://music.apple.com/us/album/abbey-road-remastered/1441164426'): array
    {
        return $this->block('apple_music', ['url' => $url, 'type' => 'album']);
    }

    private function soundcloud(string $url = 'https://soundcloud.com/forss/flickermood'): array
    {
        return $this->block('soundcloud', ['url' => $url]);
    }

    private function audio(string $title, string $url = ''): array
    {
        return $this->block('audio', ['title' => $title, 'url' => $url !== '' ? $url : asset('block-placeholders/sample.mp3')]);
    }

    /** @param  array<int, array{title:string,artist?:string,url:string,cover?:string,duration?:string}>  $tracks */
    private function audioList(string $title, array $tracks): array
    {
        return $this->block('audio_list', ['title' => $title, 'layout' => 'compact', 'tracks' => array_values($tracks)]);
    }

    private function instagramMedia(string $url = 'https://www.instagram.com/p/CkQ7-gDgF8B/'): array
    {
        return $this->block('instagram_media', ['url' => $url]);
    }

    private function tiktokVideo(string $url = 'https://www.tiktok.com/@scout2015/video/6718335390845095173'): array
    {
        return $this->block('tiktok_video', ['url' => $url]);
    }

    private function twitterTweet(string $url = 'https://twitter.com/Twitter/status/1445078208190291973'): array
    {
        return $this->block('twitter_tweet', ['url' => $url]);
    }

    private function pdfDocument(string $title, string $url = ''): array
    {
        return $this->block('pdf_document', ['title' => $title, 'url' => $url !== '' ? $url : asset('block-placeholders/sample.pdf')]);
    }

    /** @param  array<int, array{label:string,text:string}>  $tabs */
    private function tabs(array $tabs): array
    {
        return $this->block('tabs', ['layout' => 'tabs', 'tabs' => array_values($tabs)]);
    }

    /** @param  array<int, array{title:string,body:string}>  $items */
    private function accordion(array $items): array
    {
        return $this->block('accordion', ['layout' => 'plain', 'items' => array_values($items)]);
    }

    /** @param  array<int,string>  $items */
    private function ticker(array $items): array
    {
        return $this->block('ticker', ['items' => array_values($items), 'speed' => 'normal']);
    }

    /** @param  array<int, array{value:string,label:string,caption?:string}>  $items */
    private function stats(string $title, array $items): array
    {
        return $this->block('stats', ['title' => $title, 'layout' => 'row', 'items' => array_values($items)]);
    }

    /** @param  array<int, array{quote:string,name:string,title?:string,avatar?:string}>  $items */
    private function testimonialCarousel(array $items): array
    {
        return $this->block('testimonial_carousel', ['layout' => 'carousel', 'items' => array_values($items)]);
    }

    private function reviewsWall(string $heading = 'What people are saying'): array
    {
        return $this->block('reviews_wall', ['heading' => $heading, 'source' => 'native', 'layout' => 'grid', 'limit' => 6]);
    }
}
