<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The event editor pages render at all.
 *
 * This exists because they stopped. A Media section added `@error` to an
 * <img> as Alpine's shorthand for x-on:error -- but @error is a Blade
 * directive of its own, expecting a matching @enderror. Blade compiled it
 * to an `if (` that nothing ever closed, and the compiled view failed to
 * parse at end of file. Both editors returned 500 for every creator, and
 * the change shipped because every test in that branch was a unit test of
 * a helper. Nothing had ever asked these pages to render.
 *
 * So the assertion is deliberately shallow. It does not care what is on
 * the page. It cares that there is a page, which is the part that was
 * missing and the part no amount of helper testing would have caught.
 */
class TheEventEditorOpensTest extends TestCase
{
    use RefreshDatabase;

    private function organiser(): User
    {
        return User::factory()->create()->fresh();
    }

    public function test_the_create_page_renders(): void
    {
        $user = $this->organiser();

        $this->actingAs($user)
            ->get(route('user.links.ics.create'))
            ->assertOk();
    }

    public function test_the_edit_page_renders(): void
    {
        $user = $this->organiser();

        $link = new Link([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Founder Sessions',
            'is_active' => true,
        ]);
        $link->workspace_id = $user->ownedWorkspaces()->first()->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ]);

        $this->actingAs($user)
            ->get(route('user.links.ics.edit', $link->fresh()))
            ->assertOk();
    }

    public function test_no_blade_directive_is_being_used_as_an_alpine_handler(): void
    {
        // The specific trap: Alpine's @x shorthand and Blade's @directive
        // share a sigil. Blade wins, silently, at compile time. x-on:x is
        // the unambiguous spelling and costs nothing.
        $reserved = ['@error', '@if', '@class', '@checked', '@selected', '@disabled', '@style', '@props'];

        foreach (['create-ics', 'edit-ics'] as $view) {
            $source = file_get_contents(resource_path("views/user/links/{$view}.blade.php"));

            foreach ($reserved as $directive) {
                $this->assertStringNotContainsString(
                    $directive . '="',
                    $source,
                    "{$view}.blade.php uses {$directive}= as an attribute. Blade will compile it as a directive. Use the x-on: or :  spelling instead."
                );
            }
        }
    }
}
