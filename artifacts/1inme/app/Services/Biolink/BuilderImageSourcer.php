<?php

namespace App\Services\Biolink;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\BrandAssetImageClient;
use App\Services\Integrations\GoogleImageSearchService;
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
 *   3. Web search — with no uploads and nothing extractable, and Google
 *      image search configured, real photos matching the page brief are
 *      searched on the public web, downloaded SSRF-safe, validated, and
 *      stored in the vault. Free — no AI credits.
 *   4. Generation — with no uploads and nothing extractable, an avatar
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

    /** Cap on stored images per source page (og/favicon + content). */
    public const MAX_PER_PAGE = 3;

    /** Minimum decoded pixel dimension for in-page content images. */
    public const MIN_CONTENT_DIMENSION = 200;

    /** Cap on auto web-searched images fed to the model. */
    public const MAX_SEARCHED = 3;

    /** Minimum decoded pixel dimension for web-searched photos. */
    public const MIN_SEARCH_DIMENSION = 200;

    /** What the generation fallback produces: slot => gpt-image-1 size. */
    public const GENERATED_SLOTS = [
        'avatar' => '1024x1024',
        'cover'  => '1536x1024',
    ];

    public function __construct(
        protected OgMetadataService $og,
        protected BrandAssetImageClient $images,
        protected AiUsageCharger $charger,
        protected GoogleImageSearchService $webSearch,
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
     * generation isn't available. When the creator explicitly skipped
     * generation slots in the image preview step (Task #5722) only the
     * remaining slots are counted.
     *
     * @param list<string> $skipSlots slot names ('avatar'/'cover') to exclude
     */
    public function fallbackGenerationEstimate(User $user, array $skipSlots = []): int
    {
        if (!$this->generationEnabled()) {
            return 0;
        }

        $slots = array_diff(array_keys(self::GENERATED_SLOTS), $skipSlots);

        return count($slots) * $this->generationCoinCost($user);
    }

    /**
     * Free preview step (Task #5722): run the extraction pass now so the
     * creator can see the candidate images and deselect the ones they don't
     * want before the (possibly paid) build runs. Extracted files land in
     * the vault exactly as they would during a build, so keeping them in
     * the later generate call costs nothing extra. Also reports what the
     * generation fallback would produce (slots + per-image coin cost) so
     * the UI can offer per-slot skip toggles.
     *
     * @param list<string> $links cleaned absolute http(s) URLs
     * @return array{
     *   extracted: list<string>,
     *   generation: array{enabled:bool,cost_per_image:int,slots:list<string>}
     * }
     */
    public function preview(User $user, array $links, string $description = ''): array
    {
        $extracted = $this->extractFromLinks($user, $links);
        if ($extracted === []) {
            // Nothing extractable — surface real web photos matching the
            // brief as candidates so the creator can keep or drop them.
            $extracted = $this->searchFromDescription($user, $description);
        }

        return [
            'extracted'  => $extracted,
            'generation' => [
                'enabled'        => $this->generationEnabled(),
                'cost_per_image' => $this->generationEnabled() ? $this->generationCoinCost($user) : 0,
                'slots'          => array_keys(self::GENERATED_SLOTS),
            ],
        ];
    }

    /**
     * Resolve the image set for a build.
     *
     * @param list<string> $links          cleaned absolute http(s) URLs
     * @param list<string> $uploadedImages cleaned user-supplied image URLs
     * @param ?list<string> $keptExtracted when the creator confirmed the image
     *        preview step (Task #5722) this is the exact list of extracted
     *        images they chose to keep (possibly empty) — extraction is NOT
     *        re-run, the kept list is used verbatim
     * @param list<string> $skipSlots     generation slots the creator opted out of
     * @return array{
     *   images: list<string>,
     *   uploaded: int,
     *   extracted: list<string>,
     *   generated: list<array{url:string,file_id:int,tx_id:int,cost:int}>
     * }
     */
    public function source(User $user, string $description, array $links, array $uploadedImages, ?int $relatedLinkId = null, ?array $keptExtracted = null, array $skipSlots = []): array
    {
        $out = [
            'images'    => array_values($uploadedImages),
            'uploaded'  => count($uploadedImages),
            'extracted' => [],
            'searched'  => [],
            'generated' => [],
        ];

        // 1. Uploads win outright.
        if ($out['uploaded'] > 0) {
            return $out;
        }

        // 2. Pull og:image / favicon candidates from the supplied links —
        //    unless the creator already reviewed the candidates in the
        //    preview step, in which case their kept list is authoritative.
        $out['extracted'] = $keptExtracted !== null
            ? array_values($keptExtracted)
            : $this->extractFromLinks($user, $links);
        if ($out['extracted'] !== []) {
            $out['images'] = $out['extracted'];

            return $out;
        }

        // 3. Nothing supplied and nothing extractable → search the public
        //    web for real photos matching the brief (free). Skipped when
        //    the creator already reviewed candidates in the preview step —
        //    their (possibly empty) kept list is authoritative.
        if ($keptExtracted === null) {
            $out['searched'] = $this->searchFromDescription($user, $description);
            if ($out['searched'] !== []) {
                $out['images'] = $out['searched'];

                return $out;
            }
        }

        // 4. Nothing supplied and nothing extractable (or kept) → generate.
        $out['generated'] = $this->generateFallback($user, $description, $relatedLinkId, $skipSlots);
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
     * Scan the first links for og:image/favicon plus prominent in-page
     * content images, download SSRF-safe, validate, de-dupe by content
     * hash, and store in the vault. Free — no AI credits.
     *
     * Per page: the og:image (or favicon fallback) is taken first, then
     * in-page content images fill up to {@see MAX_PER_PAGE} slots. Content
     * images must decode to at least {@see MIN_CONTENT_DIMENSION}px on
     * both axes so icons, trackers, and thumbnails never make it in.
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

            // Candidate order: og:image (or favicon fallback) first, then
            // prominent in-page content images. Content images carry a
            // minimum decoded-dimension requirement; og/favicon do not
            // (favicons are intentionally small).
            $candidates = [];
            foreach ([$meta['image_url'] ?? null, $meta['favicon_url'] ?? null] as $primary) {
                if (is_string($primary) && $primary !== '') {
                    $candidates[] = ['url' => $primary, 'content' => false];
                }
            }
            foreach ($meta['content_images'] ?? [] as $contentUrl) {
                if (is_string($contentUrl) && $contentUrl !== '') {
                    $candidates[] = ['url' => $contentUrl, 'content' => true];
                }
            }

            $storedForPage = 0;
            $primaryTaken  = false;

            foreach ($candidates as $candidate) {
                if (count($stored) >= self::MAX_EXTRACTED) {
                    break 2;
                }
                if ($storedForPage >= self::MAX_PER_PAGE) {
                    break;
                }
                // og:image + favicon are alternates: once one of the two
                // primary candidates stuck, skip the other.
                if (!$candidate['content'] && $primaryTaken) {
                    continue;
                }

                $url = $candidate['url'];
                if (isset($seenCandidates[$url])) {
                    continue;
                }
                $seenCandidates[$url] = true;

                $img = $this->og->downloadImage($url);
                if ($img === null) {
                    continue;
                }

                // Real-pixel filter for content images (attributes lie).
                if ($candidate['content']
                    && (($img['width'] ?? 0) < self::MIN_CONTENT_DIMENSION
                        || ($img['height'] ?? 0) < self::MIN_CONTENT_DIMENSION)) {
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
                $storedForPage++;
                if (!$candidate['content']) {
                    $primaryTaken = true;
                }
            }
        }

        return $stored;
    }

    /**
     * Search the public web (Google image search) for real photos that
     * match the page brief — e.g. an actual person, brand, or venue named
     * in the description — download SSRF-safe, validate, de-dupe, and
     * store in the vault. Free; returns [] when the integration isn't
     * configured or nothing usable comes back, so callers simply fall
     * through to the next tier.
     *
     * @return list<string> relative vault URLs (`/f/{id}/{filename}`)
     */
    protected function searchFromDescription(User $user, string $description): array
    {
        $query = $this->searchQueryFor($description);
        if ($query === '' || !$this->webSearch->enabled()) {
            return [];
        }

        // Same per-user daily quota policy as the manual image-search
        // endpoints — capped users silently fall through to the next tier.
        if (\App\Services\Integrations\GoogleCseUsage::capReached((int) $user->id)) {
            return [];
        }

        $results = $this->webSearch->search($query, 8, (int) $user->id);

        $stored     = [];
        $seenHashes = [];

        foreach ($results as $result) {
            if (count($stored) >= self::MAX_SEARCHED) {
                break;
            }

            $img = $this->og->downloadImage($result['url']);
            if ($img === null) {
                continue;
            }
            if (($img['width'] ?? 0) < self::MIN_SEARCH_DIMENSION
                || ($img['height'] ?? 0) < self::MIN_SEARCH_DIMENSION) {
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
                    'ai-builder-web-' . substr($hash, 0, 8) . '.' . $this->extensionFor($img['mime']),
                    $img['mime'],
                    $user,
                    ['skip_scan' => true, 'context' => 'ai_builder'],
                );
            } catch (\Throwable $e) {
                Log::info('AI builder web image store skipped: ' . $e->getMessage());
                continue;
            }

            $stored[] = $file->url_path;
        }

        return $stored;
    }

    /**
     * Distil the page brief into a short image-search query: first
     * sentence/line, URLs stripped, capped in length. Blank briefs (or
     * ones that are nothing but URLs) yield '' and skip the tier.
     */
    protected function searchQueryFor(string $description): string
    {
        $text = preg_replace('#https?://\S+#i', ' ', $description) ?? '';
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        // First sentence or line is the subject statement in practice.
        $first = preg_split('/(?<=[.!?])\s+|\R/u', $text, 2)[0] ?? $text;

        return mb_substr(trim($first), 0, 120);
    }

    /**
     * Generate the avatar + cover fallback. Each image is charged
     * up-front and refunded individually if its render/store fails;
     * failures degrade to fewer (or zero) images, never an exception.
     *
     * @param list<string> $skipSlots slots the creator opted out of (Task #5722)
     * @return list<array{url:string,file_id:int,tx_id:int,cost:int}>
     */
    protected function generateFallback(User $user, string $description, ?int $relatedLinkId, array $skipSlots = []): array
    {
        if (!$this->generationEnabled()) {
            return [];
        }

        $generated = [];
        foreach (self::GENERATED_SLOTS as $slot => $size) {
            if (in_array($slot, $skipSlots, true)) {
                continue;
            }
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
