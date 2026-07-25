<?php

namespace App\Services\Biolink;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use RuntimeException;

/**
 * "Build my Link in Bio with AI" engine.
 *
 * Takes a free-text description plus optional links and uploaded image
 * URLs and asks OpenAI to assemble a complete biolink page. The model
 * is constrained — both in the prompt and again at parse time — to a
 * curated subset of real, supported {@see BiolinkBlock::TYPES} so it can
 * never invent block types the editor can't render. The result is turned
 * into a TemplateService snapshot and applied to the link, after which
 * the user lands in the standard biolink block editor.
 *
 * Credits are charged inside OpenAiService::chat() against the
 * `biolink_builder` feature so admins can tune the model independently.
 * There is no new currency path — this reuses the token-based AI credit
 * ledger exactly like ResumeTailorService.
 *
 * Out of scope: regenerating an existing page, multi-turn refinement,
 * and mobile (web only by design).
 */
class AiBiolinkBuilderService
{
    public const FEATURE = 'biolink_builder';

    /** Hard caps so a giant prompt doesn't blow the model window. */
    private const MAX_DESCRIPTION_LEN = 4000;
    private const MAX_LINKS           = 25;
    private const MAX_IMAGES          = 25;
    private const MAX_FILES           = 15;
    private const MAX_OUTPUT_TOKENS   = 3000;
    private const MAX_BLOCKS          = 40;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
        protected BuilderImageSourcer $imageSourcer,
    ) {}

    /**
     * Curated, safe block-type subset the AI is allowed to assemble from.
     * We deliberately exclude block types that require a pre-existing
     * related record (forms, social_proof, ai_companion), payment
     * connections, or admin-only system slugs — those can't be wired up
     * from a cold-start description. Each entry carries a one-line hint
     * and the key content fields so the model knows how to fill it.
     *
     * @return array<string,array{hint:string,fields:string}>
     */
    public static function blockCatalog(): array
    {
        return [
            'profile_card_v1' => ['hint' => 'Hero profile header with avatar, name, title, short bio. Set `design` to the PROFILE DESIGN that best fits the person/brand.', 'fields' => 'name, title, bio, avatar(url), verified(bool), location, website(url), cta_label, cta_url, socials:[{name,url}], design(one of the PROFILE DESIGNS)'],
            'profile_card_v2' => ['hint' => 'Profile header with a wide cover image behind the avatar. Set `design` to the PROFILE DESIGN that best fits the person/brand.', 'fields' => 'name, title, bio, avatar(url), cover(url), verified(bool), location, website(url), cta_label, cta_url, socials:[{name,url}], design(one of the PROFILE DESIGNS)'],
            'profile_card_v3' => ['hint' => 'Profile header that shows follower/following/post-style stat counters. Pick this when the person/brand wants to flaunt audience numbers. Set `design` to the PROFILE DESIGN that best fits.', 'fields' => 'name, title, bio, avatar(url), verified(bool), location, website(url), cta_label, cta_url, socials:[{name,url}], stats:[{label,value}], design(one of the PROFILE DESIGNS)'],
            'profile_card_v4' => ['hint' => 'Profile header that shows a row of badge/achievement pills. Pick this when the person/brand wants to highlight credentials, awards, or specialties. Set `design` to the PROFILE DESIGN that best fits.', 'fields' => 'name, title, bio, avatar(url), verified(bool), location, website(url), cta_label, cta_url, socials:[{name,url}], badges:[{label}], design(one of the PROFILE DESIGNS)'],
            'heading'         => ['hint' => 'Section heading / title text.', 'fields' => 'text, size(h1|h2|h3), align(left|center|right)'],
            'paragraph'       => ['hint' => 'A block of descriptive text.', 'fields' => 'text, align(left|center|right)'],
            'link'            => ['hint' => 'A simple tappable link button.', 'fields' => 'url, text'],
            'link_big'        => ['hint' => 'A prominent featured link with a blurb and thumbnail.', 'fields' => 'url, text, description, thumbnail(url)'],
            'cta_button'      => ['hint' => 'A bold call-to-action button.', 'fields' => 'text, url'],
            'image'           => ['hint' => 'A single image. Use only image URLs supplied to you.', 'fields' => 'url, alt, link(optional)'],
            'image_grid'      => ['hint' => 'A gallery grid of images. Use only supplied image URLs.', 'fields' => 'images:[{url,alt}], columns(2|3|4)'],
            'video'           => ['hint' => 'An embedded video by URL (YouTube/Vimeo/etc).', 'fields' => 'url'],
            'youtube'         => ['hint' => 'A YouTube video embed.', 'fields' => 'video_id OR url'],
            'spotify'         => ['hint' => 'A Spotify track/album/playlist embed.', 'fields' => 'url'],
            'pdf_document'    => ['hint' => 'A viewable/downloadable PDF. Use only SUPPLIED FILE URLs.', 'fields' => 'url, title'],
            'file'            => ['hint' => 'A downloadable file (doc, zip, etc). Use only SUPPLIED FILE URLs.', 'fields' => 'url, name'],
            'socials'         => ['hint' => 'A row of social media icons.', 'fields' => 'platforms:[{platform,url}]'],
            'list'            => ['hint' => 'A bulleted list of short items.', 'fields' => 'items:[{text}]'],
            'faq'             => ['hint' => 'Frequently asked questions.', 'fields' => 'items:[{question,answer}]'],
            'testimonials'    => ['hint' => 'Customer testimonials / quotes.', 'fields' => 'items:[{name,text,rating}]'],
            'timeline'        => ['hint' => 'A chronological timeline of milestones.', 'fields' => 'items:[{title,description,date}]'],
            'email_subscribe' => ['hint' => 'A newsletter signup form (no external account needed).', 'fields' => 'title, description, button_text'],
            'contact_form'    => ['hint' => 'A simple contact form.', 'fields' => 'title, button_text'],
            'whatsapp_widget' => ['hint' => 'A WhatsApp chat button.', 'fields' => 'phone, message, button_text'],
            'alert'           => ['hint' => 'An announcement / alert banner.', 'fields' => 'text, type(info|success|warning)'],
            'divider'         => ['hint' => 'A horizontal divider between sections.', 'fields' => '(none)'],
            'spacer'          => ['hint' => 'Vertical spacing between sections.', 'fields' => 'height(number)'],
            'card'            => ['hint' => 'A container that groups child blocks inside a styled card.', 'fields' => 'title, children:[...] (children may only be: link, link_big, heading, paragraph, image, cta_button)'],
        ];
    }

    /** Block types permitted as children of a `card` container. */
    private const CARD_CHILD_TYPES = ['link', 'link_big', 'heading', 'paragraph', 'image', 'cta_button'];

    /** Profile-card family slots that accept a ready-made identity design. */
    private const PROFILE_CARD_TYPES = ['profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4'];

    /**
     * Ready-made profile/identity designs (Task #1740) the AI may pick by
     * key. Derived from the `profile_identity` variant bundle so it can
     * never drift from the editor's gallery: a design is any variant whose
     * style carries a structural `_profile_layout` token. Returns a map of
     * variant key => display name for prompting and validation.
     *
     * @return array<string,string>
     */
    public static function profileDesigns(): array
    {
        $out = [];
        foreach (BlockVariantCatalog::forType('profile_card_v1') as $variant) {
            $layout = $variant['style']['_profile_layout'] ?? '';
            if (!is_string($layout) || $layout === '') continue;
            $out[$variant['key']] = (string) ($variant['name'] ?? $variant['key']);
        }
        return $out;
    }

    /**
     * The block types this specific user is allowed to use, intersecting
     * the curated catalog with their plan's block-type allowance.
     *
     * @return list<string>
     */
    public function allowedTypesFor(User $user): array
    {
        $types = array_keys(self::blockCatalog());
        return array_values(array_filter($types, fn ($t) => $user->userCanUseBlockType($t)));
    }

    /**
     * Build the chat messages. Shared by estimate() and generate() so the
     * quoted price matches what the user is actually charged.
     *
     * @param list<string> $links   Raw destination URLs the user pasted.
     * @param list<string> $images  Vault image URLs the user uploaded.
     * @param list<string> $files   Vault document/file URLs the user uploaded.
     * @param string $grounding     Optional knowledge-base context retrieved
     *                              from the user's selected AI Brains (Minds).
     *                              Prepended so the model writes copy grounded
     *                              in the user's own facts; never invents URLs.
     * @return list<array{role:string,content:string}>
     */
    public function buildMessages(User $user, string $description, array $links, array $images, array $files = [], string $grounding = '', string $brandDirectives = '', bool $autoSourced = false): array
    {
        $description = trim($description);
        if ($description === '') {
            throw new RuntimeException('Describe what you want on your page first.');
        }
        if (mb_strlen($description) > self::MAX_DESCRIPTION_LEN) {
            $description = mb_substr($description, 0, self::MAX_DESCRIPTION_LEN);
        }

        $links  = array_slice($this->cleanUrls($links), 0, self::MAX_LINKS);
        $images = array_slice($this->cleanImageUrls($images), 0, self::MAX_IMAGES);
        $files  = array_slice($this->cleanImageUrls($files), 0, self::MAX_FILES);

        $allowed = $this->allowedTypesFor($user);
        $catalog = self::blockCatalog();

        $catalogLines = [];
        foreach ($allowed as $type) {
            $meta = $catalog[$type];
            $catalogLines[] = "- {$type}: {$meta['hint']} Fields: {$meta['fields']}";
        }

        // Expose the ready-made identity designs only when this user can
        // actually emit a profile card, so the prompt stays focused.
        $profileAllowed = array_values(array_intersect($allowed, self::PROFILE_CARD_TYPES));
        $designLines = [];
        if ($profileAllowed) {
            foreach (self::profileDesigns() as $key => $name) {
                $designLines[] = "- {$key}: {$name}";
            }
        }

        $schemaHint = "Return STRICT JSON with this exact shape (no markdown, no commentary, no extra keys):\n"
            . "{\n"
            . "  \"page\": { \"theme_color\": string(hex, optional) },\n"
            . "  \"blocks\": [\n"
            . "    { \"type\": string, \"settings\": object, \"children\": [ { \"type\": string, \"settings\": object } ] }\n"
            . "  ]\n"
            . "}\n"
            . "Rules:\n"
            . "- `type` MUST be one of the allowed block types listed above. Never invent a type.\n"
            . "- `children` is ONLY valid on a `card` block; omit it elsewhere.\n"
            . "- Card children may only be: " . implode(', ', self::CARD_CHILD_TYPES) . ".\n"
            . "- Put real, specific copy in every text field — no Lorem Ipsum, no \"placeholder\".\n"
            . "- For link/link_big/cta_button blocks, set `url` to one of the SUPPLIED LINKS when relevant; never invent URLs.\n"
            . "- For image/image_grid blocks, use ONLY the SUPPLIED IMAGE URLs. If no images were supplied, do not add image blocks.\n"
            . "- For pdf_document/file blocks, use ONLY the SUPPLIED FILE URLs. If no files were supplied, do not add file blocks.\n"
            . "- Start the page with a profile_card_v1 (or profile_card_v2 if a cover image was supplied) when the page is about a person or brand.\n"
            . "- Prefer profile_card_v3 when the person/brand wants to show off audience numbers — fill `stats` with [{label,value}] entries (e.g. {\"label\":\"Followers\",\"value\":\"12.4K\"}). Prefer profile_card_v4 when they want to highlight credentials/awards/specialties — fill `badges` with [{label}] entries (e.g. {\"label\":\"Top Creator\"}). Use only ONE profile_card on the page.\n";

        if ($designLines) {
            $schemaHint .= "- For a profile_card block, set `design` to ONE of the PROFILE DESIGNS keys whose vibe best matches the person/brand (e.g. a founder/exec → identity_founder, an everyday creator → identity_classic, a glassy/modern look → identity_glass). Only use a listed key; omit `design` to keep the default look.\n"
                . "- On a profile_card, also fill `verified` (true only if the description clearly implies a public/notable figure), `location`, `website`, `cta_label`/`cta_url`, and `socials` (use SUPPLIED LINKS that are social profiles) when you can infer them; otherwise leave them empty.\n";
        }

        $schemaHint .= "- Aim for a complete, well-ordered page of roughly 5-12 blocks. Group related links inside a card when it improves layout.\n"
            . "- Use empty strings/arrays rather than null.";

        $system = "You are an expert link-in-bio page designer for the Sayzio platform. "
            . "You assemble a complete, attractive biolink page from the user's description using ONLY the "
            . "supported block types provided. Be tasteful and concrete.\n\n"
            . "ALLOWED BLOCK TYPES:\n" . implode("\n", $catalogLines) . "\n\n"
            . ($designLines ? "PROFILE DESIGNS (set `design` on a profile_card to one of these keys):\n" . implode("\n", $designLines) . "\n\n" : '')
            . $schemaHint;

        $userParts = ["WHAT THE USER WANTS:\n" . $description];

        // On-Brand AI (Task #2664): prepend the creator's saved Brand Kit
        // voice/palette directives right after the description so the model
        // treats them as binding style guidance for the whole page.
        $brandDirectives = trim($brandDirectives);
        if ($brandDirectives !== '') {
            $userParts[] = mb_substr($brandDirectives, 0, self::MAX_DESCRIPTION_LEN);
        }

        $grounding = trim($grounding);
        if ($grounding !== '') {
            // Cap so a large knowledge base can't blow the model window.
            $grounding = mb_substr($grounding, 0, self::MAX_DESCRIPTION_LEN);
            $userParts[] = "GROUNDING KNOWLEDGE (facts from the user's own AI Brain — prefer these for names, "
                . "bios, offerings, and copy; never copy verbatim secrets and never invent URLs from this):\n" . $grounding;
        }
        if ($links) {
            $userParts[] = "SUPPLIED LINKS (use these as destination URLs):\n- " . implode("\n- ", $links);
        }
        if ($images) {
            $label = $autoSourced
                ? "SUPPLIED IMAGE URLS (auto-sourced from the user's links or AI-generated to match the description — use them for image/avatar/cover/thumbnail fields)"
                : "SUPPLIED IMAGE URLS (use these for image/avatar/cover/thumbnail fields)";
            $userParts[] = $label . ":\n- " . implode("\n- ", $images);
        } else {
            $userParts[] = "No images were supplied — do not add image blocks and leave avatar/cover empty.";
        }
        if ($files) {
            $userParts[] = "SUPPLIED FILE URLS (use these for pdf_document/file block url fields):\n- " . implode("\n- ", $files);
        } else {
            $userParts[] = "No files were supplied — do not add pdf_document or file blocks.";
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => implode("\n\n", $userParts)],
        ];
    }

    /**
     * Worst-case credit cost shown before the user clicks Generate.
     *
     * When the user attached no images the auto-sourcing step (Task #5720)
     * may fall back to generating an avatar + cover, so the worst-case
     * quote includes that generation cost too (extraction from links is
     * free — if it succeeds the build costs less than quoted).
     */
    public function estimateCredits(User $user, string $description, array $links, array $images, array $files = [], string $grounding = '', string $brandDirectives = '', array $imageChoices = []): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($user, $description, $links, $images, $files, $grounding, $brandDirectives);
        $cost     = $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);

        if ($this->cleanImageUrls($images) === []) {
            // Preview-confirmed choices (Task #5722): kept extracted images
            // mean generation never runs; explicitly skipped slots are
            // removed from the worst-case quote too.
            $kept = $imageChoices['kept'] ?? null;
            if (is_array($kept) && $this->cleanImageUrls($kept) !== []) {
                // Extraction is free — no generation fallback in the quote.
            } else {
                $cost += $this->imageSourcer->fallbackGenerationEstimate(
                    $user,
                    array_values((array) ($imageChoices['skip_slots'] ?? [])),
                );
            }
        }

        return $cost;
    }

    /**
     * Run the build: call the model, turn its JSON into a TemplateService
     * snapshot constrained to allowed block types, and apply it to the
     * link (replacing any existing blocks). Returns the credits spent and
     * how many blocks were created.
     *
     * @return array{credits_spent:int,blocks:int,model:string}
     */
    public function generate(User $user, Link $link, string $description, array $links, array $images, array $files = [], string $grounding = '', bool $replaceBlocks = true, string $brandDirectives = '', array $imageChoices = []): array
    {
        $links  = $this->cleanUrls($links);
        $images = $this->cleanImageUrls($images);
        $files  = $this->cleanImageUrls($files);

        // Auto-source images when the user attached none (Task #5720):
        // extract from their links (free), else AI-generate an avatar +
        // cover (charged per image inside the sourcer, refunded below if
        // the build ultimately fails). Uploads always win untouched.
        // When the creator confirmed the image preview step (Task #5722)
        // their kept extracted images are used verbatim (no re-extraction)
        // and any deselected generation slots are skipped.
        $kept = $imageChoices['kept'] ?? null;
        $sourced = $this->imageSourcer->source(
            $user, $description, $links, $images, $link->id,
            is_array($kept) ? $this->cleanImageUrls($kept) : null,
            array_values((array) ($imageChoices['skip_slots'] ?? [])),
        );
        $images  = $this->cleanImageUrls($sourced['images']);

        $messages = $this->buildMessages(
            $user, $description, $links, $images, $files, $grounding, $brandDirectives,
            $sourced['uploaded'] === 0 && $images !== [],
        );
        $model = AiEngineSettings::featureModel(self::FEATURE);

        try {
            $result = $this->openai->chat($user, $model, $messages, [
                'temperature'     => 0.4,
                'max_tokens'      => self::MAX_OUTPUT_TOKENS,
                'response_format' => ['type' => 'json_object'],
                'feature'         => self::FEATURE,
                'related_id'      => $link->id,
                'reason'          => 'AI Link in Bio page builder',
                'meta'            => [
                    'desc_excerpt'     => mb_substr($description, 0, 160),
                    'links'            => count($links),
                    'images'           => count($images),
                    'files'            => count($files),
                    'images_extracted' => count($sourced['extracted']),
                    'images_searched'  => count($sourced['searched'] ?? []),
                    'images_generated' => count($sourced['generated']),
                ],
            ]);
        } catch (\Throwable $e) {
            // The chat call itself failed — undo any generated-image
            // charges/files so a page that never materialized costs nothing.
            $this->imageSourcer->rollback($user, $sourced);
            throw $e;
        }

        // OpenAiService::chat() charges on a successful API call. Everything
        // below (parsing, validation, materialisation) can still fail — if it
        // does we refund the exact credits spent so a failed build never nets
        // a charge, then surface the error to the controller.
        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $parsed = json_decode((string) $result['content'], true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }

            $snapshot = $this->snapshotFromAi($user, $parsed, $links, $images, $files);
            if (empty($snapshot['blocks'])) {
                throw new RuntimeException('The assistant could not build a page from that description. Add more detail and try again.');
            }

            app(TemplateService::class)->applyPageToLink($link, $snapshot, $replaceBlocks);
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($user, $creditsSpent, [
                    'feature'    => self::FEATURE,
                    'related_id' => $link->id,
                    'reason'     => 'AI Link in Bio builder failed — auto refund',
                ]);
            }
            throw $e;
        }

        return [
            'credits_spent' => $creditsSpent,
            'blocks'        => count($snapshot['blocks']),
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }

    // ───────── internals ─────────

    /**
     * Turn the model's parsed JSON into a TemplateService page snapshot,
     * dropping any block whose type isn't in this user's allowed set and
     * coercing each block's settings onto sane per-type defaults.
     *
     * After the AI blocks are built we deterministically wire up any
     * supplied link/image/file the model failed to reference, appending
     * real blocks for them so no user resource is silently dropped.
     *
     * @param list<string> $links
     * @param list<string> $images
     * @param list<string> $files
     * @return array{biolink:array<string,mixed>,blocks:list<array<string,mixed>>}
     */
    private function snapshotFromAi(User $user, array $parsed, array $links = [], array $images = [], array $files = []): array
    {
        $allowed = array_flip($this->allowedTypesFor($user));

        $biolink = [];
        $theme = $parsed['page']['theme_color'] ?? null;
        if (is_string($theme) && preg_match('/^#?[0-9a-fA-F]{6}$/', trim($theme))) {
            $biolink['theme_color'] = '#' . ltrim(trim($theme), '#');
        }

        $blocks = [];
        foreach ((array) ($parsed['blocks'] ?? []) as $raw) {
            if (count($blocks) >= self::MAX_BLOCKS) break;
            if (!is_array($raw)) continue;
            $type = (string) ($raw['type'] ?? '');
            if (!isset($allowed[$type])) continue;

            $block = $this->buildBlock($type, is_array($raw['settings'] ?? null) ? $raw['settings'] : []);

            if ($type === 'card') {
                $children = [];
                foreach ((array) ($raw['children'] ?? []) as $childRaw) {
                    if (!is_array($childRaw)) continue;
                    $childType = (string) ($childRaw['type'] ?? '');
                    if (!in_array($childType, self::CARD_CHILD_TYPES, true)) continue;
                    if (!isset($allowed[$childType])) continue;
                    $children[] = $this->buildBlock($childType, is_array($childRaw['settings'] ?? null) ? $childRaw['settings'] : []);
                }
                $block['children'] = $children;
            }

            $blocks[] = $block;
        }

        $blocks = $this->appendUnreferencedResources($blocks, $allowed, $links, $images, $files);

        return ['biolink' => $biolink, 'blocks' => $blocks];
    }

    /**
     * Guarantee every supplied resource lands on the page. We collect every
     * string already present anywhere in the built blocks (recursively,
     * including card children), then append blocks for any supplied
     * link/image/file that wasn't referenced — respecting the user's
     * allowed block types and the overall block cap.
     *
     * @param list<array<string,mixed>> $blocks
     * @param array<string,int>         $allowed  type => index (from array_flip)
     * @param list<string>              $links
     * @param list<string>              $images
     * @param list<string>              $files
     * @return list<array<string,mixed>>
     */
    private function appendUnreferencedResources(array $blocks, array $allowed, array $links, array $images, array $files): array
    {
        $seen = [];
        foreach ($this->collectStrings($blocks) as $s) {
            $seen[$s] = true;
        }

        $missingLinks  = array_values(array_filter($links,  fn ($u) => !isset($seen[$u])));
        $missingImages = array_values(array_filter($images, fn ($u) => !isset($seen[$u])));
        $missingFiles  = array_values(array_filter($files,  fn ($u) => !isset($seen[$u])));

        $room = fn () => count($blocks) < self::MAX_BLOCKS;

        // Unreferenced images → a single image_grid when possible, else one image block each.
        if ($missingImages && $room()) {
            if (count($missingImages) > 1 && isset($allowed['image_grid'])) {
                $blocks[] = $this->buildBlock('image_grid', [
                    'images' => array_map(fn ($u) => ['url' => $u, 'alt' => ''], $missingImages),
                ]);
            } elseif (isset($allowed['image'])) {
                foreach ($missingImages as $u) {
                    if (!$room()) break;
                    $blocks[] = $this->buildBlock('image', ['url' => $u, 'alt' => '']);
                }
            }
        }

        // Unreferenced files → pdf_document for PDFs (if allowed), else file.
        foreach ($missingFiles as $u) {
            if (!$room()) break;
            $isPdf = (bool) preg_match('/\.pdf($|[?#])/i', $u);
            if ($isPdf && isset($allowed['pdf_document'])) {
                $blocks[] = $this->buildBlock('pdf_document', ['url' => $u, 'title' => $this->fileLabelFromUrl($u)]);
            } elseif (isset($allowed['file'])) {
                $blocks[] = $this->buildBlock('file', ['url' => $u, 'name' => $this->fileLabelFromUrl($u)]);
            }
        }

        // Unreferenced links → simple link buttons.
        if (isset($allowed['link'])) {
            foreach ($missingLinks as $u) {
                if (!$room()) break;
                $blocks[] = $this->buildBlock('link', ['url' => $u, 'text' => $this->linkLabelFromUrl($u)]);
            }
        }

        return $blocks;
    }

    /**
     * Recursively collect every scalar string contained in a structure,
     * used to detect which supplied URLs the AI already referenced.
     *
     * @param mixed $value
     * @return list<string>
     */
    private function collectStrings($value): array
    {
        $out = [];
        if (is_string($value)) {
            $out[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $v) {
                foreach ($this->collectStrings($v) as $s) {
                    $out[] = $s;
                }
            }
        }
        return $out;
    }

    /** Human-ish label for a file URL (basename, de-slugged). */
    private function fileLabelFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $base = rawurldecode(basename($path));
        $base = trim($base);
        return $base !== '' ? $base : 'Download';
    }

    /** Human-ish label for a plain link (host without www). */
    private function linkLabelFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./i', '', $host);
        return $host !== '' ? $host : 'Visit link';
    }

    /**
     * Build one snapshot block: per-type content defaults overlaid with
     * the AI's settings (real content wins), placeholder flags removed,
     * and a first-paint `_style` seeded exactly like a hand-added block.
     * Final sanitization happens in TemplateService on apply.
     *
     * @return array{type:string,settings:array<string,mixed>,is_active:bool}
     */
    private function buildBlock(string $type, array $aiSettings): array
    {
        $base = BlockDefaults::contentForType($type);

        // AI content takes precedence over the placeholder seed.
        $settings = array_replace($base, $aiSettings);

        // The AI supplies real content, so this isn't a placeholder block.
        unset($settings['_placeholder'], $settings['_placeholder_seed']);

        // `design` is a meta hint for picking a ready-made profile look, not
        // a real content field — pull it out before it lands in settings.
        $design = is_string($settings['design'] ?? null) ? trim($settings['design']) : '';
        unset($settings['design']);

        // Seed structural first-paint styling, matching store().
        $style = array_merge(
            BiolinkBlock::STYLE_DEFAULTS,
            BlockDefaults::styleForType($type)
        );

        // Apply a chosen profile/identity design exactly like the editor's
        // applyVariant: the catalog variant carries the structural
        // `_profile_layout` token (preserved by the style sanitizer's slug
        // group on apply) plus the matching skin, and we stamp the variant
        // bookkeeping so the saved block is indistinguishable from a
        // hand-picked design. Unknown keys fall through to the default look.
        if ($design !== '' && in_array($type, self::PROFILE_CARD_TYPES, true)) {
            $variant = BlockVariantCatalog::find($type, $design);
            if ($variant && !empty($variant['style'])) {
                $style = array_merge($style, $variant['style'], [
                    '_variant'         => $variant['key'],
                    '_variant_version' => BlockVariantCatalog::VERSION,
                ]);
            }
        }

        $settings['_style'] = $style;

        return [
            'type'      => $type,
            'settings'  => $settings,
            'is_active' => true,
        ];
    }

    /**
     * Keep only well-formed absolute http(s) URLs, de-duplicated.
     *
     * @param array<int|string,mixed> $urls
     * @return list<string>
     */
    private function cleanUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $u) {
            if (!is_string($u)) continue;
            $u = trim($u);
            if ($u === '') continue;
            if (!preg_match('#^https?://#i', $u)) continue;
            if (filter_var($u, FILTER_VALIDATE_URL) === false) continue;
            $out[$u] = true;
        }
        return array_keys($out);
    }

    /**
     * Like cleanUrls() but also accepts root-relative paths (e.g. the
     * `/f/{id}/{filename}` vault URLs returned by the file uploader),
     * since biolink image blocks store relative URLs natively.
     *
     * @param array<int|string,mixed> $urls
     * @return list<string>
     */
    private function cleanImageUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $u) {
            if (!is_string($u)) continue;
            $u = trim($u);
            if ($u === '') continue;
            if (preg_match('#^https?://#i', $u)) {
                if (filter_var($u, FILTER_VALIDATE_URL) === false) continue;
            } elseif ($u[0] !== '/' || str_starts_with($u, '//')) {
                // Only absolute http(s) or single-leading-slash relative paths.
                continue;
            }
            $out[$u] = true;
        }
        return array_keys($out);
    }
}
