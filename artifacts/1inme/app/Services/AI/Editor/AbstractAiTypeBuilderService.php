<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared pipeline for the per-link-type "Build with AI" tools (Task #5727).
 *
 * Mirrors the contract proven by {@see \App\Services\Biolink\AiBiolinkBuilderService}
 * (which stays untouched — its behaviour is load-bearing for the Link in Bio
 * flow): the chat call itself charges the wallet via OpenAiService, and if
 * the response can't be parsed or materialized into real content, the exact
 * charged credits are refunded so a failed build never nets a charge.
 *
 * Subclasses supply the AI-credit feature key, the JSON contract prompt, and
 * the materializer that turns the parsed payload into rows for their type.
 */
abstract class AbstractAiTypeBuilderService
{
    /** Hard input caps shared by every type builder. */
    public const MAX_DESCRIPTION = 4000;
    public const MAX_LINKS       = 10;
    public const MAX_IMAGES      = 10;

    /** Output budget for the generation call (shared default). */
    public const MAX_OUTPUT_TOKENS = 4000;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $charger,
    ) {}

    /** AI-credit feature key (must exist in AiFeatureCatalog + AiEngineSettings::FEATURES). */
    abstract public function feature(): string;

    /** The links.type this builder materializes into. */
    abstract public function linkType(): string;

    /** Human label used in charge reasons ("AI Slides builder", ...). */
    abstract public function label(): string;

    /**
     * System prompt describing the strict JSON contract for this type.
     * Must instruct the model to answer with a single JSON object.
     */
    abstract protected function systemPrompt(User $user): string;

    /**
     * What this page already holds, for the model to modify rather than
     * replace blind.
     *
     * Empty by default, which is exactly the old behaviour: a builder that
     * does not override this keeps building from the brief alone. Overriding
     * it is what turns "Build" into "Modify" for that page type.
     */
    public function existingContext(Link $link): string
    {
        return '';
    }

    /** Does this page have content a build would overwrite? */
    public function hasExistingContent(Link $link): bool
    {
        return $this->existingContext($link) !== '';
    }

    /**
     * Can this builder change a page IN PLACE, rather than replacing it?
     *
     * Sana, 2026-10-05: "it should not be modify whole.. it should able to
     * update as per instructions... it should able to change backgroud, add
     * and modify items, add stickers or any blocks or update price or
     * anything features available".
     *
     * He is describing a different operation from the one this class was
     * built for. A builder WRITES A PAGE: it deletes what is there and puts
     * its answer in place. The previous change made that survivable on a
     * page with content -- the model is sent the current menu and told that
     * anything omitted is deleted -- but survivable is not right. "Change
     * the dosa price" should not re-generate eighty dishes, should not
     * touch descriptions nobody mentioned, and should not stake a live menu
     * on one model copying seventy-nine rows back correctly.
     *
     * A builder that returns true here answers with a LIST OF CHANGES
     * instead, and {@see applyPlan()} applies them to the rows already
     * there. Anything the plan does not name is not written to at all.
     */
    public function supportsEditing(): bool
    {
        return false;
    }

    /** True when this run should edit rather than rebuild. */
    public function isEditing(Link $link): bool
    {
        return $this->supportsEditing() && $this->hasExistingContent($link);
    }

    /**
     * What this builder's edit path can actually change, in the creator's
     * words, for the screen to list.
     *
     * Asked of the SERVICE rather than written into the view, so the one
     * list the creator reads and the one the model is given come from the
     * same place. A screen that promises a change the AI cannot make is the
     * same bug this codebase keeps shipping from the other direction.
     *
     * @return array<int, string>
     */
    public function editAbilities(): array
    {
        return [];
    }

    /** The JSON contract for an edit plan. Required when supportsEditing(). */
    protected function editPrompt(User $user): string
    {
        return '';
    }

    /**
     * Apply a parsed edit plan to the page's existing rows.
     *
     * Runs inside the same transaction materialize() would. Must throw
     * \RuntimeException when nothing could be applied, so the charge is
     * refunded exactly as it is for a failed build.
     *
     * @return array summary meta for the UI
     */
    protected function applyPlan(User $user, Link $link, array $parsed): array
    {
        throw new \RuntimeException('This page type cannot be edited by AI yet.');
    }

    /**
     * Turn the parsed model JSON into persisted rows for $link.
     * Runs inside a DB transaction. Must throw \RuntimeException when the
     * payload yields no usable content (triggers the auto-refund).
     *
     * @param  array $parsed  decoded model JSON
     * @param  array $links   caller-supplied URLs (already cleaned)
     * @param  array $images  caller-supplied image URLs (already cleaned)
     * @return array          summary meta for the UI (e.g. counts)
     */
    abstract protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array;

    /** Whether the intake should offer an image list for this type. */
    public function supportsImages(): bool
    {
        return true;
    }

    /**
     * Whether this builder can READ pictures, as opposed to referencing
     * them.
     *
     * These are two different jobs and conflating them is why the feature
     * Sana asked for looked like it already existed. `supportsImages()`
     * means "the user can hand me photo URLs to hang on the dishes"; the
     * model never looks at those, it just quotes them back. This means
     * "the user can hand me a photograph of their actual menu card and I
     * will read what is on it" -- which needs the picture to travel as a
     * vision content part rather than as a line of text saying its
     * address.
     */
    public function readsImages(): bool
    {
        return false;
    }

    /**
     * How many scans of a card this builder will look at in one go.
     *
     * A real menu card is one to four sides. The cap is low on purpose:
     * every image is charged for, and a creator who uploads twelve photos
     * of the same laminated sheet should be stopped before the charge,
     * not after it.
     */
    public const MAX_SCANS = 4;

    /** Whether the intake should offer a URL list for this type. */
    public function supportsLinks(): bool
    {
        return true;
    }

    /**
     * Coin estimate for the given inputs (same model the generation will use).
     *
     * `$link` is optional and last so every existing caller is unchanged,
     * but passing it matters: on a page that already has content the
     * generation sends that content too, and an estimate that leaves it out
     * quotes a price the build then exceeds.
     */
    public function estimateCredits(User $user, string $description, array $links, array $images, array $scans = [], ?Link $link = null): int
    {
        $messages = $this->buildMessages(
            $user,
            $description,
            $this->cleanUrls($links),
            $this->cleanImageUrls($images),
            $this->cleanScans($scans),
            $link ? $this->existingContext($link) : '',
            $link ? $this->isEditing($link) : false
        );
        $model    = AiEngineSettings::featureModel($this->feature(), $user);

        return $this->openai->estimateChatCoins($model, $messages, static::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the paid generation and materialize the result.
     *
     * @return array{credits_spent:int, summary:array}
     * @throws \RuntimeException when the response can't be turned into content
     *         (the charge is refunded first).
     */
    public function generate(User $user, Link $link, string $description, array $links, array $images, array $scans = []): array
    {
        $links  = $this->cleanUrls($links);
        $images = $this->supportsImages() ? $this->cleanImageUrls($images) : [];
        $scans  = $this->cleanScans($scans);

        $editing  = $this->isEditing($link);
        $messages = $this->buildMessages($user, $description, $links, $images, $scans, $this->existingContext($link), $editing);
        $model    = AiEngineSettings::featureModel($this->feature(), $user);

        $response = $this->openai->chat($user, $model, $messages, [
            'max_tokens'      => static::MAX_OUTPUT_TOKENS,
            'temperature'     => 0.7,
            'response_format' => ['type' => 'json_object'],
            'feature'         => $this->feature(),
            'related_id'      => $link->id,
            'reason'          => $this->label(),
        ]);

        $creditsSpent = (int) ($response['credits_spent'] ?? 0);

        try {
            $parsed = $this->parseJson((string) ($response['content'] ?? ''));

            // The branch that makes "Modify" an honest word. An edit plan
            // touches only the rows it names; materialize() deletes the
            // catalogue and writes a new one. Which of the two runs is
            // decided once, above, and used for the prompt, the parse and
            // the write -- so the model can never be asked for a plan and
            // have its answer read as a page.
            $summary = DB::transaction(fn () => $editing
                ? $this->applyPlan($user, $link, $parsed)
                : $this->materialize($user, $link, $parsed, $links, $images));

            return [
                'credits_spent' => $creditsSpent,
                'summary'       => $summary,
            ];
        } catch (\Throwable $e) {
            // Failed build — refund exactly what the chat call charged.
            if ($creditsSpent > 0) {
                try {
                    $this->charger->refund($user, $creditsSpent, [
                        'feature'    => $this->feature(),
                        'related_id' => $link->id,
                        'reason'     => $this->label() . ' failed — auto refund',
                    ]);
                } catch (\Throwable $refundError) {
                    Log::error('AI type builder refund failed', [
                        'feature' => $this->feature(),
                        'link_id' => $link->id,
                        'error'   => $refundError->getMessage(),
                    ]);
                }
            }

            if ($e instanceof \RuntimeException) {
                throw $e;
            }

            Log::warning('AI type builder materialization failed', [
                'feature' => $this->feature(),
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
            throw new \RuntimeException('The AI response could not be turned into a page. Your coins were refunded — please try again.');
        }
    }

    /** Chat messages: shared framing + subclass contract + the user's brief. */
    protected function buildMessages(User $user, string $description, array $links, array $images, array $scans = [], string $existing = '', bool $editing = false): array
    {
        $userParts = [
            ($editing ? "Instruction:\n" : "Brief:\n") . mb_substr(trim($description), 0, self::MAX_DESCRIPTION),
        ];

        // Sana, 2026-10-05: "when already created... it should show like
        // modify with AI".
        //
        // Every builder REPLACES the catalogue wholesale -- the service
        // deletes every category and item and writes the response in their
        // place. So "modify" could not be an honest word while the model was
        // only ever told the brief: ask for "add a desserts section" on a
        // page with eighty dishes and you got a menu with desserts and
        // nothing else.
        //
        // Sending the current content, plus the instruction that anything
        // omitted is deleted, is what makes the word true.
        // An edit plan needs the current page for a different reason: not
        // so the model can copy it back, but so it can NAME the rows it
        // wants changed. Nothing is copied, so nothing can be lost in the
        // copying -- which is the whole reason this path exists.
        if ($editing && $existing !== '') {
            $userParts[] = "The page currently holds the content below. Name the sections and "
                ."items EXACTLY as they are written here when you refer to them. Do not "
                ."return this content back to me — return only the changes the instruction "
                ."asks for.\n\n"
                .$existing;
        } elseif ($existing !== '') {
            $userParts[] = "This page ALREADY has the content below. The brief above is a CHANGE to it, "
                ."not a description of a new page.\n\n"
                ."Return the COMPLETE result with that change applied: every section and every item that "
                ."should still be there, not only the new or edited ones. Anything you leave out is deleted. "
                ."Keep existing names, descriptions and prices exactly as they are unless the brief asks for "
                ."them to change.\n\n"
                .$existing;
        }

        if ($links) {
            $userParts[] = "URLs supplied by the user (use them where they fit; never invent other URLs):\n- " . implode("\n- ", $links);
        }

        if ($this->supportsImages()) {
            if ($images) {
                $userParts[] = "Image URLs supplied by the user (the ONLY images you may reference, keep each URL EXACTLY as given):\n- " . implode("\n- ", $images);
            } else {
                $userParts[] = 'No images supplied — do not reference or invent any image URLs.';
            }
        }

        $text = implode("\n\n", $userParts);

        // The contract differs by job: a build is told how to describe a
        // page, an edit is told how to describe a change. One line, so the
        // two can never be sent against each other's parser.
        $system = $editing ? $this->editPrompt($user) : $this->systemPrompt($user);

        // No scans: the message is a plain string, exactly as before. Every
        // existing builder and every existing estimate is unchanged.
        if (! $scans || ! $this->readsImages()) {
            return [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $text],
            ];
        }

        // With scans, the user turn becomes content parts so the model
        // actually SEES the card. The instruction goes first: a model given
        // pictures and then told what to do with them reads better than one
        // told afterwards.
        $parts = [['type' => 'text', 'text' => $text . "\n\n" . $this->scanInstruction()]];
        foreach ($scans as $scan) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $scan]];
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $parts],
        ];
    }

    /**
     * What to do with the pictures. Overridden by builders that read them.
     *
     * The default says "transcribe, do not invent", because the failure
     * that matters here is a model filling gaps: a menu card with a smudged
     * price becomes a confidently wrong price on a live page, and the
     * creator has no way to tell which lines were read and which were
     * guessed.
     */
    protected function scanInstruction(): string
    {
        return 'The attached images are photographs or scans of a real document. '
            .'Transcribe what is actually printed on them. Do not invent entries, '
            .'prices or descriptions that are not there, and omit anything you '
            .'cannot read rather than guessing at it.';
    }

    /**
     * Scans the model may be shown: http(s) URLs or data URLs, capped.
     *
     * Data URLs are allowed because a vault on a local disk has no publicly
     * fetchable address, and a scan the model cannot fetch is a charge for
     * nothing.
     *
     * @return array<int, string>
     */
    protected function cleanScans(array $urls): array
    {
        if (! $this->readsImages()) {
            return [];
        }

        $out = [];

        foreach ($urls as $url) {
            if (! is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $isData = str_starts_with($url, 'data:image/');
            $isHttp = (bool) filter_var($url, FILTER_VALIDATE_URL)
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
            if (! $isData && ! $isHttp) {
                continue;
            }
            $out[] = $url;
        }

        return array_slice(array_values(array_unique($out)), 0, static::MAX_SCANS);
    }

    /** Decode the model output, tolerating fenced code blocks. */
    protected function parseJson(string $content): array
    {
        $raw = trim($content);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('The AI response was not valid JSON. Your coins were refunded — please try again.');
        }

        return $parsed;
    }

    /** Keep only plausible http(s) URLs, capped. */
    protected function cleanUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            if (!is_string($url)) continue;
            $url = trim($url);
            if ($url === '' || mb_strlen($url) > 2048) continue;
            if (!preg_match('#^https?://#i', $url)) continue;
            $out[] = $url;
            if (count($out) >= self::MAX_LINKS) break;
        }
        return array_values(array_unique($out));
    }

    /** Image URLs may be absolute http(s) OR relative vault paths (/f/...). */
    protected function cleanImageUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            if (!is_string($url)) continue;
            $url = trim($url);
            if ($url === '' || mb_strlen($url) > 2048) continue;
            if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) continue;
            $out[] = $url;
            if (count($out) >= self::MAX_IMAGES) break;
        }
        return array_values(array_unique($out));
    }

    /** A supplied-image guard: only URLs the user actually provided pass. */
    protected function suppliedImage(?string $url, array $images): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        return ($url !== '' && in_array($url, $images, true)) ? $url : null;
    }

    /** Clamp helper for model-provided strings. */
    protected function str(mixed $value, int $max): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** Clamp helper for model-provided prices. */
    protected function price(mixed $value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;
        return max(0.0, min(9999999.0, round($n, 2)));
    }
}
