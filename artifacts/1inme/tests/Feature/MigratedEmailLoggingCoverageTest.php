<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\BlogComment;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Second half of the positive coverage started in
 * {@see MigratedEmailLoggingTest}. Task #4740 rerouted ~13 previously
 * untracked outbound emails through the central
 * {@see \App\Modules\Common\Services\Emailer} pipeline so they land in
 * `email_logs`; Task #4743 covered 5 representative keys. This file closes
 * the gap for the remaining migrated senders so a future refactor can't
 * quietly drop the Emailer call at one of these sites while
 * {@see DirectMailSendAllowlistTest} (which only proves nothing *bypasses*
 * Emailer) stays green and the admin email history silently loses the type.
 *
 * Each test drives a migrated call site through its real entry point (HTTP
 * route or the shared service the route delegates to) and asserts an
 * `email_logs` row keyed by the expected EmailTemplateRegistry key:
 *   - blog.comment_reply       — admin replies to a guest blog comment
 *   - newsletter.test          — admin sends themselves a draft preview
 *   - subscriber.broadcast     — creator blasts their email subscribers
 *   - inbox.reply              — unified-inbox reply back over email
 *   - inbox.forward            — forwarding-rule "send test" delivery
 *   - follower.instant_update  — a new post pings instant-mode followers
 *   - client_portal.magic_link — the API mirror of the web magic-link send
 *
 * phpunit.xml pins MAIL_MAILER=array, so sends flow through the full
 * dispatch → writeLog path but hit the in-memory transport (no socket),
 * producing a real `email_logs` row with status 'sent'. We assert on those
 * rows, NOT Mail::fake() counts (which never record Emailer's
 * Mail::html/raw sends).
 */
class MigratedEmailLoggingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Log Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function emailLogFor(string $key): EmailLog
    {
        $log = EmailLog::where('email_key', $key)->latest('id')->first();
        $this->assertNotNull(
            $log,
            "Expected an email_logs row keyed '{$key}' after triggering its call site, "
            . "but none was written — the send likely no longer flows through Emailer."
        );

        return $log;
    }

    /**
     * blog.comment_reply — a staff reply to a guest blog comment via the
     * admin moderation route emails (and logs) the original commenter.
     */
    public function test_blog_comment_reply_send_is_logged(): void
    {
        $admin = $this->makeSuperAdmin();

        $post = BlogPost::create([
            'title'          => 'A Replied-To Post',
            'slug'           => 'a-replied-to-post-' . Str::random(6),
            'body_html'      => '<p>Hello</p>',
            'status'         => 'published',
            'published_at'   => now(),
            'allow_comments' => true,
        ]);

        // A guest comment (no author_id) so notifyOriginalCommenter takes the
        // email branch, not the in-app UserNotification branch.
        $comment = BlogComment::create([
            'post_id'      => $post->id,
            'author_type'  => 'guest',
            'author_name'  => 'Guest Reader',
            'author_email' => 'guest@example.com',
            'body'         => 'Any thoughts on part two?',
            'status'       => 'approved',
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/blogs/comments/' . $comment->id . '/reply', [
                'body' => 'Thanks for reading — part two lands next week!',
            ])->assertRedirect();

        $log = $this->emailLogFor('blog.comment_reply');
        $this->assertSame('guest@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        // The site passes ['related' => $post]; the log must carry it.
        $this->assertSame((string) $post->id, (string) $log->related_id);
        $this->assertStringContainsString('BlogPost', (string) $log->related_type);
    }

    /**
     * newsletter.test — the "send myself a test" action emails (and logs)
     * the current draft to the logged-in admin. Exercises the
     * opts-supplied subject/body (html) shape with a `user` association.
     */
    public function test_newsletter_test_send_is_logged(): void
    {
        $admin = $this->makeSuperAdmin();
        // Keep this independent of any other newsletter-test in the process.
        RateLimiter::clear('newsletter-test:' . $admin->id);

        $this->actingAs($admin, 'admin')->post('/admin/newsletter/send-test', [
            'subject'   => 'Our monthly roundup',
            'body_html' => '<h1>Hello</h1><p>Here is what is new.</p>',
        ])->assertRedirect();

        $log = $this->emailLogFor('newsletter.test');
        $this->assertSame($admin->email, $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('html', $log->format);
        // The '[TEST] ' prefix is added by the controller before sending.
        $this->assertStringContainsString('[TEST]', (string) $log->subject);
    }

    /**
     * subscriber.broadcast — a creator's email blast to their active email
     * subscribers emails (and logs) each recipient. Exercises the
     * caller-supplied subject/body (html) with a `user` association.
     */
    public function test_subscriber_broadcast_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'Broadcast Owner',
            'email' => 'bcast' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $owner->ownedWorkspaces()->first();

        // workspace_id is not in Subscriber::$fillable, so set it directly —
        // otherwise it lands NULL and the workspace-scoped recipient query in
        // SubscriberController::send (run with current_workspace bound) skips it.
        $sub = new Subscriber([
            'user_id'       => $owner->id,
            'type'          => 'email',
            'email'         => 'fan@example.com',
            'status'        => 'active',
            'subscribed_at' => now(),
        ]);
        $sub->workspace_id = $ws->id;
        $sub->save();

        session(['active_workspace_id' => $ws->id]);

        $this->actingAs($owner)->post('/user/subscribers/send', [
            'channel'     => 'email',
            'subject'     => 'A new drop is live',
            'body'        => 'Check out the latest — link in bio.',
            'filter_type' => 'email',
        ])->assertRedirect();

        $log = $this->emailLogFor('subscriber.broadcast');
        $this->assertSame('fan@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('html', $log->format);
        $this->assertSame($owner->id, $log->user_id);
    }

    /**
     * inbox.reply — replying to a (non-DM) unified-inbox thread that has a
     * usable sender email dispatches the reply over email and logs it.
     */
    public function test_inbox_reply_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'Inbox Owner',
            'email' => 'inbox' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $owner->ownedWorkspaces()->first();

        $thread = InboxThread::create([
            'workspace_id'    => $ws->id,
            'user_id'         => $owner->id,
            'source_type'     => 'form_submission',
            'source_id'       => 1,
            'channel'         => 'email',
            'subject'         => 'Question about your services',
            'sender_name'     => 'Curious Lead',
            'sender_email'    => 'lead@example.com',
            'category'        => 'lead',
            'status'          => 'open',
            'last_message_at' => now(),
        ]);

        session(['active_workspace_id' => $ws->id]);

        $this->actingAs($owner)
            ->post('/user/inbox/unified/' . $thread->id . '/reply', [
                'body' => 'Happy to help — here are the details you asked for.',
            ])->assertRedirect();

        $log = $this->emailLogFor('inbox.reply');
        $this->assertSame('lead@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame($owner->id, (int) $log->user_id);
    }

    /**
     * inbox.forward — the forwarding-rule "send test" delivers a synthetic
     * form_submission payload to an email destination, which emails (and
     * logs) the configured address. Driven through the shared
     * {@see InboxForwarder} the web + API routes both delegate to.
     */
    public function test_inbox_forward_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'Forward Owner',
            'email' => 'fwd' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $owner->ownedWorkspaces()->first();

        $dest = InboxForwardDestination::create([
            'user_id'      => $owner->id,
            'workspace_id' => $ws->id,
            'label'        => 'My assistant',
            'type'         => 'email',
            'target'       => 'assistant@example.com',
            'is_active'    => true,
        ]);

        app(InboxForwarder::class)->sendTest($dest);

        $log = $this->emailLogFor('inbox.forward');
        $this->assertSame('assistant@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('text', $log->format);
        // The site passes ['related' => $dest]; the log must carry it.
        $this->assertSame((string) $dest->id, (string) $log->related_id);
        $this->assertStringContainsString('InboxForwardDestination', (string) $log->related_type);
    }

    /**
     * follower.instant_update — publishing a new post pings followers whose
     * updates mode is 'instant' by email (and logs each send).
     */
    public function test_follower_instant_update_send_is_logged(): void
    {
        $creator = User::factory()->create([
            'name'  => 'The Creator',
            'email' => 'creator' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $creator->ownedWorkspaces()->first();

        $follower = User::factory()->create([
            'name'                  => 'Loyal Follower',
            'email'                 => 'follower' . Str::random(6) . '@example.com',
            'follower_updates_mode' => 'instant',
        ]);

        // Scope the follow to the creator's active workspace so the
        // workspace-scoped Follow query inside notifyFollowersDebounced
        // (run with current_workspace bound during the HTTP post) sees it.
        // workspace_id is not fillable on Follow, so set it directly or it
        // lands NULL and the scoped query skips this follower.
        $follow = new Follow([
            'follower_id' => $follower->id,
            'creator_id'  => $creator->id,
            'created_at'  => now(),
        ]);
        $follow->workspace_id = $ws->id;
        $follow->save();

        session(['active_workspace_id' => $ws->id]);

        $this->actingAs($creator)->post('/user/posts', [
            'body' => 'Just dropped something new — take a look!',
        ])->assertRedirect();

        $log = $this->emailLogFor('follower.instant_update');
        $this->assertSame($follower->email, $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame($follower->id, (int) $log->user_id);
    }

    /**
     * client_portal.magic_link (API) — the mobile/API mirror of the web
     * magic-link send (already covered on the web side in
     * {@see MigratedEmailLoggingTest}). Exercises the opts-supplied
     * subject/body (text) shape via a sanctum bearer token.
     */
    public function test_api_client_portal_magic_link_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'API Portal Owner',
            'email' => 'apiowner' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $owner->ownedWorkspaces()->first();

        $portal = ClientPortal::create([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'name'               => 'Acme API Portal',
            'brand_name'         => 'Acme',
            'is_enabled'         => true,
        ]);

        session(['active_workspace_id' => $ws->id]);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->postJson('/api/v1/client-portals/' . $portal->id . '/links', [
                'email'      => 'apiclient@example.com',
                'expires_in' => 14,
            ])->assertStatus(201);

        $log = $this->emailLogFor('client_portal.magic_link');
        $this->assertSame('apiclient@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('text', $log->format);
    }
}
