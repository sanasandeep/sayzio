<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3749 — regression coverage for the extended mobile event organizer
 * payload (Task #3736). The public event detail endpoint
 * `GET /api/v1/events/{alias}` (EventTicketApiController::show -> eventShape())
 * must expose the reusable organizer profile (logo/description/website/
 * contact/address/socials) plus a `filled` flag, and — when the host has NOT
 * filled in a profile — fall back field-by-field to the host account so the
 * plain "Hosted by" avatar+name card still renders on mobile. Without this a
 * regression would silently drop host contact details from the mobile screen.
 *
 * The map is driven purely by the event's `latitude`/`longitude`, which the
 * shape already returns; we assert those pass through untouched so the mobile
 * map thumbnail keeps rendering.
 */
class EventApiOrganizerShapeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $u = User::create(array_merge([
            'name'     => 'Ada Organizer',
            'email'    => 'o' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeEvent(User $user, array $icsOverrides = []): Link
    {
        $link = Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Launch Party',
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);
        IcsData::create(array_merge([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'UTC',
            'all_day'    => false,
        ], $icsOverrides));

        return $link;
    }

    public function test_event_shape_returns_extended_organizer_profile_when_filled(): void
    {
        $host = $this->makeUser([
            'name'   => 'Grace Host',
            'handle' => 'grace' . Str::random(5),
            'avatar' => 'https://cdn.example.com/avatar.png',
            'organizer_profile' => [
                'logo'          => 'https://cdn.example.com/org-logo.png',
                'name'          => 'Grace Events Co',
                'description'   => 'We throw the best launch parties.',
                'website'       => 'https://grace.events',
                'contact_name'  => 'Grace Hopper',
                'contact_phone' => '+15551234567',
                'contact_email' => 'hello@grace.events',
                'address'       => '1 Market St, San Francisco',
                'socials'       => ['instagram' => '@graceevents', 'x' => 'graceevents'],
            ],
        ]);
        $link = $this->makeEvent($host, ['latitude' => 37.7937, 'longitude' => -122.3965]);

        $resp = $this->getJson('/api/v1/events/' . $link->alias);
        $resp->assertOk();

        $org = $resp->json('data.organizer');
        $this->assertIsArray($org);

        // Rich profile values win over the raw account fields.
        $this->assertTrue($org['filled']);
        $this->assertSame('Grace Events Co', $org['name']);
        $this->assertSame('https://cdn.example.com/org-logo.png', $org['avatar']);
        $this->assertSame('https://cdn.example.com/org-logo.png', $org['logo']);
        $this->assertSame('We throw the best launch parties.', $org['description']);
        $this->assertSame('https://grace.events', $org['website']);
        $this->assertSame('Grace Hopper', $org['contact_name']);
        $this->assertSame('+15551234567', $org['contact_phone']);
        $this->assertSame('hello@grace.events', $org['contact_email']);
        $this->assertSame('1 Market St, San Francisco', $org['address']);
        $this->assertSame('@graceevents', $org['socials']['instagram']);
        $this->assertSame('graceevents', $org['socials']['x']);
        $this->assertSame($host->handle, $org['handle']);

        // The map fields the mobile thumbnail relies on pass through intact.
        $this->assertEquals(37.7937, (float) $resp->json('data.latitude'));
        $this->assertEquals(-122.3965, (float) $resp->json('data.longitude'));
    }

    public function test_event_shape_falls_back_field_by_field_to_host_account_when_no_profile(): void
    {
        $host = $this->makeUser([
            'name'   => 'Plain Pat',
            'handle' => 'pat' . Str::random(5),
            'avatar' => 'https://cdn.example.com/pat.png',
            // organizer_profile intentionally unset.
        ]);
        $link = $this->makeEvent($host);

        $resp = $this->getJson('/api/v1/events/' . $link->alias);
        $resp->assertOk();

        $org = $resp->json('data.organizer');
        $this->assertIsArray($org);

        // No profile ⇒ not filled, but display identity still resolves from
        // the host account so the plain "Hosted by" card keeps working.
        $this->assertFalse($org['filled']);
        $this->assertSame('Plain Pat', $org['name']);
        $this->assertSame('https://cdn.example.com/pat.png', $org['avatar']);
        $this->assertSame($host->handle, $org['handle']);

        // Every extended field is null (not "" and not a stale account value).
        $this->assertNull($org['logo']);
        $this->assertNull($org['description']);
        $this->assertNull($org['website']);
        $this->assertNull($org['contact_name']);
        $this->assertNull($org['contact_phone']);
        $this->assertNull($org['contact_email']);
        $this->assertNull($org['address']);
        $this->assertSame([], (array) $org['socials']);
    }

    public function test_event_shape_partial_profile_keeps_unset_fields_null(): void
    {
        // Only a couple of profile fields set: `filled` must be true, the set
        // fields returned, and the unset ones null (field-by-field, not
        // all-or-nothing).
        $host = $this->makeUser([
            'name'   => 'Half Filled',
            'avatar' => 'https://cdn.example.com/half.png',
            'organizer_profile' => [
                'website'     => 'https://only-website.example',
                'description' => 'Just a blurb.',
            ],
        ]);
        $link = $this->makeEvent($host);

        $resp = $this->getJson('/api/v1/events/' . $link->alias);
        $resp->assertOk();

        $org = $resp->json('data.organizer');
        $this->assertTrue($org['filled']);
        $this->assertSame('https://only-website.example', $org['website']);
        $this->assertSame('Just a blurb.', $org['description']);
        // Name/logo unset in profile ⇒ fall back to the host account.
        $this->assertSame('Half Filled', $org['name']);
        $this->assertSame('https://cdn.example.com/half.png', $org['avatar']);
        $this->assertNull($org['logo']);
        $this->assertNull($org['contact_email']);
        $this->assertNull($org['address']);
    }
}
