<?php

namespace Tests\Feature;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\BiolinkPageRecipes;
use App\Modules\User\Services\BiolinkWizardQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cross-taxonomy coverage for the guided biolink wizard (task #2024).
 *
 * The happy-path suites (BiolinkWizardGenerateTest / BiolinkWizardIntegrationTest)
 * only exercise the canonical creator/influencer combo. The wizard taxonomy,
 * however, spans many category/page-type combos (business, restaurant, event,
 * musician, coach, faith, education, …), each with its own recipe branch in
 * BiolinkPageRecipes::extrasFor() and its own required fields.
 *
 * A regression in a single recipe — most dangerously an *unknown block type*
 * that makes TemplateService::applyPageToLink throw and roll back the whole
 * transaction — would only surface in production for that one combo. This test
 * walks EVERY combo in the taxonomy (and, where present, the first industry so
 * the industry-tinted recipe branch runs too) and asserts, data-driven:
 *
 *   1. A complete answer set generated from BiolinkWizardQuestions::questions()
 *      passes validateAnswers() (i.e. every required/typed field is satisfied).
 *   2. BiolinkPageRecipes::build() yields a non-empty, correctly-keyed block
 *      list whose every `type` is a real BiolinkBlock::TYPES entry.
 *   3. TemplateService::applyPageToLink() persists those blocks — proving they
 *      survive the platform sanitizer and that no recipe emits a block type the
 *      sanitizer/insert path rejects.
 */
class BiolinkWizardAllPageTypesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every (category, page_type, industry?) combo in the taxonomy.
     *
     * The provider is pure (BiolinkWizardQuestions is a static, DB-free
     * service) so it runs before the app boots — each combo becomes its own
     * named PHPUnit case for pinpoint failure reporting.
     *
     * @return iterable<string, array{0:string, 1:string, 2:?string}>
     */
    public static function comboProvider(): iterable
    {
        foreach (BiolinkWizardQuestions::categories() as $cat) {
            $category = $cat['slug'];
            foreach (BiolinkWizardQuestions::pageTypes($category) as $pt) {
                $pageType = $pt['slug'];

                // Base combo (no industry refinement).
                yield "{$category}/{$pageType}" => [$category, $pageType, null];

                // If this combo has an industry sub-step, also exercise the
                // first one so the industry-tinted recipe branch runs.
                $industries = BiolinkWizardQuestions::industries($category, $pageType);
                if (!empty($industries)) {
                    $ind = $industries[0]['slug'];
                    yield "{$category}/{$pageType} [{$ind}]" => [$category, $pageType, $ind];
                }
            }
        }
    }

    /**
     * Generate a complete, well-typed answer set for a combo straight from its
     * question bank, so validateAnswers() passes and every recipe branch that
     * keys off a question field gets a value (maximising block coverage).
     *
     * @return array<string, string>
     */
    private static function answersFor(string $category, string $pageType, ?string $industry): array
    {
        $answers = [];
        foreach (BiolinkWizardQuestions::questions($category, $pageType, $industry) as $q) {
            $key  = $q['key'];
            $type = $q['type'] ?? 'text';

            $answers[$key] = match ($type) {
                'url'      => 'https://example.com/' . str_replace('_', '-', $key),
                'email'    => 'test@example.com',
                'phone'    => '+1 555 0100',
                'color'    => '#7c3aed',
                'image'    => 'https://example.com/image.png',
                'select'   => (string) (($q['options'][0]['v']) ?? ''),
                'textarea' => "Sample copy for {$key}.\nA second line of detail.",
                default    => "Sample {$key}",
            };
        }
        return $answers;
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Wiz ' . Str::random(4),
            'email'    => 'wiz-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    /**
     * @dataProvider comboProvider
     */
    public function test_every_combo_builds_and_applies_a_complete_page(
        string $category,
        string $pageType,
        ?string $industry,
    ): void {
        $label = $industry ? "{$category}/{$pageType} [{$industry}]" : "{$category}/{$pageType}";

        $answers = self::answersFor($category, $pageType, $industry);

        // 1. A complete answer set satisfies every required / typed field.
        $errors = BiolinkWizardQuestions::validateAnswers($category, $pageType, $industry, $answers);
        $this->assertSame([], $errors,
            "validateAnswers should pass for {$label}, got: " . json_encode($errors));

        // 2. The recipe yields a non-empty, correctly-keyed block list whose
        //    every type is a real, insertable block type.
        $snapshot = BiolinkPageRecipes::build($category, $pageType, $industry, $answers);

        $this->assertArrayHasKey('biolink', $snapshot, "snapshot missing 'biolink' for {$label}");
        $this->assertArrayHasKey('blocks', $snapshot, "snapshot missing 'blocks' for {$label}");
        $this->assertNotEmpty($snapshot['blocks'], "recipe produced no blocks for {$label}");

        foreach ($snapshot['blocks'] as $i => $block) {
            $this->assertArrayHasKey('type', $block, "block #{$i} missing 'type' for {$label}");
            $this->assertArrayHasKey('settings', $block, "block #{$i} ({$block['type']}) missing 'settings' for {$label}");
            $this->assertArrayHasKey($block['type'], BiolinkBlock::TYPES,
                "recipe emitted unknown block type '{$block['type']}' for {$label} — applyPageToLink would roll back");
        }

        // Every recipe leads with a profile card.
        $this->assertSame('profile_card_v1', $snapshot['blocks'][0]['type'] ?? null,
            "first block should be the profile card for {$label}");

        // 3. The blocks survive the sanitizer and persist via TemplateService.
        $user = $this->makeUser();
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'Combo ' . Str::random(4),
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(TemplateService::class)->applyPageToLink($link, $snapshot, true);

        $persisted = BiolinkBlock::where('link_id', $link->id)->whereNull('parent_id')->get();
        $this->assertNotEmpty($persisted,
            "applyPageToLink persisted no blocks for {$label} (a recipe block type was likely rejected)");

        // No block should have been silently dropped on the way in.
        $this->assertSame(count($snapshot['blocks']), $persisted->count(),
            "block count drifted between recipe and DB for {$label}");

        // The sanitizer kept the user's real profile copy (not a placeholder).
        $profile = $persisted->firstWhere('type', 'profile_card_v1');
        $this->assertNotNull($profile, "profile_card_v1 not persisted for {$label}");
        $this->assertNotEmpty($profile->settings ?? [],
            "profile settings were stripped by the sanitizer for {$label}");
    }
}
