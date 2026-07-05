<?php

namespace Tests\Feature;

use App\Modules\Common\Services\DeliveryProjectNotifier;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalLink;
use App\Modules\User\Models\ClientPortalShare;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectComment;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Task #3573 — make sure a client's questions always reach the team and that
 * milestone emails always go out (or are deliberately skipped), across all
 * four comment-posting surfaces plus the milestone hooks.
 *
 *   Surfaces:
 *     • team web    — user.delivery-projects.comments.store (tasks.edit)
 *     • client portal — portal.delivery-project.comment
 *     • public share  — delivery-project.share.comment (throttle:20,1 + author_name)
 *     • REST API      — GET/POST /api/v1/delivery-projects/{id}/comments
 *
 *   Fan-out + milestones:
 *     • a client comment fans out to the team via NotificationService
 *       (`delivery_project.comment`),
 *     • task-completed (todo→done), project-completed and warranty
 *       ending/expired all route through {@see DeliveryProjectNotifier},
 *     • client emails are skipped when the project has no client_email,
 *     • every email key resolves in {@see EmailTemplateRegistry}.
 *
 * The test env mailer is `array`, so real Emailer sends land in `email_logs`
 * (keyed by `email_key`) — no Mail::fake (which would silently no-op raw sends).
 * API tests use a REAL bearer token (Sanctum::actingAs breaks TouchSessionToken).
 */
class DeliveryProjectCommentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        $user = User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function memberOf(Workspace $ws, User $user, string $role): void
    {
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => $role,
        ]);
    }

    /** @param array{in_app?:bool,email?:bool,push?:bool} $channels */
    private function setPref(User $user, string $type, array $channels): void
    {
        NotificationPreference::create(array_merge([
            'user_id' => $user->id,
            'type'    => $type,
        ], $channels));
    }

    private function makeProject(Workspace $ws, array $attrs = []): DeliveryProject
    {
        return DeliveryProject::create(array_merge([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $ws->owner_user_id,
            'title'              => 'Proj ' . Str::random(4),
            'status'             => DeliveryProject::STATUS_ACTIVE,
        ], $attrs));
    }

    private function portalLinkFor(Workspace $ws, DeliveryProject $project, bool $share = true): ClientPortalLink
    {
        $portal = ClientPortal::create([
            'workspace_id' => $ws->id,
            'name'         => 'Acme portal',
            'is_enabled'   => true,
        ]);
        $link = ClientPortalLink::create([
            'portal_id'    => $portal->id,
            'workspace_id' => $ws->id,
            'email'        => 'client@example.com',
            'token'        => ClientPortalLink::newToken(),
        ]);
        if ($share) {
            ClientPortalShare::create([
                'portal_id'      => $portal->id,
                'workspace_id'   => $ws->id,
                'shareable_type' => ClientPortalShare::TYPE_DELIVERY_PROJECT,
                'shareable_id'   => $project->id,
                'label'          => 'Your delivery',
            ]);
        }
        return $link;
    }

    // ----- Surface 1: team web reply ----------------------------------------

    public function test_team_web_reply_persists_and_emails_the_client(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com', 'client_name' => 'Buyer']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)
            ->post("/user/delivery-projects/{$project->id}/comments", ['body' => 'On it, shipping Monday.'])
            ->assertRedirect();

        $comment = DeliveryProjectComment::query()->withoutGlobalScope('workspace')
            ->where('project_id', $project->id)->first();
        $this->assertNotNull($comment);
        $this->assertSame(DeliveryProjectComment::ROLE_TEAM, $comment->author_role);

        // The client is emailed via the team-reply template.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.team_reply',
            'recipient' => 'buyer@example.com',
        ]);
    }

    public function test_team_web_reply_requires_tasks_edit(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $viewer  = $this->makeUser('vw');
        $this->memberOf($ws, $viewer, 'viewer'); // view-only, no tasks.edit
        $project = $this->makeProject($ws);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($viewer)
            ->post("/user/delivery-projects/{$project->id}/comments", ['body' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(0, DeliveryProjectComment::query()->withoutGlobalScope('workspace')->count());
    }

    // ----- Surface 2: logged-in client portal -------------------------------

    public function test_portal_client_comment_fans_out_to_the_team(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $member  = $this->makeUser('mem');
        $this->memberOf($ws, $member, 'editor');
        $project = $this->makeProject($ws, ['title' => 'Portal delivery']);
        $link    = $this->portalLinkFor($ws, $project);

        $this->withSession(['portal_link_id' => $link->id])
            ->post(route('portal.delivery-project.comment', $project->id), ['body' => 'When do the cabinets arrive?'])
            ->assertRedirect();

        $comment = DeliveryProjectComment::query()->withoutGlobalScope('workspace')
            ->where('project_id', $project->id)->first();
        $this->assertNotNull($comment);
        $this->assertSame(DeliveryProjectComment::ROLE_CLIENT, $comment->author_role);

        // Owner + editor member both receive the in-app team notification.
        foreach ([$owner->id, $member->id] as $uid) {
            $this->assertDatabaseHas('user_notifications', [
                'user_id' => $uid,
                'type'    => 'delivery_project.comment',
            ]);
        }
    }

    public function test_comment_fanout_respects_each_members_channel_preferences(): void
    {
        $owner = $this->makeUser('owner');
        $ws    = $owner->ownedWorkspaces()->first();

        // Three teammates with different appetites for this notification type.
        $emailOn  = $this->makeUser('on');   // wants in-app + email
        $emailOff = $this->makeUser('off');  // in-app only, email muted
        $muted    = $this->makeUser('mute'); // muted the type entirely
        $this->memberOf($ws, $emailOn, 'editor');
        $this->memberOf($ws, $emailOff, 'editor');
        $this->memberOf($ws, $muted, 'editor');

        $this->setPref($emailOn, 'delivery_project.comment', ['in_app' => true, 'email' => true, 'push' => false]);
        $this->setPref($emailOff, 'delivery_project.comment', ['in_app' => true, 'email' => false, 'push' => false]);
        $this->setPref($muted, 'delivery_project.comment', ['in_app' => false, 'email' => false, 'push' => false]);

        $project = $this->makeProject($ws, ['title' => 'Pref delivery']);
        $link    = $this->portalLinkFor($ws, $project);

        $this->withSession(['portal_link_id' => $link->id])
            ->post(route('portal.delivery-project.comment', $project->id), ['body' => 'Any update on my order?'])
            ->assertRedirect();

        // --- Email side: only members who allow email get a client_comment email.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.client_comment',
            'recipient' => $emailOn->email,
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'delivery_project.client_comment',
            'recipient' => $emailOff->email,
        ]);

        // --- In-app side: the email-muted member still gets the in-app row.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $emailOff->id,
            'type'    => 'delivery_project.comment',
        ]);

        // --- The fully-muted member gets NEITHER channel.
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $muted->id,
            'type'    => 'delivery_project.comment',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'delivery_project.client_comment',
            'recipient' => $muted->email,
        ]);
    }

    public function test_portal_comment_requires_a_share(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);
        $link    = $this->portalLinkFor($ws, $project, share: false);

        $this->withSession(['portal_link_id' => $link->id])
            ->post(route('portal.delivery-project.comment', $project->id), ['body' => 'Hi'])
            ->assertNotFound();

        $this->assertSame(0, DeliveryProjectComment::query()->withoutGlobalScope('workspace')->count());
    }

    // ----- Surface 3: anonymous public share page ---------------------------

    public function test_public_share_comment_persists_author_name_and_notifies_team(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['title' => 'Public delivery']);

        // No auth, no session — the unguessable token is the only authenticator.
        $this->post('/dp/' . $project->share_token . '/comments', [
            'author_name' => 'Walk-in Buyer',
            'body'        => 'Is my order ready?',
        ])->assertRedirect();

        $comment = DeliveryProjectComment::query()->withoutGlobalScope('workspace')
            ->where('project_id', $project->id)->first();
        $this->assertNotNull($comment);
        $this->assertSame(DeliveryProjectComment::ROLE_CLIENT, $comment->author_role);
        $this->assertSame('Walk-in Buyer', $comment->author_name);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'type'    => 'delivery_project.comment',
        ]);
    }

    public function test_public_share_comment_is_throttled(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);
        $uri     = '/dp/' . $project->share_token . '/comments';

        // The route is throttled at 20/min; the 21st request is rejected (429).
        for ($i = 0; $i < 20; $i++) {
            $this->post($uri, ['body' => "msg {$i}"])->assertRedirect();
        }
        $this->post($uri, ['body' => 'over the limit'])->assertStatus(429);
    }

    public function test_invalid_share_token_comment_returns_404(): void
    {
        $this->post('/dp/' . str_repeat('z', 48) . '/comments', ['body' => 'Hi'])
            ->assertNotFound();
    }

    // ----- Surface 4: REST API ----------------------------------------------

    public function test_api_lists_the_comment_thread(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);
        $project->comments()->create([
            'workspace_id' => $ws->id,
            'author_role'  => DeliveryProjectComment::ROLE_CLIENT,
            'author_name'  => 'Buyer',
            'body'         => 'A question',
        ]);

        $resp = $this->withToken($this->token($owner))
            ->getJson("/api/v1/delivery-projects/{$project->id}/comments")
            ->assertOk()
            ->assertJsonStructure(['data' => ['items' => [['id', 'author_role', 'is_team', 'author_name', 'body']]]]);

        $this->assertSame('A question', $resp->json('data.items.0.body'));
    }

    public function test_api_team_reply_persists_and_emails_the_client(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com', 'client_name' => 'Buyer']);

        $this->withToken($this->token($owner))
            ->postJson("/api/v1/delivery-projects/{$project->id}/comments", ['body' => 'Thanks for reaching out.'])
            ->assertStatus(201)
            ->assertJsonPath('data.comment.is_team', true);

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.team_reply',
            'recipient' => 'buyer@example.com',
        ]);
    }

    public function test_api_comment_read_and_post_require_permissions(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);
        $outsider = $this->makeUser('out');

        // No membership → read resolves nothing → 404 error envelope.
        $this->withToken($this->token($outsider))
            ->getJson("/api/v1/delivery-projects/{$project->id}/comments")
            ->assertStatus(404)
            ->assertJsonStructure(['error' => ['message']]);

        // A viewer lacks tasks.edit → the post cannot resolve the project → 404.
        $viewer = $this->makeUser('vw');
        $this->memberOf($ws, $viewer, 'viewer');
        $this->withToken($this->token($viewer))
            ->postJson("/api/v1/delivery-projects/{$project->id}/comments", ['body' => 'Hack'])
            ->assertStatus(404)
            ->assertJsonStructure(['error' => ['message']]);

        $this->assertSame(0, DeliveryProjectComment::query()->withoutGlobalScope('workspace')->count());
    }

    // ----- Milestone hooks route through DeliveryProjectNotifier -------------

    public function test_completing_a_task_calls_the_notifier_once(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com']);
        $task    = $project->tasks()->create([
            'workspace_id' => $ws->id,
            'title'        => 'Install cabinets',
            'status'       => DeliveryProjectTask::STATUS_TODO,
            'progress'     => 0,
            'position'     => 1,
        ]);

        $notifier = Mockery::mock(DeliveryProjectNotifier::class);
        $notifier->shouldReceive('taskCompleted')->once()
            ->withArgs(fn ($p, $t) => $p->id === $project->id && $t->id === $task->id);
        $this->app->instance(DeliveryProjectNotifier::class, $notifier);

        session(['active_workspace_id' => $ws->id]);

        // todo → done fires the hook exactly once.
        $this->actingAs($owner)
            ->patchJson("/user/delivery-projects/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk();

        // Already-done → no second call (the mock's ->once() enforces this).
        $this->actingAs($owner)
            ->patchJson("/user/delivery-projects/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk();
    }

    public function test_completing_a_task_emails_the_client_step_update(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com', 'client_name' => 'Buyer']);
        $task    = $project->tasks()->create([
            'workspace_id' => $ws->id,
            'title'        => 'Install cabinets',
            'status'       => DeliveryProjectTask::STATUS_TODO,
            'progress'     => 0,
            'position'     => 1,
        ]);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)
            ->patchJson("/user/delivery-projects/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk();

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.task_completed',
            'recipient' => 'buyer@example.com',
        ]);
    }

    public function test_completing_the_project_calls_the_notifier(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com', 'client_name' => 'Buyer']);

        $notifier = Mockery::mock(DeliveryProjectNotifier::class);
        $notifier->shouldReceive('projectCompleted')->once()
            ->withArgs(fn ($p) => $p->id === $project->id);
        $this->app->instance(DeliveryProjectNotifier::class, $notifier);

        session(['active_workspace_id' => $ws->id]);

        // Active → completed fires the milestone hook exactly once.
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();

        // Re-saving as completed must not re-fire (the mock's ->once() enforces it).
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();
    }

    public function test_completing_the_project_emails_the_client(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['client_email' => 'buyer@example.com', 'client_name' => 'Buyer']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.completed',
            'recipient' => 'buyer@example.com',
        ]);

        // Re-saving as completed must not re-email (only the transition fires).
        $before = \App\Modules\Common\Models\EmailLog::where('email_key', 'delivery_project.completed')->count();
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();
        $after = \App\Modules\Common\Models\EmailLog::where('email_key', 'delivery_project.completed')->count();
        $this->assertSame($before, $after);
    }

    public function test_warranty_command_calls_the_notifier_for_ending_and_expired(): void
    {
        $owner = $this->makeUser('owner');
        $ws    = $owner->ownedWorkspaces()->first();

        // Ending soon: lead date already reached.
        $ending = $this->makeProject($ws, [
            'title'                  => 'Ending warranty',
            'client_email'           => 'buyer@example.com',
            'warranty_expires_at'    => now()->addDays(3),
            'warranty_reminder_days' => 7,
        ]);
        // Expired: past the expiry date.
        $expired = $this->makeProject($ws, [
            'title'                  => 'Expired warranty',
            'client_email'           => 'buyer2@example.com',
            'warranty_expires_at'    => now()->subDay(),
            'warranty_reminder_days' => 7,
        ]);

        // Both branches must route through the notifier with the right state.
        $notifier = Mockery::mock(DeliveryProjectNotifier::class);
        $notifier->shouldReceive('warranty')->once()
            ->withArgs(fn ($p, $state) => $p->id === $ending->id && $state === 'ending');
        $notifier->shouldReceive('warranty')->once()
            ->withArgs(fn ($p, $state) => $p->id === $expired->id && $state === 'expired');
        $this->app->instance(DeliveryProjectNotifier::class, $notifier);

        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful();
    }

    public function test_warranty_command_emails_the_client(): void
    {
        $owner = $this->makeUser('owner');
        $ws    = $owner->ownedWorkspaces()->first();

        // Ending soon: lead date already reached.
        $ending = $this->makeProject($ws, [
            'title'                  => 'Ending warranty',
            'client_email'           => 'buyer@example.com',
            'client_name'            => 'Buyer',
            'warranty_expires_at'    => now()->addDays(3),
            'warranty_reminder_days' => 7,
        ]);
        // Expired: past the expiry date.
        $expired = $this->makeProject($ws, [
            'title'                  => 'Expired warranty',
            'client_email'           => 'buyer2@example.com',
            'client_name'            => 'Buyer2',
            'warranty_expires_at'    => now()->subDay(),
            'warranty_reminder_days' => 7,
        ]);

        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful();

        // Both ending + expired route through the notifier's single warranty key.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.warranty_reminder',
            'recipient' => 'buyer@example.com',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'delivery_project.warranty_reminder',
            'recipient' => 'buyer2@example.com',
        ]);
    }

    // ----- Client emails are skipped when there is no client_email ----------

    public function test_client_emails_are_skipped_without_a_client_email(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        // No client_email captured on the project.
        $project = $this->makeProject($ws);
        $task    = $project->tasks()->create([
            'workspace_id' => $ws->id,
            'title'        => 'Step',
            'status'       => DeliveryProjectTask::STATUS_TODO,
            'progress'     => 0,
            'position'     => 1,
        ]);

        session(['active_workspace_id' => $ws->id]);

        // Team reply, task-completed and project-completed all no-op the email.
        $this->actingAs($owner)
            ->post("/user/delivery-projects/{$project->id}/comments", ['body' => 'Reply'])
            ->assertRedirect();
        $this->actingAs($owner)
            ->patchJson("/user/delivery-projects/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk();
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();

        foreach ([
            'delivery_project.team_reply',
            'delivery_project.task_completed',
            'delivery_project.completed',
        ] as $key) {
            $this->assertDatabaseMissing('email_logs', ['email_key' => $key]);
        }
    }

    // ----- Every email key resolves in the registry -------------------------

    public function test_delivery_project_email_keys_resolve_in_registry(): void
    {
        foreach ([
            'delivery_project.client_comment',
            'delivery_project.team_reply',
            'delivery_project.task_completed',
            'delivery_project.completed',
            'delivery_project.warranty_reminder',
        ] as $key) {
            $this->assertTrue(
                EmailTemplateRegistry::exists($key),
                "Email registry is missing key: {$key}"
            );
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
