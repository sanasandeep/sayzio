<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateLinkDomainDropdownTest extends TestCase
{
    public function test_create_link_page_shows_domain_dropdown_with_global_domains(): void
    {
        foreach (['sayzio.app', 'bizs.club', 'getbio.one'] as $i => $host) {
            Domain::firstOrCreate(['domain' => $host], [
                'user_id' => null, 'type' => 'redirect', 'is_active' => true,
                'is_verified' => true, 'verified_at' => now(),
                'is_global' => true, 'is_primary' => $i === 0,
                'verification_token' => Str::random(32),
            ]);
        }

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.links.create'));
        $resp->assertOk();
        $resp->assertSee('name="domain_id"', false);
        $resp->assertSee('bizs.club/');
        $resp->assertSee('getbio.one/');
    }

    public function test_choose_type_forwards_domain_id_to_step_two(): void
    {
        $domain = Domain::firstOrCreate(['domain' => 'bizs.club'], [
            'user_id' => null, 'type' => 'redirect', 'is_active' => true,
            'is_verified' => true, 'verified_at' => now(), 'is_global' => true,
            'verification_token' => Str::random(32),
        ]);

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->post(route('user.links.choose-type'), [
            'type' => 'url', 'alias' => '', 'domain_id' => $domain->id,
        ]);
        $resp->assertRedirect();
        $this->assertStringContainsString('domain_id=' . $domain->id, $resp->headers->get('Location'));
    }

    public function test_choose_type_rejects_domain_not_available_to_user(): void
    {
        $other = User::factory()->create();
        $foreign = Domain::create([
            'user_id' => $other->id, 'domain' => 'someone-elses-' . Str::random(6) . '.com',
            'type' => 'redirect', 'is_active' => true, 'is_verified' => true,
            'verified_at' => now(), 'verification_token' => Str::random(32),
        ]);

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->post(route('user.links.choose-type'), [
            'type' => 'url', 'alias' => '', 'domain_id' => $foreign->id,
        ]);
        $resp->assertSessionHasErrors('domain_id');
    }

    public function test_step_two_preselects_forwarded_domain(): void
    {
        $domain = Domain::firstOrCreate(['domain' => 'bizs.club'], [
            'user_id' => null, 'type' => 'redirect', 'is_active' => true,
            'is_verified' => true, 'verified_at' => now(), 'is_global' => true,
            'verification_token' => Str::random(32),
        ]);

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.links.url.create', ['domain_id' => $domain->id]));
        $resp->assertOk();
        $resp->assertSee('value="' . $domain->id . '" selected', false);
    }
}
