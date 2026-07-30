<?php

namespace Tests\Feature;

use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\SocialProofSubmission;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialProofSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Buzz Owner',
            'email'    => 'buzz-owner-' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pass-123'),
        ]);
    }

    private function makeProof(User $user, string $type = 'collector_modal', bool $notifActive = true): SocialProof
    {
        return SocialProof::create([
            'user_id'   => $user->id,
            'name'      => 'Test campaign',
            'type'      => $type,
            'is_active' => true,
            'design'    => SocialProof::defaultDesign(),
            'targeting' => SocialProof::defaultTargeting(),
            'notifications' => [SocialProof::normalizeNotification([
                'id'        => 'notif-1',
                'type'      => $type,
                'is_active' => $notifActive,
            ])],
        ]);
    }

    public function test_public_config_exposes_inline_selector_and_loader_uses_current_widget_version(): void
    {
        $user = $this->makeUser();
        $proof = SocialProof::create([
            'user_id'   => $user->id,
            'name'      => 'Inline campaign',
            'type'      => 'inline_informational',
            'is_active' => true,
            'design'    => SocialProof::defaultDesign(),
            'targeting' => SocialProof::defaultTargeting(),
            'notifications' => [SocialProof::normalizeNotification([
                'id'       => 'notif-1',
                'type'     => 'inline_informational',
                'settings' => ['text' => 'Free shipping', 'selector' => '#reviews'],
            ])],
        ]);

        $config = $this->getJson("/sp/{$proof->uuid}.json");
        $config->assertOk();
        $this->assertSame('#reviews', $config->json('notifications.0.settings.selector'));
        $this->assertSame('inline_informational', $config->json('notifications.0.type'));

        $loader = $this->get("/sp/{$proof->uuid}.js");
        $loader->assertOk();
        $this->assertStringContainsString(
            '?v=' . \App\Modules\Common\Controllers\SocialProofPublicController::WIDGET_VERSION,
            $loader->getContent()
        );

        // The runtime widget must actually implement selector-based inline mounting.
        $widget = file_get_contents(public_path('js/social-proof-widget.js'));
        $this->assertStringContainsString('document.querySelector(String(s.selector))', $widget);
    }

    public function test_newsletter_signup_accepts_public_email_submission(): void
    {
        $proof = $this->makeProof($this->makeUser(), 'newsletter_signup');

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'email'           => 'subscriber@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('social_proof_submissions', [
            'social_proof_id' => $proof->id,
            'type'            => 'newsletter_signup',
            'email'           => 'subscriber@example.com',
        ]);
    }

    public function test_public_submit_stores_a_submission(): void
    {
        $proof = $this->makeProof($this->makeUser());

        $res = $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'name'            => 'Visitor',
            'email'           => 'visitor@example.com',
            'message'         => 'Hello there',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('social_proof_submissions', [
            'social_proof_id' => $proof->id,
            'notification_id' => 'notif-1',
            'type'            => 'collector_modal',
            'email'           => 'visitor@example.com',
        ]);
    }

    public function test_submit_rejects_non_collector_notification_types(): void
    {
        $proof = $this->makeProof($this->makeUser(), 'recent_activity');

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'email'           => 'visitor@example.com',
        ])->assertStatus(422)->assertJsonPath('error', 'not_collector');

        $this->assertSame(0, SocialProofSubmission::count());
    }

    public function test_submit_rejects_unknown_notification_and_empty_payload_and_bad_email(): void
    {
        $proof = $this->makeProof($this->makeUser());

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'nope',
            'email'           => 'visitor@example.com',
        ])->assertStatus(422);

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
        ])->assertStatus(422)->assertJsonPath('error', 'empty');

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'email'           => 'not-an-email',
        ])->assertStatus(422)->assertJsonPath('error', 'invalid_email');

        $this->assertSame(0, SocialProofSubmission::count());
    }

    public function test_submit_returns_404_for_inactive_campaign(): void
    {
        $proof = $this->makeProof($this->makeUser());
        $proof->update(['is_active' => false]);

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'email'           => 'visitor@example.com',
        ])->assertStatus(404);
    }

    public function test_submit_stores_feedback_rating_and_clamps_it(): void
    {
        $proof = $this->makeProof($this->makeUser(), 'score_feedback');

        $this->postJson("/sp/{$proof->uuid}/submit", [
            'notification_id' => 'notif-1',
            'rating'          => 99,
        ])->assertOk();

        $this->assertDatabaseHas('social_proof_submissions', [
            'social_proof_id' => $proof->id,
            'type'            => 'score_feedback',
            'rating'          => 10,
        ]);
    }

    public function test_owner_can_view_submissions_and_export_csv(): void
    {
        $user = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        $proof = $this->makeProof($user);

        SocialProofSubmission::create([
            'social_proof_id' => $proof->id,
            'notification_id' => 'notif-1',
            'type'            => 'collector_modal',
            'name'            => 'Visitor',
            'email'           => 'visitor@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('user.social-proofs.submissions', $proof))
            ->assertOk()
            ->assertSee('visitor@example.com');

        $csv = $this->actingAs($user)
            ->get(route('user.social-proofs.submissions.csv', $proof));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('visitor@example.com', $csv->streamedContent());
    }

    public function test_non_owner_cannot_view_submissions(): void
    {
        $owner = $this->makeUser();
        $proof = $this->makeProof($owner);
        $other = $this->makeUser();

        $ws = app(WorkspaceContext::class)->resolve($other);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $other);

        // The workspace-scoped route binding hides foreign campaigns (404)
        // before the ownership 403 check runs; either way access is denied.
        $status = $this->actingAs($other)
            ->get(route('user.social-proofs.submissions', $proof))
            ->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }
}
