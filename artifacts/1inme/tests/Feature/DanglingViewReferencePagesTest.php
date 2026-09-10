<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages whose templates pointed at a view that does not exist.
 *
 * Blade resolves @extends and @include at RENDER time, so a template naming a
 * layout that was renamed -- or never existed -- compiles fine, passes every
 * static check, and throws "View [x] not found" the first time a visitor opens
 * the page. Nothing upstream hints at it: the controller's own view name is
 * correct, it is the template's reference that dangles.
 *
 * Three of these were live, all the same typo. There is no
 * resources/views/layouts/ directory at all -- the layout is
 * `user.layouts.app`, which the other 45 pages in those folders extend:
 *
 *     user/links/insurance/dashboard.blade.php  @extends('layouts.user')
 *     user/links/insurance/settings.blade.php   @extends('layouts.user')
 *     user/roadmap/triage.blade.php             @extends('layouts.app')
 *
 * Link Health (/user/insurance) had been answering 500 to every visitor.
 *
 * A fourth was found by scripts/check-view-references.php on its first run:
 * common/updates-page.blade.php included 'common.partials.tracking-pixels',
 * which has never existed. The include sat behind an "if this link has
 * pixels" check, so public Updates pages worked right up until their owner
 * configured a pixel, and then broke for every visitor. The real partial is
 * common.partials.pixel-scripts, and it takes $link.
 *
 * These tests just render the pages. A 200 proves the template's references
 * resolve; that is the whole contract being asserted, and it is exactly what
 * nothing was checking.
 */
class DanglingViewReferencePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_health_dashboard_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('user.insurance.dashboard'))
            ->assertOk();
    }

    public function test_roadmap_triage_renders(): void
    {
        $user = User::factory()->create();

        // RoadmapTriageController::authorize() is the standard biolink-edit
        // gate: 403 unless the link belongs to the active workspace owner AND
        // is in Link::BIOLINK_FAMILY. A default-typed factory link fails the
        // second half and never reaches the template, which is the thing
        // under test here.
        $link = Link::factory()->create([
            'user_id' => $user->id,
            'type'    => Link::BIOLINK_FAMILY[0],
        ]);

        $this->actingAs($user)
            ->get(route('user.roadmap.triage', $link))
            ->assertOk();
    }
}
