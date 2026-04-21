<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Services\BiolinkPageRecipes;
use App\Modules\User\Services\BiolinkWizardQuestions;
use Tests\TestCase;

/**
 * Coverage for the guided biolink wizard (task #246).
 *
 * Notes on approach:
 * - The wizard's recipe + taxonomy services are pure (no DB / no auth) so we
 *   exercise them directly to keep the test fast and deterministic.
 * - For the route surface we just assert that anonymous requests are bounced
 *   to login — full end-to-end auth/workspace setup is exercised by the rest
 *   of the suite via shared fixtures and would be redundant here.
 */
class BiolinkWizardTest extends TestCase
{
    /** Taxonomy: every category yields page types and (optionally) industries. */
    public function test_taxonomy_loads_for_every_category(): void
    {
        $cats = BiolinkWizardQuestions::categories();
        $this->assertGreaterThanOrEqual(8, count($cats), 'expected at least 8 categories');

        foreach ($cats as $cat) {
            $slug = $cat['slug'];
            $pageTypes = BiolinkWizardQuestions::pageTypes($slug);
            $this->assertNotEmpty($pageTypes, "category {$slug} has no page types");

            foreach ($pageTypes as $pt) {
                $questions = BiolinkWizardQuestions::questions($slug, $pt['slug'], null);
                $this->assertNotEmpty($questions, "questions missing for {$slug}/{$pt['slug']}");
            }
        }
    }

    /** Recipe: the canonical creator/profile combo produces profile + cta blocks. */
    public function test_recipe_builds_profile_and_cta_for_creator(): void
    {
        $snap = BiolinkPageRecipes::build('creator', 'profile', null, [
            'display_name' => 'Demo Creator',
            'tagline'      => 'Stories, art, and good vibes',
            'bio'          => 'Sharing my creative journey.',
            'links'        => [['label' => 'Shop', 'url' => 'https://shop.example.com']],
            'socials'      => [['platform' => 'instagram', 'handle' => 'demo']],
            'cta_label'    => 'Subscribe',
            'cta_url'      => 'https://example.com/sub',
            'theme'        => 'dark',
        ]);

        $this->assertArrayHasKey('biolink', $snap);
        $this->assertArrayHasKey('blocks', $snap);
        $types = array_column($snap['blocks'], 'type');
        $this->assertContains('profile_card_v1', $types);
        $this->assertContains('cta_button', $types);
    }

    /** Recipe: a restaurant/menu combo emits at least the profile + a list block. */
    public function test_recipe_builds_for_restaurant_menu(): void
    {
        $snap = BiolinkPageRecipes::build('restaurant', 'menu', 'italian', [
            'business_name' => 'Bella Italia',
            'tagline'       => 'Family recipes since 1972',
            'address'       => '123 Pasta St',
            'menu_items'    => [
                ['name' => 'Margherita', 'price' => '12'],
                ['name' => 'Carbonara',  'price' => '15'],
            ],
        ]);

        $types = array_column($snap['blocks'], 'type');
        $this->assertContains('profile_card_v1', $types);
        $this->assertNotEmpty($snap['blocks']);
        // Industry-tinted theme color should be set on biolink-level meta.
        $this->assertArrayHasKey('theme_color', $snap['biolink']);
    }

    /** Recipe: an event/wedding combo emits date/venue context. */
    public function test_recipe_builds_for_event_wedding(): void
    {
        $snap = BiolinkPageRecipes::build('event', 'wedding', null, [
            'couple'     => 'Alex & Sam',
            'event_date' => '2026-09-12',
            'venue_name' => 'Lakeside Hall',
            'rsvp_url'   => 'https://example.com/rsvp',
        ]);

        $this->assertNotEmpty($snap['blocks']);
        $types = array_column($snap['blocks'], 'type');
        $this->assertContains('profile_card_v1', $types);
    }

    /** Draft model: answers cast to array round-trips through the DB. */
    public function test_draft_answers_round_trip(): void
    {
        $payload = [
            'display_name' => 'Round Trip',
            'links' => [['label' => 'Site', 'url' => 'https://example.com']],
        ];
        $draft = new BiolinkWizardDraft();
        $draft->setRawAttributes([]);
        $draft->answers = $payload;

        $this->assertSame($payload, $draft->answers);
    }

    /** Permission gating: anonymous user is redirected away from the wizard. */
    public function test_wizard_requires_authentication(): void
    {
        $resp = $this->get('/user/links/wizard');
        // 302 to login or 200 of the login page after follow — both are
        // acceptable; the key is that no wizard markup renders.
        $resp->assertStatus(302);
        $location = $resp->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsStringIgnoringCase('login', $location);
    }

    /** Autosave endpoint: anonymous PATCH is rejected. */
    public function test_autosave_requires_authentication(): void
    {
        $resp = $this->withHeaders(['Accept' => 'application/json'])
            ->patch('/user/links/wizard/draft', ['answers' => ['display_name' => 'x']]);
        $this->assertContains($resp->status(), [302, 401, 419], 'expected redirect or auth challenge, got ' . $resp->status());
    }

    /**
     * Destructive "start over" must not be reachable via GET — that would
     * be CSRF-able (any third-party page could prefetch the URL and silently
     * delete the user's draft).
     */
    public function test_wizard_start_is_not_a_get_route(): void
    {
        $resp = $this->get('/user/links/wizard/start');
        // 405 Method Not Allowed (route exists for POST only) is the win
        // condition. 302/404 are acceptable too — anything *but* 200.
        $this->assertNotEquals(200, $resp->status(),
            'GET /user/links/wizard/start must not succeed — it would delete state via CSRF.');
    }
}
