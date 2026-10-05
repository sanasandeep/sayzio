<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\Builder\AiRestaurantMenuBuilderService;
use App\Services\AI\Builder\AiStoreMenuBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "when already created... it should show like modify
 * with AI and also all features like blocks and all should be
 * possible...... live right side should be shown".
 *
 * ---- Why this was not a relabelling job -------------------------------
 *
 * Every type builder REPLACES the catalogue wholesale. The service deletes
 * every category and every item and writes the model's response in their
 * place -- the comment in the code says "Replace the previous catalogue
 * wholesale" and means it.
 *
 * So putting "Modify with AI" on that button without changing anything
 * else would have been the most expensive thing in this whole session: a
 * restaurant with eighty dishes types "add a desserts section", and gets a
 * menu containing desserts and nothing else. The word would have been a
 * lie told by the interface.
 *
 * What makes it true is sending the current content with the brief, and
 * telling the model that anything it leaves out is deleted. That is what
 * these tests hold:
 *
 *   - the existing catalogue reaches the prompt;
 *   - the instruction that omission means deletion reaches it with the
 *     catalogue, never without;
 *   - a page with nothing on it still builds exactly as before;
 *   - the estimate covers the context the build will send, so the quote
 *     and the charge agree;
 *   - and the screen says "Modify", shows the live page, and warns -- in
 *     the plainest words I could find -- that the result replaces what is
 *     there.
 */
