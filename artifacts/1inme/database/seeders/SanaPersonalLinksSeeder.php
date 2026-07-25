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
            ['heading', ['text' => 'Sayzio', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['link', ['text' => 'Explore Sayzio — one link for everything', 'url' => self::SITE, 'icon' => 'fa-link']],
            ['link', ['text' => 'See what you can create', 'url' => self::SITE . '/demos', 'icon' => 'fa-wand-magic-sparkles']],
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

    private function avatarUrl(): string
    {
        return 'https://ui-avatars.com/api/?name=Sana+Sandeep&size=320&background=2563eb&color=ffffff&bold=true&format=png';
    }
}
