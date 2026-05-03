<?php

namespace App\Services\AI;

use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Vision-extraction pipeline for the "Scan a card / brochure" feature.
 *
 * Flow:
 *   1. Persist the original upload to the user's vault (so the review
 *      screen can show it and the user can re-extract later).
 *   2. Rasterise PDFs to a small set of PNG pages (cards are typically
 *      1 page; brochures may be 2–4). Images skip this step.
 *   3. Build a multimodal chat message and call {@see OpenAiService::chat}
 *      with `feature='card_scan'` and the related scan id, so credit
 *      metering, model gating and the audit trail flow through the
 *      same chokepoint as every other AI feature.
 *   4. Parse the JSON the model returns (response_format=json_object) and
 *      normalise it into a flat DTO suitable for Contact + Biolink draft
 *      seeding.
 *
 * Idempotency: the {user_id, sha256(file), model} tuple keys the
 * scan row's `idempotency_key`. Re-submitting the same file in the
 * same session returns the existing CardScan instead of re-charging.
 *
 * On failure mid-extraction we set status=failed, record the error,
 * and refund any partial credit charge so the user isn't billed for
 * something we couldn't deliver.
 */
class CardBrochureExtractionService
{
    /** Max PDF pages we're willing to render + send to vision. */
    public const MAX_PDF_PAGES = 4;

    /** Max upload size in MB for both images and PDFs. */
    public const MAX_UPLOAD_MB = 10;

    /** Max distinct files per scan (front + back of card, brochure pages…). */
    public const MAX_UPLOADS = 6;

    /** PDF rasterisation DPI — high enough for OCR-quality text on a card. */
    private const RASTER_DPI = 200;

    public function __construct(
        protected OpenAiService $openai,
        protected AiCreditService $credits,
    ) {}