class ModifyingWithAiKeepsWhatIsAlreadyThereTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->owner);

        // The builder screen 404s with the engine off, so without this the
        // three screen tests would assert nothing at all. ::put() because
        // AppSetting keeps an in-process key set that a direct write does
        // not update.
        \App\Modules\Admin\Models\AppSetting::put(\App\Services\AI\AiEngineSettings::KEY_ENABLED, '1');
        $this->assertTrue(\App\Services\AI\AiEngineSettings::isEnabled(), 'could not switch the AI engine on');
    }

    private function emptyRestaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => Link::generateAlias(), 'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);

        return $link;
    }

    private function filledRestaurant(): Link
    {
        $link = $this->emptyRestaurant();
        $menu = RestaurantMenu::where('link_id', $link->id)->firstOrFail();
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Poori',
            'price' => 60, 'sort_order' => 1, 'is_active' => true,
        ]);

        return $link;
    }

    private function filledStore(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => Link::generateAlias(), 'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    // ── The context itself ────────────────────────────────────────

    public function test_an_empty_page_has_no_context_and_builds_as_before(): void
    {
        $service = app(AiRestaurantMenuBuilderService::class);
        $link = $this->emptyRestaurant();

        $this->assertSame('', $service->existingContext($link));
        $this->assertFalse($service->hasExistingContent($link));
    }

    public function test_the_current_menu_reaches_the_context(): void
    {
        $service = app(AiRestaurantMenuBuilderService::class);
        $context = $service->existingContext($this->filledRestaurant());

        $this->assertTrue($service->hasExistingContent($this->filledRestaurant()));
        $this->assertStringContainsString('Tiffins', $context);
        $this->assertStringContainsString('Masala Dosa', $context);
        $this->assertStringContainsString('Poori', $context);
        // Prices, because "make the dosas cheaper" is a brief somebody
        // writes and the model cannot act on it without them.
        $this->assertStringContainsString('120', $context);
        $this->assertStringContainsString('INR', $context);
    }

    public function test_the_store_gets_the_same_treatment(): void
    {
        $service = app(AiStoreMenuBuilderService::class);
        $context = $service->existingContext($this->filledStore());

        $this->assertStringContainsString('Mugs', $context);
        $this->assertStringContainsString('Big Mug', $context);
        $this->assertStringContainsString('450', $context);
    }

    // ── The instruction that makes "modify" true ──────────────────

    public function test_the_prompt_says_that_anything_omitted_is_deleted(): void
    {
        $service = app(AiRestaurantMenuBuilderService::class);
        $messages = $this->buildMessages($service, 'Add a desserts section', $service->existingContext($this->filledRestaurant()));
        $user = $this->userText($messages);

        // Without this sentence the model answers the brief literally and
        // returns a menu of desserts, and eighty dishes are deleted.
        $this->assertStringContainsString('Anything you leave out is deleted', $user);
        $this->assertStringContainsString('COMPLETE result', $user);
        // And the menu it must preserve.
        $this->assertStringContainsString('Masala Dosa', $user);
        // The brief is still there and still first.
        $this->assertStringContainsString('Add a desserts section', $user);
    }

    public function test_an_empty_page_gets_no_such_instruction(): void
    {
        $service = app(AiRestaurantMenuBuilderService::class);
        $user = $this->userText($this->buildMessages($service, 'An Italian trattoria', ''));

        // Telling a model to preserve content that does not exist is how a
        // first build comes back hedged and half empty.
        $this->assertStringNotContainsString('Anything you leave out is deleted', $user);
        $this->assertStringNotContainsString('ALREADY has the content', $user);
        $this->assertStringContainsString('An Italian trattoria', $user);
    }

    /**
     * The one that matters most: generate() really sends it.
     *
     * The first version of this file tested buildMessages() through
     * reflection and nothing else. Deleting the existingContext() argument
     * from generate() -- which is the change that silently wipes an
     * eighty-dish menu -- passed every test in it. A test of the piece is
     * not a test of the wiring, and the wiring is where the damage is.
     *
     * So this one drives the real generate() with a stand-in OpenAI client
     * and reads what was actually put on the wire.
     */
    public function test_generate_really_sends_the_existing_menu(): void
    {
        $seen = null;

        $fake = new class($seen) extends \App\Services\AI\OpenAiService
        {
            public static array $captured = [];

            public function __construct($ignored = null) {}

            public function chat(\App\Modules\User\Models\User $user, string $model, array $messages, array $opts = []): array
            {
                self::$captured = $messages;

                // Enough of a reply that generate() reaches the end rather
                // than throwing before we can assert anything.
                throw new \RuntimeException('captured');
            }

            public function estimateChatCoins(string $model, array $messages, int $maxOutputTokens = 4096, ?\App\Modules\User\Models\User $user = null): int
            {
                return 1;
            }
        };
        $fake::$captured = [];

        $service = new AiRestaurantMenuBuilderService($fake, app(\App\Services\AI\AiUsageCharger::class));
        $link = $this->filledRestaurant();

        try {
            $service->generate($this->owner, $link, 'Add a desserts section', [], [], []);
        } catch (\Throwable $e) {
            // Expected: the stand-in client never answers.
        }

        $user = $this->userText($fake::$captured);

        $this->assertStringContainsString(
            'Masala Dosa',
            $user,
            'generate() did not send the existing menu — a build would delete it'
        );
        $this->assertStringContainsString('Anything you leave out is deleted', $user);
    }

    // ── The quote and the charge agree ────────────────────────────

    public function test_the_estimate_can_be_told_about_the_existing_content(): void
    {
        // The signature is what this guards: the context is real tokens, and
        // an estimate that cannot see it quotes a build and charges a
        // modify. `$link` is optional and last so old callers still work.
        $m = new \ReflectionMethod(AiRestaurantMenuBuilderService::class, 'estimateCredits');
        $params = $m->getParameters();
        $last = end($params);

        $this->assertSame('link', $last->getName());
        $this->assertTrue($last->isOptional(), 'adding this must not break existing callers');
    }

    public function test_the_controller_passes_the_link_to_the_estimate(): void
    {
        $src = file_get_contents(app_path('Modules/User/Controllers/AiTypeBuilderController.php'));

        // Not [^)]* -- the first argument is `$request->user()`, whose own
        // ')' ends the class before it ever reaches `$link`. A regex that
        // cannot match the real line is a test that passes on nothing.
        $this->assertMatchesRegularExpression(
            '/estimateCredits\(.*\$link\s*\);/',
            $src,
            'the estimate is quoted without the content the build will send'
        );
    }

    // ── The screen ────────────────────────────────────────────────

    public function test_a_page_with_content_says_modify_and_warns(): void
    {
        $html = $this->screen($this->filledRestaurant());

        $this->assertStringContainsString('Modify your', $html);
        $this->assertStringContainsString('Modify with AI', $html);
        $this->assertStringNotContainsString('Build with AI</span>', $html);

        // The warning, in words rather than a shrug. A model can still drop
        // something, and somebody about to spend coins on a live menu is
        // owed that sentence.
        $this->assertStringContainsString('rewrites the whole', $html);
        $this->assertStringContainsString('check it afterwards', $html);
    }

    public function test_an_empty_page_still_says_build_and_does_not_warn(): void
    {
        $html = $this->screen($this->emptyRestaurant());

        $this->assertStringContainsString('Build your', $html);
        $this->assertStringNotContainsString('rewrites the whole', $html);
    }

    public function test_the_live_page_and_the_other_tabs_are_there(): void
    {
        foreach ([$this->filledRestaurant(), $this->emptyRestaurant()] as $link) {
            $html = $this->screen($link);

            // "live right side should be shown".
            $this->assertStringContainsString('device-preview-root', $html);
            // "all features like blocks and all should be possible" -- the
            // shared tab row, so this screen is not a dead end.
            $this->assertStringContainsString('editor-tabs', $html);
        }
    }

    // ── helpers ───────────────────────────────────────────────────

    private function screen(Link $link): string
    {
        return $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/ai-type-builder")
            ->assertOk()
            ->getContent();
    }

    /** buildMessages is protected; this is the only way to read what is sent. */
    private function buildMessages($service, string $description, string $existing): array
    {
        $m = new \ReflectionMethod($service, 'buildMessages');
        $m->setAccessible(true);

        return $m->invoke($service, $this->owner, $description, [], [], [], $existing);
    }

    /** The user turn, whether it was sent as a string or as parts. */
    private function userText(array $messages): string
    {
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') !== 'user') {
                continue;
            }
            $c = $msg['content'];

            return is_string($c) ? $c : json_encode($c);
        }

        return '';
    }
}
