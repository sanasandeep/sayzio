<?php

namespace App\Services\Brand;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Services\FormTemplateCatalog;
use App\Modules\User\Support\QrCodeDesignSanitizer;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AI Brand Studio (Task #5551) — bulk on-brand asset creator.
 *
 * One plain-language brief (grounded in a saved Brand Kit's directives or
 * inline brand details) is turned into a structured multi-asset plan:
 * a Link in Bio page, short links, QR codes, a form and a vCard. The plan
 * is reviewed by the user before anything is created; confirming it
 * materializes every asset through the existing creation paths and groups
 * them as a named kit with a results page.
 *
 * Two modes:
 *  - `kit`  — one brief → a mixed set of assets created together.
 *  - `bulk` — N on-brand variants of ONE asset type, capped by the per-plan
 *             `max_brand_studio_bulk` quantity.
 *
 * Credits are charged inside OpenAiService::chat() against the
 * `brand_studio` feature (token-charged AI feature pattern) with an
 * automatic refund when the model's response can't be parsed into a valid
 * plan — a failed run never nets a charge. Asset creation itself is
 * deterministic and free; per-type plan caps (max_links, max_biolinks,
 * max_qr_codes, max_forms) are enforced server-side before creation.
 */
class AiBrandStudioService
{
    public const FEATURE = 'brand_studio';

    /** Asset kinds the studio can propose and create. */
    public const ASSET_KINDS = ['biolink', 'short_link', 'qr_code', 'form', 'vcard'];

    /** Hard per-run ceilings so a runaway plan can't flood an account. */
    public const KIT_CAPS = [
        'biolink'    => 3,
        'short_link' => 10,
        'qr_code'    => 10,
        'form'       => 3,
        'vcard'      => 2,
    ];

    /** Absolute bulk-variations ceiling regardless of plan. */
    public const HARD_BULK_CAP = 50;

    /** Human labels for composition validation messages. */
    public const KIND_LABELS = [
        'biolink'    => 'Link in Bio pages',
        'short_link' => 'short links',
        'qr_code'    => 'QR codes',
        'form'       => 'forms',
        'vcard'      => 'digital cards',
    ];

    /** Max length of a per-asset purpose label. */
    public const MAX_PURPOSE_LEN = 120;

    private const MAX_REQUEST_LEN   = 4000;
    private const MAX_OUTPUT_TOKENS = 4000;

    /** Safe biolink block subset the studio plan may include. */
    private const BIOLINK_BLOCK_TYPES = ['heading', 'paragraph', 'link', 'cta_button', 'socials', 'divider', 'email_subscribe'];

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
    ) {}

    /** Effective per-run bulk-variations cap for this user. -1 = unlimited (clamped to the hard ceiling). */
    public static function bulkCap(User $user): int
    {
        $cap = AiPlanAccess::quantityCap($user, 'brand_studio_bulk');
        if ($cap < 0 || $cap > self::HARD_BULK_CAP) {
            return self::HARD_BULK_CAP;
        }
        return $cap;
    }

    /**
     * Resolve the brand directive block: a saved Brand Kit's promptDirectives
     * or inline brand details typed straight into the studio.
     *
     * @param array{name?:string,colors?:string,voice?:string,description?:string} $inline
     * @return array{directives:string,brand:array<string,mixed>}
     */
    public function resolveBrand(User $owner, ?int $brandKitId, array $inline): array
    {
        if ($brandKitId) {
            $kit = BrandKit::where('user_id', $owner->id)->find($brandKitId);
            if (!$kit) {
                throw new RuntimeException('That brand kit was not found.');
            }
            return [
                'directives' => $kit->promptDirectives(true),
                'brand'      => ['brand_kit_id' => $kit->id, 'name' => $kit->name, 'palette' => $kit->palette()],
            ];
        }

        $name   = trim((string) ($inline['name'] ?? ''));
        $colors = trim((string) ($inline['colors'] ?? ''));
        $voice  = trim((string) ($inline['voice'] ?? ''));
        $desc   = trim((string) ($inline['description'] ?? ''));

        $lines = [];
        if ($name !== '')   $lines[] = "Brand: {$name}.";
        if ($desc !== '')   $lines[] = "About the brand: {$desc}";
        if ($voice !== '')  $lines[] = "Voice & tone: {$voice}.";
        if ($colors !== '') $lines[] = "Brand colors: {$colors}. Use them for theme colors and QR styling.";

        return [
            'directives' => $lines ? "Keep everything ON-BRAND:\n- " . implode("\n- ", $lines) : '',
            'brand'      => array_filter(['name' => $name, 'colors' => $colors, 'voice' => $voice, 'description' => $desc], fn ($v) => $v !== ''),
        ];
    }

    /**
     * Validate + normalize a structured kit composition: a list of
     * {kind, count, purpose} rows the user explicitly requested. Enforces
     * per-kind KIT_CAPS with a clear message; returns [] when nothing given.
     *
     * @return list<array{kind:string,count:int,purpose:string}>
     */
    public static function sanitizeComposition($raw): array
    {
        $out = [];
        $perKind = [];
        foreach ((array) $raw as $row) {
            if (!is_array($row)) continue;
            $kind = (string) ($row['kind'] ?? '');
            if (!in_array($kind, self::ASSET_KINDS, true)) {
                throw new RuntimeException('Unknown asset type in the composition.');
            }
            $count = (int) ($row['count'] ?? 1);
            if ($count < 1) $count = 1;
            $perKind[$kind] = ($perKind[$kind] ?? 0) + $count;
            if ($perKind[$kind] > self::KIT_CAPS[$kind]) {
                throw new RuntimeException(sprintf(
                    'Too many %s in the composition — the maximum per kit is %d.',
                    self::KIND_LABELS[$kind], self::KIT_CAPS[$kind]
                ));
            }
            $out[] = [
                'kind'    => $kind,
                'count'   => $count,
                'purpose' => mb_substr(trim((string) ($row['purpose'] ?? '')), 0, self::MAX_PURPOSE_LEN),
            ];
        }
        return $out;
    }

    /**
     * Build the chat messages. Shared by estimate + plan so the quoted price
     * matches what the user is charged.
     *
     * @param list<array{kind:string,count:int,purpose:string}> $composition
     * @return list<array{role:string,content:string}>
     */
    public function buildMessages(User $user, string $request, string $brandDirectives, string $mode, ?string $bulkKind, int $bulkCount, array $composition = []): array
    {
        $request = trim($request);
        if ($request === '' && !$composition) {
            throw new RuntimeException('Describe what you want to create first.');
        }
        if (mb_strlen($request) > self::MAX_REQUEST_LEN) {
            $request = mb_substr($request, 0, self::MAX_REQUEST_LEN);
        }

        $kindLines = [
            '- biolink: A Link in Bio page. Fields: title, theme_color(hex), blocks:[{type,settings}] where type is one of: ' . implode(', ', self::BIOLINK_BLOCK_TYPES) . '. Block settings: heading{text,size(h1|h2|h3)}, paragraph{text}, link{text,url}, cta_button{text,url}, socials{platforms:[{platform,url}]}, email_subscribe{title,description,button_text}, divider{}. 4-10 blocks.',
            '- short_link: A branded short redirect link. Fields: title, url(destination, required http/https).',
            '- qr_code: A styled QR code. Fields: name, url(what it opens, required http/https).',
            '- form: A lead/contact form. Fields: title, description, template(one of: ' . implode(', ', FormTemplateCatalog::keys()) . ').',
            '- vcard: A digital contact card. Fields: title, first_name, last_name, organization, job_title, phone, email, website, note.',
        ];

        if ($mode === BrandStudioKit::MODE_BULK) {
            $modeHint = "MODE: BULK VARIATIONS. Produce exactly {$bulkCount} DISTINCT on-brand variants of asset kind `{$bulkKind}` — vary the copy, names and (for biolinks/QR) styling per variant so no two are identical. Every asset in `assets` must have kind `{$bulkKind}`.";
        } elseif ($composition) {
            $lines = [];
            $n = 0;
            foreach ($composition as $row) {
                for ($i = 0; $i < $row['count']; $i++) {
                    $n++;
                    $lines[] = "{$n}. {$row['kind']}" . ($row['purpose'] !== '' ? " — purpose: {$row['purpose']}" : '');
                }
            }
            $modeHint = "MODE: FULL KIT with an EXACT REQUESTED COMPOSITION. Produce EXACTLY these {$n} assets, in this order — no more, no fewer, no other kinds:\n"
                . implode("\n", $lines) . "\n"
                . "Shape each asset's title, name and copy around its stated purpose so the purpose is obvious from the content.";
        } else {
            $caps = implode(', ', array_map(fn ($k, $c) => "{$k} ≤ {$c}", array_keys(self::KIT_CAPS), self::KIT_CAPS));
            $modeHint = "MODE: FULL KIT. Produce the mixed set of assets the user asked for (only what they need — don't pad). Per-kind ceilings: {$caps}.";
        }

        $schema = "Return STRICT JSON (no markdown, no commentary):\n"
            . "{ \"name\": string(short kit name), \"assets\": [ { \"kind\": string, ...fields for that kind } ] }\n"
            . "Rules:\n"
            . "- `kind` MUST be one of: " . implode(', ', self::ASSET_KINDS) . ".\n"
            . "- Real, specific copy everywhere — no Lorem Ipsum, no placeholders.\n"
            . "- Only include URLs the user supplied or clearly implied (their own site/socials); never invent third-party URLs.\n"
            . "- Use empty strings rather than null.\n"
            . $modeHint;

        $system = "You are the AI Brand Studio for the Sayzio link-management platform. "
            . "You turn a creator's brief into a structured plan of on-brand link assets using ONLY the supported asset kinds below. Be tasteful and concrete.\n\n"
            . "ASSET KINDS:\n" . implode("\n", $kindLines) . "\n\n" . $schema;

        $userParts = ["WHAT THE USER WANTS:\n" . ($request !== '' ? $request : 'Exactly the requested composition described in the system instructions.')];
        $brandDirectives = trim($brandDirectives);
        if ($brandDirectives !== '') {
            $userParts[] = mb_substr($brandDirectives, 0, self::MAX_REQUEST_LEN);
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => implode("\n\n", $userParts)],
        ];
    }

    /** Worst-case credit cost shown before the user clicks Generate. */
    public function estimateCredits(User $user, string $request, string $brandDirectives, string $mode, ?string $bulkKind, int $bulkCount, array $composition = []): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE, $user);
        $messages = $this->buildMessages($user, $request, $brandDirectives, $mode, $bulkKind, $bulkCount, $composition);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the AI planning step: call the model, parse + clamp the proposal,
     * and persist a BrandStudioKit in `proposal` status for review.
     * Auto-refunds the charge if the response can't be parsed.
     *
     * @param array<string,mixed> $brand snapshot from resolveBrand()
     * @param list<array{kind:string,count:int,purpose:string}> $composition
     * @return array{kit:BrandStudioKit,credits_spent:int}
     */
    public function plan(User $owner, string $request, string $brandDirectives, array $brand, string $mode, ?string $bulkKind, int $bulkCount, array $composition = []): array
    {
        $mode = $mode === BrandStudioKit::MODE_BULK ? BrandStudioKit::MODE_BULK : BrandStudioKit::MODE_KIT;
        if ($mode === BrandStudioKit::MODE_BULK) {
            $composition = [];
            if (!in_array($bulkKind, self::ASSET_KINDS, true)) {
                throw new RuntimeException('Pick which asset type to bulk-generate.');
            }
            $cap = self::bulkCap($owner);
            if ($cap === 0) {
                throw new RuntimeException('Bulk variations are not available on your plan.');
            }
            $bulkCount = max(1, min($bulkCount, $cap));
        }

        $messages = $this->buildMessages($owner, $request, $brandDirectives, $mode, $bulkKind, $bulkCount, $composition);
        $model    = AiEngineSettings::featureModel(self::FEATURE, $owner);

        $result = $this->openai->chat($owner, $model, $messages, [
            'temperature'     => 0.5,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'reason'          => 'AI Brand Studio asset plan',
            'meta'            => [
                'mode'         => $mode,
                'bulk_kind'    => $bulkKind,
                'bulk_count'   => $bulkCount,
                'brief_excerpt'=> mb_substr($request, 0, 160),
            ],
        ]);

        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $parsed = json_decode((string) $result['content'], true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }

            $assets = $this->sanitizeAssets($parsed['assets'] ?? [], $mode, $bulkKind, $bulkCount, $composition);
            if (!$assets) {
                throw new RuntimeException('The assistant could not plan any assets from that brief. Add more detail and try again.');
            }

            $name = trim((string) ($parsed['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 120) {
                $name = mb_substr($name !== '' ? $name : ('Brand kit — ' . now()->format('M j, Y')), 0, 120);
            }

            $kit = BrandStudioKit::create([
                'user_id'       => $owner->id,
                'name'          => $name,
                'mode'          => $mode,
                'status'        => BrandStudioKit::STATUS_PROPOSAL,
                'request'       => mb_substr($request, 0, self::MAX_REQUEST_LEN),
                'brand'         => $brand,
                'proposal'      => array_filter([
                    'assets'      => $assets,
                    'composition' => $composition ?: null,
                ]),
                'credits_spent' => $creditsSpent,
            ]);
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($owner, $creditsSpent, [
                    'feature' => self::FEATURE,
                    'reason'  => 'AI Brand Studio plan failed — auto refund',
                ]);
            }
            throw $e;
        }

        return ['kit' => $kit, 'credits_spent' => $creditsSpent];
    }

    /**
     * Discard a kit: delete the record and, when it is still an unconfirmed
     * proposal, refund the credits charged for the planning run — the user
     * got nothing out of the charge. Created kits are deleted without a
     * refund (the assets were materialized). The refund carries an
     * idempotency key so a double-submitted discard can never credit twice.
     *
     * @return int credits refunded (0 when nothing was refundable)
     */
    public function discard(BrandStudioKit $kit): int
    {
        $refunded = 0;

        DB::transaction(function () use ($kit, &$refunded) {
            $locked = BrandStudioKit::whereKey($kit->id)->lockForUpdate()->first();
            if (!$locked) {
                return; // already deleted by a concurrent request
            }

            $credits = (int) $locked->credits_spent;
            if (!$locked->isCreated() && $credits > 0) {
                $this->credits->refund($locked->user, $credits, [
                    'feature'         => self::FEATURE,
                    'reason'          => 'AI Brand Studio plan discarded — refund',
                    'related_id'      => $locked->id,
                    'idempotency_key' => 'brand_studio_discard_' . $locked->id,
                ]);
                $refunded = $credits;
            }

            $locked->delete();
        });

        return $refunded;
    }

    /**
     * Materialize a reviewed proposal: create every kept asset through the
     * existing creation paths, enforcing per-type plan caps, and store the
     * results on the kit. $keep is a list of proposal indexes to create
     * (null = all).
     *
     * @param list<int>|null $keep
     * @return array{kit:BrandStudioKit,created:int,skipped:list<string>}
     */
    public function materialize(User $owner, BrandStudioKit $kit, ?array $keep = null): array
    {
        if ($kit->isCreated()) {
            throw new RuntimeException('This kit has already been created.');
        }

        $assets = $kit->proposedAssets();
        if ($keep !== null) {
            $keepIdx = array_flip(array_map('intval', $keep));
            $assets  = array_values(array_filter($assets, fn ($a, $i) => isset($keepIdx[$i]), ARRAY_FILTER_USE_BOTH));
        }
        if (!$assets) {
            throw new RuntimeException('Nothing selected to create.');
        }

        // Snapshot current usage once; increment locally per creation.
        $counts = [
            'links'    => Link::withoutGlobalScope('workspace')->where('user_id', $owner->id)->count(),
            'biolinks' => Link::withoutGlobalScope('workspace')->where('user_id', $owner->id)->where('type', 'biolink')->count(),
            'qr'       => QrCode::withoutGlobalScope('workspace')->where('user_id', $owner->id)->count(),
            'forms'    => Form::withoutGlobalScope('workspace')->where('user_id', $owner->id)->count(),
        ];

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($owner, $kit, $assets, &$counts, &$created, &$skipped) {
            foreach ($assets as $asset) {
                $kind = (string) ($asset['kind'] ?? '');
                try {
                    $row = match ($kind) {
                        'biolink'    => $this->createBiolink($owner, $kit, $asset, $counts),
                        'short_link' => $this->createShortLink($owner, $kit, $asset, $counts),
                        'qr_code'    => $this->createQrCode($owner, $kit, $asset, $counts),
                        'form'       => $this->createForm($owner, $kit, $asset, $counts),
                        'vcard'      => $this->createVcard($owner, $kit, $asset, $counts),
                        default      => throw new RuntimeException("Unknown asset kind {$kind}."),
                    };
                    if (!empty($asset['purpose'])) {
                        $row['purpose'] = (string) $asset['purpose'];
                    }
                    $created[] = $row;
                } catch (PlanCapReachedException $e) {
                    $skipped[] = $e->getMessage();
                }
            }

            if (!$created) {
                // Roll the whole run back — nothing could be created.
                throw new RuntimeException($skipped ? implode(' ', array_unique($skipped)) : 'No assets could be created.');
            }

            $kit->update([
                'status'  => BrandStudioKit::STATUS_CREATED,
                'results' => ['assets' => $created, 'skipped' => array_values(array_unique($skipped))],
            ]);
        });

        return ['kit' => $kit->refresh(), 'created' => count($created), 'skipped' => array_values(array_unique($skipped))];
    }

    // ───────── proposal sanitization ─────────

    /**
     * Validate + clamp the model's proposed assets to known kinds, per-kind
     * field shapes and per-run ceilings. When a requested $composition is
     * present (kit mode), the plan is post-validated/repaired against it:
     * kinds outside the composition are dropped, per-kind counts are clamped
     * to the requested amounts, and each kept asset is labeled with its
     * requested purpose (assigned per kind, in order).
     *
     * @param list<array{kind:string,count:int,purpose:string}> $composition
     * @return list<array<string,mixed>>
     */
    public function sanitizeAssets($raw, string $mode, ?string $bulkKind, int $bulkCount, array $composition = []): array
    {
        // Per-kind requested count + FIFO purpose queues from the composition.
        $wanted   = [];
        $purposes = [];
        foreach ($composition as $row) {
            $wanted[$row['kind']] = ($wanted[$row['kind']] ?? 0) + $row['count'];
            for ($i = 0; $i < $row['count']; $i++) {
                $purposes[$row['kind']][] = $row['purpose'];
            }
        }

        $out = [];
        $perKind = [];
        foreach ((array) $raw as $asset) {
            if (!is_array($asset)) continue;
            $kind = (string) ($asset['kind'] ?? '');
            if (!in_array($kind, self::ASSET_KINDS, true)) continue;
            if ($mode === BrandStudioKit::MODE_BULK) {
                if ($kind !== $bulkKind) continue;
                if (count($out) >= $bulkCount) break;
            } elseif ($wanted) {
                if (!isset($wanted[$kind])) continue; // kind not requested
                $perKind[$kind] = ($perKind[$kind] ?? 0) + 1;
                if ($perKind[$kind] > $wanted[$kind]) continue; // over the requested count
            } else {
                $perKind[$kind] = ($perKind[$kind] ?? 0) + 1;
                if ($perKind[$kind] > (self::KIT_CAPS[$kind] ?? 0)) continue;
            }

            $clean = $this->sanitizeAsset($kind, $asset);
            if ($clean !== null) {
                if ($wanted && $mode !== BrandStudioKit::MODE_BULK) {
                    $purpose = array_shift($purposes[$kind]);
                    if ($purpose !== null && $purpose !== '') {
                        $clean['purpose'] = $purpose;
                    }
                }
                $out[] = $clean;
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    private function sanitizeAsset(string $kind, array $a): ?array
    {
        $str = fn ($k, $max = 190) => mb_substr(trim((string) ($a[$k] ?? '')), 0, $max);
        $url = function ($k) use ($a) {
            $u = trim((string) ($a[$k] ?? ''));
            return preg_match('~^https?://~i', $u) && mb_strlen($u) <= 2048 ? $u : '';
        };

        switch ($kind) {
            case 'short_link':
                $dest = $url('url');
                if ($dest === '') return null;
                return ['kind' => $kind, 'title' => $str('title', 160) ?: 'Short link', 'url' => $dest];

            case 'qr_code':
                $dest = $url('url');
                if ($dest === '') return null;
                return ['kind' => $kind, 'name' => $str('name', 160) ?: ($str('title', 160) ?: 'QR code'), 'url' => $dest];

            case 'form':
                $template = (string) ($a['template'] ?? '');
                if (!FormTemplateCatalog::isValid($template)) {
                    $template = 'contact';
                }
                return [
                    'kind'        => $kind,
                    'title'       => $str('title', 160) ?: 'Contact form',
                    'description' => $str('description', 1000),
                    'template'    => $template,
                ];

            case 'vcard':
                $first = $str('first_name', 80);
                $last  = $str('last_name', 80);
                if ($first === '' && $last === '' && $str('organization', 160) === '') return null;
                return [
                    'kind'         => $kind,
                    'title'        => $str('title', 160) ?: trim($first . ' ' . $last),
                    'first_name'   => $first,
                    'last_name'    => $last,
                    'organization' => $str('organization', 160),
                    'job_title'    => $str('job_title', 160),
                    'phone'        => $str('phone', 40),
                    'email'        => mb_substr(trim((string) ($a['email'] ?? '')), 0, 190),
                    'website'      => $url('website'),
                    'note'         => $str('note', 500),
                ];

            case 'biolink':
                $blocks = [];
                foreach ((array) ($a['blocks'] ?? []) as $b) {
                    if (count($blocks) >= 12) break;
                    if (!is_array($b)) continue;
                    $type = (string) ($b['type'] ?? '');
                    if (!in_array($type, self::BIOLINK_BLOCK_TYPES, true)) continue;
                    $settings = is_array($b['settings'] ?? null) ? $b['settings'] : [];
                    $blocks[] = ['type' => $type, 'settings' => $this->sanitizeBlockSettings($type, $settings)];
                }
                if (!$blocks) return null;
                $theme = trim((string) ($a['theme_color'] ?? ''));
                $theme = preg_match('/^#?[0-9a-fA-F]{6}$/', $theme) ? '#' . ltrim($theme, '#') : '';
                return [
                    'kind'        => $kind,
                    'title'       => $str('title', 160) ?: 'Link in Bio',
                    'theme_color' => $theme,
                    'blocks'      => $blocks,
                ];
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function sanitizeBlockSettings(string $type, array $s): array
    {
        $str = fn ($k, $max = 500) => mb_substr(trim((string) ($s[$k] ?? '')), 0, $max);
        $url = function ($k) use ($s) {
            $u = trim((string) ($s[$k] ?? ''));
            return preg_match('~^https?://~i', $u) && mb_strlen($u) <= 2048 ? $u : '';
        };

        return match ($type) {
            'heading'   => ['text' => $str('text', 190), 'size' => in_array($s['size'] ?? '', ['h1', 'h2', 'h3'], true) ? $s['size'] : 'h2'],
            'paragraph' => ['text' => $str('text', 2000)],
            'link'      => ['text' => $str('text', 190), 'url' => $url('url')],
            'cta_button'=> ['text' => $str('text', 190), 'url' => $url('url')],
            'socials'   => ['platforms' => array_values(array_filter(array_map(function ($p) {
                if (!is_array($p)) return null;
                $platform = mb_substr(trim((string) ($p['platform'] ?? '')), 0, 40);
                $u = trim((string) ($p['url'] ?? ''));
                if ($platform === '' || !preg_match('~^https?://~i', $u)) return null;
                return ['platform' => $platform, 'url' => mb_substr($u, 0, 2048)];
            }, array_slice((array) ($s['platforms'] ?? []), 0, 10))))],
            'email_subscribe' => ['title' => $str('title', 190), 'description' => $str('description', 500), 'button_text' => $str('button_text', 60)],
            default     => [],
        };
    }

    // ───────── materializers ─────────

    /** @param array<string,int> $counts */
    private function createShortLink(User $owner, BrandStudioKit $kit, array $a, array &$counts): array
    {
        $this->assertUnder($owner, 'max_links', $counts['links'], 'short links');
        $link = Link::create([
            'user_id'  => $owner->id,
            'type'     => 'url',
            'alias'    => Link::generateAlias(),
            'title'    => $a['title'],
            'long_url' => $a['url'],
            'settings' => ['brand_studio_kit_id' => $kit->id],
        ]);
        $counts['links']++;
        return ['kind' => 'short_link', 'id' => $link->id, 'title' => $link->title, 'alias' => $link->alias];
    }

    /** @param array<string,int> $counts */
    private function createQrCode(User $owner, BrandStudioKit $kit, array $a, array &$counts): array
    {
        $this->assertUnder($owner, 'max_qr_codes', $counts['qr'], 'QR codes');

        // Style from the kit's brand palette through the shared design
        // vocabulary so nothing off-catalog can be stored.
        $palette = (array) (($kit->brand['palette'] ?? []) ?: []);
        $hex     = fn ($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : null;
        $design  = QrCodeDesignSanitizer::sanitize(array_filter([
            'fg_color'            => $hex($palette['primary'] ?? null),
            'corner_square_color' => $hex($palette['secondary'] ?? null) ?? $hex($palette['primary'] ?? null),
            'corner_dot_color'    => $hex($palette['accent'] ?? null) ?? $hex($palette['primary'] ?? null),
        ], fn ($v) => $v !== null));

        $qr = QrCode::create([
            'user_id' => $owner->id,
            'name'    => $a['name'],
            'type'    => 'url',
            'payload' => ['url' => $a['url']],
            'design'  => $design,
        ]);
        $counts['qr']++;
        return ['kind' => 'qr_code', 'id' => $qr->id, 'name' => $qr->name];
    }

    /** @param array<string,int> $counts */
    private function createForm(User $owner, BrandStudioKit $kit, array $a, array &$counts): array
    {
        $this->assertUnder($owner, 'max_forms', $counts['forms'], 'forms');
        // `user_id` is not mass-assignable on Form; set it explicitly.
        $form = new Form([
            'slug'          => Form::uniqueSlug($a['title']),
            'title'         => $a['title'],
            'description'   => $a['description'] ?: null,
            'fields'        => FormTemplateCatalog::fieldsFor($a['template']),
            'design'        => Form::defaultDesign(),
            'settings'      => Form::defaultSettings(),
            'notifications' => Form::defaultNotifications(),
            'is_active'     => true,
        ]);
        $form->user_id = $owner->id;
        $form->save();
        $counts['forms']++;
        return ['kind' => 'form', 'id' => $form->id, 'title' => $form->title];
    }

    /** @param array<string,int> $counts */
    private function createVcard(User $owner, BrandStudioKit $kit, array $a, array &$counts): array
    {
        $this->assertUnder($owner, 'max_links', $counts['links'], 'links');
        $link = Link::create([
            'user_id'  => $owner->id,
            'type'     => 'vcf',
            'alias'    => Link::generateAlias(),
            'title'    => $a['title'] ?: 'Digital card',
            'settings' => ['brand_studio_kit_id' => $kit->id],
        ]);
        VcfData::create([
            'link_id'      => $link->id,
            'first_name'   => $a['first_name'],
            'last_name'    => $a['last_name'],
            'organization' => $a['organization'],
            'title'        => $a['job_title'],
            'phone'        => $a['phone'],
            'email'        => $a['email'],
            'website'      => $a['website'],
            'note'         => $a['note'],
        ]);
        $counts['links']++;
        return ['kind' => 'vcard', 'id' => $link->id, 'title' => $link->title, 'alias' => $link->alias];
    }

    /** @param array<string,int> $counts */
    private function createBiolink(User $owner, BrandStudioKit $kit, array $a, array &$counts): array
    {
        $this->assertUnder($owner, 'max_links', $counts['links'], 'links');
        $this->assertUnder($owner, 'max_biolinks', $counts['biolinks'], 'Link in Bio pages');

        $link = Link::create([
            'user_id'  => $owner->id,
            'type'     => 'biolink',
            'alias'    => Link::generateAlias(),
            'title'    => $a['title'],
            'settings' => ['brand_studio_kit_id' => $kit->id],
        ]);

        $biolink = [];
        if (($a['theme_color'] ?? '') !== '') {
            $biolink['theme_color'] = $a['theme_color'];
        }
        app(TemplateService::class)->applyPageToLink($link, [
            'biolink' => $biolink,
            'blocks'  => $a['blocks'],
        ]);

        $counts['links']++;
        $counts['biolinks']++;
        return ['kind' => 'biolink', 'id' => $link->id, 'title' => $link->title, 'alias' => $link->alias];
    }

    private function assertUnder(User $owner, string $planKey, int $current, string $noun): void
    {
        if (!$owner->planUnderLimit($planKey, $current, 0)) {
            throw new PlanCapReachedException("Your plan's {$noun} limit was reached — some assets were skipped.");
        }
    }
}