    /**
     * Run the full upload → vision → normalise pipeline.
     * Returns the persisted CardScan (status=completed on success).
     *
     * @throws \RuntimeException for caller-fixable validation problems
     *         (mime, size, page count).
     * @throws InsufficientAiCreditsException when the user can't afford
     *         the worst-case vision call.
     * @throws \Throwable for unexpected failures (vision API, etc.) —
     *         the scan row is marked failed and any credits refunded
     *         before the throw propagates.
     */
    /**
     * @param UploadedFile|list<UploadedFile> $files One or more uploads
     *        — typically a single image, both sides of a card, or a
     *        multi-page brochure rendered as photos.
     */
    public function extract(User $owner, User $actor, UploadedFile|array $files): CardScan
    {
        $files = is_array($files) ? array_values($files) : [$files];
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        if (!$files) {
            throw new \RuntimeException('Please attach at least one image or PDF.');
        }
        if (count($files) > self::MAX_UPLOADS) {
            throw new \RuntimeException(
                'Too many files — please attach at most ' . self::MAX_UPLOADS . ' at a time.'
            );
        }
        foreach ($files as $f) $this->validateUpload($f);

        // Hash the joined upload bytes (in upload order) so the same
        // bundle of files re-uploaded together collapses to one scan
        // but adding/removing a file produces a new one.
        $hashes = array_map(fn ($f) => hash_file('sha256', $f->getRealPath()), $files);
        $bundleHash = hash('sha256', implode('|', $hashes));
        $model    = $this->modelName();
        $idem     = "card_scan:{$owner->id}:{$bundleHash}:{$model}";

        // Idempotency: race-safe firstOrCreate against the unique
        // `idempotency_key` index. The DB transaction + unique
        // constraint mean two concurrent uploads of the same files
        // collapse to a single CardScan row (and a single AI charge).
        $sourceFiles = [];
        $created     = false;
        $scan = \Illuminate\Support\Facades\DB::transaction(function () use ($owner, $actor, $files, $idem, &$sourceFiles, &$created) {
            $existing = CardScan::withoutGlobalScope('workspace')
                ->where('idempotency_key', $idem)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            // Vault every original first so the review screen always
            // has something to show, even if the vision call dies.
            foreach ($files as $f) {
                $sourceFiles[] = UserFile::createFromUpload($f, $owner, [
                    'enforce_allowlist' => false,
                    'max_size_mb'       => self::MAX_UPLOAD_MB,
                ]);
            }
            $ids = array_map(fn ($u) => $u->id, $sourceFiles);

            $row = CardScan::create([
                'user_id'         => $owner->id,
                'actor_user_id'   => $actor->id,
                'source_file_id'  => $ids[0] ?? null,
                'source_file_ids' => $ids,
                'status'          => 'processing',
                'idempotency_key' => $idem,
            ]);
            $created = true;
            return $row;
        });

        // A concurrent request beat us to it — return its result
        // (it may still be in 'processing' which the controller
        // will surface to the user).
        if (!$created) {
            return $scan;
        }

        try {
            // Rasterise every upload (PDFs may yield multiple pages). We
            // then vault each rasterised page as a derived UserFile so
            // brochures can be browsed page-by-page from the review
            // screen, the contact and the biolink draft.
            $images       = [];
            $derivedIds   = [];
            $sourceModels = $scan->sourceFiles();
            foreach ($sourceModels as $sf) {
                $perFile = $this->rasteriseToImages($sf);
                foreach ($perFile as $i => $img) {
                    $images[] = $img;
                    if ($sf->mime_type === 'application/pdf') {
                        // PDF page → vault as a derived asset.
                        try {
                            $derived = UserFile::createFromBytes(
                                $img['bytes'],
                                "card-scan-{$scan->id}-{$sf->id}-p" . ($i + 1) . '.png',
                                'image/png',
                                $owner,
                                ['max_size_mb' => self::MAX_UPLOAD_MB]
                            );
                            $derivedIds[] = $derived->id;
                        } catch (\Throwable $e) {
                            Log::info('card_scan derived page save failed', ['scan' => $scan->id, 'err' => $e->getMessage()]);
                        }
                    }
                }
                if (count($images) >= self::MAX_PDF_PAGES * self::MAX_UPLOADS) break;
            }

            $result    = $this->callVision($owner, $scan, $model, $images);
            $extracted = $this->normalise($result['parsed']);

            // Best-effort logo crop. Reads the first rasterised page,
            // crops to the model-provided bbox using GD, and saves the
            // result back into the user's vault as a derived asset.
            // Failures here are non-fatal — the original upload remains
            // available as a fallback.
            $logoUrl = $this->extractLogo($owner, $scan, $images, $extracted, $derivedIds);
            $extracted['logo_url'] = $logoUrl;

            $scan->forceFill([
                'status'           => 'completed',
                'raw_response'     => $result['raw'],
                'extracted'        => $extracted,
                'derived_file_ids' => $derivedIds,
                'credits_spent'    => $result['credits_spent'],
            ])->save();

            return $scan->fresh();
        } catch (InsufficientAiCreditsException $e) {
            // Out of credits *before* OpenAI was called — nothing to
            // refund. Mark failed and rethrow for the controller to
            // redirect the user to the credits page.
            $scan->forceFill([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 480),
            ])->save();
            throw $e;
        } catch (\Throwable $e) {
            // Mark the scan as failed and refund any partial spend so
            // the user isn't billed for a broken extraction. Surface a
            // friendly message — the controller catches and renders it.
            $scan->forceFill([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 480),
            ])->save();

