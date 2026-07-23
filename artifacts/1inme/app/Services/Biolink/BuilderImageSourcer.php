<?php

namespace App\Services\Biolink;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\BrandAssetImageClient;
use App\Services\OgMetadataService;
use Illuminate\Support\Facades\Log;

/**
 * Auto-sources images for the AI biolink builder (Task #5720) when the
 * creator supplies none themselves. Strict priority order:
 *
 *   1. User uploads — if any were attached, they win outright; nothing
 *      is fetched or generated.
 *   2. Extraction — the first few supplied links are scanned via
 *      {@see OgMetadataService} (og:image, then favicon); candidates are
 *      downloaded SSRF-safe, validated as real images, de-duplicated by
 *      content hash, and stored in the creator's vault (quota-counted,
 *      context `ai_builder`). Extraction is free.
 *   3. Generation — with no uploads and nothing extractable, an avatar
 *      and a cover are AI-generated (gpt-image-1 via
 *      {@see BrandAssetImageClient}), each charged up-front in coins via
 *      {@see AiUsageCharger} with an automatic refund if rendering or
 *      storage fails.
 *
 * Every step is best-effort: a page still builds with zero images. If the
 * overall build later fails, {@see rollback()} refunds generation charges
 * and deletes the freshly generated files so the creator pays nothing for
 * a page they never got.
 */
class BuilderImageSourcer
{
    /** Only the first few links are scanned — bounded outbound work. */
    public const MAX_LINKS_SCANNED = 5;

    /** Cap on extracted images fed to the model. */
    public const MAX_EXTRACTED = 6;

    /** What the generation fallback produces: slot => gpt-image-1 size. */
    public const GENERATED_SLOTS = [
        'avatar' => '1024x1024',
        'cover'  => '1536x1024',
    ];

    public function __construct(
        protected OgMetadataService $og,
        protected BrandAssetImageClient $images,
        protected AiUsageCharger $charger,
    ) {}

    /** Image generation is usable (engine on + OpenAI key stored). */
    public function generationEnabled(): bool
    {
        return $this->images->enabled();
    }

    /** Coin cost for ONE generated fallback image for this user. */
    public function generationCoinCost(User $user): int
    {
        $base = AiEngineSettings::brandAssetCoinsPerGeneration();
        $mult = AiPlanAccess::coinMultiplier($user, 'openai');

        return max(1, (int) ceil($base * $mult));
    }

    /**
     * Worst-case extra coins the fallback may add to a build (used by the
     * upfront estimate when the creator attached no images). Zero when
     * generation isn't available.
     */
    public function fallbackGenerationEstimate(User $user): int
    {
        if (!$this->generationEnabled()) {
            return 0;
        }

        return count(self::GENERATED_SLOTS) * $this->generationCoinCost($user);
    }

    /**
     * Resolve the image set for a build.
     *
     * @param list<string> $links          cleaned absolute http(s) URLs
     * @param list<string> $uploadedImages cleaned user-supplied image URLs
     * @return array{
     *   images: list<string>,
     *   uploaded: int,
     *   extracted: list<string>,
     *   generated: list<array{url:string,file_id:int,tx_id:int,cost:int}>
     * }
     */
    public function source(User $user, string $description, array $links, array $uploadedImages, ?int $relatedLinkId = null): array
    {
        $out = [
            'images'    => array_values($uploadedImages),
            'uploaded'  => count($uploadedImages),
            'extracted' => [],
            'generated' => [],
        ];

        // 1. Uploads win outright.
        if ($out['uploaded'] > 0) {
            return $out;
        }

        // 2. Pull og:image / favicon candidates from the supplied links.
        $out['extracted'] = $this->extractFromLinks($user, $links);
        if ($out['extracted'] !== []) {
            $out['images'] = $out['extracted'];

            return $out;
        }

        // 3. Nothing supplied and nothing extractable → generate.
        $out['generated'] = $this->generateFallback($user, $description, $relatedLinkId);
        $out['images']    = array_values(array_map(static fn (array $g) => $g['url'], $out['generated']));

        return $out;
    }

