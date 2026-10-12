<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The AI biolink builder's "fully populated" promise: every supplied
 * link / image / file the model forgot to use is still wired onto the
 * finished page as a real block (`appendUnreferencedResources`).
 *
 * The credit/charge/refund flow is covered by BiolinkWizardAiCreditTest;
 * this suite isolates the resource-completeness guarantee that had no
 * direct coverage. A regression here would silently drop a user's
 * uploaded photos, files, or pasted links from their page with no error.
 *
 * We leave the REAL builder in place and fake only the OpenAI HTTP layer
 * (same pattern as BiolinkWizardAiCreditTest), driving the genuine
 * snapshotFromAi → appendUnreferencedResources → TemplateService apply
 * path, then asserting against the persisted BiolinkBlock rows.
 */
class BiolinkAiBuilderResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

    private function plan(array $features = ['max_links' => 100, 'max_biolinks' => 100]): Plan
    {
        return Plan::create([
            'name'          => 'Test Plan',
            'slug'          => 'test-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => $features,
        ]);
    }

    private function makeUser(?Plan $plan = null): User
    {
        return User::factory()->create([
            'plan_id' => $plan?->id,
        ])->fresh();
    }

    private function biolink(User $user): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'is_active' => true,
        ]);
    }

    private function seedCoins(User $user, int $coins = 100_000): void
    {
        app(WalletService::class)->credit($user, $coins, ['reason' => 'test seed']);
    }

    /** A well-formed OpenAI chat-completion envelope wrapping $content. */
    private function fakeOpenAi(string $content): void
    {
        $envelope = [
            'id'      => 'chatcmpl-fake-' . Str::random(8),
            'object'  => 'chat.completion',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage'   => ['prompt_tokens' => 800, 'completion_tokens' => 400, 'total_tokens' => 1200],
            'model'   => 'gpt-4o-mini',
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($envelope),
        ]);
    }

    /**
     * A model response that builds a tidy page but references NONE of the
     * supplied resources (no images, no files, no links) — the exact case
     * appendUnreferencedResources exists to backstop.
     */
    private function pageReferencingNothing(): string
    {
        return json_encode([
            'page'   => ['theme_color' => '#3d6bff'],
            'blocks' => [
                ['type' => 'profile_card_v1', 'settings' => [
                    'name'  => 'Bob Bakes',
                    'title' => 'Artisan Bakery',
                    'bio'   => 'Fresh sourdough every morning.',
                ]],
                ['type' => 'heading',   'settings' => ['text' => 'Visit Us']],
                ['type' => 'paragraph', 'settings' => ['text' => 'Open daily.']],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /** Persisted top-level blocks for a link, keyed nothing (flat list). */
    private function blocksFor(Link $link)
    {
        return BiolinkBlock::where('link_id', $link->id)->get();
    }

    /**
     * Types of every persisted block (top-level + card children) whose
     * settings contain $needle anywhere. URL-agnostic to field renames in
     * the sanitizer — we only care that the resource landed, and as what.
     *
     * @return list<string>
     */
    private function blockTypesContaining(Link $link, string $needle): array
    {
        $types = [];
        foreach (BiolinkBlock::where('link_id', $link->id)->get() as $block) {
            $json = json_encode($block->settings ?? []);
            if (is_string($json) && str_contains($json, $needle)) {
                $types[] = $block->type;
            }
        }
        return $types;
    }

    // ── Unreferenced resources are still added ────────────────────────

    /**
     * Given supplied images, files, and links the model ignored entirely,
     * the finished page still carries a block for every one of them:
     * image_grid for the photos, pdf_document/file for the documents, and
     * a link button for each pasted URL.
     */
    public function test_unreferenced_resources_are_all_appended_as_blocks(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $link = $this->biolink($user);
        $this->fakeOpenAi($this->pageReferencingNothing());

        $imageA = 'https://cdn.example.com/photos/a.jpg';
        $imageB = 'https://cdn.example.com/photos/b.png';
        $pdf    = 'https://cdn.example.com/docs/menu.pdf';
        $zip    = 'https://cdn.example.com/docs/pack.zip';
        $linkA  = 'https://shop.example.com/store';
        $linkB  = 'https://blog.example.com/post';

        app(AiBiolinkBuilderService::class)->generate(
            $user,
            $link,
            'A bakery page',
            [$linkA, $linkB],
            [$imageA, $imageB],
            [$pdf, $zip],
        );

        // Two unreferenced images collapse into a single image_grid.
        $this->assertContains('image_grid', $this->blockTypesContaining($link, $imageA),
            'first supplied image must land in an image_grid block');
        $this->assertContains('image_grid', $this->blockTypesContaining($link, $imageB),
            'second supplied image must land in an image_grid block');

        // The PDF becomes a pdf_document; the non-PDF becomes a file block.
        $this->assertContains('pdf_document', $this->blockTypesContaining($link, $pdf),
            'a .pdf file must become a pdf_document block');
        $this->assertContains('file', $this->blockTypesContaining($link, $zip),
            'a non-pdf file must become a file block');

        // Each pasted link becomes a tappable link button.
        $this->assertContains('link', $this->blockTypesContaining($link, $linkA),
            'first pasted link must become a link block');
        $this->assertContains('link', $this->blockTypesContaining($link, $linkB),
            'second pasted link must become a link block');
    }

    /**
     * A single unreferenced image is added as a standalone `image` block
     * (the image_grid collapse only kicks in for 2+ photos).
     */
    public function test_single_unreferenced_image_becomes_image_block(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $link = $this->biolink($user);
        $this->fakeOpenAi($this->pageReferencingNothing());

        $image = 'https://cdn.example.com/photos/solo.jpg';

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A page', [], [$image], [],
        );

        $this->assertContains('image', $this->blockTypesContaining($link, $image),
            'a lone supplied image must become a single image block');
        $this->assertNotContains('image_grid', $this->blockTypesContaining($link, $image),
            'a lone image must not be wrapped in an image_grid');
    }

    // ── Referenced resources are not duplicated ───────────────────────

    /**
     * Resources the model DID reference (an image inside an image block, a
     * file inside a pdf_document, a link inside a link block) must not be
     * appended a second time by the safety net.
     */
    public function test_referenced_resources_are_not_duplicated(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $link = $this->biolink($user);

        $image = 'https://cdn.example.com/photos/used.jpg';
        $pdf   = 'https://cdn.example.com/docs/used.pdf';
        $url   = 'https://shop.example.com/used';

        // The model wires up every supplied resource itself.
        $page = json_encode([
            'page'   => ['theme_color' => '#3d6bff'],
            'blocks' => [
                ['type' => 'profile_card_v1', 'settings' => ['name' => 'Bob', 'title' => 'Baker', 'bio' => 'Hi.']],
                ['type' => 'image',        'settings' => ['url' => $image, 'alt' => 'A photo']],
                ['type' => 'pdf_document', 'settings' => ['url' => $pdf, 'title' => 'My doc']],
                ['type' => 'link',         'settings' => ['url' => $url, 'text' => 'My shop']],
            ],
        ], JSON_THROW_ON_ERROR);
        $this->fakeOpenAi($page);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A page', [$url], [$image], [$pdf],
        );

        // Each resource appears in exactly one block — no safety-net dupes.
        $this->assertCount(1, $this->blockTypesContaining($link, $image),
            'an already-referenced image must not be duplicated');
        $this->assertCount(1, $this->blockTypesContaining($link, $pdf),
            'an already-referenced file must not be duplicated');
        $this->assertCount(1, $this->blockTypesContaining($link, $url),
            'an already-referenced link must not be duplicated');
    }

    // ── Respects plan block-type allowance ────────────────────────────

    /**
     * When the user's plan forbids link/image/file block types, the safety
     * net must NOT smuggle disallowed blocks onto the page — supplied
     * resources of those types are simply skipped.
     */
    public function test_disallowed_block_types_are_not_appended(): void
    {
        // Plan only permits the blocks the AI response itself uses.
        $user = $this->makeUser($this->plan([
            'max_links'           => 100,
            'max_biolinks'        => 100,
            'block_types_allowed' => ['profile_card_v1', 'heading', 'paragraph'],
        ]));
        $this->seedCoins($user);
        $link = $this->biolink($user);
        $this->fakeOpenAi($this->pageReferencingNothing());

        $image = 'https://cdn.example.com/photos/x.jpg';
        $pdf   = 'https://cdn.example.com/docs/x.pdf';
        $url   = 'https://shop.example.com/x';

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A page', [$url], [$image], [$pdf],
        );

        $this->assertEmpty($this->blockTypesContaining($link, $image),
            'no image block may be appended when image types are disallowed');
        $this->assertEmpty($this->blockTypesContaining($link, $pdf),
            'no file block may be appended when file types are disallowed');
        $this->assertEmpty($this->blockTypesContaining($link, $url),
            'no link block may be appended when the link type is disallowed');

        // The allowed AI blocks still made it onto the page.
        $types = $this->blocksFor($link)->pluck('type')->all();
        $this->assertContains('profile_card_v1', $types);
    }

    // ── Respects the overall block cap ────────────────────────────────

    /**
     * The append step honours the MAX_BLOCKS cap: feeding far more links
     * than the cap allows never produces a page exceeding the limit.
     */
    public function test_block_cap_is_respected_when_appending(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $link = $this->biolink($user);
        $this->fakeOpenAi($this->pageReferencingNothing());

        // 100 distinct links, well above the 40-block cap.
        $links = [];
        for ($i = 0; $i < 100; $i++) {
            $links[] = "https://example.com/path-{$i}";
        }

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A page', $links, [], [],
        );

        $count = BiolinkBlock::where('link_id', $link->id)->whereNull('parent_id')->count();
        $this->assertLessThanOrEqual(40, $count,
            'the finished page must never exceed the MAX_BLOCKS cap');
    }
}
