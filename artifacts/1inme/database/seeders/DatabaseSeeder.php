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

        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Full access to all admin features',
            'guard' => 'admin',
        ]);

        $staffRole = Role::create([
            'name' => 'Staff',
            'slug' => 'staff',
            'description' => 'Limited admin access',
            'guard' => 'admin',
        ]);

        $supportRole = Role::create([
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Customer support access',
            'guard' => 'admin',
        ]);

        $permissionGroups = [
            'users' => [
                ['name' => 'View Users', 'slug' => 'users.view'],
                ['name' => 'Edit Users', 'slug' => 'users.edit'],
                ['name' => 'Delete Users', 'slug' => 'users.delete'],
                ['name' => 'Impersonate Users', 'slug' => 'users.impersonate'],
            ],
            'staff' => [
                ['name' => 'View Staff', 'slug' => 'staff.view'],
                ['name' => 'Create Staff', 'slug' => 'staff.create'],
                ['name' => 'Edit Staff', 'slug' => 'staff.edit'],
                ['name' => 'Delete Staff', 'slug' => 'staff.delete'],
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
                $p = Permission::create([
                    'name' => $perm['name'],
                    'slug' => $perm['slug'],
                    'group' => $group,
                ]);
                $allPermIds[] = $p->id;

                if (in_array($group, ['users', 'links', 'analytics'])) {
                    $supportPermIds[] = $p->id;
                }
            }
        }

        $staffRole->permissions()->sync($allPermIds);
        $supportRole->permissions()->sync($supportPermIds);

        Admin::create([
            'name' => 'Admin',
            'email' => 'official1inme@gmail.com',
            'password' => Hash::make('password'),
            'role_id' => $superAdminRole->id,
            'status' => 'active',
        ]);

        // Plans (Free / Starter / Pro / Business / Enterprise) and the
        // default addon catalog are seeded idempotently by
        // PlansAndAddonsSeeder above. We just resolve the named plans
        // here so the domain seeding below can attach them.
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $businessPlan = Plan::where('slug', 'business')->firstOrFail();

        // Example admin-global domains. `short.1inme.io` is open to every
        // plan (no plan tags); `pro.1inme.io` is gated to Pro+Business;
        // `biz.1inme.io` is Business-only. These show up automatically as
        // selectable hosts in the link create/edit screens.
        $cnameTarget = parse_url(config('app.url'), PHP_URL_HOST) ?: '1inme.com';

        $shared = Domain::create([
            'user_id' => null, 'domain' => 'short.1inme.io', 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);

        $proDomain = Domain::create([
            'user_id' => null, 'domain' => 'pro.1inme.io', 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);
        $proDomain->plans()->sync([$proPlan->id, $businessPlan->id]);

        $bizDomain = Domain::create([
            'user_id' => null, 'domain' => 'biz.1inme.io', 'type' => 'redirect',
            'is_active' => true, 'is_verified' => true, 'verified_at' => now(),
            'verification_token' => Str::random(32), 'cname_target' => $cnameTarget,
        ]);
        $bizDomain->plans()->sync([$businessPlan->id]);

        // Marketing blog content. Idempotent (matches by slug) and runs
        // last so it can attribute posts to the super-admin created above.
        // Safe to call again from `db:seed --class=MarketingBlogPostsSeeder`.
        $this->call(MarketingBlogPostsSeeder::class);

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
    }
}
