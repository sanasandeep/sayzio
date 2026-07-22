<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards /user/notifications (and the header bell dropdown, which renders on
 * the same page via the shared layout) against malformed production rows.
 *
 * The production 500 root cause was a Blade compile error: `you@endif`
 * (directive glued to a word character) is not parsed as a directive,
 * leaving an unclosed @if → the compiled view was a PHP parse error and
 * the whole page 500'd for everyone. These tests lock down that the page
 * compiles and renders, including the workspace_member_left branch, plus
 * defensive tolerance for row shapes that could still fatal per-row:
 *   - a `data` JSON payload that is a scalar instead of an object (the old
 *     'array' cast decoded to a non-array → string offsets and the
 *     array-typed targetUrl()/previewText() helpers would fatal)
 *   - unknown/new notification types with unexpected payload shapes
 *
 * Note: the schema enforces created_at NOT NULL and `data` is a real json
 * column, so NULL dates / corrupt JSON cannot exist as rows — the view
 * guards for those are belt-and-braces only. Rows here are inserted with
 * raw DB statements to bypass the model's normalization (creating hook +
 * data accessor), like pre-existing production rows.
 */
class NotificationsPagePathologicalRowsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function rawInsert(User $user, array $overrides = []): int
    {
        return DB::table('user_notifications')->insertGetId(array_merge([
            'user_id'    => $user->id,
            'type'       => 'new_follower',
            'data'       => json_encode(['follower_name' => 'Ana']),
            'created_at' => now(),
        ], $overrides));
    }

    public function test_page_renders_workspace_member_left_row(): void
    {
        // This branch contained the `you@endif` Blade compile bug that
        // 500'd the entire page in production.
        $user = $this->makeUser();
        $this->rawInsert($user, [
            'type' => 'workspace_member_left',
            'data' => json_encode(['user_name' => 'Kim', 'workspace_name' => 'Acme', 'reassigned' => 3]),
        ]);

        $this->actingAs($user)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('moved to you');
    }

    public function test_page_renders_with_scalar_data_row(): void
    {
        $user = $this->makeUser();
        // JSON scalar string — decodes to a PHP string, not an array.
        $this->rawInsert($user, ['data' => json_encode('just a plain string')]);

        $this->actingAs($user)->get(route('user.notifications.index'))->assertOk();
    }

    public function test_page_renders_with_unknown_type_and_null_data(): void
    {
        $user = $this->makeUser();
        $this->rawInsert($user, ['type' => 'some.brand_new_type', 'data' => null]);

        $this->actingAs($user)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('some.brand_new_type');
    }

    public function test_page_renders_all_pathological_rows_together(): void
    {
        $user = $this->makeUser();
        $this->rawInsert($user, ['data' => json_encode('scalar')]);
        $this->rawInsert($user, ['type' => 'mystery.type', 'data' => json_encode(42)]);
        $this->rawInsert($user); // one healthy row

        $this->actingAs($user)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('started following you');
    }

    public function test_dismissed_section_tolerates_scalar_data(): void
    {
        $user = $this->makeUser();
        $this->rawInsert($user, [
            'data'         => json_encode('scalar'),
            'dismissed_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('Recently dismissed');
    }

    public function test_model_helpers_tolerate_scalar_data(): void
    {
        $user = $this->makeUser();
        $id = $this->rawInsert($user, ['data' => json_encode('scalar'), 'type' => 'unknown.kind']);
        $n = UserNotification::findOrFail($id);

        $this->assertSame([], $n->data);
        $this->assertNull($n->targetUrl());
        $this->assertIsString($n->previewText());
    }

    public function test_create_without_created_at_is_stamped(): void
    {
        $user = $this->makeUser();
        $n = UserNotification::create([
            'user_id' => $user->id,
            'type'    => 'new_follower',
            'data'    => ['follower_name' => 'Bo'],
        ]);

        $this->assertNotNull($n->fresh()->created_at);
    }
}
