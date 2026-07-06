<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks the link-type catalog to the only remaining way to pick a type.
 *
 * The "What are you trying to do?" goal grid and free-text goal search were
 * removed from the Create Link page, so the manual category picker
 * (rendered straight from LinkTypeCategories::categories()) plus the
 * Step 1 → Step 2 router (LinkController::chooseType) is now the sole path
 * to selecting and creating any link type.
 *
 * These tests assert that EVERY value in LinkTypeCategories::types() is:
 *   1. surfaced as a selectable card on the Create Link view, and
 *   2. accepted by chooseType() and forwarded to a Step 2 create form
 *      (rather than rejected as an invalid type or dropped on the floor).
 *
 * They are deliberately driven by the catalog itself (no hard-coded type
 * list here), so adding a new type/category to LinkTypeCategories without
 * also surfacing it on the picker — or without wiring it into chooseType's
 * `type` allow-list and route match — fails the suite. That guards against a
 * future tweak to the picker (or the validation/match) silently dropping a
 * type now that the goal-grid shortcut is gone.
 */
class CreateLinkTypeCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    public function test_create_view_surfaces_a_card_for_every_catalog_type(): void
    {
        $resp = $this->actingAs($this->makeUser())
            ->get(route('user.links.create'))
            ->assertOk();

        $types = LinkTypeCategories::types();
        $this->assertNotEmpty($types, 'the link-type catalog must not be empty');

        foreach ($types as $value => $meta) {
            // Each card carries a stable per-type id and a selectable radio
            // bound to name="type". Both must be present so the type is
            // discoverable AND pickable through the manual flow.
            $resp->assertSee('id="lt-card-' . $value . '"', false);
            $resp->assertSee('name="type" value="' . $value . '"', false);
        }
    }

    public function test_choose_type_accepts_and_forwards_every_catalog_type(): void
    {
        $user = $this->makeUser();

        foreach (array_keys(LinkTypeCategories::types()) as $value) {
            $alias = 'lt' . Str::lower(Str::random(8));

            $resp = $this->actingAs($user)->post('/user/links/choose-type', [
                'type'  => $value,
                'alias' => $alias,
            ]);

            // No validation error (the type is in chooseType's allow-list) and
            // the request is forwarded to a Step 2 create form rather than
            // bounced back to the picker. A missing route in chooseType's match
            // would throw here, so this also proves each type is fully wired.
            $resp->assertSessionHasNoErrors();
            $resp->assertRedirect();

            $location = (string) $resp->headers->get('Location');
            $this->assertStringNotContainsString(
                route('user.links.create'),
                $location,
                "type [{$value}] was not forwarded to a Step 2 create form"
            );
            // The chosen type and typed alias are carried through to Step 2.
            $this->assertStringContainsString('alias=' . $alias, $location);
        }
    }
}
