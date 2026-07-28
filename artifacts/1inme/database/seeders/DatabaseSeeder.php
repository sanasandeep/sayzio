<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data (idempotent — safely no-ops if already populated).
        $this->call(CitiesTableSeeder::class);
        $this->call(BannedNamesSeeder::class);
        // Starter page templates run BEFORE the persona library so the
        // page-template picker has a usable baseline (5 broadly-useful
        // templates) even when PersonaCatalog is empty or the expanded
        // library hasn't been (re)seeded yet.
        $this->call(StarterPageTemplatesSeeder::class);
        $this->call(PageTemplatePersonaSeeder::class);
        $this->call(ExpandedPageTemplateLibrarySeeder::class);
        $this->call(PlansAndAddonsSeeder::class);
        $this->call(CoinPackagesSeeder::class);
        $this->call(OnboardingSlidesSeeder::class);
        $this->call(TaxJurisdictionsSeeder::class);
        $this->call(GatewaySettingsSeeder::class);
        $this->call(SitePagesSeeder::class);
        $this->call(VerificationTickTypeSeeder::class);

        // firstOrCreate: several data migrations (e.g. the primary
        // super-admin provisioning migration) already create these roles on
        // a fresh `migrate`, so plain create() would hit a unique violation.
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], [
            'name' => 'Super Admin',
            'description' => 'Full access to all admin features',
            'guard' => 'admin',
        ]);

        $staffRole = Role::firstOrCreate(['slug' => 'staff'], [
            'name' => 'Staff',
            'description' => 'Limited admin access',
            'guard' => 'admin',
        ]);

        $supportRole = Role::firstOrCreate(['slug' => 'support'], [
            'name' => 'Support',
            'description' => 'Customer support access',
            'guard' => 'admin',
        ]);

        $permissionGroups = [
            'users' => [
                ['name' => 'View Users', 'slug' => 'users.view'],
                ['name' => 'Edit Users', 'slug' => 'users.edit'],
                ['name' => 'Delete Users', 'slug' => 'users.delete'],
                ['name' => 'Impersonate Users', 'slug' => 'users.impersonate'],
                ['name' => 'Provision New Accounts', 'slug' => 'users.create'],
                ['name' => 'Suspend / Reactivate Accounts', 'slug' => 'users.suspend'],
                ['name' => 'Assign User Roles', 'slug' => 'users.assign_roles'],
                ['name' => 'Review Badge Requests', 'slug' => 'badge_requests.review'],
            ],
            'billing' => [
                ['name' => 'Grant / Deduct Credits', 'slug' => 'users.credits'],
                ['name' => 'Assign / Comp Plans', 'slug' => 'users.assign_plan'],
                ['name' => 'Bulk Credit Grants', 'slug' => 'users.bulk_credits'],
                ['name' => 'Bulk Plan Assignment', 'slug' => 'users.bulk_plan'],
            ],
            'staff' => [
                ['name' => 'View Staff', 'slug' => 'staff.view'],
                ['name' => 'Create Staff', 'slug' => 'staff.create'],
                ['name' => 'Edit Staff', 'slug' => 'staff.edit'],
                ['name' => 'Delete Staff', 'slug' => 'staff.delete'],
                ['name' => 'Grant Staff Admin Access', 'slug' => 'users.grant_admin'],
                ['name' => 'Revoke Staff Admin Access', 'slug' => 'users.revoke_admin'],
            ],
            'roles' => [
                ['name' => 'View Roles', 'slug' => 'roles.view'],
                ['name' => 'Manage Roles', 'slug' => 'roles.manage'],
            ],
            'plans' => [
                ['name' => 'View Plans', 'slug' => 'plans.view'],
                ['name' => 'Manage Plans', 'slug' => 'plans.manage'],
            ],
            'links' => [
                ['name' => 'View All Links', 'slug' => 'links.view'],
                ['name' => 'Manage Links', 'slug' => 'links.manage'],
                ['name' => 'Delete Links', 'slug' => 'links.delete'],
            ],
            'analytics' => [
                ['name' => 'View Analytics', 'slug' => 'analytics.view'],
                ['name' => 'Export Analytics', 'slug' => 'analytics.export'],
            ],
            'settings' => [
                ['name' => 'View Settings', 'slug' => 'settings.view'],
                ['name' => 'Manage Settings', 'slug' => 'settings.manage'],
            ],
        ];

        $allPermIds = [];
        $supportPermIds = [];

        foreach ($permissionGroups as $group => $perms) {
            foreach ($perms as $perm) {
                $p = Permission::firstOrCreate(['slug' => $perm['slug']], [
                    'name' => $perm['name'],
                    'group' => $group,
                ]);
                $allPermIds[] = $p->id;

                // Support keeps its narrower scope: the user-billing actions
                // (billing group) plus the user/links/analytics groups, but
                // never the staff group (incl. grant/revoke admin access).
                if (in_array($group, ['users', 'links', 'analytics', 'billing'])) {
                    $supportPermIds[] = $p->id;
                }
            }
        }

        $staffRole->permissions()->sync($allPermIds);
        $supportRole->permissions()->sync($supportPermIds);

        Admin::firstOrCreate(['email' => 'sayzioapp@gmail.com'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role_id' => $superAdminRole->id,
            'status' => 'active',
        ]);

        // The 7-plan lineup (Starter / Creator / Professional / Business /
        // Agency / Developer / Enterprise API) and the default addon catalog
        // are seeded idempotently by PlansAndAddonsSeeder above. We just
        // resolve a couple of named plans here so the example domain seeding
        // below can attach them.
        $proPlan = Plan::where('slug', 'professional')->firstOrFail();
        $businessPlan = Plan::where('slug', 'business')->firstOrFail();

        // Example admin-global domains. `short.1inme.io` is open to every
        // plan (no plan tags); `pro.1inme.io` is gated to Pro+Business;
        // `biz.1inme.io` is Business-only. These show up automatically as
        // selectable hosts in the link create/edit screens.
        $cnameTarget = parse_url(config('app.url'), PHP_URL_HOST) ?: '1in.me';

        $shared = Domain::firstOrCreate(['domain' => 'short.1inme.io'], [
            'user_id' => null, 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);

        // sayzio.link — open to every plan (no plan or badge tags), active
        // and verified. Not primary. Uses firstOrCreate-style logic so
        // re-running the seeder never produces a duplicate row.
        $sayzioLink = Domain::firstOrCreate(
            ['domain' => 'sayzio.link'],
            [
                'user_id'            => null,
                'type'               => 'redirect',
                'is_active'          => true,
                'is_verified'        => true,
                'is_primary'         => false,
                'verified_at'        => now(),
                'verification_token' => Str::random(32),
                'cname_target'       => $cnameTarget,
            ]
        );
        // Normalize existing row to global + active + verified + redirect type with no plan/badge
        // restrictions in case it was previously created differently.
        if (!$sayzioLink->wasRecentlyCreated) {
            $sayzioLink->update([
                'user_id'     => null,
                'type'        => 'redirect',
                'is_active'   => true,
                'is_verified' => true,
                'verified_at' => $sayzioLink->verified_at ?? now(),
            ]);
        }
        $sayzioLink->plans()->detach();
        $sayzioLink->badges()->detach();

        $proDomain = Domain::firstOrCreate(['domain' => 'pro.1inme.io'], [
            'user_id' => null, 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);
        $proDomain->plans()->sync([$proPlan->id, $businessPlan->id]);

        $bizDomain = Domain::firstOrCreate(['domain' => 'biz.1inme.io'], [
            'user_id' => null, 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);
        $bizDomain->plans()->sync([$businessPlan->id]);

        // Marketing blog content. Idempotent (matches by slug) and runs
        // last so it can attribute posts to the super-admin created above.
        // Safe to call again from `db:seed --class=MarketingBlogPostsSeeder`.
        $this->call(MarketingBlogPostsSeeder::class);

        // 10 hand-written explainer biolink pages (one per marketing
        // headline link type) seeded into the super-admin account as a live
        // demo gallery. Idempotent (keyed on alias) and never clobbers a
        // page an admin has edited. Safe to call again standalone via
        // `db:seed --class=LinkTypeExplainerSeeder`.
        $this->call(LinkTypeExplainerSeeder::class);

        // 100+ card-container templates surfaced in the biolink editor.
        // Idempotent (matches by slug) so re-running just refreshes copy.
        $this->call(CardTemplateSeeder::class);

        // 200+ background templates (animated / gradient / mesh / pattern /
        // svg / neon) surfaced in the Appearance > Background > Template
        // picker. Both seeders are idempotent (updateOrCreate by slug) so
        // re-runs just refresh CSS/JS and sort_order.
        $this->call(BgTemplateSeeder::class);
        $this->call(BgPatternTemplatesSeeder::class);
        $this->call(LightBgTemplatesSeeder::class);
        $this->call(ClassicGradientBgTemplatesSeeder::class);

        // Fully-loaded showcase/demo account (sana@sayzio.app) — every link
        // type, every biolink widget, and every other feature surface
        // populated with sample data + backdated analytics. Idempotent
        // (wipes and rebuilds only its own account's rows) and safe to call
        // again standalone via `db:seed --class=ShowcaseAccountSeeder`.
        $this->call(ShowcaseAccountSeeder::class);

        // Second, publicly-safe showcase account (demo@sayzio.app):
        // reuses ShowcaseAccountSeeder's full content pipeline for the
        // exact same breadth of demo content, but with no admin/super-admin
        // access and `is_readonly_demo = true` so the global write-guard
        // middleware blocks every save attempt. Idempotent and scoped only
        // to this one email — safe to call again standalone via
        // `db:seed --class=ReadonlyDemoAccountSeeder`.
        $this->call(ReadonlyDemoAccountSeeder::class);
    }
}
