<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\BlogComment;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Positive coverage for the outbound emails that Task #4740 rerouted from raw
 * Mail::* sends onto the central {@see \App\Modules\Common\Services\Emailer}
 * pipeline so they land in `email_logs`.
 *
 * {@see DirectMailSendAllowlistTest} already proves (negatively) that no app/
 * file still bypasses Emailer. This file is the missing positive half: it
 * actually triggers a representative subset of the migrated call sites through
 * their real HTTP entry points and asserts each one writes an `email_logs` row
 * keyed by the expected EmailTemplateRegistry key. Without this, a refactor
 * could quietly drop the Emailer call at one of these sites (falling back to a
 * silent no-send or a direct facade send caught later) and the allowlist test
 * would still be green while the admin email history silently lost that type.
 *
 * The keys covered here span the distinct wiring shapes used by the migrated
 * sites, so a regression in any of the shared paths is caught:
 *   - contact.relay        — token-rendered inline body + `related` model
 *   - account.credentials  — token-rendered inline body + `user` association
 *   - blog.comment_approved — token-rendered inline body, admin-guard trigger
 *   - client_portal.magic_link — caller-supplied subject/body (opts), text fmt
 *   - form.notification    — caller-supplied subject/body via a public submit
 *
 * phpunit.xml pins MAIL_MAILER=array, so every send is captured by the
 * in-memory transport (no real socket) yet still flows through the full
 * dispatch → writeLog path, producing a real `email_logs` row with status
 * 'sent'. We therefore assert on `email_logs` rows, NOT Mail::fake() counts
 * (which never record Emailer's Mail::html/raw sends).
 */
class MigratedEmailLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SitePageController::submitContact keys its in-process rate limit on
        // the request IP; tests share 127.0.0.1, so clear the bucket to keep
        // this test independent of any other contact-form test in the process.
        RateLimiter::clear('contact:127.0.0.1');
    }

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
     * contact.relay — POST /contact with an admin recipient configured relays
     * the visitor's message and logs it against the ContactMessage.
     */
    public function test_contact_relay_send_is_logged(): void
    {
        $page = SitePage::firstOrNew(['slug' => 'contact']);
        $page->fill([
            'title'            => 'Contact us',
            'meta_description' => 'Seeded contact page.',
            'sections'         => SitePagesContent::contactSectionsDefault(),
            'extra'            => SitePagesContent::contactExtraDefault(),
        ])->save();

        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        $this->from('/contact')->post('/contact', [
            'name'    => 'Alice Example',
            'email'   => 'alice@example.com',
            'subject' => 'Partnership inquiry',
            'message' => 'Would love to chat about an integration.',
        ])->assertRedirect('/contact')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_messages', 1);
        $msg = ContactMessage::firstOrFail();

        $log = $this->emailLogFor('contact.relay');
        $this->assertSame('inbox@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        // The migrated site passes ['related' => $msg]; the log must carry it so
        // the admin can trace a relayed email back to its inbox message.
        $this->assertSame((string) $msg->id, (string) $log->related_id);
        $this->assertStringContainsString('ContactMessage', (string) $log->related_type);
    }

    /**
     * account.credentials — creating a user via the admin panel without an
     * explicit password emails (and logs) their generated credentials.
     */
    public function test_account_credentials_send_is_logged(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'admin')->post('/admin/users', [
            'name'        => 'New Member',
            'email'       => 'member@example.com',
            'send_invite' => '1',
        ])->assertRedirect();

        $user = User::where('email', 'member@example.com')->firstOrFail();

        $log = $this->emailLogFor('account.credentials');
        $this->assertSame('member@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        // The site associates the recipient user; the log must record it.
        $this->assertSame($user->id, $log->user_id);
    }

    /**
     * blog.comment_approved — approving a previously-pending guest comment via
     * the admin moderation route emails (and logs) the author.
     */
    public function test_blog_comment_approved_send_is_logged(): void
    {
        $admin = $this->makeSuperAdmin();

        $post = BlogPost::create([
            'title'          => 'A Logged Post',
            'slug'           => 'a-logged-post-' . Str::random(6),
            'body_html'      => '<p>Hello</p>',
            'status'         => 'published',
            'published_at'   => now(),
            'allow_comments' => true,
        ]);

        // A guest comment (no author_id) so notifyApproval takes only the email
        // branch, not the in-app UserNotification branch.
        $comment = BlogComment::create([
            'post_id'      => $post->id,
            'author_type'  => 'guest',
            'author_name'  => 'Guest Reader',
            'author_email' => 'guest@example.com',
            'body'         => 'Great write-up!',
            'status'       => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/blogs/comments/' . $comment->id, ['action' => 'approve'])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame('approved', $comment->status);

        $log = $this->emailLogFor('blog.comment_approved');
        $this->assertSame('guest@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
    }

    /**
     * client_portal.magic_link — sending a portal access link from the owner UI
     * emails (and logs) the recipient. Exercises the opts-supplied subject/body
     * shape (text format) rather than a registry-rendered body.
     */
    public function test_client_portal_magic_link_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'Portal Owner',
            'email' => 'owner' . Str::random(6) . '@example.com',
        ]);
        /** @var Workspace $ws */
        $ws = $owner->ownedWorkspaces()->first();

        $portal = ClientPortal::create([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'name'               => 'Acme Portal',
            'brand_name'         => 'Acme',
            'is_enabled'         => true,
        ]);

        session(['active_workspace_id' => $ws->id]);

        $this->actingAs($owner)
            ->post('/user/client-portals/' . $portal->id . '/links', [
                'email'      => 'client@example.com',
                'expires_in' => 14,
            ])->assertRedirect();

        $log = $this->emailLogFor('client_portal.magic_link');
        $this->assertSame('client@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('text', $log->format);
    }

    /**
     * form.notification — a public form submission with owner-email
     * notifications enabled emails (and logs) the configured recipient.
     */
    public function test_form_notification_send_is_logged(): void
    {
        $owner = User::factory()->create([
            'name'  => 'Form Owner',
            'email' => 'formowner' . Str::random(6) . '@example.com',
        ]);

        $notifications = Form::defaultNotifications();
        $notifications['email']['enabled'] = true;
        $notifications['email']['to']      = 'owner-inbox@example.com';
        $notifications['email']['config_id'] = null; // use the default (array) mailer

        $form = $owner->forms()->create([
            'slug'          => 'logged-form-' . Str::lower(Str::random(6)),
            'title'         => 'Logged Form',
            'fields'        => [
                ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => false],
                ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => false],
            ],
            'settings'      => Form::defaultSettings(),
            'notifications' => $notifications,
            'is_active'     => true,
        ]);

        $this->post('/f/' . $form->slug, [
            'email'   => 'visitor@example.com',
            'message' => 'Hello from a visitor.',
        ])->assertRedirect();

        $log = $this->emailLogFor('form.notification');
        $this->assertSame('owner-inbox@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame($owner->id, $log->user_id);
    }
}
