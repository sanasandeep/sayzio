<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web contact activity items are clickable (Task #6525): every item in the
 * "Activity across Sayzio" section of the web contact show page that carries
 * a non-null `url` renders as an anchor to that url, matching the tappable
 * mobile items. Items with a null `url` stay static text.
 */
class WebContactActivityLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_item_with_url_renders_as_anchor(): void
    {
        $owner = User::factory()->create();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Linked Larry']);

        $s = Subscriber::withoutGlobalScope('workspace')->create([
            'user_id' => $owner->id,
            'email'   => 'larry@example.com',
        ]);
        $s->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $res = $this->actingAs($owner)
            ->get(route('user.contacts.show', $contact))
            ->assertOk();

        $res->assertSee('Activity across Sayzio');
        $res->assertSee(
            '<a href="' . route('user.subscribers.index') . '"',
            false
        );
        $res->assertSee('larry@example.com');
    }

    public function test_activity_item_without_url_stays_static_text(): void
    {
        $owner = User::factory()->create();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Static Sam']);

        // Bind a fake service BEFORE the request so the controller resolves it.
        $fake = new class extends ContactActivityService {
            public function timeline(Contact $contact): array
            {
                return [[
                    'key'   => 'form_submissions',
                    'label' => 'Form submissions',
                    'icon'  => 'clipboard',
                    'count' => 1,
                    'items' => [[
                        'title'    => 'Orphaned submission entry',
                        'subtitle' => null,
                        'date'     => now()->toIso8601String(),
                        'url'      => null,
                        'refs'     => (object) [],
                    ]],
                ]];
            }
        };
        $this->app->instance(ContactActivityService::class, $fake);

        $res = $this->actingAs($owner)
            ->get(route('user.contacts.show', $contact))
            ->assertOk();

        $res->assertSee('Orphaned submission entry');
        $res->assertDontSee('Orphaned submission entry</a>', false);
    }
}