            $alreadySpent = (int) $scan->credits_spent;
            if ($alreadySpent > 0) {
                try {
                    $this->credits->refund($owner, $alreadySpent, [
                        'feature'         => 'card_scan',
                        'related_id'      => $scan->id,
                        'reason'          => 'Card scan failed: refunded',
                        'idempotency_key' => "card_scan_refund:{$scan->id}",
                    ]);
                    $scan->forceFill(['credits_spent' => 0])->save();
                } catch (\Throwable $r) {
                    Log::warning('card_scan refund failed', ['scan' => $scan->id, 'err' => $r->getMessage()]);
                }
            }
            throw $e;
        }
    }

    /** Validate caller-supplied uploads before we touch storage. */
    protected function validateUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Upload failed — please try again.');
        }
        $sizeMb = $file->getSize() / 1048576;
        if ($sizeMb > self::MAX_UPLOAD_MB) {
            throw new \RuntimeException(
                'File is too large (max ' . self::MAX_UPLOAD_MB . ' MB).'
            );
        }
        $mime = strtolower((string) $file->getMimeType());
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException(
                'Unsupported file type. Upload a JPG/PNG/WebP image or a PDF.'
            );
        }
    }

    /**
     * Returns a list of raw image bytes (PNG or JPEG) ready to be
     * base64-attached to a chat message. PDFs are rasterised to PNG via
     * `pdftoppm`; the page cap protects credits + latency.
     *
     * @return list<array{bytes:string, mime:string}>
     */
    protected function rasteriseToImages(UserFile $file): array
    {
        $disk  = $file->disk === 'public' ? 'public' : ($file->disk === 's3' ? 's3' : 'user_files');
        $bytes = Storage::disk($disk)->get($file->path);
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('We couldn\'t read the uploaded file.');
        }

        if ($file->mime_type !== 'application/pdf') {
            // Send the original image as-is. OpenAI vision accepts JPEG /
            // PNG / WebP and will downscale internally.
            return [['bytes' => $bytes, 'mime' => $file->mime_type]];
        }

        // PDF path — write to a temp file, run pdftoppm, collect pages.
        // tempnam() creates an empty placeholder file at the path it
        // returns; we have to delete it for both the .pdf rename and
        // the pdftoppm prefix to avoid leaking 0-byte files in /tmp.
        $tmpPdfBase = tempnam(sys_get_temp_dir(), 'cs_');
        $tmpPdf     = $tmpPdfBase . '.pdf';
        @unlink($tmpPdfBase);
        file_put_contents($tmpPdf, $bytes);
        $outPrefix = tempnam(sys_get_temp_dir(), 'csp_');
        @unlink($outPrefix); // pdftoppm wants a prefix, not a path

        try {
            $proc = new Process([
                'pdftoppm',
                '-png',
                '-r', (string) self::RASTER_DPI,
                '-l', (string) self::MAX_PDF_PAGES,
                $tmpPdf,
                $outPrefix,
            ]);
            $proc->setTimeout(60);
            $proc->run();
            if (!$proc->isSuccessful()) {
                throw new \RuntimeException(
                    'PDF rasterisation failed. Try uploading the page as a JPG or PNG instead.'
                );
            }

            $pages = glob($outPrefix . '-*.png') ?: [];
            sort($pages);
            $pages = array_slice($pages, 0, self::MAX_PDF_PAGES);
            if (!$pages) {
                throw new \RuntimeException('We couldn\'t find any pages in that PDF.');
            }

            $out = [];
            foreach ($pages as $p) {
                $b = @file_get_contents($p);
                if ($b !== false && $b !== '') {
                    $out[] = ['bytes' => $b, 'mime' => 'image/png'];
                }
                @unlink($p);
            }
            return $out;
        } finally {
            @unlink($tmpPdf);
            // Sweep any stragglers the loop missed (e.g. if pdftoppm
            // produced more files than we limited to).
            foreach (glob($outPrefix . '-*.png') ?: [] as $stray) {
                @unlink($stray);
            }
        }
    }

    /**
     * Push the rasterised images at OpenAI vision and return the parsed
     * JSON, raw response, and credits spent. The chat call is metered
     * via the standard ledger using the `card_scan` feature tag.
     *
     * @param list<array{bytes:string,mime:string}> $images
     * @return array{parsed:array,raw:array,credits_spent:int}
     */
    protected function callVision(User $owner, CardScan $scan, string $model, array $images): array
    {
        if (!$images) {
            throw new \RuntimeException('No image data to send to the vision model.');
        }

        $content = [[
            'type' => 'text',
            'text' => $this->extractionPrompt(),
        ]];
        foreach ($images as $img) {
            $b64 = base64_encode($img['bytes']);
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url'    => "data:{$img['mime']};base64,{$b64}",
                    'detail' => 'high',
                ],
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user',   'content' => $content],
        ];

        $resp = $this->openai->chat($owner, $model, $messages, [
            'feature'         => 'card_scan',
            'related_id'      => $scan->id,
            'response_format' => ['type' => 'json_object'],
            'temperature'     => 0,
            'max_tokens'      => 1500,
            'reason'          => 'Card / brochure scan',
            'meta'            => [
                'scan_id'   => $scan->id,
                'image_n'   => count($images),
            ],
        ]);

        // The credit charge has already been written to the ledger by
        // OpenAiService::chat(). Persist the spend on the scan row
        // **before** any further validation so that if JSON parsing
        // (or any other downstream step) throws, the outer catch can
        // see what was charged and issue an exact-amount refund.
        $creditsSpent = (int) ($resp['credits_spent'] ?? 0);
        if ($creditsSpent > 0) {
            $scan->forceFill(['credits_spent' => $creditsSpent])->save();
        }

        $text   = (string) ($resp['content'] ?? '');
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException(
                'The AI response wasn\'t valid JSON. Please try a clearer photo.'
            );
        }

        return [
            'parsed'        => $parsed,
            'raw'           => is_array($resp['raw'] ?? null) ? $resp['raw'] : [],
            'credits_spent' => $creditsSpent,
        ];
    }

    /**
     * Resolve the chat model used for card scans. We piggy-back on the
     * admin-configurable feature_models map; if no override is set we
     * default to the same gpt-4o-mini fallback as every other feature
     * (which supports vision).
     */
    public function modelName(): string
    {
        return AiEngineSettings::featureModel('card_scan');
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a precise OCR + IE engine for business cards and small
brochures. Read every visible text element and emit ONLY a single
JSON object that matches the schema below. Do not include any
prose or markdown — only valid JSON. Use null (not empty string)
for fields you cannot find. Confidence is your subjective 0..1
estimate of how sure you are about each field.
PROMPT;
    }

    protected function extractionPrompt(): string
    {
        return <<<'PROMPT'
Extract the contents of this business card or brochure into JSON.

Schema:
{
  "kind": "card" | "brochure" | "other",
  "person": {
    "full_name":    string|null,
    "first_name":   string|null,
    "last_name":    string|null,
    "title":        string|null
  },
  "company": {
    "name":         string|null,
    "tagline":      string|null,
    "description":  string|null
  },
  "contact": {
    "emails":  [ { "value": string, "label": "Work"|"Personal"|"Other"|null } ],
    "phones":  [ { "value": string, "label": "Mobile"|"Work"|"Home"|"Main"|"Other"|null } ],
    "website": string|null,
    "address": string|null
  },
  "socials": {
    "instagram": string|null,
    "tiktok":    string|null,
    "youtube":   string|null,
    "twitter":   string|null,
    "linkedin":  string|null,
    "facebook":  string|null
  },
  "branding": {
    "primary_color_hex":   string|null,
    "secondary_color_hex": string|null,
    "has_logo":            boolean,
    "logo_bbox":           { "x": number, "y": number, "w": number, "h": number } | null
  },
  "products": [
    { "name": string, "description": string|null, "price": string|null }
  ],
  "confidence": {
    "overall": number,
    "name":    number,
    "email":   number,
    "phone":   number,
    "company": number
  }
}

Rules:
- Strip social handles to bare usernames (no leading "@" or URL).
- Keep phones in the original format (we'll normalise them later).
- Hex colors must be #RRGGBB (uppercase) or null.
- Limit "products" to a maximum of 6 items even if more are visible.
- For "branding.logo_bbox": if a logo / brandmark is visible on the FIRST
  image, return its bounding box as fractions of the image, where x and y
  are the top-left corner and w and h are the width and height, all in
  the range 0..1 (e.g. {"x":0.05,"y":0.10,"w":0.30,"h":0.20}). Pad the
  box by ~5% so the crop isn't tight. Return null if no logo is present
  or you can't localise it confidently.
- Output ONLY the JSON object, with no commentary.
PROMPT;
    }

    /**
     * Clamp/sanitise a logo bbox dict to fractions in [0,1] and reject
     * absurdly small / inverted rectangles. Returns null when invalid.
     *
     * @return array{x:float,y:float,w:float,h:float}|null
     */
    protected function normaliseBbox($v): ?array
    {
        if (!is_array($v)) return null;
        $f = static fn ($k) => isset($v[$k]) && is_numeric($v[$k]) ? (float) $v[$k] : null;
        $x = $f('x'); $y = $f('y'); $w = $f('w'); $h = $f('h');
        if ($x === null || $y === null || $w === null || $h === null) return null;
        $x = max(0.0, min(1.0, $x));
        $y = max(0.0, min(1.0, $y));
        $w = max(0.0, min(1.0 - $x, $w));
        $h = max(0.0, min(1.0 - $y, $h));
        // Ignore micro-boxes that would crop to a few pixels.
        if ($w < 0.04 || $h < 0.04) return null;
        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /**
     * Crop the model-localised logo region out of the first rasterised
     * page and persist the PNG as a derived UserFile, returning the
     * public URL. Best-effort: returns null if GD is missing, the bbox
     * is unusable, or any crop step fails — the wizard / contact will
     * fall back to the original upload.
     *
     * @param list<array{bytes:string,mime:string}> $images
     */
    protected function extractLogo(User $owner, CardScan $scan, array $images, array $extracted, array &$derivedIds = []): ?string
    {
        $bbox = $extracted['branding']['logo_bbox'] ?? null;
        if (!$bbox || empty($extracted['branding']['has_logo'])) return null;
        if (!function_exists('imagecreatefromstring')) return null;
        $first = $images[0] ?? null;
        if (!$first || !is_string($first['bytes']) || $first['bytes'] === '') return null;

        try {
            $src = @imagecreatefromstring($first['bytes']);
            if (!$src) return null;
            $sw = imagesx($src);
            $sh = imagesy($src);
            $cx = (int) floor($bbox['x'] * $sw);
            $cy = (int) floor($bbox['y'] * $sh);
            $cw = max(8, (int) floor($bbox['w'] * $sw));
            $ch = max(8, (int) floor($bbox['h'] * $sh));
            // Re-clamp in pixel space.
            $cw = min($cw, $sw - $cx);
            $ch = min($ch, $sh - $cy);
            if ($cw < 16 || $ch < 16) { imagedestroy($src); return null; }

            $dst = imagecreatetruecolor($cw, $ch);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopy($dst, $src, 0, 0, $cx, $cy, $cw, $ch);

            ob_start();
            imagepng($dst, null, 6);
            $bytes = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            if (!is_string($bytes) || $bytes === '') return null;

            $logoFile = UserFile::createFromBytes(
                $bytes,
                "card-scan-{$scan->id}-logo.png",
                'image/png',
                $owner,
                ['max_size_mb' => self::MAX_UPLOAD_MB]
            );
            $derivedIds[] = $logoFile->id;
            return $logoFile->url;
        } catch (\Throwable $e) {
            Log::info('card_scan logo crop failed', ['scan' => $scan->id, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Coerce the model output into the flat shape the review screen and
     * downstream Contact / Biolink seeders expect. Defensive against
     * missing keys or wrong types.
     */
    public function normalise(array $parsed): array
    {
        $get = static function ($a, $key, $default = null) {
            if (!is_array($a)) return $default;
            $v = $a[$key] ?? $default;
            if (is_string($v)) {
                $v = trim($v);
                return $v === '' ? $default : $v;
            }
            return $v;
        };

        $person  = is_array($parsed['person']  ?? null) ? $parsed['person']  : [];
        $company = is_array($parsed['company'] ?? null) ? $parsed['company'] : [];
        $contact = is_array($parsed['contact'] ?? null) ? $parsed['contact'] : [];
        $socials = is_array($parsed['socials'] ?? null) ? $parsed['socials'] : [];
        $brand   = is_array($parsed['branding']?? null) ? $parsed['branding']: [];
        $conf    = is_array($parsed['confidence'] ?? null) ? $parsed['confidence'] : [];

        $cleanContacts = function (array $rows, string $kind): array {
            $out = [];
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $val = is_string($r['value'] ?? null) ? trim($r['value']) : '';
                if ($val === '') continue;
                if ($kind === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) continue;
                $out[] = [
                    'value' => $val,
                    'label' => is_string($r['label'] ?? null) ? trim($r['label']) : null,
                ];
            }
            return $out;
        };

        $hex = static function ($v): ?string {
            if (!is_string($v)) return null;
            $v = strtoupper(trim($v));
            return preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : null;
        };

        $sociaClean = static function ($v): ?string {
            if (!is_string($v)) return null;
            $v = trim($v);
            if ($v === '') return null;
            // Drop URL prefix and trailing slash if the model included one.
            $v = preg_replace('#^https?://[^/]+/#i', '', $v) ?? $v;
            return ltrim($v, '@/');
        };

        $products = [];
        if (is_array($parsed['products'] ?? null)) {
            foreach (array_slice($parsed['products'], 0, 6) as $p) {
                if (!is_array($p)) continue;
                $name = is_string($p['name'] ?? null) ? trim($p['name']) : '';
                if ($name === '') continue;
                $products[] = [
                    'name'        => $name,
                    'description' => is_string($p['description'] ?? null) ? trim($p['description']) : null,
                    'price'       => is_string($p['price'] ?? null) ? trim($p['price']) : null,
                ];
            }
        }

        $confNum = static function ($v): float {
            $f = is_numeric($v) ? (float) $v : 0.0;
            return max(0.0, min(1.0, $f));
        };

        return [
            'kind'        => in_array($parsed['kind'] ?? null, ['card', 'brochure', 'other'], true)
                                ? $parsed['kind'] : 'card',
            'full_name'   => $get($person, 'full_name'),
            'first_name'  => $get($person, 'first_name'),
            'last_name'   => $get($person, 'last_name'),
            'title'       => $get($person, 'title'),
            'company'     => $get($company, 'name'),
            'tagline'     => $get($company, 'tagline'),
            'description' => $get($company, 'description'),
            'emails'      => $cleanContacts($contact['emails'] ?? [], 'email'),
            'phones'      => $cleanContacts($contact['phones'] ?? [], 'phone'),
            'website'     => $get($contact, 'website'),
            'address'     => $get($contact, 'address'),
            'socials'     => [
                'instagram' => $sociaClean($socials['instagram'] ?? null),
                'tiktok'    => $sociaClean($socials['tiktok']    ?? null),
                'youtube'   => $sociaClean($socials['youtube']   ?? null),
                'twitter'   => $sociaClean($socials['twitter']   ?? null),
                'linkedin'  => $sociaClean($socials['linkedin']  ?? null),
                'facebook'  => $sociaClean($socials['facebook']  ?? null),
            ],
            'branding'    => [
                'primary_color_hex'   => $hex($brand['primary_color_hex']   ?? null),
                'secondary_color_hex' => $hex($brand['secondary_color_hex'] ?? null),
                'has_logo'            => (bool) ($brand['has_logo'] ?? false),
                'logo_bbox'           => $this->normaliseBbox($brand['logo_bbox'] ?? null),
            ],
            // Filled in by extractLogo() after the model returns — URL of
            // the cropped logo asset that's been vaulted via UserFile so
            // the wizard / contact / future scans can re-use it.
            'logo_url'    => null,
            'products'    => $products,
            'confidence'  => [
                'overall' => $confNum($conf['overall'] ?? 0),
                'name'    => $confNum($conf['name']    ?? 0),
                'email'   => $confNum($conf['email']   ?? 0),
                'phone'   => $confNum($conf['phone']   ?? 0),
                'company' => $confNum($conf['company'] ?? 0),
            ],
        ];
    }
}
