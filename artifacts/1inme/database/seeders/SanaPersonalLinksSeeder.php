<?php

namespace Database\Seeders;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Support\BlockDefaults;
use Illuminate\Database\Seeder;

/**
 * Sana's personal links, owned by the sana@sayzio.app account:
 *
 *   - sayzio.app/sana        → personal biolink page
 *   - sayzio.app/sana-vcard  → digital contact card (vCard download)
 *
 * Strictly scoped to that one account. Idempotent: the two aliases are
 * wiped and rebuilt on every run; nothing else is touched. Safe to run
 * on dev and (with DB_HOST override) production:
 *
 *   php artisan db:seed --class=SanaPersonalLinksSeeder
 */
class SanaPersonalLinksSeeder extends Seeder
{
    public const EMAIL = 'sana@sayzio.app';

    private const NAME_FIRST = 'Sana';
    private const NAME_LAST = 'Sandeep';
    private const PHONE = '+91 7013406816';
    private const PHONE_WA = '917013406816';
    private const SITE = 'https://sayzio.app';
    private const TAGLINE = 'Founder of Sayzio (formerly 1inme)';

    /** Publicly verified profiles (LinkedIn + GitHub; others were not verifiable). */
    private const LINKEDIN = 'https://www.linkedin.com/in/sanasandeep/';
    private const GITHUB = 'https://github.com/sanasandeep';

    /** Stable CDN copies of the public images (uploaded to the S3 user-content bucket). */
    private const CDN = 'https://d3l7wvr1shk1cg.cloudfront.net/media/sana';

    public function run(): void
    {
        $user = User::where('email', static::EMAIL)->first();
        if (!$user) {
            $this->command?->warn('SanaPersonalLinksSeeder: ' . static::EMAIL . ' not found; skipping.');
            return;
        }

        $workspace = $user->ensureDefaultWorkspace();
        app()->instance('current_workspace', $workspace);

        try {
            // Idempotency: rebuild only these two aliases, owned by this user.
            Link::where('user_id', $user->id)
                ->whereIn('alias', ['sana', 'sana-vcard'])
                ->get()
                ->each(fn (Link $l) => $l->delete());

            $vcard = $this->seedVcardLink($user);
            $this->seedBiolink($user);

            $this->command?->info(sprintf(
                'Sana personal links ready: %s/sana (biolink) and %s/sana-vcard (vcard, link id %d).',
                self::SITE, self::SITE, $vcard->id
            ));
        } finally {
            app()->forgetInstance('current_workspace');
        }
    }

    private function seedVcardLink(User $user): Link
    {
        $link = Link::create([
            'user_id' => $user->id,
            'type' => 'vcf',
            'alias' => 'sana-vcard',
            'title' => 'Sana Sandeep — Digital Card',
            'is_active' => true,
            'visibility' => 'public',
        ]);

        VcfData::create([
            'link_id' => $link->id,
            'first_name' => self::NAME_FIRST,
            'last_name' => self::NAME_LAST,
            'organization' => 'Sayzio',
            'title' => 'Founder',
            'photo_path' => $this->avatarUrl(),
            'emails' => [['label' => 'Work', 'value' => static::EMAIL]],
            'phones' => [['label' => 'Mobile', 'value' => self::PHONE]],
            'urls' => [
                ['label' => 'Website', 'value' => self::SITE . '/sana'],
                ['label' => 'Work', 'value' => self::SITE],
            ],
            'note' => self::TAGLINE . '. One link for everything.',
        ]);

        return $link;
    }

