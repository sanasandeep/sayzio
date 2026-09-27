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
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "here it should have modified version of extracting
 * content with pdf or multiple scanned images of menu card.... thats usuall
 * used by restro".
 *
 * He is right about what restaurants actually have. Not a brief -- a
 * laminated card, a PDF the designer sent, photos taken on a phone.
 * Retyping it is the reason a menu never gets onto the platform at all.
 *
 * ---- Why this is not a new feature ---------------------------------------
 *
 * There has been an AI menu builder for a while, and it already took
 * "images". Those are a different thing: URLs the model quotes back to hang
 * on dishes as photos. It never LOOKS at them -- they travel as a line of
 * text saying their address.
 *
 * Conflating those two is why this looked done. Reading a card needs the
 * picture to travel as a vision content part, and that is the change:
 * `readsImages()` alongside `supportsImages()`, scans alongside images.
 *
 * ---- What is worth testing -----------------------------------------------
 *
 * Not that OpenAI can read a menu. Two things: that a builder which does
 * not read pictures is completely unchanged (every other type, and every
 * existing estimate), and that what comes back is written down faithfully
 * -- including the card's own groupings, which the schema could not express
 * until sub-sections shipped.
 */
class AMenuCanBeBuiltFromAPhotographOfTheCardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurantLink(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        return $link;
    }

    private function storeLink(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        return $link;
    }

    /** Reach a builder's protected members without a live model call. */
    private function inner(object $svc, string $method, array $args): mixed
    {
        $m = new \ReflectionMethod($svc, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($svc, $args);
    }

    /** The intake screen 404s with the engine off, so turn it on. */
    private function enableAi(): void
    {
        \App\Services\AI\AiEngineSettings::setEnabled(true);
        \App\Services\AI\AiEngineSettings::setOpenAiKey('sk-test-key');
    }

    private function restaurantBuilder(): AiRestaurantMenuBuilderService
    {
        return app(AiRestaurantMenuBuilderService::class);
    }

    // ===== 1. The two kinds of image are not the same kind =====

    /**
     * The distinction this whole change rests on. A builder that can be
     * HANDED photo URLs is not a builder that can READ a photograph.
     */
    public function test_reading_a_picture_is_a_separate_capability_from_referencing_one(): void
    {
        $menu = $this->restaurantBuilder();

        $this->assertTrue($menu->supportsImages(), 'dish photos: URLs the model quotes back');
        $this->assertTrue($menu->readsImages(), 'card scans: pictures the model actually looks at');

        // A type with nothing to transcribe must not have quietly gained it.
        $this->assertFalse(app(\App\Services\AI\Builder\AiSlidesBuilderService::class)->readsImages());
    }

    /**
     * A builder that does not read pictures produces exactly the message it
     * always did -- a plain string, not content parts. Every other type and
     * every existing estimate depends on that.
     */
    public function test_a_builder_that_reads_nothing_sends_the_message_it_always_did(): void
    {
        $slides = app(\App\Services\AI\Builder\AiSlidesBuilderService::class);

        $messages = $this->inner($slides, 'buildMessages', [
            $this->user, 'A six slide pitch for my photography business', [], [],
            ['data:image/png;base64,AAAA'],
        ]);

        $this->assertIsString($messages[1]['content'],
            'a scan handed to a builder that cannot read it must not change the request');
    }

    /** And one that does gets the picture as a vision part. */
    public function test_a_scan_travels_as_something_the_model_can_see(): void
    {
        $messages = $this->inner($this->restaurantBuilder(), 'buildMessages', [
            $this->user, 'Read this card', [], [],
            ['data:image/png;base64,AAAA'],
        ]);

        $parts = $messages[1]['content'];

        $this->assertIsArray($parts);
        $this->assertSame('text', $parts[0]['type'], 'the instruction goes before the picture');
        $this->assertSame('image_url', $parts[1]['type']);
        $this->assertSame('data:image/png;base64,AAAA', $parts[1]['image_url']['url']);
    }

    /**
     * And it says transcribe, not invent. A model filling a smudged price
     * with a plausible number produces a live page that charges the wrong
     * amount, with nothing on screen saying which lines were guessed.
     */
    public function test_the_instruction_forbids_inventing_what_is_not_there(): void
    {
        $parts = $this->inner($this->restaurantBuilder(), 'buildMessages', [
            $this->user, 'Read this card', [], [], ['data:image/png;base64,AAAA'],
        ])[1]['content'];

        $this->assertStringContainsString('Do NOT invent', $parts[0]['text']);
        $this->assertStringContainsString('set it to 0 rather than guessing', $parts[0]['text']);
    }

    // ===== 2. What may be shown =====

    public function test_only_pictures_the_model_can_actually_fetch_are_sent(): void
    {
        $clean = $this->inner($this->restaurantBuilder(), 'cleanScans', [[
            'data:image/jpeg;base64,AAAA',
            'https://cdn.example.com/card.png',
            'file:///etc/passwd',
            'javascript:alert(1)',
            'not a url',
            '',
        ]]);

        $this->assertSame([
            'data:image/jpeg;base64,AAAA',
            'https://cdn.example.com/card.png',
        ], $clean);
    }

    /**
     * Capped, and capped before the charge. Every image is paid for, and a
     * creator who uploads twelve photos of one laminated sheet should be
     * stopped first rather than billed for it.
     */
    public function test_the_number_of_scans_is_capped(): void
    {
        $many = array_map(fn ($i) => 'https://cdn.example.com/'.$i.'.png', range(1, 12));

        $this->assertCount(
            AiRestaurantMenuBuilderService::MAX_SCANS,
            $this->inner($this->restaurantBuilder(), 'cleanScans', [$many])
        );
    }

    public function test_a_builder_that_reads_nothing_keeps_no_scans(): void
    {
        $slides = app(\App\Services\AI\Builder\AiSlidesBuilderService::class);

        $this->assertSame([], $this->inner($slides, 'cleanScans', [['https://cdn.example.com/a.png']]));
    }

    // ===== 3. What comes back is written down faithfully =====

    /**
     * The card's own groupings survive. Until sub-sections shipped the
     * schema could not express "Tiffins holding Steamed and Fried", so a
     * transcription of a real card had to be flattened -- which is not what
     * the card says.
     */
    public function test_the_cards_own_groupings_are_kept(): void
    {
        $link = $this->restaurantLink();

        $this->inner($this->restaurantBuilder(), 'materialize', [
            $this->user, $link,
            [
                'currency' => 'INR',
                'categories' => [[
                    'name' => 'Tiffins',
                    'items' => [['name' => 'Poori', 'price' => 60]],
                    'subcategories' => [[
                        'name'  => 'Steamed',
                        'items' => [['name' => 'Idli', 'price' => 40]],
                    ]],
                ]],
            ],
            [], [],
        ]);

        $menu = RestaurantMenu::where('link_id', $link->id)->first();
        $tiffins = RestaurantMenuCategory::where('menu_id', $menu->id)->where('name', 'Tiffins')->first();
        $steamed = RestaurantMenuCategory::where('menu_id', $menu->id)->where('name', 'Steamed')->first();

        $this->assertNotNull($steamed);
        $this->assertSame($tiffins->id, (int) $steamed->parent_id,
            'a flattened transcription is not what the card says');

        $idli = RestaurantMenuItem::where('menu_id', $menu->id)->where('name', 'Idli')->first();
        $this->assertSame($steamed->id, (int) $idli->category_id);
    }

    /** Sub-sections get the same fields as sections, not a thinner copy. */
    public function test_an_item_in_a_sub_section_is_written_out_in_full(): void
    {
        $link = $this->restaurantLink();

        $this->inner($this->restaurantBuilder(), 'materialize', [
            $this->user, $link,
            [
                'currency' => 'INR',
                'categories' => [[
                    'name' => 'Tiffins',
                    'subcategories' => [[
                        'name'  => 'Steamed',
                        'items' => [['name' => 'Idli', 'description' => 'Two pieces', 'price' => 40]],
                    ]],
                ]],
            ],
            [], [],
        ]);

        $idli = RestaurantMenuItem::where('name', 'Idli')->first();

        $this->assertSame('Two pieces', $idli->description);
        $this->assertSame('40.00', (string) $idli->price);
        $this->assertSame('INR', $idli->currency);
        $this->assertTrue((bool) $idli->is_active);
    }

    /** The store gets the same treatment. */
    public function test_the_store_keeps_its_groupings_too(): void
    {
        $link = $this->storeLink();

        $this->inner(app(AiStoreMenuBuilderService::class), 'materialize', [
            $this->user, $link,
            [
                'currency' => 'INR',
                'categories' => [[
                    'name' => 'Drinkware',
                    'subcategories' => [[
                        'name'     => 'Mugs',
                        'products' => [['name' => 'Big Mug', 'price' => 450]],
                    ]],
                ]],
            ],
            [], [],
        ]);

        $menu = StoreMenu::where('link_id', $link->id)->first();
        $mugs = StoreCategory::where('menu_id', $menu->id)->where('name', 'Mugs')->first();

        $this->assertNotNull($mugs->parent_id);
        $this->assertSame($mugs->id, (int) StoreProduct::where('name', 'Big Mug')->first()->category_id);
    }

    /** A flat card still comes out flat -- groupings are not invented. */
    public function test_a_flat_card_stays_flat(): void
    {
        $link = $this->restaurantLink();

        $this->inner($this->restaurantBuilder(), 'materialize', [
            $this->user, $link,
            ['currency' => 'INR', 'categories' => [['name' => 'Dosas', 'items' => [['name' => 'Masala Dosa', 'price' => 120]]]]],
            [], [],
        ]);

        $menu = RestaurantMenu::where('link_id', $link->id)->first();

        $this->assertSame(1, RestaurantMenuCategory::where('menu_id', $menu->id)->count());
        $this->assertNull(RestaurantMenuCategory::where('menu_id', $menu->id)->first()->parent_id);
    }

    // ===== 4. The screen offers it =====

    public function test_both_menu_builders_offer_the_upload(): void
    {
        $this->enableAi();

        foreach ([$this->restaurantLink(), $this->storeLink()] as $link) {
            $html = $this->actingAs($this->user)
                ->get('/user/links/'.$link->id.'/ai-type-builder')
                ->assertOk()->getContent();

            $this->assertStringContainsString('Photograph of your menu', $html);
            $this->assertStringContainsString('addScans($event)', $html);
        }
    }

    /**
     * A card IS a brief. Requiring ten words of description on top of a
     * photograph of the thing is a gate with nothing behind it.
     */
    public function test_a_card_counts_as_the_brief(): void
    {
        $this->enableAi();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$this->restaurantLink()->id.'/ai-type-builder')
            ->assertOk()->getContent();

        $this->assertStringContainsString('this.scans.length > 0', $html);
        $this->assertStringContainsString('(optional when you have uploaded a card)', $html);
    }

    // ===== 5. Guard =====

    /**
     * Every builder that says it reads pictures has to say what to do with
     * them. A vision-capable builder with the generic instruction would
     * transcribe a menu without being told to keep its prices exact.
     */
    public function test_every_reading_builder_gives_its_own_instruction(): void
    {
        foreach ([AiRestaurantMenuBuilderService::class, AiStoreMenuBuilderService::class] as $class) {
            $svc = app($class);

            $this->assertTrue($svc->readsImages());
            $this->assertStringContainsString(
                'set it to 0 rather than guessing',
                $this->inner($svc, 'scanInstruction', []),
                class_basename($class).' transcribes prices and must say what to do with an unreadable one'
            );
        }
    }
}
