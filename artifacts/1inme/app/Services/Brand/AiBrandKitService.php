<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Support\QrCodeDesignSanitizer;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "AI Brand Kit" engine (Task #2662).
 *
 * Takes a free-text brand brief (plus an optional website URL and/or logo
 * URL) and asks OpenAI to craft a cohesive, persistent brand identity:
 * a color palette (primary/secondary/accent + neutrals), a heading/body
 * font pairing, a voice/tone, tagline options, an about/bio blurb, and the
 * recommended global biolink block theme. The model is constrained — both
 * in the prompt and again at parse time — to real hex colors and one of the
 * supported {@see BiolinkBlock::BLOCK_TEMPLATES} keys so it can never invent
 * a theme the editor can't render.
 *
 * Credits are charged inside OpenAiService::chat() against the `brand_kit`
 * feature (no new currency path — same token-based coin ledger as the AI
 * Link in Bio builder). If parsing/validation fails after the model was
 * billed, the exact credits are refunded so a failed generation never nets
 * a charge.
 *
 * The kit can then be APPLIED:
 *  - to a biolink (palette → font/button colors, fonts, global block theme);
 *  - to a QR code (palette → foreground/background + corner colors).
 *
 * Out of scope (handled by other tasks): a public shareable Brand/Press Kit
 * link type, brand-consistency scoring, and one-click rebrand-all.
 */
class AiBrandKitService
{
    public const FEATURE = 'brand_kit';

    /**
     * The selectable output components. `palette` is always generated (a
     * kit can't exist without colors) — the UI shows it locked-on.
     */
    public const COMPONENTS = ['palette', 'fonts', 'voice', 'taglines', 'bio', 'block_theme'];

    private const MAX_PROMPT_LEN    = 2000;
    private const MAX_URL_LEN       = 2048;
    private const MAX_OUTPUT_TOKENS = 1200;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
    ) {}

    /** The biolink block themes the AI may recommend, by key. */
    public function allowedBlockThemes(): array
    {
        return array_keys(BiolinkBlock::BLOCK_TEMPLATES);
    }

    /**
     * Build the chat messages. Shared by estimateCredits() and generate()
     * so the quoted price matches what the user is actually charged.
     *
     * @return list<array{role:string,content:string}>
     */
    // buildMessages() is below; see normalizeComponents() first.
    /**
     * Normalize a requested component list: keep only known keys, always
     * include `palette`, and treat an empty selection as "everything".
     *
     * @param  list<string> $components
     * @return list<string>
     */
    public function normalizeComponents(array $components): array
    {
        $components = array_values(array_intersect(self::COMPONENTS, array_map('strval', $components)));
        if (!$components) {
            return self::COMPONENTS;
        }
        if (!in_array('palette', $components, true)) {
            array_unshift($components, 'palette');
        }
        return $components;
    }

    public function buildMessages(User $user, string $prompt, ?string $websiteUrl = null, ?string $logoUrl = null, string $kbContext = '', array $components = []): array
    {
        $components = $this->normalizeComponents($components);
        $prompt     = trim($prompt);
        $websiteUrl = trim((string) $websiteUrl);
        $logoUrl    = trim((string) $logoUrl);

        if ($prompt === '' && $websiteUrl === '' && $logoUrl === '') {
            throw new RuntimeException('Describe your brand, or add a website or logo to start.');
        }
        if (mb_strlen($prompt) > self::MAX_PROMPT_LEN) {
            $prompt = mb_substr($prompt, 0, self::MAX_PROMPT_LEN);
        }
        $websiteUrl = mb_substr($websiteUrl, 0, self::MAX_URL_LEN);
        $logoUrl    = mb_substr($logoUrl, 0, self::MAX_URL_LEN);

        $themes = implode(', ', $this->allowedBlockThemes());

        $want = fn(string $c) => in_array($c, $components, true);

        $fields = [];
        $fields[] = "  \"name\": string,                       // short, memorable brand kit name";
        $fields[] = "  \"palette\": {\n"
            . "    \"primary\": string(hex),\n"
            . "    \"secondary\": string(hex),\n"
            . "    \"accent\": string(hex),\n"
            . "    \"neutrals\": [string(hex), ...]       // 2-4 neutral hex colors, light to dark\n"
            . "  },";
        if ($want('fonts')) {
            $fields[] = "  \"fonts\": { \"heading\": string, \"body\": string },  // real Google Font family names";
        }
        if ($want('voice')) {
            $fields[] = "  \"voice\": { \"tone\": string, \"descriptors\": [string, ...] },";
        }
        if ($want('taglines')) {
            $fields[] = "  \"taglines\": [string, ...],             // 3-5 short, distinct taglines";
        }
        if ($want('bio')) {
            $fields[] = "  \"bio\": string,                         // 1-2 sentence about/bio copy";
        }
        if ($want('block_theme')) {
            $fields[] = "  \"block_theme\": string                  // one of: {$themes}";
        }

        $rules = ["- Every color MUST be a 6-digit hex like #1A2B3C."];
        if ($want('fonts')) {
            $rules[] = "- fonts.heading and fonts.body MUST be real Google Font family names that pair well.";
        }
        if ($want('block_theme')) {
            $rules[] = "- block_theme MUST be EXACTLY one of the listed keys.";
        }
        $rules[] = "- Put real, specific copy in every text field — no Lorem Ipsum, no \"placeholder\".";
        $rules[] = "- Use empty strings/arrays rather than null.";

        $schema = "Return STRICT JSON (no markdown, no commentary, no extra keys) with this exact shape:\n"
            . "{\n" . implode("\n", $fields) . "\n}\n"
            . "Rules:\n" . implode("\n", $rules);

        $deliverables = ['a color palette'];
        if ($want('fonts')) {
            $deliverables[] = 'a heading + body font pairing';
        }
        if ($want('voice')) {
            $deliverables[] = 'a voice/tone';
        }
        if ($want('taglines')) {
            $deliverables[] = 'tagline options';
        }
        if ($want('bio')) {
            $deliverables[] = 'a short about/bio';
        }
        if ($want('block_theme')) {
            $deliverables[] = 'the on-brand Link in Bio block theme';
        }

        $system = "You are a senior brand designer for the Sayzio platform. From the user's brief "
            . "you craft a cohesive, modern brand identity: " . implode(', ', $deliverables)
            . ". Be tasteful and concrete.\n\n" . $schema;

        $kbContext = trim($kbContext);
        if ($kbContext !== '') {
            $system .= "\n\nGround the brand identity in the Knowledge Base context below — reuse its "
                . "terminology, products, audience and any stated brand preferences. Do not invent "
                . "facts that contradict the context.\n\n"
                . "Knowledge Base context:\n" . $kbContext;
        }

        $parts = [];
        if ($prompt !== '') {
            $parts[] = "BRAND BRIEF:\n" . $prompt;
        }
        if ($websiteUrl !== '') {
            $parts[] = "BRAND WEBSITE (infer the palette, naming, and voice from the brand and domain):\n" . $websiteUrl;
        }
        if ($logoUrl !== '') {
            $parts[] = "BRAND LOGO URL (infer the palette from the logo's likely colors):\n" . $logoUrl;
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => implode("\n\n", $parts)],
        ];
    }

    /** Worst-case credit cost shown before the user clicks Generate. */
    public function estimateCredits(User $user, string $prompt, ?string $websiteUrl = null, ?string $logoUrl = null, string $kbContext = '', array $components = []): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($user, $prompt, $websiteUrl, $logoUrl, $kbContext, $components);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the generation: call the model, turn its JSON into a validated
     * brand-kit config, and persist a new BrandKit owned by $user. On any
     * parse/validation failure the exact credits charged are refunded and
     * the error is surfaced to the controller.
     *
     * @return array{kit:BrandKit,credits_spent:int,model:string}
     */
    public function generate(User $user, string $prompt, ?string $websiteUrl = null, ?string $logoUrl = null, string $kbContext = '', array $components = []): array
    {
        $components = $this->normalizeComponents($components);
        $messages   = $this->buildMessages($user, $prompt, $websiteUrl, $logoUrl, $kbContext, $components);
        $model    = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.5,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'reason'          => 'AI Brand Kit generation',
            'meta'            => [
                'prompt_excerpt' => mb_substr(trim($prompt), 0, 160),
                'has_website'    => trim((string) $websiteUrl) !== '' ? 1 : 0,
                'has_logo'       => trim((string) $logoUrl) !== '' ? 1 : 0,
            ],
        ]);

        // OpenAiService::chat() charges on a successful API call. Everything
        // below (parsing, validation) can still fail — if it does we refund
        // the exact credits spent so a failed generation never nets a charge.
        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $parsed = json_decode((string) $result['content'], true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }
            $config = $this->normalizeConfig($parsed, $prompt, $websiteUrl, $logoUrl, $components);

            $name = trim((string) ($parsed['name'] ?? ''));
            if ($name === '') {
                $name = 'Brand Kit';
            }
            $name = mb_substr($name, 0, 120);

            $kit = new BrandKit();
            $kit->user_id    = $user->id;
            $kit->name       = $name;
            $kit->slug       = Str::slug($name) ?: 'brand-kit';
            $kit->config     = $config;
            $kit->is_default = !BrandKit::where('user_id', $user->id)->exists();
            $kit->save();
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($user, $creditsSpent, [
                    'feature' => self::FEATURE,
                    'reason'  => 'AI Brand Kit generation failed — auto refund',
                ]);
            }
            throw $e;
        }

        return [
            'kit'           => $kit,
            'credits_spent' => $creditsSpent,
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }

    /**
     * Apply a kit to a biolink: palette → font/button colors, the brand
     * fonts, and the recommended global block theme (applied to all blocks).
     * Mirrors the shape written by BiolinkBlockController::updatePageSettings.
     */
    public function applyToBiolink(BrandKit $kit, Link $link): void
    {
        $palette = $kit->palette();
        $fonts   = $kit->fonts();
        $theme   = $kit->blockTheme();

        $settings = is_array($link->settings) ? $link->settings : [];
        $bio      = is_array($settings['biolink'] ?? null) ? $settings['biolink'] : [];

        $bodyFont    = $this->fontName($fonts['body'] ?? null);
        $headingFont = $this->fontName($fonts['heading'] ?? null);
        if ($bodyFont) {
            $bio['font_family'] = $bodyFont;
        }

        $neutrals    = $this->paletteNeutrals($palette);
        $darkNeutral = $neutrals ? end($neutrals) : null;
        if ($darkNeutral) {
            $bio['font_color'] = $darkNeutral;
        }

        $primary = $this->hex($palette['primary'] ?? null);
        if ($primary) {
            $bio['button_color'] = $primary;
        }

        if (in_array($theme, $this->allowedBlockThemes(), true)) {
            $tpl        = BiolinkBlock::BLOCK_TEMPLATES[$theme] ?? [];
            $blockTheme = is_array($tpl['style'] ?? null) ? $tpl['style'] : [];
            $blockTheme['_template'] = $theme;
            if ($headingFont) {
                $blockTheme['font_family'] = $headingFont;
            }
            if ($darkNeutral) {
                $blockTheme['text_color'] = $darkNeutral;
            }
            // Mirrors updatePageSettings(): apply_to_all pushes the theme
            // across every block on the page.
            $blockTheme['apply_to_all'] = true;
            $bio['block_theme'] = $blockTheme;
        }

        $settings['biolink'] = $bio;
        $link->settings = $settings;
        $link->save();
    }

    /**
     * Apply a kit's palette to a QR code's design: dark neutral foreground
     * on a light neutral background, with the primary/accent driving the
     * corner colors. Run through QrCodeDesignSanitizer so the stored design
     * stays within the supported vocabulary.
     */
    public function applyToQr(BrandKit $kit, QrCode $qrCode): void
    {
        $palette = $kit->palette();

        $primary   = $this->hex($palette['primary'] ?? null);
        $secondary = $this->hex($palette['secondary'] ?? null);
        $accent    = $this->hex($palette['accent'] ?? null);
        $neutrals  = $this->paletteNeutrals($palette);

        $light = $neutrals[0] ?? '#ffffff';
        $dark  = $neutrals ? end($neutrals) : ($primary ?? '#000000');

        $design = is_array($qrCode->design) ? $qrCode->design : [];
        $design['fg_color'] = $dark;
        $design['bg_color'] = $light;
        if ($primary) {
            $design['corner_square_color'] = $primary;
        }
        $cornerDot = $accent ?? $secondary ?? $primary;
        if ($cornerDot) {
            $design['corner_dot_color'] = $cornerDot;
        }

        $qrCode->design = QrCodeDesignSanitizer::sanitize($design);
        $qrCode->save();
    }

    // ───────── internals ─────────

    /**
     * Turn the model's parsed JSON into a clean, validated brand-kit config.
     * Throws when no usable palette could be produced so the caller refunds.
     *
     * @return array<string,mixed>
     */
    private function normalizeConfig(array $parsed, string $prompt, ?string $websiteUrl, ?string $logoUrl, array $components = []): array
    {
        $components = $this->normalizeComponents($components);
        $want       = fn(string $c) => in_array($c, $components, true);
        $palette   = is_array($parsed['palette'] ?? null) ? $parsed['palette'] : [];
        $primary   = $this->hex($palette['primary'] ?? null);
        $secondary = $this->hex($palette['secondary'] ?? null);
        $accent    = $this->hex($palette['accent'] ?? null);

        if ($primary === null) {
            throw new RuntimeException('The assistant could not produce a valid color palette. Add more detail and try again.');
        }

        $neutrals = [];
        foreach ((array) ($palette['neutrals'] ?? []) as $n) {
            $h = $this->hex($n);
            if ($h !== null) {
                $neutrals[] = $h;
            }
            if (count($neutrals) >= 6) {
                break;
            }
        }
        if (!$neutrals) {
            $neutrals = ['#ffffff', '#111111'];
        }

        $heading = null;
        $body    = null;
        if ($want('fonts')) {
            $fonts   = is_array($parsed['fonts'] ?? null) ? $parsed['fonts'] : [];
            $heading = $this->fontName($fonts['heading'] ?? null) ?: 'Inter';
            $body    = $this->fontName($fonts['body'] ?? null) ?: 'Inter';
        }

        $tone        = '';
        $descriptors = [];
        if ($want('voice')) {
            $voiceRaw = is_array($parsed['voice'] ?? null) ? $parsed['voice'] : [];
            $tone     = mb_substr(trim((string) ($voiceRaw['tone'] ?? '')), 0, 200);
            foreach ((array) ($voiceRaw['descriptors'] ?? []) as $d) {
                $d = trim((string) $d);
                if ($d !== '') {
                    $descriptors[] = mb_substr($d, 0, 60);
                }
                if (count($descriptors) >= 10) {
                    break;
                }
            }
        }

        $taglines = [];
        if ($want('taglines')) {
            foreach ((array) ($parsed['taglines'] ?? []) as $t) {
                $t = trim((string) $t);
                if ($t !== '') {
                    $taglines[] = mb_substr($t, 0, 200);
                }
                if (count($taglines) >= 8) {
                    break;
                }
            }
        }

        $bio = $want('bio') ? mb_substr(trim((string) ($parsed['bio'] ?? '')), 0, 1000) : '';

        $theme = '';
        if ($want('block_theme')) {
            $theme = (string) ($parsed['block_theme'] ?? '');
            if (!in_array($theme, $this->allowedBlockThemes(), true)) {
                $theme = 'minimal';
            }
        }

        $prompt     = trim($prompt);
        $websiteUrl = trim((string) $websiteUrl);
        $logoUrl    = trim((string) $logoUrl);
        if ($websiteUrl !== '') {
            $source = ['type' => 'website', 'value' => mb_substr($websiteUrl, 0, self::MAX_URL_LEN)];
        } elseif ($logoUrl !== '') {
            $source = ['type' => 'logo', 'value' => mb_substr($logoUrl, 0, self::MAX_URL_LEN)];
        } else {
            $source = ['type' => 'prompt', 'value' => mb_substr($prompt, 0, self::MAX_PROMPT_LEN)];
        }

        return [
            'palette' => [
                'primary'   => $primary,
                'secondary' => $secondary ?? $primary,
                'accent'    => $accent ?? ($secondary ?? $primary),
                'neutrals'  => $neutrals,
            ],
            'fonts'       => ['heading' => $heading, 'body' => $body],
            'voice'       => ['tone' => $tone, 'descriptors' => $descriptors],
            'taglines'    => $taglines,
            'bio'         => $bio,
            'block_theme' => $theme,
            'source'      => $source,
        ];
    }

    /** @return list<string> validated neutral hex colors from a palette array */
    private function paletteNeutrals(array $palette): array
    {
        $out = [];
        foreach ((array) ($palette['neutrals'] ?? []) as $n) {
            $h = $this->hex($n);
            if ($h !== null) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /** Normalize a 6-digit hex (with or without leading #) to lowercase #rrggbb, or null. */
    private function hex($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $v)) {
            return '#' . strtolower(ltrim($v, '#'));
        }
        return null;
    }

    /** Sanitize a Google Font family name (letters, digits, space, _ and -). */
    private function fontName($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $safe = trim((string) preg_replace('/[^a-zA-Z0-9 _\-]/', '', trim($v)));
        return $safe !== '' ? mb_substr($safe, 0, 80) : null;
    }
}
