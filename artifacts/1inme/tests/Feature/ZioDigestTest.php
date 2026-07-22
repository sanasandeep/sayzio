<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Models\ZioDigest;
use App\Modules\Common\Models\ZioDigestRecipient;
use App\Modules\User\Models\User;
use App\Services\ZioDigest\ZioDigestAudience;
use App\Services\ZioDigest\ZioDigestRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZioDigestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'zio-digest-admin-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret-password'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makeDigest(array $attrs = []): ZioDigest
    {
        return ZioDigest::create(array_merge([
            'title'        => 'Weekly Digest ' . Str::random(6),
            'slug'         => ZioDigest::uniqueSlug('Weekly Digest ' . Str::random(6)),
            'status'       => 'published',
            'published_at' => now(),
            'summary'      => 'A quick roundup.',
            'blocks'       => [
                ['type' => 'heading', 'text' => 'Hello'],
                ['type' => 'text', 'text' => 'World'],
                ['type' => 'link', 'url' => 'https://example.com', 'title' => 'Example'],
            ],
            'audience'     => ['mode' => 'all', 'plan_ids' => []],
        ], $attrs));
    }

    public function test_published_digest_public_page_renders(): void
    {
        $digest = $this->makeDigest();

        $this->get('/digest/' . $digest->slug)
            ->assertOk()
            ->assertSee($digest->title)
            ->assertSee('Hello')
            ->assertSee('World');
    }

    public function test_draft_digest_public_page_is_404(): void
    {
        $digest = $this->makeDigest(['status' => 'draft', 'published_at' => null]);

        $this->get('/digest/' . $digest->slug)->assertNotFound();
    }

    public function test_admin_can_create_and_update_digest(): void
    {
        $this->be($this->admin(), 'admin');

        $this->post('/admin/zio-digests', [
            'title'         => 'Launch Notes',
            'status'        => 'draft',
            'summary'       => 'What shipped this week.',
            'blocks_json'   => json_encode([
                ['type' => 'heading', 'text' => 'Shipped'],
                ['type' => 'bogus', 'text' => 'dropped'],
            ]),
            'audience_mode' => 'opted_in',
        ])->assertRedirect();

        $digest = ZioDigest::where('title', 'Launch Notes')->firstOrFail();
        $this->assertSame('draft', $digest->status);
        $this->assertCount(1, $digest->blocks, 'Unknown block types must be stripped.');
        $this->assertNull($digest->published_at);

        $this->put('/admin/zio-digests/' . $digest->id, [
            'title'         => 'Launch Notes',
            'status'        => 'published',
            'blocks_json'   => json_encode([['type' => 'text', 'text' => 'Updated']]),
            'audience_mode' => 'all',
        ])->assertRedirect();

        $digest->refresh();
        $this->assertSame('published', $digest->status);
        $this->assertNotNull($digest->published_at);
        $this->assertSame('all', $digest->audience['mode']);
    }

    public function test_admin_pages_render(): void
    {
        $this->be($this->admin(), 'admin');
        $digest = $this->makeDigest();

        $this->get('/admin/zio-digests')->assertOk()->assertSee($digest->title);
        $this->get('/admin/zio-digests/create')->assertOk();
        $this->get('/admin/zio-digests/' . $digest->id . '/edit')->assertOk()->assertSee('Content blocks');
        $this->get('/admin/zio-digests/' . $digest->id . '/report')->assertOk()->assertSee('Delivery report');
        $this->get('/admin/zio-digests/' . $digest->id . '/preview')->assertOk()->assertSee('Admin preview');
    }

    public function test_send_requires_published_digest(): void
    {
        $this->be($this->admin(), 'admin');
        $digest = $this->makeDigest(['status' => 'draft', 'published_at' => null]);

        $this->from('/admin/zio-digests/' . $digest->id . '/edit')
            ->post('/admin/zio-digests/' . $digest->id . '/send', ['channels' => ['email']])
            ->assertRedirect('/admin/zio-digests/' . $digest->id . '/edit')
            ->assertSessionHas('error');

        $this->assertSame('idle', $digest->fresh()->email_status);
    }

    public function test_audience_counts_respect_opt_out_and_phone(): void
    {
        $this->be($this->admin(), 'admin');

        $suffix = Str::random(8);
        User::forceCreate(['name' => 'A', 'email' => "zd-a-{$suffix}@example.com", 'password' => bcrypt('x')]);
        $optedOut = User::forceCreate(['name' => 'B', 'email' => "zd-b-{$suffix}@example.com", 'password' => bcrypt('x')]);
        $optedOut->forceFill(['digest_email_opt_out' => true])->save();

        $audience = ZioDigestAudience::normalize(['mode' => 'all']);
        $counts = ZioDigestAudience::counts($audience);

        $this->assertGreaterThanOrEqual(2, $counts['total']);
        $this->assertSame($counts['total'] - $counts['email'], $counts['email_opted_out']);
        $this->assertSame($counts['total'] - $counts['whatsapp'], $counts['no_phone']);

        $emailIds = ZioDigestAudience::emailQuery($audience)->pluck('users.id');
        $this->assertFalse($emailIds->contains($optedOut->id));
    }

    public function test_signed_unsubscribe_get_and_post(): void
    {
        $digest = $this->makeDigest();
        $user = User::forceCreate([
            'name' => 'Unsub', 'email' => 'zd-unsub-' . Str::random(8) . '@example.com', 'password' => bcrypt('x'),
        ]);

        // Unsigned request is rejected.
        $this->get('/digest/unsubscribe/' . $user->id)->assertForbidden();

        $url = ZioDigestRenderer::unsubscribeUrl($user->id, $digest->id);
        $this->get($url)->assertOk()->assertSee('unsubscribed');
        $this->assertTrue($user->fresh()->digest_email_opt_out);
        $this->assertSame(1, $digest->fresh()->unsubscribed_count);

        // Idempotent: second hit (RFC 8058 POST) doesn't double-count.
        $this->post($url)->assertOk();
        $this->assertSame(1, $digest->fresh()->unsubscribed_count);
    }

    public function test_recipient_rows_unique_per_channel(): void
    {
        $digest = $this->makeDigest();
        $user = User::forceCreate([
            'name' => 'R', 'email' => 'zd-r-' . Str::random(8) . '@example.com', 'password' => bcrypt('x'),
        ]);

        ZioDigestRecipient::create(['digest_id' => $digest->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'queued']);
        \Illuminate\Support\Facades\DB::table('zio_digest_recipients')->insertOrIgnore([
            'digest_id' => $digest->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'queued',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, ZioDigestRecipient::where('digest_id', $digest->id)->count());
    }
}
