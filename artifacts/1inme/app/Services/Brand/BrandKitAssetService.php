<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandKitAsset;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\BrandAssetImageClient;
use App\Services\Billing\LetterheadValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * AI-generated Brand Kit visual assets (Task #5612).
 *
 * Turns a saved Brand Kit's identity (palette, fonts, voice, brief) into
 * ready-to-use images: logo, favicon, letterhead, social banners, avatar,
 * OG image, business card, email banner, background and watermark. Billing
 * mirrors AI Artistic QR: a flat per-generation coin charge (admin-set base
 * × per-type weight × the user's OpenAI plan multiplier) taken up-front via
 * {@see AiUsageCharger}, auto-refunded if rendering or storage fails.
 *
 * Availability is plan-gated by the legacy-safe `brand_kit_assets` feature;
 * regenerations per asset are capped by the per-plan
 * `max_brand_asset_versions` quantity key (-1 = unlimited).
 *
 * Each kit stores at most one asset per type — regenerating (optionally
 * with tweak instructions) replaces the previous image, deletes the old
 * {@see UserFile} and bumps `version`. Rendered PNGs live in the user's
 * file vault (S3, quota-counted).
 */
class BrandKitAssetService
{
    public const FEATURE = 'brand_asset';

    /** Fallback regeneration cap for plans that predate the key. */
    public const VERSIONS_FALLBACK = 5;

    /**
     * The asset catalog. `size` must be a gpt-image-1 size; `weight`
     * scales the admin base coin price (bigger canvases cost more).
     *
     * @var array<string,array{label:string,size:string,weight:float,hint:string}>
     */
    public const TYPES = [
        'logo'          => ['label' => 'Logo',            'size' => '1024x1024', 'weight' => 1.5, 'hint' => 'A clean primary logo mark on a plain background.'],
        'favicon'       => ['label' => 'Favicon',         'size' => '1024x1024', 'weight' => 1.0, 'hint' => 'A simple, bold icon that stays readable at 32px.'],
        'letterhead'    => ['label' => 'Letterhead',      'size' => '1024x1536', 'weight' => 1.5, 'hint' => 'An elegant A4 portrait letterhead background with header/footer accents and lots of clear space.'],
        'social_banner' => ['label' => 'Social banner',   'size' => '1536x1024', 'weight' => 1.5, 'hint' => 'A wide social profile cover banner.'],
        'avatar'        => ['label' => 'Profile avatar',  'size' => '1024x1024', 'weight' => 1.0, 'hint' => 'A circular-crop-friendly brand avatar.'],
        'og_image'      => ['label' => 'OG share image',  'size' => '1536x1024', 'weight' => 1.5, 'hint' => 'A link-preview share card with strong branding.'],
        'business_card' => ['label' => 'Business card',   'size' => '1536x1024', 'weight' => 1.5, 'hint' => 'A modern business-card front design.'],
        'email_banner'  => ['label' => 'Email banner',    'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A slim, wide email header banner.'],
        'background'    => ['label' => 'Background',      'size' => '1024x1536', 'weight' => 1.0, 'hint' => 'A subtle on-brand page/phone background texture.'],
        'watermark'     => ['label' => 'Watermark',       'size' => '1024x1024', 'weight' => 1.0, 'hint' => 'A minimal monochrome watermark mark on a plain background.'],
        'tagline_card'  => ['label' => 'Tagline card',    'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A bold typographic card built around the brand tagline — large, legible headline treatment.'],
        'mission_card'  => ['label' => 'Mission card',    'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'An inspiring "Our mission" statement card with clear headline space for the mission text.'],
        'vision_card'   => ['label' => 'Vision card',     'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A forward-looking "Our vision" statement card with clear headline space for the vision text.'],
        'stats_card'    => ['label' => 'Stats card',      'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A clean infographic-style card with placeholder stat blocks (big numbers + short labels) for key metrics.'],
        'ppt_cover'     => ['label' => 'Deck cover slide','size' => '1536x1024', 'weight' => 1.5, 'hint' => 'A 16:9 presentation title-slide design with a strong hero area and space for a deck title.'],
        'ppt_slide'     => ['label' => 'Deck slide background', 'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A quiet 16:9 presentation content-slide background with generous clear space for text.'],
        'ppt_closing'   => ['label' => 'Deck closing slide', 'size' => '1536x1024', 'weight' => 1.0, 'hint' => 'A 16:9 "thank you / contact" closing-slide design with space for contact details.'],
    ];

    /** Regeneration modes for an asset that already exists. */
    public const MODES = ['new', 'variation', 'alteration'];

    public function __construct(
        protected AiUsageCharger $charger,
        protected BrandAssetImageClient $images,
    ) {}

    /** Image generation is usable (engine on + OpenAI key stored). */
    public function enabled(): bool
    {
        return $this->images->enabled();
    }

    /** Effective coin cost for one generation of $type for $user. */
    public function coinCost(User $user, string $type): int
    {
        $base   = AiEngineSettings::brandAssetCoinsPerGeneration();
        $weight = (float) (self::TYPES[$type]['weight'] ?? 1.0);
        $mult   = AiPlanAccess::coinMultiplier($user, 'openai');
        return max(1, (int) ceil($base * $weight * $mult));
    }

    /**
     * Catalog + per-type cost/current-asset map for UI/API payloads.
     *
     * @return list<array<string,mixed>>
     */
    public function catalogFor(User $user, BrandKit $kit): array
    {
        $assets = BrandKitAsset::where('brand_kit_id', $kit->id)
            ->with('file')
            ->get()
            ->keyBy('type');

        $out = [];
        foreach (self::TYPES as $type => $meta) {
            $asset = $assets->get($type);
            $out[] = [
                'type'          => $type,
                'label'         => $meta['label'],
                'hint'          => $meta['hint'],
                'size'          => $meta['size'],
                'cost'          => $this->coinCost($user, $type),
                'apply_targets' => self::applyTargetsFor($type),
                'asset'         => $asset ? $this->present($asset) : null,
            ];
        }
        return $out;
    }

    /**
     * One-click apply targets that make sense for a given asset type.
     *
     * @return string[]
     */
    public static function applyTargetsFor(string $type): array
    {
        return match ($type) {
            'logo', 'avatar', 'watermark' => ['kit_logo'],
            'favicon'                     => ['kit_logo', 'biolink_favicon'],
            'og_image', 'social_banner'   => ['biolink_og'],
            'letterhead'                  => ['company_letterhead'],
            default                       => [],
        };
    }

    /** @return array<string,mixed> */
    public function present(BrandKitAsset $asset): array
    {
        return [
            'id'            => (int) $asset->id,
            'type'          => (string) $asset->type,
            'status'        => (string) $asset->status,
            'version'       => (int) $asset->version,
            'credits_spent' => (int) $asset->credits_spent,
            'prompt'        => $asset->prompt,
            'image_url'     => $asset->file?->url,
            'download_url'  => $asset->file?->url,
            'created_at'    => $asset->created_at?->toIso8601String(),
            'updated_at'    => $asset->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate (or regenerate) one asset. Charges coins up-front and
     * refunds automatically when rendering or storage fails.
     *
     * @throws RuntimeException on any user-facing failure (already refunded)
     */
    public function generate(User $user, BrandKit $kit, string $type, ?string $instructions = null, string $mode = 'new'): BrandKitAsset
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Unknown asset type.');
        }
        if (!in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Unknown generation mode.');
        }
        if (!$this->enabled()) {
            throw new RuntimeException('AI image generation is not available right now.');
        }
        if (!AiPlanAccess::featureAllowed($user, 'brand_kit_assets')) {
            throw new RuntimeException('Brand asset generation is not included in your plan.');
        }

        $existing = BrandKitAsset::where('brand_kit_id', $kit->id)->where('type', $type)->first();

        // Per-plan regeneration cap: version N means N generations so far.
        $used = (int) ($existing->version ?? 0);
        if (!AiPlanAccess::underQuantityCap($user, 'brand_asset_versions', $used)) {
            throw new RuntimeException(AiPlanAccess::quantityLimitMessage(
                $user, 'brand_asset_versions', 'generation for this asset', $used
            ));
        }

        $meta = self::TYPES[$type];
        $cost = $this->coinCost($user, $type);
        $tx = $this->charger->charge($user, $cost, [
            'feature'  => self::FEATURE,
            'provider' => 'openai',
            'reason'   => 'Brand asset — ' . $meta['label'],
            'meta'     => ['brand_kit_id' => $kit->id, 'type' => $type, 'mode' => $mode],
        ]);

        try {
            $bytes = $this->images->generate(
                $this->buildPrompt($kit, $type, $instructions, $mode, $existing),
                $meta['size']
            );
            // Stored outside the vault's max_files count (context tag) but
            // still counted toward the storage-byte quota.
            $file  = UserFile::createFromBytes(
                $bytes,
                'brand-' . $type . '-' . substr(md5($kit->id . microtime()), 0, 8) . '.png',
                'image/png',
                $user,
                ['skip_scan' => true, 'context' => 'brand_asset']
            );
        } catch (\Throwable $e) {
            try {
                $this->charger->refund($user, $cost, [
                    'feature'         => self::FEATURE,
                    'provider'        => 'openai',
                    'reason'          => 'Brand asset refund',
                    'idempotency_key' => 'brand_asset_refund:' . $tx->id,
                    'meta'            => ['related_id' => $tx->id],
                ]);
            } catch (\Throwable $refundError) {
                Log::error('Brand asset refund failed: ' . $refundError->getMessage());
            }
            throw new RuntimeException(
                $e instanceof RuntimeException ? $e->getMessage() : 'Brand asset generation failed. You have not been charged.'
            );
        }

        // Replace: drop the previous stored image before pointing at the new one.
        $oldFile = $existing?->file;

        $asset = BrandKitAsset::updateOrCreate(
            ['brand_kit_id' => $kit->id, 'type' => $type],
            [
                'user_id'       => $user->id,
                'status'        => BrandKitAsset::STATUS_READY,
                'user_file_id'  => $file->id,
                'prompt'        => $instructions !== null && trim($instructions) !== '' ? trim($instructions) : null,
                'params'        => ['size' => $meta['size'], 'model' => BrandAssetImageClient::MODEL, 'mode' => $mode],
                'version'       => $used + 1,
                'credits_spent' => $cost,
            ],
        );

        if ($oldFile) {
            try {
                $oldFile->deleteFile();
            } catch (\Throwable $e) {
                Log::warning('Brand asset old file cleanup failed: ' . $e->getMessage());
            }
        }

        return $asset->load('file');
    }

    /** Delete an asset and its stored file. */
    public function delete(BrandKitAsset $asset): void
    {
        $file = $asset->file;
        $asset->delete();
        if ($file) {
            try {
                $file->deleteFile();
            } catch (\Throwable $e) {
                Log::warning('Brand asset file cleanup failed: ' . $e->getMessage());
            }
        }
    }

    // ── Apply targets ──────────────────────────────────────────────────

    /** logo / avatar / watermark → the kit's own config logo_url. */
    public function applyLogoToKit(BrandKitAsset $asset, BrandKit $kit): void
    {
        $url = $asset->file?->url;
        if (!$url) {
            throw new RuntimeException('This asset has no stored image to apply.');
        }
        $config = is_array($kit->config) ? $kit->config : [];
        $config['logo_url'] = $url;
        $kit->update(['config' => $config]);
    }

    /** favicon asset → a biolink's favicon. */
    public function applyFaviconToLink(BrandKitAsset $asset, Link $link): void
    {
        $url = $asset->file?->url;
        if (!$url) {
            throw new RuntimeException('This asset has no stored image to apply.');
        }
        $link->update(['favicon' => $url]);
    }

    /** og_image asset → a biolink's SEO share image. */
    public function applyOgToLink(BrandKitAsset $asset, Link $link): void
    {
        $url = $asset->file?->url;
        if (!$url) {
            throw new RuntimeException('This asset has no stored image to apply.');
        }
        $link->update(['seo_image' => $url]);
    }

    /**
     * letterhead asset → a BillingCompany's default letterhead. Copies the
     * bytes onto the public disk (where invoice rendering reads them) and
     * records the pixel dimensions, matching the manual-upload flow.
     *
     * @param \App\Modules\User\Models\BillingCompany $company
     */
    public function applyLetterheadToCompany(BrandKitAsset $asset, $company): void
    {
        $file = $asset->file;
        if (!$file) {
            throw new RuntimeException('This asset has no stored image to apply.');
        }

        $sourceDisk = $file->disk === 'public' ? 'public' : ($file->disk === 's3' ? 's3' : 'user_files');
        $bytes = Storage::disk($sourceDisk)->get($file->path);
        if (!is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Could not read the stored asset image.');
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false) {
            throw new RuntimeException('The stored asset is not a valid image.');
        }
        [$width, $height] = $size;
        if ($width < LetterheadValidator::MIN_WIDTH || $height < LetterheadValidator::MIN_HEIGHT
            || $width > LetterheadValidator::MAX_WIDTH || $height > LetterheadValidator::MAX_HEIGHT) {
            throw new RuntimeException('The generated letterhead does not meet the letterhead size requirements.');
        }

        $path = 'billing/letterheads/brand-asset-' . $asset->id . '-' . Str::random(8) . '.png';
        Storage::disk('public')->put($path, $bytes);

        $old = $company->letterhead_path;
        $company->update([
            'letterhead_path'   => $path,
            'letterhead_width'  => $width,
            'letterhead_height' => $height,
        ]);
        if ($old && $old !== $path) {
            try {
                Storage::disk('public')->delete($old);
            } catch (\Throwable $e) {
                // best-effort cleanup
            }
        }
    }

    // ── Prompt building ────────────────────────────────────────────────

    /**
     * Compose the image prompt from the kit identity + type recipe.
     *
     * Modes (only meaningful when the asset already exists):
     *   new        — fresh generation from the brand brief (default)
     *   variation  — a distinctly different creative take on the same brief
     *   alteration — keep the previous direction, apply the tweaks in
     *                $instructions (the previous tweak prompt is carried
     *                forward as context)
     */
    public function buildPrompt(BrandKit $kit, string $type, ?string $instructions = null, string $mode = 'new', ?BrandKitAsset $previous = null): string
    {
        $config  = is_array($kit->config) ? $kit->config : [];
        $palette = is_array($config['palette'] ?? null) ? $config['palette'] : [];
        $fonts   = is_array($config['fonts'] ?? null) ? $config['fonts'] : [];
        $voice   = is_array($config['voice'] ?? null) ? $config['voice'] : [];

        $colors = array_values(array_filter(array_merge(
            [$palette['primary'] ?? null, $palette['secondary'] ?? null, $palette['accent'] ?? null],
            array_slice((array) ($palette['neutrals'] ?? []), 0, 2),
        )));

        $lines = [
            'Design a ' . strtolower(self::TYPES[$type]['label']) . ' for the brand "' . $kit->name . '".',
            self::TYPES[$type]['hint'],
        ];

        // Text-bearing cards get the kit's actual words so the artwork can
        // feature them (tagline) or leave properly-shaped space for them.
        $taglines = array_values(array_filter((array) ($config['taglines'] ?? []), 'is_string'));
        if ($type === 'tagline_card' && $taglines) {
            $lines[] = 'The card must feature this exact tagline as the headline: "' . mb_substr($taglines[0], 0, 140) . '".';
        } elseif (in_array($type, ['mission_card', 'vision_card', 'stats_card', 'ppt_cover', 'ppt_closing'], true) && $taglines) {
            $lines[] = 'The brand tagline (may appear small as a supporting line): "' . mb_substr($taglines[0], 0, 140) . '".';
        }
        if ($colors) {
            $lines[] = 'Use ONLY this brand color palette: ' . implode(', ', $colors) . '.';
        }
        if (!empty($fonts['heading'])) {
            $lines[] = 'Typography should feel like the "' . $fonts['heading'] . '" typeface.';
        }
        if (!empty($voice['tone'])) {
            $lines[] = 'Overall mood: ' . $voice['tone'] . '.';
        }
        if (!empty($config['bio'])) {
            $lines[] = 'Brand context: ' . mb_substr((string) $config['bio'], 0, 300);
        }
        $lines[] = 'Flat, professional, production-ready design. No watermarks, no lorem ipsum, no mockup photos.';

        // Regeneration mode context (only when a previous version exists).
        if ($previous) {
            $prevPrompt = trim((string) $previous->prompt);
            if ($mode === 'variation') {
                $lines[] = 'This is a VARIATION request: produce a distinctly different creative take (new layout/composition) while staying strictly on the same brand palette and mood.';
                if ($prevPrompt !== '') {
                    $lines[] = 'The previous version used these owner instructions (explore a different direction from them): ' . mb_substr($prevPrompt, 0, 300);
                }
            } elseif ($mode === 'alteration') {
                $lines[] = 'This is an ALTERATION request: keep the previous version\'s overall direction and composition, changing only what the owner asks for below.';
                if ($prevPrompt !== '') {
                    $lines[] = 'The previous version was generated with these owner instructions (carry them forward unless contradicted): ' . mb_substr($prevPrompt, 0, 300);
                }
            }
        }

        $instructions = trim((string) $instructions);
        if ($instructions !== '') {
            $lines[] = 'Additional instructions from the brand owner: ' . mb_substr($instructions, 0, 500);
        }

        return implode("\n", $lines);
    }
}
