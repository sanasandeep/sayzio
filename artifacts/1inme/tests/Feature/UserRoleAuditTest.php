<?php

namespace Tests\Feature;

use App\Mail\PlatformRoleAttachedAlertMail;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the audit ledger behind the "User access" page and the
 * back-office user-roles editor. The two surfaces share one logger
 * service, so we exercise the diff logic plus both controller
 * endpoints to make sure neither path silently skips a write.
 */
class UserRoleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    private function makeRole(string $slug, string $guard = 'web'): Role
    {
        return Role::create([
            'name'  => ucfirst($slug),
            'slug'  => $slug . '-' . Str::random(4),
            'guard' => $guard,
        ]);
    }

    public function test_logger_writes_one_row_per_attached_and_detached_role(): void
    {
        $target = $this->makeUser();
        $roleA  = $this->makeRole('alpha');
        $roleB  = $this->makeRole('beta');
        $roleC  = $this->makeRole('gamma');

        // Pretend target started with A & B and now has B & C — so A
        // is detached and C is attached. B is unchanged.
        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$roleA->id, $roleB->id],
            [$roleB->id, $roleC->id],
            UserRoleAudit::SOURCE_USER_ACCESS,
            '203.0.113.7',
        );

        $this->assertSame(2, UserRoleAudit::where('target_user_id', $target->id)->count());

        $this->assertDatabaseHas('user_role_audits', [
            'target_user_id' => $target->id,
            'role_id'        => $roleA->id,
            'action'         => 'detached',
            'source'         => 'user_access',
            'ip'             => '203.0.113.7',
        ]);
        $this->assertDatabaseHas('user_role_audits', [
            'target_user_id' => $target->id,
            'role_id'        => $roleC->id,
            'action'         => 'attached',
            'source'         => 'user_access',
        ]);
    }

    public function test_logger_is_a_no_op_when_role_set_unchanged(): void
    {
        $target = $this->makeUser();
        $role   = $this->makeRole('same');

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$role->id],
            [$role->id],
            UserRoleAudit::SOURCE_ADMIN,
        );

        $this->assertSame(0, UserRoleAudit::count());
    }

    public function test_user_access_update_records_actor_on_web_guard(): void
    {
        // Operator with `user.roles.manage` (which is bundled into the
        // user-admin role seeded by the migration).
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Op One']);
        $operator->roles()->attach($userAdminRole->id);

        $target = $this->makeUser(['name' => 'Promoted Pat']);
        $newRole = $this->makeRole('writer');

        $this->actingAs($operator, 'web')
            ->post(route('user.access.users.update', $target), [
                'role_ids' => [$newRole->id],
            ])
            ->assertRedirect();

        $row = UserRoleAudit::where('target_user_id', $target->id)
            ->where('role_id', $newRole->id)
            ->firstOrFail();

        $this->assertSame('attached', $row->action);
        $this->assertSame('user_access', $row->source);
        $this->assertSame('web', $row->actor_guard);
        $this->assertSame((int) $operator->id, (int) $row->actor_user_id);
        $this->assertNull($row->actor_admin_id);
        $this->assertSame('Op One', $row->actor_name);
    }

    public function test_user_detail_page_hides_role_audits_from_view_only_admins(): void
    {
        // A "Support" admin with `users.view` but NOT `users.edit`
        // should see the user-detail page render, but the role-change
        // audit panel and its data must not be present.
        $supportRole = Role::create([
            'name'  => 'Support ' . Str::random(4),
            'slug'  => 'support-' . Str::random(4),
            'guard' => 'admin',
        ]);
        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'users.view'],
            ['name' => 'View Users', 'group' => 'users'],
        );
        $supportRole->permissions()->attach($viewPerm->id);

        $admin = Admin::create([
            'name'     => 'Read Only Riley',
            'email'    => 'riley@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $supportRole->id,
            'status'   => 'active',
        ]);

        $target  = $this->makeUser(['name' => 'Subject Sam']);
        $oldRole = $this->makeRole('historical');
        UserRoleAudit::create([
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => 'Some Past Operator',
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => $oldRole->id,
            'role_slug'      => $oldRole->slug,
            'role_name'      => $oldRole->name,
            'action'         => 'attached',
            'source'         => 'admin',
            'ip'             => '203.0.113.9',
            'created_at'     => now(),
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $target))
            ->assertOk();

        $resp->assertDontSee('Role change history');
        $resp->assertDontSee('Some Past Operator');
    }

    public function test_deleting_user_records_detached_rows_for_each_attached_role(): void
    {
        // The pivot has cascadeOnDelete(), so the DB would silently
        // strip these rows the moment the user is gone. The model's
        // `deleting` hook must capture them first or reviewers see an
        // attach with no matching detach when investigating the
        // removed admin.
        $target = $this->makeUser(['name' => 'Doomed Dan']);
        $roleA  = $this->makeRole('alpha');
        $roleB  = $this->makeRole('beta');
        $target->roles()->attach([$roleA->id, $roleB->id]);

        // Sanity: clear out the 'attached' rows the controllers would
        // normally have written so this test only asserts the new
        // cascade-driven detach behavior.
        UserRoleAudit::query()->where('target_user_id', $target->id)->delete();

        $target->delete();

        $this->assertSame(2, UserRoleAudit::where('target_user_id', $target->id)->count());

        foreach ([$roleA, $roleB] as $role) {
            $this->assertDatabaseHas('user_role_audits', [
                'target_user_id' => $target->id,
                'role_id'        => $role->id,
                'role_slug'      => $role->slug,
                'action'         => 'detached',
                'source'         => 'user_deleted',
            ]);
        }
    }

    public function test_deleting_user_with_no_roles_writes_no_audit_rows(): void
    {
        $target = $this->makeUser(['name' => 'Lonely Lou']);

        $target->delete();

        $this->assertSame(0, UserRoleAudit::where('target_user_id', $target->id)->count());
    }

    public function test_deleting_role_records_detached_rows_for_each_user_that_held_it(): void
    {
        // Same cascade story on the role side: dropping a role nukes
        // every pivot row for it. The Role model's `deleting` hook
        // walks the pivot first so each affected user account gets
        // its 'detached' counterpart written through the same logger.
        $role = $this->makeRole('soon-gone');
        $u1 = $this->makeUser(['name' => 'User One']);
        $u2 = $this->makeUser(['name' => 'User Two']);
        $u1->roles()->attach($role->id);
        $u2->roles()->attach($role->id);

        UserRoleAudit::query()->whereIn('target_user_id', [$u1->id, $u2->id])->delete();

        $slugSnapshot = $role->slug;
        $role->delete();

        foreach ([$u1, $u2] as $user) {
            $this->assertDatabaseHas('user_role_audits', [
                'target_user_id' => $user->id,
                'role_slug'      => $slugSnapshot,
                'action'         => 'detached',
                'source'         => 'role_deleted',
            ]);
        }
        $this->assertSame(2, UserRoleAudit::where('source', 'role_deleted')->count());
    }

    public function test_admin_destroying_a_user_attributes_cascade_detach_to_that_admin(): void
    {
        // The actor on the audit row should be whoever called delete,
        // not 'System' — back-office operators want to see who
        // destroyed the account that took these roles with it.
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Destroyer Dana',
            'email'    => 'dana@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $target = $this->makeUser(['name' => 'Goodbye Greg']);
        $role   = $this->makeRole('keeper');
        $target->roles()->attach($role->id);
        UserRoleAudit::query()->where('target_user_id', $target->id)->delete();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect();

        $row = UserRoleAudit::where('source', 'user_deleted')
            ->where('role_id', $role->id)
            ->firstOrFail();

        $this->assertSame('detached', $row->action);
        $this->assertSame('admin', $row->actor_guard);
        $this->assertSame((int) $admin->id, (int) $row->actor_admin_id);
        $this->assertSame('Destroyer Dana', $row->actor_name);
    }

    public function test_attaching_sensitive_role_emails_ops_recipients(): void
    {
        // Default config picks up `user-admin` as a sensitive slug AND
        // every permission it carries (e.g. `user.platform.admin`),
        // so attaching the seeded user-admin role must trigger an alert.
        Mail::fake();

        // Recipient: a user holding `user.ops_alerts.receive`. With no
        // explicit recipient list configured, the service falls back
        // to this permission-based audience.
        $opsRole = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::random(4),
            'guard' => 'web',
        ]);
        $opsPerm = Permission::firstOrCreate(
            ['slug' => 'user.ops_alerts.receive'],
            ['name' => 'Receive operational alerts', 'group' => 'user-app'],
        );
        $opsRole->permissions()->attach($opsPerm->id);

        $opsUser = $this->makeUser(['name' => 'Ops Olivia', 'email' => 'olivia@ops.test']);
        $opsUser->roles()->attach($opsRole->id);
        $opsUser->flushPermissionCache();

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $target = $this->makeUser(['name' => 'Promoted Pat']);

        // Use the explicit recipient override path is empty; service
        // should fall back to the ops-permission audience.
        config(['platform_role_alerts.recipient_emails' => []]);

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$userAdminRole->id],
            UserRoleAudit::SOURCE_ADMIN,
            '198.51.100.7',
        );

        Mail::assertSent(PlatformRoleAttachedAlertMail::class, function ($m) use ($opsUser, $target, $userAdminRole) {
            return $m->hasTo($opsUser->email)
                && count($m->grants) === 1
                && $m->grants[0]['audit']->target_user_id === $target->id
                && $m->grants[0]['audit']->role_id === $userAdminRole->id
                && $m->grants[0]['audit']->action === UserRoleAudit::ACTION_ATTACHED
                && !empty($m->grants[0]['reasons']);
        });
    }

    public function test_attaching_non_sensitive_role_does_not_email(): void
    {
        Mail::fake();

        $target = $this->makeUser();
        $editor = $this->makeRole('editor'); // freshly created, no sensitive perms

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$editor->id],
            UserRoleAudit::SOURCE_USER_ACCESS,
        );

        Mail::assertNothingSent();
    }

    public function test_detaching_sensitive_role_does_not_email(): void
    {
        // Revokes are still ledger'd but they are NOT a privilege
        // escalation, so no ops alert should fire on detach.
        Mail::fake();

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $target = $this->makeUser();
        $target->roles()->attach($userAdminRole->id);

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$userAdminRole->id],
            [],
            UserRoleAudit::SOURCE_ADMIN,
        );

        Mail::assertNotSent(PlatformRoleAttachedAlertMail::class);
    }

    public function test_explicit_recipient_emails_override_ops_permission_holders(): void
    {
        Mail::fake();

        // An ops-permission holder exists but should be ignored once
        // an explicit recipient list is configured.
        $opsRole = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::random(4),
            'guard' => 'web',
        ]);
        $opsPerm = Permission::firstOrCreate(
            ['slug' => 'user.ops_alerts.receive'],
            ['name' => 'Receive operational alerts', 'group' => 'user-app'],
        );
        $opsRole->permissions()->attach($opsPerm->id);

        $opsUser = $this->makeUser(['name' => 'Ignored Iris', 'email' => 'iris@ops.test']);
        $opsUser->roles()->attach($opsRole->id);
        $opsUser->flushPermissionCache();

        config([
            'platform_role_alerts.recipient_emails' => ['security-team@example.com', 'on-call@example.com'],
        ]);

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $target = $this->makeUser(['name' => 'Sensitive Sam']);

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$userAdminRole->id],
            UserRoleAudit::SOURCE_ADMIN,
        );

        Mail::assertSent(PlatformRoleAttachedAlertMail::class, fn ($m) => $m->hasTo('security-team@example.com'));
        Mail::assertSent(PlatformRoleAttachedAlertMail::class, fn ($m) => $m->hasTo('on-call@example.com'));
        Mail::assertNotSent(PlatformRoleAttachedAlertMail::class, fn ($m) => $m->hasTo('iris@ops.test'));
    }

    public function test_custom_role_with_sensitive_permission_triggers_alert(): void
    {
        // A role with a slug nobody listed but which carries
        // `user.workspaces.access_any` should still trip the alert via
        // the permission-side check. This is the catch-all that
        // prevents quietly-elevated custom roles slipping past ops.
        Mail::fake();

        $custom = Role::create([
            'name'  => 'Quiet Custom ' . Str::random(4),
            'slug'  => 'custom-' . Str::random(6),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.workspaces.access_any'],
            ['name' => 'Access any workspace', 'group' => 'user-app'],
        );
        $custom->permissions()->attach($perm->id);

        config([
            'platform_role_alerts.recipient_emails' => ['ops@example.com'],
        ]);

        $target = $this->makeUser();

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$custom->id],
            UserRoleAudit::SOURCE_USER_ACCESS,
        );

        Mail::assertSent(PlatformRoleAttachedAlertMail::class, function ($m) use ($custom) {
            return $m->hasTo('ops@example.com')
                && count($m->grants) === 1
                && in_array('permission:user.workspaces.access_any', $m->grants[0]['reasons'], true)
                && $m->grants[0]['audit']->role_id === $custom->id;
        });
    }

    public function test_attaching_two_sensitive_roles_in_one_diff_sends_one_batched_email(): void
    {
        // Operator grants TWO platform-admin level roles in the same
        // save. The old code path emailed once per attached role per
        // recipient; the batched path collapses both grants into a
        // single multi-row email per recipient.
        Mail::fake();

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();

        // Second sensitive role: a custom role carrying a permission
        // listed in `sensitive_permission_slugs`. This exercises both
        // halves of the sensitivity rule (slug-listed AND permission-listed)
        // ending up in the same email.
        $custom = Role::create([
            'name'  => 'Quiet Custom ' . Str::random(4),
            'slug'  => 'custom-' . Str::random(6),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.workspaces.access_any'],
            ['name' => 'Access any workspace', 'group' => 'user-app'],
        );
        $custom->permissions()->attach($perm->id);

        config([
            'platform_role_alerts.recipient_emails' => ['security-team@example.com', 'on-call@example.com'],
        ]);

        $target = $this->makeUser(['name' => 'Doubly Promoted Pat']);

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$userAdminRole->id, $custom->id],
            UserRoleAudit::SOURCE_ADMIN,
            '198.51.100.9',
        );

        // Exactly two emails total: one per recipient, NOT one per
        // (recipient × attached role).
        Mail::assertSent(PlatformRoleAttachedAlertMail::class, 2);

        foreach (['security-team@example.com', 'on-call@example.com'] as $email) {
            Mail::assertSent(PlatformRoleAttachedAlertMail::class, function ($m) use ($email, $target, $userAdminRole, $custom) {
                if (!$m->hasTo($email)) {
                    return false;
                }
                if (count($m->grants) !== 2) {
                    return false;
                }
                $roleIds = array_map(fn ($g) => $g['audit']->role_id, $m->grants);
                sort($roleIds);
                $expected = [$userAdminRole->id, $custom->id];
                sort($expected);
                if ($roleIds !== $expected) {
                    return false;
                }
                // Both rows must point at the same target user, and
                // each grant must carry its own non-empty reasons.
                foreach ($m->grants as $g) {
                    if ($g['audit']->target_user_id !== $target->id) {
                        return false;
                    }
                    if ($g['audit']->action !== UserRoleAudit::ACTION_ATTACHED) {
                        return false;
                    }
                    if (empty($g['reasons'])) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Both audit rows should still be persisted independently.
        $this->assertSame(
            2,
            UserRoleAudit::where('target_user_id', $target->id)
                ->where('action', UserRoleAudit::ACTION_ATTACHED)
                ->count(),
        );
    }

    public function test_mixed_diff_only_batches_the_sensitive_attaches(): void
    {
        // A diff with one sensitive attach, one non-sensitive attach
        // and one detach should still email exactly once per recipient
        // and that email should ONLY mention the sensitive grant.
        Mail::fake();

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $editor        = $this->makeRole('editor');
        $oldRole       = $this->makeRole('previously');

        $target = $this->makeUser(['name' => 'Mixed Bag Mia']);
        $target->roles()->attach($oldRole->id);

        config([
            'platform_role_alerts.recipient_emails' => ['ops@example.com'],
        ]);

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$oldRole->id],
            [$userAdminRole->id, $editor->id],
            UserRoleAudit::SOURCE_USER_ACCESS,
        );

        Mail::assertSent(PlatformRoleAttachedAlertMail::class, 1);
        Mail::assertSent(PlatformRoleAttachedAlertMail::class, function ($m) use ($userAdminRole) {
            return $m->hasTo('ops@example.com')
                && count($m->grants) === 1
                && $m->grants[0]['audit']->role_id === $userAdminRole->id;
        });
    }

    public function test_alert_is_skipped_when_no_recipients_configured(): void
    {
        Mail::fake();

        // No explicit recipients AND no ops-permission holders exist
        // => the service should swallow the dispatch silently.
        config(['platform_role_alerts.recipient_emails' => []]);

        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $target = $this->makeUser();

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [],
            [$userAdminRole->id],
            UserRoleAudit::SOURCE_ADMIN,
        );

        // Audit row still written even though no email could be sent.
        $this->assertDatabaseHas('user_role_audits', [
            'target_user_id' => $target->id,
            'role_id'        => $userAdminRole->id,
            'action'         => 'attached',
        ]);
        Mail::assertNothingSent();
    }

    /**
     * Helper for the filter tests: seed one audit row per known
     * source value (plus a NULL/system row) all targeting the same
     * user, so each test can assert which rows survive a filter.
     *
     * @return array{User, array<string,UserRoleAudit>}
     */
    private function seedFourSources(): array
    {
        $target = $this->makeUser(['name' => 'Filter Target']);
        $role   = $this->makeRole('filterable');

        $base = [
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => $role->id,
            'role_slug'      => $role->slug,
            'role_name'      => $role->name,
            'action'         => 'attached',
        ];

        $rows = [
            'user_access' => UserRoleAudit::create($base + [
                'actor_name'  => 'Self-Service Sue',
                'source'      => UserRoleAudit::SOURCE_USER_ACCESS,
                'created_at'  => now()->subMinutes(4),
            ]),
            'admin' => UserRoleAudit::create($base + [
                'actor_name'  => 'Back-Office Bob',
                'source'      => UserRoleAudit::SOURCE_ADMIN,
                'created_at'  => now()->subMinutes(3),
            ]),
            'backfill' => UserRoleAudit::create($base + [
                'actor_name'  => 'Backfill Bot',
                'source'      => UserRoleAudit::SOURCE_BACKFILL,
                'created_at'  => now()->subMinutes(2),
            ]),
            'system' => UserRoleAudit::create($base + [
                'actor_name'  => 'CLI Seeder',
                'source'      => null,
                'created_at'  => now()->subMinute(),
            ]),
        ];

        return [$target, $rows];
    }

    public function test_source_filter_scope_returns_exact_match_for_named_sources(): void
    {
        [$target] = $this->seedFourSources();

        $userAccessRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter(UserRoleAudit::SOURCE_USER_ACCESS)
            ->get();
        $this->assertSame(1, $userAccessRows->count());
        $this->assertSame('Self-Service Sue', $userAccessRows->first()->actor_name);

        $adminRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter(UserRoleAudit::SOURCE_ADMIN)
            ->get();
        $this->assertSame(1, $adminRows->count());
        $this->assertSame('Back-Office Bob', $adminRows->first()->actor_name);

        $backfillRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter(UserRoleAudit::SOURCE_BACKFILL)
            ->get();
        $this->assertSame(1, $backfillRows->count());
        $this->assertSame('Backfill Bot', $backfillRows->first()->actor_name);
    }

    public function test_source_filter_system_returns_only_null_source_rows(): void
    {
        [$target] = $this->seedFourSources();

        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter(UserRoleAudit::FILTER_SYSTEM)
            ->get();

        $this->assertSame(1, $rows->count());
        $this->assertNull($rows->first()->source);
        $this->assertSame('CLI Seeder', $rows->first()->actor_name);
    }

    public function test_source_filter_not_backfill_excludes_only_backfill_rows(): void
    {
        [$target] = $this->seedFourSources();

        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter(UserRoleAudit::FILTER_NOT_BACKFILL)
            ->orderBy('created_at')
            ->get();

        // Should keep user_access, admin, and the NULL/system row but
        // drop the single backfill row.
        $this->assertSame(3, $rows->count());
        $this->assertSame(
            ['user_access', 'admin', null],
            $rows->pluck('source')->all(),
        );
        foreach ($rows as $r) {
            $this->assertNotSame(UserRoleAudit::SOURCE_BACKFILL, $r->source);
        }
    }

    public function test_source_filter_ignores_unknown_value_and_returns_everything(): void
    {
        [$target] = $this->seedFourSources();

        // Unknown / typo'd filter value should normalise to null and
        // therefore NOT restrict the query.
        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->bySourceFilter('garbage-value')
            ->get();

        $this->assertSame(4, $rows->count());
    }

    public function test_user_access_page_applies_audit_source_filter_from_query_string(): void
    {
        // Operator with `user.roles.manage` so they can hit the page.
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Audit Viewer']);
        $operator->roles()->attach($userAdminRole->id);

        $this->seedFourSources();

        // Filter to backfill only — the page should show the
        // Backfill Bot row but hide the others.
        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.index', ['audit_source' => 'backfill']))
            ->assertOk();

        $resp->assertSee('Backfill Bot');
        $resp->assertDontSee('Self-Service Sue');
        $resp->assertDontSee('Back-Office Bob');
        $resp->assertDontSee('CLI Seeder');

        // "Hide backfilled" should drop only the Backfill Bot row.
        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.index', ['audit_source' => 'not_backfill']))
            ->assertOk();

        $resp->assertSee('Self-Service Sue');
        $resp->assertSee('Back-Office Bob');
        $resp->assertSee('CLI Seeder');
        $resp->assertDontSee('Backfill Bot');
    }

    public function test_admin_user_show_applies_audit_source_filter_from_query_string(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Filter Admin',
            'email'    => 'fa@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        [$target] = $this->seedFourSources();

        // System / CLI filter — only the NULL-source row should
        // remain in the rendered timeline.
        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', ['user' => $target, 'audit_source' => 'system']))
            ->assertOk();

        $resp->assertSee('CLI Seeder');
        $resp->assertDontSee('Self-Service Sue');
        $resp->assertDontSee('Back-Office Bob');
        $resp->assertDontSee('Backfill Bot');
    }

    public function test_admin_role_edit_applies_audit_source_filter_from_query_string(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Roles Filter Admin',
            'email'    => 'rfa@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        [$target] = $this->seedFourSources();

        // Admin source filter — only Back-Office Bob's row should
        // appear in the per-user history.
        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.roles.edit', ['user' => $target, 'audit_source' => 'admin']))
            ->assertOk();

        $resp->assertSee('Back-Office Bob');
        $resp->assertDontSee('Self-Service Sue');
        $resp->assertDontSee('Backfill Bot');
        $resp->assertDontSee('CLI Seeder');
    }

    public function test_admin_role_update_records_actor_on_admin_guard(): void
    {
        // Seed a super-admin Admin (role lookup is permissive — slug
        // 'super-admin' grants every permission).
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Back Office Bob',
            'email'    => 'bob@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $target = $this->makeUser(['name' => 'Demoted Dee']);
        $oldRole = $this->makeRole('previously');
        $target->roles()->attach($oldRole->id);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.users.roles.update', $target), [
                // Empty role_ids => detach everything.
                'role_ids' => [],
            ])
            ->assertRedirect();

        $row = UserRoleAudit::where('target_user_id', $target->id)
            ->where('role_id', $oldRole->id)
            ->firstOrFail();

        $this->assertSame('detached', $row->action);
        $this->assertSame('admin', $row->source);
        $this->assertSame('admin', $row->actor_guard);
        $this->assertNull($row->actor_user_id);
        $this->assertSame((int) $admin->id, (int) $row->actor_admin_id);
        $this->assertSame('Back Office Bob', $row->actor_name);
    }

    /**
     * Helper for the date-range tests: seed four audit rows for the
     * same target spaced across the last ~6 weeks (now-1h, now-3d,
     * now-2w, now-6w) so each preset has a clear inclusion boundary.
     *
     * @return array{User, array<string,UserRoleAudit>}
     */
    private function seedFourAcrossDates(): array
    {
        $target = $this->makeUser(['name' => 'Range Target']);
        $role   = $this->makeRole('rangetest');

        $base = [
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => $role->id,
            'role_slug'      => $role->slug,
            'role_name'      => $role->name,
            'action'         => 'attached',
            'source'         => UserRoleAudit::SOURCE_ADMIN,
        ];

        $rows = [
            'recent'   => UserRoleAudit::create($base + [
                'actor_name' => 'Recent Rachel',
                'created_at' => now()->subHour(),
            ]),
            'lastWeek' => UserRoleAudit::create($base + [
                'actor_name' => 'Last-Week Larry',
                'created_at' => now()->subDays(3),
            ]),
            'lastMonth' => UserRoleAudit::create($base + [
                'actor_name' => 'Last-Month Mona',
                'created_at' => now()->subWeeks(2),
            ]),
            'old'      => UserRoleAudit::create($base + [
                'actor_name' => 'Ancient Alex',
                'created_at' => now()->subWeeks(6),
            ]),
        ];

        return [$target, $rows];
    }

    public function test_range_preset_24h_keeps_only_rows_within_the_last_day(): void
    {
        [$target] = $this->seedFourAcrossDates();

        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(UserRoleAudit::RANGE_24H)
            ->get();

        $this->assertSame(1, $rows->count());
        $this->assertSame('Recent Rachel', $rows->first()->actor_name);
    }

    public function test_range_preset_7d_keeps_rows_within_the_last_week(): void
    {
        [$target] = $this->seedFourAcrossDates();

        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(UserRoleAudit::RANGE_7D)
            ->orderBy('created_at')
            ->get();

        $this->assertSame(2, $rows->count());
        $this->assertSame(
            ['Last-Week Larry', 'Recent Rachel'],
            $rows->pluck('actor_name')->all(),
        );
    }

    public function test_range_preset_30d_keeps_rows_within_the_last_month(): void
    {
        [$target] = $this->seedFourAcrossDates();

        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(UserRoleAudit::RANGE_30D)
            ->orderBy('created_at')
            ->get();

        // Drops only the 6-week-old "Ancient Alex" row.
        $this->assertSame(3, $rows->count());
        $this->assertFalse($rows->pluck('actor_name')->contains('Ancient Alex'));
    }

    public function test_range_preset_all_and_unknown_value_apply_no_constraint(): void
    {
        [$target] = $this->seedFourAcrossDates();

        $allRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(UserRoleAudit::RANGE_ALL)
            ->get();
        $this->assertSame(4, $allRows->count());

        $bogusRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates('totally-bogus')
            ->get();
        $this->assertSame(4, $bogusRows->count());

        $nullRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(null)
            ->get();
        $this->assertSame(4, $nullRows->count());
    }

    public function test_range_explicit_from_to_inputs_are_inclusive_and_skip_unparsable(): void
    {
        [$target] = $this->seedFourAcrossDates();

        // From three days ago up through today should keep just the
        // two most recent rows (now-1h and now-3d). `to` defaults to
        // end-of-day so a same-day "now-1h" row is included.
        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(
                null,
                now()->subDays(3)->startOfDay()->toDateString(),
                now()->toDateString(),
            )
            ->orderBy('created_at')
            ->get();

        $this->assertSame(2, $rows->count());
        $this->assertSame(
            ['Last-Week Larry', 'Recent Rachel'],
            $rows->pluck('actor_name')->all(),
        );

        // Garbage from/to should be silently ignored, not 500.
        $allRows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(null, 'definitely not a date', 'also nope')
            ->get();
        $this->assertSame(4, $allRows->count());
    }

    public function test_range_preset_and_explicit_from_to_compose_as_intersection(): void
    {
        [$target] = $this->seedFourAcrossDates();

        // 30-day preset alone keeps 3 rows; layering a `from` of
        // 5 days ago should narrow that to the 2 most recent.
        $rows = UserRoleAudit::query()
            ->where('target_user_id', $target->id)
            ->betweenDates(
                UserRoleAudit::RANGE_30D,
                now()->subDays(5)->toDateString(),
                null,
            )
            ->orderBy('created_at')
            ->get();

        $this->assertSame(2, $rows->count());
        $this->assertSame(
            ['Last-Week Larry', 'Recent Rachel'],
            $rows->pluck('actor_name')->all(),
        );
    }

    public function test_range_preset_normalisation_collapses_unknown_and_all_to_null(): void
    {
        $this->assertNull(UserRoleAudit::normaliseRangePreset(null));
        $this->assertNull(UserRoleAudit::normaliseRangePreset(''));
        $this->assertNull(UserRoleAudit::normaliseRangePreset('all'));
        $this->assertNull(UserRoleAudit::normaliseRangePreset('garbage'));
        $this->assertSame('24h', UserRoleAudit::normaliseRangePreset('24h'));
        $this->assertSame('7d', UserRoleAudit::normaliseRangePreset('7d'));
        $this->assertSame('30d', UserRoleAudit::normaliseRangePreset('30d'));
    }

    public function test_user_access_page_applies_audit_range_filter_from_query_string(): void
    {
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Range Viewer']);
        $operator->roles()->attach($userAdminRole->id);

        $this->seedFourAcrossDates();

        // 7d preset should drop the 2-week-old and 6-week-old rows.
        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.index', ['audit_range' => '7d']))
            ->assertOk();

        $resp->assertSee('Recent Rachel');
        $resp->assertSee('Last-Week Larry');
        $resp->assertDontSee('Last-Month Mona');
        $resp->assertDontSee('Ancient Alex');
    }

    public function test_user_access_page_applies_audit_range_chip_alongside_source_chip(): void
    {
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Range Viewer Combo']);
        $operator->roles()->attach($userAdminRole->id);

        // Seed a 4-source spread (all "minutes ago") plus an old
        // admin-source row from 6 weeks back. Combining `audit_source=admin`
        // with `audit_range=7d` should keep the recent admin row but
        // drop both the old admin row and the recent non-admin rows.
        [$target] = $this->seedFourSources();
        UserRoleAudit::create([
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => 'Old Admin Row',
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => null,
            'role_slug'      => 'oldadmin',
            'role_name'      => 'Old Admin',
            'action'         => 'attached',
            'source'         => UserRoleAudit::SOURCE_ADMIN,
            'created_at'     => now()->subWeeks(6),
        ]);

        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.index', [
                'audit_source' => 'admin',
                'audit_range'  => '7d',
            ]))
            ->assertOk();

        $resp->assertSee('Back-Office Bob');
        $resp->assertDontSee('Old Admin Row');
        $resp->assertDontSee('Self-Service Sue');
        $resp->assertDontSee('Backfill Bot');
        $resp->assertDontSee('CLI Seeder');
    }

    public function test_admin_user_show_applies_audit_range_filter_from_query_string(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Range Admin',
            'email'    => 'ra@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        [$target] = $this->seedFourAcrossDates();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', ['user' => $target, 'audit_range' => '24h']))
            ->assertOk();

        $resp->assertSee('Recent Rachel');
        $resp->assertDontSee('Last-Week Larry');
        $resp->assertDontSee('Last-Month Mona');
        $resp->assertDontSee('Ancient Alex');
    }

    public function test_admin_role_edit_applies_audit_range_from_to_inputs(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Range From-To Admin',
            'email'    => 'rfta@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        [$target] = $this->seedFourAcrossDates();

        // From-to window centred on the 2-week-old row only.
        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.roles.edit', [
                'user'       => $target,
                'audit_from' => now()->subWeeks(3)->toDateString(),
                'audit_to'   => now()->subWeek()->toDateString(),
            ]))
            ->assertOk();

        $resp->assertSee('Last-Month Mona');
        $resp->assertDontSee('Recent Rachel');
        $resp->assertDontSee('Last-Week Larry');
        $resp->assertDontSee('Ancient Alex');
    }

    public function test_user_access_page_keeps_range_chip_visible_when_filter_returns_no_rows(): void
    {
        // Seeding nothing intentionally — the panel's empty state with
        // an active filter must still render the chip group so the
        // reviewer can adjust without losing context.
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Empty Viewer']);
        $operator->roles()->attach($userAdminRole->id);

        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.index', ['audit_range' => '7d']))
            ->assertOk();

        // The chip filter container should still render when the
        // result set is empty but a filter is active, so the user can
        // adjust it.
        $resp->assertSee('data-testid="audit-range-filter"', false);
        $resp->assertSee('No entries match this filter.');
    }
}
