<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Focused coverage for the real {@see AiBiolinkBuilderService::generate()}.
 *
 * The wizard-level tests in BiolinkWizardValidationTest mock this builder
 * wholesale, so they prove the wiring (Link creation, resource recording,
 * cleanup) but never exercise the builder's own contract: it must
 *
 *   1. translate the model's JSON into blocks while DROPPING any type the
 *      user's plan doesn't allow and any invented type — and keep
 *      supplied image URLs *relative* (vault paths), never rewriting them;
 *   2. refund the exact credits charged when the response can't be turned
 *      into a page (unparseable JSON, or zero usable blocks);
 *   3. leave the charge standing (no refund) on the success path.
 *
 * OpenAiService::chat() is a Mockery double so no network call happens and
 * we control the model's "response" + the credits it reports spending.
 * AiUsageCharger is a Mockery spy so we can assert the refund branch
 * exactly (the charge itself lives inside chat(), which we stub).
 */
class AiBiolinkBuilderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Plan that allows only a curated slice of block types so we can prove
     * the builder drops a curated-but-not-allowed type ('video') as well as
     * an entirely invented one.
     */
    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Builder Plan',
            'slug'          => 'builder-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'    => 100,
                'max_biolinks' => 100,
                // 'video' is deliberately excluded.
                'block_types_allowed' => [
                    'profile_card_v1', 'heading', 'paragraph',
                    'link', 'cta_button', 'image', 'image_grid', 'card',
                ],
            ],
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        $user = User::create([
            'name'     => 'Builder ' . Str::random(4),
            'email'    => 'builder-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $plan->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function biolink(User $user): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'biolink',
            'alias'        => Str::random(8),
            'title'        => 'My page',
            'is_active'    => true,
        ]);
    }

    /**
     * Bind an OpenAiService double whose chat() records the call and returns
     * the supplied raw content + a fixed credits_spent (simulating that the
     * real chat() already charged the wallet).
     */
    private function bindChat(string $content, int $creditsSpent): void
    {
        $calls =& $this->chatCalls;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$calls, $content, $creditsSpent) {
                $calls[] = ['messages' => $messages, 'opts' => $opts];
                return [
                    'content'       => $content,
                    'tool_calls'    => [],
                    'finish_reason' => 'stop',
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    /** Bind a spy charger so the refund branch can be asserted precisely. */
    private function spyCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::spy(AiUsageCharger::class);
        $this->app->instance(AiUsageCharger::class, $charger);
        return $charger;
    }

    private function builder(): AiBiolinkBuilderService
    {
        return app(AiBiolinkBuilderService::class);
    }

    // ── 1. constraint + relative image URLs + charge stands on success ──

    /**
     * generate() must emit ONLY plan-allowed block types (dropping a
     * curated-but-disallowed 'video' and an invented 'evil_block'), keep a
     * supplied vault image URL relative, and — because the build succeeded —
     * never refund the credits the chat call reported spending.
     */
    public function test_generate_constrains_to_allowed_blocks_and_keeps_relative_image_urls(): void
    {
        $user = $this->makeUser($this->plan());
        $link = $this->biolink($user);

        $relativeImage = '/f/9/photo.png';

        // The "model" answers with a mix of allowed, disallowed, and invented
        // blocks, plus a card whose children include both an allowed and a
        // disallowed child.
        $aiJson = json_encode([
            'page'   => ['theme_color' => '#1133FF'],
            'blocks' => [
                ['type' => 'heading',    'settings' => ['text' => 'Welcome to Bob Bakes', 'size' => 'h1']],
                ['type' => 'image',      'settings' => ['url' => $relativeImage, 'alt' => 'Our shop']],
                ['type' => 'video',      'settings' => ['url' => 'https://youtube.com/watch?v=abc']],
                ['type' => 'evil_block', 'settings' => ['danger' => true]],
                ['type' => 'card', 'settings' => ['title' => 'Order'], 'children' => [
                    ['type' => 'link',  'settings' => ['url' => 'https://bobbakes.test/order', 'text' => 'Order now']],
                    ['type' => 'video', 'settings' => ['url' => 'https://youtube.com/watch?v=xyz']],
                ]],
            ],
        ]);
        $this->bindChat($aiJson, 7);

        $charger = $this->spyCharger();

        $result = $this->builder()->generate(
            $user, $link,
            'A friendly neighbourhood bakery page.',
            [], // links
            [$relativeImage], // images (relative vault path)
            [], // files
        );

        // The chat call carried the biolink_builder feature + this link's id,
        // which is what makes OpenAiService charge against the right meter.
        $this->assertCount(1, $this->chatCalls);
        $this->assertSame(AiBiolinkBuilderService::FEATURE, $this->chatCalls[0]['opts']['feature'] ?? null);
        $this->assertSame($link->id, $this->chatCalls[0]['opts']['related_id'] ?? null);

        // Successful build returns the credits the chat reported.
        $this->assertSame(7, $result['credits_spent']);
        $this->assertGreaterThan(0, $result['blocks']);

        // Every persisted block (incl. card children) is a plan-allowed type.
        $allowed = $this->builder()->allowedTypesFor($user);
        $types = BiolinkBlock::where('link_id', $link->id)->pluck('type')->all();
        foreach ($types as $t) {
            $this->assertContains($t, $allowed, "Emitted disallowed block type: {$t}");
        }
        // The disallowed + invented types were dropped entirely.
        $this->assertNotContains('video', $types);
        $this->assertNotContains('evil_block', $types);

        // The supplied vault image survived as a RELATIVE url (not rewritten
        // to an absolute https URL).
        $image = BiolinkBlock::where('link_id', $link->id)->where('type', 'image')->first();
        $this->assertNotNull($image, 'Expected an image block for the supplied vault image.');
        $this->assertSame($relativeImage, $image->settings['url'] ?? null);

        // The card kept its allowed child and dropped the disallowed one.
        $card = BiolinkBlock::where('link_id', $link->id)->where('type', 'card')->first();
        $this->assertNotNull($card);
        $childTypes = BiolinkBlock::where('parent_id', $card->id)->pluck('type')->all();
        $this->assertContains('link', $childTypes);
        $this->assertNotContains('video', $childTypes);

        // Success path: the charge stands — no refund was issued.
        $charger->shouldNotHaveReceived('refund');
    }

    // ── 2. unparseable response → exact credit refund ──────────────────

    /**
     * When the model returns content that isn't valid JSON, generate() must
     * throw AND refund the precise credits the chat call charged, so a
     * failed build never nets a charge.
     */
    public function test_generate_refunds_credits_when_response_unparseable(): void
    {
        $user = $this->makeUser($this->plan());
        $link = $this->biolink($user);

        $this->bindChat('this is definitely not json', 11);
        $charger = $this->spyCharger();

        try {
            $this->builder()->generate($user, $link, 'Make me a page', [], [], []);
            $this->fail('Expected generate() to throw on unparseable output.');
        } catch (\RuntimeException $e) {
            // expected
        }

        // No blocks were applied.
        $this->assertSame(0, BiolinkBlock::where('link_id', $link->id)->count());

        // Exactly the charged credits were refunded against this link/feature.
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            11,
            Mockery::on(fn ($o) => is_array($o)
                && ($o['feature'] ?? null) === AiBiolinkBuilderService::FEATURE
                && ($o['related_id'] ?? null) === $link->id),
        );
    }

    // ── 3. valid JSON but no usable blocks → refund ────────────────────

    /**
     * A response that parses but yields zero usable blocks (here: only
     * invented/disallowed types) is also a failed build and must refund.
     */
    public function test_generate_refunds_when_no_usable_blocks(): void
    {
        $user = $this->makeUser($this->plan());
        $link = $this->biolink($user);

        $this->bindChat(json_encode([
            'blocks' => [
                ['type' => 'evil_block', 'settings' => []],
                ['type' => 'video',      'settings' => ['url' => 'https://youtube.com/watch?v=z']],
            ],
        ]), 5);
        $charger = $this->spyCharger();

        try {
            $this->builder()->generate($user, $link, 'Make me a page', [], [], []);
            $this->fail('Expected generate() to throw when no usable blocks remain.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(0, BiolinkBlock::where('link_id', $link->id)->count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            5,
            Mockery::on(fn ($o) => is_array($o) && ($o['feature'] ?? null) === AiBiolinkBuilderService::FEATURE),
        );
    }
}
