<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestimonialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    // ── Public form ────────────────────────────────────────────────────

    public function test_public_submit_form_is_accessible(): void
    {
        $response = $this->get(route('testimonials.submit.show'));
        $response->assertStatus(200);
        $response->assertSee('Share your experience');
    }

    public function test_valid_submission_creates_pending_testimonial(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'quote'           => 'Sayzio is absolutely brilliant for creators.',
            'author_name'     => 'Alice Tester',
            'author_role'     => 'Content creator',
            'rating'          => 5,
            'submitter_email' => 'alice@example.com',
            'website'         => '',
        ]);

        $response->assertRedirect(route('testimonials.submit.show'));

        $this->assertDatabaseHas('testimonials', [
            'quote'           => 'Sayzio is absolutely brilliant for creators.',
            'author_name'     => 'Alice Tester',
            'status'          => 'pending',
            'source'          => 'public',
            'is_active'       => false,
            'submitter_email' => 'alice@example.com',
        ]);
    }

    public function test_submission_shows_thank_you_state(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'quote'       => 'Great product!',
            'author_name' => 'Bob Test',
            'website'     => '',
        ]);

        $response->assertRedirect(route('testimonials.submit.show'));
        $follow = $this->followRedirects($response);
        $follow->assertSee('Thank you');
    }

    public function test_honeypot_filled_silently_discards_submission(): void
    {
        $this->post(route('testimonials.submit.store'), [
            'quote'       => 'Bot submission',
            'author_name' => 'Spammer Bot',
            'website'     => 'http://spam.example.com',
        ]);

        $this->assertDatabaseMissing('testimonials', [
            'author_name' => 'Spammer Bot',
        ]);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'website' => '',
        ]);

        $response->assertSessionHasErrors(['quote', 'author_name']);
    }

    public function test_validation_rejects_quote_over_600_chars(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'quote'       => str_repeat('a', 601),
            'author_name' => 'Test',
            'website'     => '',
        ]);

        $response->assertSessionHasErrors(['quote']);
    }

    public function test_validation_rejects_invalid_rating(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'quote'       => 'Good product',
            'author_name' => 'Test',
            'rating'      => 6,
            'website'     => '',
        ]);

        $response->assertSessionHasErrors(['rating']);
    }

    public function test_validation_rejects_invalid_email(): void
    {
        $response = $this->post(route('testimonials.submit.store'), [
            'quote'           => 'Good product',
            'author_name'     => 'Test',
            'submitter_email' => 'not-an-email',
            'website'         => '',
        ]);

        $response->assertSessionHasErrors(['submitter_email']);
    }

    // ── Admin moderation ───────────────────────────────────────────────

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
        ]);
    }

    private function makePending(array $attrs = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'quote'        => 'Pending quote text',
            'author_name'  => 'Pending Author',
            'author_role'  => 'Tester',
            'accent_color' => '#3d6bff',
            'rating'       => 5,
            'row'          => 'top',
            'is_active'    => false,
            'sort_order'   => 0,
            'status'       => 'pending',
            'source'       => 'public',
            'submitted_at' => now(),
        ], $attrs));
    }

    public function test_admin_pending_page_shows_pending_submissions(): void
    {
        $admin = $this->makeAdmin();
        $this->makePending();
        $this->makePending(['author_name' => 'Another Pending']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.testimonials.pending'));

        $response->assertStatus(200);
        $response->assertSee('Pending Author');
        $response->assertSee('Another Pending');
    }

    public function test_admin_index_shows_pending_badge_when_pending_exist(): void
    {
        $admin = $this->makeAdmin();
        $this->makePending();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.testimonials.index'));

        $response->assertStatus(200);
        $response->assertSee('pending');
    }

    public function test_admin_can_approve_pending_testimonial(): void
    {
        $admin = $this->makeAdmin();
        $t = $this->makePending();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.testimonials.approve', $t), [
                'row'          => 'bottom',
                'accent_color' => '#e94e8c',
                'sort_order'   => 10,
                'is_active'    => '1',
            ]);

        $response->assertRedirect(route('admin.testimonials.pending'));

        $t->refresh();
        $this->assertSame('approved', $t->status);
        $this->assertSame('bottom', $t->row);
        $this->assertSame('#e94e8c', $t->accent_color);
        $this->assertTrue($t->is_active);
    }

    public function test_admin_can_reject_pending_testimonial(): void
    {
        $admin = $this->makeAdmin();
        $t = $this->makePending();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.testimonials.reject', $t));

        $response->assertRedirect();

        $t->refresh();
        $this->assertSame('rejected', $t->status);
        $this->assertFalse($t->is_active);
    }

    // ── Homepage only shows approved+active testimonials ───────────────

    public function test_cached_active_excludes_pending_testimonials(): void
    {
        Cache::flush();

        Testimonial::create([
            'quote'        => 'Approved and live',
            'author_name'  => 'Active User',
            'accent_color' => '#3d6bff',
            'rating'       => 5,
            'row'          => 'top',
            'is_active'    => true,
            'sort_order'   => 10,
            'status'       => 'approved',
            'source'       => 'admin',
        ]);

        $this->makePending(['is_active' => false]);

        Testimonial::create([
            'quote'        => 'Rejected quote',
            'author_name'  => 'Rejected User',
            'accent_color' => '#3d6bff',
            'rating'       => 5,
            'row'          => 'top',
            'is_active'    => false,
            'sort_order'   => 20,
            'status'       => 'rejected',
            'source'       => 'public',
        ]);

        $active = Testimonial::cachedActive();

        $this->assertCount(1, $active);
        $this->assertSame('Active User', $active->first()->author_name);
    }

    public function test_approving_testimonial_flushes_cache(): void
    {
        $admin = $this->makeAdmin();
        $t = $this->makePending();

        Cache::put('home:testimonials:active', [['quote' => 'stale']], 300);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.testimonials.approve', $t), [
                'row'       => 'top',
                'is_active' => '1',
            ]);

        $this->assertFalse(Cache::has('home:testimonials:active'));
    }
}
