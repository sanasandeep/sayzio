<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use App\Modules\Common\Models\NotificationBroadcast;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards multi-user targeting for admin broadcasts.
 *
 * Ensures that the "user" audience can resolve a comma/newline-delimited
 * mix of emails and numeric IDs into the correct unique recipient set.
 */
class BroadcastMultiUserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin'],
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function sendBroadcast(Admin $admin, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'admin')->post(route('admin.notifications.send'), array_merge([
            'subject'      => 'Test broadcast',
            'body'         => 'Hello there.',
            'target_kind'  => 'user',
            'target_value' => '',
        ], $overrides));
    }

    public function test_multi_email_and_id_mix_delivers_to_all_matched_users(): void
    {
        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);
        $u3 = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        $target = implode(', ', [
            $u1->email,
            (string) $u2->id,
            $u3->email,
            $inactive->email,
        ]);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertRedirect(route('admin.notifications.index'));
        $resp->assertSessionHas('success');

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertSame(3, $broadcast->recipients_count);

        $notifiedIds = UserNotification::where('type', 'system_broadcast')
            ->pluck('user_id')
            ->sort()
            ->values()
            ->toArray();

        $this->assertEqualsCanonicalizing([$u1->id, $u2->id, $u3->id], $notifiedIds);
    }

    public function test_duplicates_are_deduplicated(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $target = implode("\n", [
            $user->email,
            strtoupper($user->email),
            (string) $user->id,
        ]);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertRedirect(route('admin.notifications.index'));

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertSame(1, $broadcast->recipients_count);

        $this->assertSame(
            1,
            UserNotification::where('type', 'system_broadcast')
                ->where('user_id', $user->id)
                ->count(),
        );
    }

    public function test_single_value_still_works(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $user->email]);

        $resp->assertRedirect(route('admin.notifications.index'));

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertSame(1, $broadcast->recipients_count);
    }

    public function test_no_match_returns_validation_error(): void
    {
        $resp = $this->sendBroadcast($this->admin(), ['target_value' => 'nobody@example.com, 99999999']);

        $resp->assertSessionHasErrors('target_value');
    }

    public function test_invalid_tokens_never_broadcast_to_everyone(): void
    {
        User::factory()->count(3)->create(['status' => 'active']);

        // Tokens that are neither emails nor numeric IDs must resolve to
        // ZERO recipients — never fall through to "all active users".
        $resp = $this->sendBroadcast($this->admin(), ['target_value' => 'abc, foo-bar']);

        $resp->assertSessionHasErrors('target_value');
        $this->assertSame(0, UserNotification::where('type', 'system_broadcast')->count());
        $this->assertSame(0, NotificationBroadcast::count());
    }

    public function test_empty_target_value_is_rejected(): void
    {
        $resp = $this->sendBroadcast($this->admin(), ['target_value' => '']);

        $resp->assertSessionHasErrors('target_value');
    }

    public function test_newline_separated_emails_work(): void
    {
        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);

        $target = $u1->email . "\n" . $u2->email;

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertRedirect(route('admin.notifications.index'));

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertSame(2, $broadcast->recipients_count);
    }

    public function test_all_recipients_opted_out_is_success_not_validation_error(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        \App\Modules\User\Models\NotificationPreference::create([
            'user_id' => $user->id,
            'type'    => 'system_broadcast',
            'in_app'  => false,
        ]);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $user->email]);

        // The target resolved to a real user who muted broadcasts — that
        // is a valid zero-delivery send, not an input error.
        $resp->assertRedirect(route('admin.notifications.index'));
        $resp->assertSessionHasNoErrors();

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertNotNull($broadcast);
        $this->assertSame(0, $broadcast->recipients_count);
    }

    public function test_flash_reports_unmatched_entries(): void
    {
        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);

        $target = implode(', ', [
            $u1->email,
            $u2->email,
            'missing@example.com',
            '99999999',
        ]);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertRedirect(route('admin.notifications.index'));
        $resp->assertSessionHas('success', 'Broadcast sent to 2 users. (2 entries not found)');
    }

    public function test_flash_reports_single_unmatched_entry(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $resp = $this->sendBroadcast($this->admin(), [
            'target_value' => $user->email . ', typo@example.com',
        ]);

        $resp->assertRedirect(route('admin.notifications.index'));
        $resp->assertSessionHas('success', 'Broadcast sent to 1 user. (1 entry not found)');
    }

    public function test_flash_has_no_unmatched_suffix_when_all_entries_match(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $user->email]);

        $resp->assertSessionHas('success', 'Broadcast sent to 1 user.');
    }

    public function test_duplicate_entries_do_not_count_as_unmatched(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $target = $user->email . "\n" . strtoupper($user->email);

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertSessionHas('success', 'Broadcast sent to 1 user.');
    }

    public function test_long_target_list_persists_without_truncation(): void
    {
        $users  = User::factory()->count(5)->create(['status' => 'active']);
        $target = $users->pluck('email')->implode(', ') . ', ' . str_repeat('longpadding@example.com, ', 10) . $users->first()->email;

        $this->assertGreaterThan(120, strlen($target));

        $resp = $this->sendBroadcast($this->admin(), ['target_value' => $target]);

        $resp->assertRedirect(route('admin.notifications.index'));

        $broadcast = NotificationBroadcast::latest('id')->first();
        $this->assertSame(5, $broadcast->recipients_count);
        $this->assertSame($target, $broadcast->target_value);
    }
}
