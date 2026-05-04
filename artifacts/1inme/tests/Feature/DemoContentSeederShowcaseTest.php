<?php

namespace Tests\Feature;

use App\Modules\User\Models\ConversationAction;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\Link;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the slideshow + conversational demo profiles seeded by
 * DemoContentSeeder. These profiles previously had no automated coverage,
 * so a refactor to the appearance settings shape, the conversational view
 * dispatcher in RedirectController::biolinkViewFor, or the seeder itself
 * could silently regress the demo experience.
 */
class DemoContentSeederShowcaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Re-runs the seeder twice in the same process so we exercise the
     * wipe-then-reseed cycle that admins trigger from the demo dashboard.
     * Every assertion below must hold after the SECOND seed run.
     */
    public function test_seeder_produces_slideshow_and_conversational_profiles(): void
    {
        // First seed: establishes baseline demo content.
        $this->seed(DemoContentSeeder::class);

        $firstSlideshow = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)->where('type', 'biolink')->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.background_type') === 'slideshow')
            ->count();
        $firstConversational = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)->where('type', 'biolink')->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.mode') === 'conversational')
            ->count();
        $firstFlowCount = ConversationFlow::count();

        // Second seed: the seeder internally calls wipePreviousDemoContent,
        // so this is a real wipe-then-reseed. If the seeder accidentally
        // double-creates rows, leaks orphan flows, or drops the showcase
        // profiles on the second pass, the assertions below will fail.
        $this->seed(DemoContentSeeder::class);

        // Counts are stable across re-seeds (no drift, no duplicates).
        $secondSlideshow = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)->where('type', 'biolink')->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.background_type') === 'slideshow')
            ->count();
        $secondConversational = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)->where('type', 'biolink')->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.mode') === 'conversational')
            ->count();
        $this->assertSame($firstSlideshow, $secondSlideshow,
            'Slideshow demo profile count drifted across reseed.');
        $this->assertSame($firstConversational, $secondConversational,
            'Conversational demo profile count drifted across reseed.');
        $this->assertSame($firstFlowCount, ConversationFlow::count(),
            'ConversationFlow count drifted across reseed (orphan rows or duplicates).');

        // ── slideshow profiles ────────────────────────────────────────
        $slideshowLinks = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)
            ->where('type', 'biolink')
            ->get()
            ->filter(function (Link $l) {
                $bg = data_get($l->settings, 'biolink.background_type');
                $imgs = data_get($l->settings, 'biolink.slideshow_images');
                return $bg === 'slideshow' && is_array($imgs) && count($imgs) > 0;
            });
        $this->assertGreaterThanOrEqual(
            2,
            $slideshowLinks->count(),
            'Expected at least 2 demo biolinks with slideshow background + non-empty slideshow_images.'
        );

        // ── conversational profiles ───────────────────────────────────
        $conversationalLinks = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)
            ->where('type', 'biolink')
            ->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.mode') === 'conversational');
        $this->assertGreaterThanOrEqual(
            2,
            $conversationalLinks->count(),
            'Expected at least 2 demo biolinks in conversational mode.'
        );

        // Each conversational link must have a published flow with ≥1 entry step.
        foreach ($conversationalLinks as $link) {
            $flow = ConversationFlow::query()
                ->where('link_id', $link->id)
                ->where('is_published', true)
                ->first();
            $this->assertNotNull(
                $flow,
                "Conversational demo link {$link->alias} is missing a published ConversationFlow."
            );
            $entrySteps = ConversationStep::query()
                ->where('flow_id', $flow->id)
                ->where('is_entry', true)
                ->count();
            $this->assertGreaterThanOrEqual(
                1,
                $entrySteps,
                "Flow for {$link->alias} must have at least one entry step."
            );
        }

        // ── combined: at least one link has both ──────────────────────
        $combined = $conversationalLinks->filter(function (Link $l) {
            return data_get($l->settings, 'biolink.background_type') === 'slideshow'
                && is_array(data_get($l->settings, 'biolink.slideshow_images'))
                && count(data_get($l->settings, 'biolink.slideshow_images')) > 0;
        });
        $this->assertGreaterThanOrEqual(
            1,
            $combined->count(),
            'Expected at least one demo biolink with BOTH conversational mode and slideshow background.'
        );
    }

    public function test_public_slideshow_profile_renders_bg_slideshow_container(): void
    {
        $this->seed(DemoContentSeeder::class);

        $link = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)
            ->where('type', 'biolink')
            ->where('visibility', 'public')
            ->get()
            ->first(function (Link $l) {
                $bg = data_get($l->settings, 'biolink.background_type');
                $mode = data_get($l->settings, 'biolink.mode', 'list');
                return $bg === 'slideshow' && $mode !== 'conversational';
            });
        $this->assertNotNull($link, 'No public-visibility slideshow demo profile found.');

        $response = $this->get('/' . $link->alias);
        $response->assertOk();
        $response->assertViewIs('common.biolink');
        // The list-mode renderer emits a `bg-slideshow` container.
        $response->assertSee('bg-slideshow', false);
    }

    public function test_public_conversational_profile_renders_conversational_view(): void
    {
        $this->seed(DemoContentSeeder::class);

        $link = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)
            ->where('type', 'biolink')
            ->where('visibility', 'public')
            ->get()
            ->first(fn (Link $l) => data_get($l->settings, 'biolink.mode') === 'conversational');
        $this->assertNotNull($link, 'No public-visibility conversational demo profile found.');

        $response = $this->get('/' . $link->alias);
        $response->assertOk();
        $response->assertViewIs('common.biolink-conversational');
    }

    public function test_wipe_all_demo_content_removes_conversation_flows_and_children(): void
    {
        $this->seed(DemoContentSeeder::class);

        $linkIds = Link::query()->withoutWorkspaceScope()
            ->where('is_demo', true)
            ->where('type', 'biolink')
            ->get()
            ->filter(fn (Link $l) => data_get($l->settings, 'biolink.mode') === 'conversational')
            ->pluck('id')
            ->all();
        $this->assertNotEmpty($linkIds, 'Pre-condition: should have conversational demo links before wipe.');

        // Pre-wipe sanity: flows + steps + actions exist.
        $this->assertGreaterThan(0, ConversationFlow::whereIn('link_id', $linkIds)->count());
        $flowIds = ConversationFlow::whereIn('link_id', $linkIds)->pluck('id')->all();
        $this->assertGreaterThan(0, ConversationStep::whereIn('flow_id', $flowIds)->count());
        $this->assertGreaterThan(0, ConversationAction::whereIn('flow_id', $flowIds)->count());

        DemoContentSeeder::wipeAllDemoContent();

        // Post-wipe: every flow / step / action attached to those demo
        // conversational links is gone (cascade on conversation_flows.link_id).
        $this->assertSame(0, ConversationFlow::whereIn('link_id', $linkIds)->count());
        $this->assertSame(0, ConversationFlow::whereIn('id', $flowIds)->count());
        $this->assertSame(0, ConversationStep::whereIn('flow_id', $flowIds)->count());
        $this->assertSame(0, ConversationAction::whereIn('flow_id', $flowIds)->count());
    }
}