    /**
     * Refund + remove any generated images after a failed build so the
     * creator isn't charged for artwork on a page that never materialized.
     * Extracted images are free and left in the vault (harmless, reusable).
     *
     * @param array{generated?:list<array{url:string,file_id:int,tx_id:int,cost:int}>} $sourced
     */
    public function rollback(User $user, array $sourced): void
    {
        foreach ($sourced['generated'] ?? [] as $g) {
            try {
                $this->charger->refund($user, (int) $g['cost'], [
                    'feature'         => AiBiolinkBuilderService::FEATURE,
                    'provider'        => 'openai',
                    'reason'          => 'AI builder image refund (build failed)',
                    'idempotency_key' => 'ai_builder_image_rollback:' . $g['tx_id'],
                    'meta'            => ['related_id' => $g['tx_id']],
                ]);
            } catch (\Throwable $e) {
                Log::error('AI builder image rollback refund failed: ' . $e->getMessage());
            }

            try {
                UserFile::find($g['file_id'])?->deleteFile();
            } catch (\Throwable $e) {
                Log::warning('AI builder image rollback cleanup failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Scan the first links for og:image/favicon, download SSRF-safe,
     * validate, de-dupe by content hash, and store in the vault.
     *
     * @param list<string> $links
     * @return list<string> relative vault URLs (`/f/{id}/{filename}`)
     */
    protected function extractFromLinks(User $user, array $links): array
    {
        $stored         = [];
        $seenCandidates = [];
        $seenHashes     = [];

        foreach (array_slice($links, 0, self::MAX_LINKS_SCANNED) as $pageUrl) {
            if (count($stored) >= self::MAX_EXTRACTED) {
                break;
            }

            try {
                $meta = $this->og->extractFromUrl($pageUrl);
            } catch (\Throwable $e) {
                continue; // unreachable/blocked page — skip silently
            }

            foreach ([$meta['image_url'] ?? null, $meta['favicon_url'] ?? null] as $candidate) {
                if (count($stored) >= self::MAX_EXTRACTED) {
                    break 2;
                }
                if (!is_string($candidate) || $candidate === '' || isset($seenCandidates[$candidate])) {
                    continue;
                }
                $seenCandidates[$candidate] = true;

                $img = $this->og->downloadImage($candidate);
                if ($img === null) {
                    continue;
                }

                $hash = md5($img['bytes']);
                if (isset($seenHashes[$hash])) {
                    continue;
                }
                $seenHashes[$hash] = true;

                try {
                    $file = UserFile::createFromBytes(
                        $img['bytes'],
                        'ai-builder-' . substr($hash, 0, 8) . '.' . $this->extensionFor($img['mime']),
                        $img['mime'],
                        $user,
                        ['skip_scan' => true, 'context' => 'ai_builder'],
                    );
                } catch (\Throwable $e) {
                    // Quota/storage problems must never sink the build.
                    Log::info('AI builder image store skipped: ' . $e->getMessage());
                    continue;
                }

                $stored[] = $file->url_path;

                // One image per source page is plenty — favicon is only the
                // fallback when the page had no usable og:image.
                continue 2;
            }
        }

        return $stored;
    }

    /**
     * Generate the avatar + cover fallback. Each image is charged
     * up-front and refunded individually if its render/store fails;
     * failures degrade to fewer (or zero) images, never an exception.
     *
     * @return list<array{url:string,file_id:int,tx_id:int,cost:int}>
     */
    protected function generateFallback(User $user, string $description, ?int $relatedLinkId): array
    {
        if (!$this->generationEnabled()) {
            return [];
        }

        $generated = [];
        foreach (self::GENERATED_SLOTS as $slot => $size) {
            $cost = $this->generationCoinCost($user);

            try {
                $tx = $this->charger->charge($user, $cost, [
                    'feature'  => AiBiolinkBuilderService::FEATURE,
                    'provider' => 'openai',
                    'reason'   => 'AI builder image — ' . $slot,
                    'meta'     => ['slot' => $slot, 'related_id' => $relatedLinkId],
                ]);
            } catch (\Throwable $e) {
                // Not enough coins for artwork — build the page without it.
                Log::info('AI builder image charge skipped (' . $slot . '): ' . $e->getMessage());
                continue;
            }

            try {
                $bytes = $this->images->generate($this->promptFor($slot, $description), $size);
                $file  = UserFile::createFromBytes(
                    $bytes,
                    'ai-builder-' . $slot . '-' . substr(md5($slot . microtime()), 0, 8) . '.png',
                    'image/png',
                    $user,
                    ['skip_scan' => true, 'context' => 'ai_builder'],
                );
            } catch (\Throwable $e) {
                try {
                    $this->charger->refund($user, $cost, [
                        'feature'         => AiBiolinkBuilderService::FEATURE,
                        'provider'        => 'openai',
                        'reason'          => 'AI builder image refund',
                        'idempotency_key' => 'ai_builder_image_refund:' . $tx->id,
                        'meta'            => ['related_id' => $tx->id],
                    ]);
                } catch (\Throwable $refundError) {
                    Log::error('AI builder image refund failed: ' . $refundError->getMessage());
                }
                Log::info('AI builder image generation failed (' . $slot . '): ' . $e->getMessage());
                continue;
            }

            $generated[] = [
                'url'     => $file->url_path,
                'file_id' => (int) $file->id,
                'tx_id'   => (int) $tx->id,
                'cost'    => $cost,
            ];
        }

        return $generated;
    }

    /** Image prompt for one fallback slot, grounded in the page brief. */
    protected function promptFor(string $slot, string $description): string
    {
        $brief = mb_substr(trim($description), 0, 500);

        $lines = match ($slot) {
            'avatar' => [
                'Design a circular-crop-friendly profile avatar for a link-in-bio page.',
                'A clean, bold, iconic mark or illustration — no photographic faces.',
            ],
            default => [
                'Design a wide cover/banner image for a link-in-bio page.',
                'An atmospheric, on-theme hero visual with gentle composition and clear space.',
            ],
        };

        $lines[] = 'The page is about: ' . ($brief !== '' ? $brief : 'a personal links page.');
        $lines[] = 'Flat, professional, production-ready. No text, no watermarks, no lorem ipsum.';

        return implode("\n", $lines);
    }

    /** File extension for a validated image MIME. */
    protected function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
            default      => 'ico',
        };
    }
}