    private function seedBiolink(User $user): Link
    {
        $link = Link::create([
            'user_id' => $user->id,
            'type' => 'biolink',
            'alias' => 'sana',
            'title' => 'Sana Sandeep — Founder of Sayzio',
            'is_active' => true,
            'visibility' => 'public',
            'settings' => [
                'biolink' => [
                    'biolink_title' => 'Sana Sandeep',
                    'biolink_description' => self::TAGLINE . ' — get in touch or save my contact card.',
                ],
            ],
        ]);

        $defs = [
            ['avatar', ['url' => $this->avatarUrl(), 'size' => 112, 'rounded' => true]],
            ['heading', ['text' => 'Sana Sandeep', 'size' => 'h1', 'align' => 'center', 'style' => 'plain']],
            ['paragraph', ['text' => self::TAGLINE . '. Building the one-link home for creators and businesses — links, biolinks, QR codes and analytics in one place.', 'align' => 'center']],
            ['socials', ['size' => 'md', 'platforms' => [
                ['name' => 'linkedin', 'url' => self::LINKEDIN],
                ['name' => 'github', 'url' => self::GITHUB],
            ]]],
            ['heading', ['text' => 'About', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['paragraph', ['text' => 'Entrepreneur and product builder from India. I started 1INME as a simple link-in-bio tool and grew it into Sayzio — a full link-management platform with biolinks, short links, QR Studio, forms, digital cards and live analytics. Along the way I have shipped products in travel and education, and I love turning everyday friction into simple software.', 'align' => 'center']],
            ['vcard', [
                'name' => self::NAME_FIRST . ' ' . self::NAME_LAST,
                'title' => 'Founder',
                'company' => 'Sayzio',
                'phone' => self::PHONE,
                'email' => static::EMAIL,
                'website' => self::SITE . '/sana',
            ]],
            ['heading', ['text' => 'Get in touch', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['link', ['text' => 'Save my contact card', 'url' => self::SITE . '/sana-vcard', 'icon' => 'fa-id-card']],
            ['link', ['text' => 'Email me', 'url' => 'mailto:' . static::EMAIL, 'icon' => 'fa-envelope']],
            ['link', ['text' => 'Call me', 'url' => 'tel:' . str_replace(' ', '', self::PHONE), 'icon' => 'fa-phone']],
            ['link', ['text' => 'WhatsApp me', 'url' => 'https://wa.me/' . self::PHONE_WA, 'icon' => 'fa-brands fa-whatsapp']],
            ['heading', ['text' => 'Scan to save my contact', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['qr_code', ['url' => $this->contactVcardPayload(), 'size' => 220]],
            ['paragraph', ['text' => 'Point your camera at the code above and my contact card (name, phone and email) saves straight to your phone.', 'align' => 'center']],
            ['heading', ['text' => 'Projects', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['link', ['text' => 'Sayzio — link in bio, short links, QR codes & analytics in one place', 'url' => self::SITE, 'icon' => 'fa-link']],
            ['link', ['text' => '1INME — the original one-link project that grew into Sayzio', 'url' => self::SITE, 'icon' => 'fa-clock-rotate-left']],
            ['link', ['text' => 'Ustay — simple, fast hotel-stay booking app for India', 'url' => 'https://play.google.com/store/apps/details?id=com.ustay.app', 'icon' => 'fa-hotel']],
            ['link', ['text' => 'MySlate — bite-sized, gamified learning app for K-12 students', 'url' => 'https://myslates.com', 'icon' => 'fa-graduation-cap']],
            ['link', ['text' => 'See what you can create with Sayzio', 'url' => self::SITE . '/demos', 'icon' => 'fa-wand-magic-sparkles']],
            ['heading', ['text' => 'Gallery', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['image_grid', ['columns' => 3, 'gap' => 2, 'images' => [
                ['url' => self::CDN . '/portrait.png', 'alt' => 'Sana Sandeep'],
                ['url' => self::CDN . '/sayzio-app.png', 'alt' => 'The Sayzio app'],
                ['url' => self::CDN . '/sayzio-brand.jpg', 'alt' => 'Sayzio — one link for everything'],
            ]]],
            ['paragraph', ['text' => 'Made with Sayzio.', 'align' => 'center']],
        ];

        foreach ($defs as $i => [$type, $settings]) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($type)
            );
            BiolinkBlock::forceCreate([
                'link_id' => $link->id,
                'type' => $type,
                'settings' => $settings,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        return $link;
    }

    /**
     * Raw vCard 3.0 payload for the "scan to save contact" QR block. The
     * qr_code renderer urlencodes this into the QR image, so scanning it
     * saves the contact directly (no URL hop). Written by direct DB seed on
     * purpose — editor saves would blank a non-http(s) "url" value.
     */
    private function contactVcardPayload(): string
    {
        return implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . self::NAME_LAST . ';' . self::NAME_FIRST . ';;;',
            'FN:' . self::NAME_FIRST . ' ' . self::NAME_LAST,
            'ORG:Sayzio',
            'TITLE:Founder',
            'TEL;TYPE=CELL:' . str_replace(' ', '', self::PHONE),
            'EMAIL;TYPE=WORK:' . static::EMAIL,
            'URL:' . self::SITE . '/sana',
            'END:VCARD',
        ]);
    }

    private function avatarUrl(): string
    {
        return 'https://ui-avatars.com/api/?name=Sana+Sandeep&size=320&background=2563eb&color=ffffff&bold=true&format=png';
    }
}
