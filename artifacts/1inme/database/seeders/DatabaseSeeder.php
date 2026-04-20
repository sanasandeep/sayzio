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
            'email' => 'admin@1inme.com',
            'password' => Hash::make('password'),
            'role_id' => $superAdminRole->id,
            'status' => 'active',
        ]);

        Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Get started with basic features',
            'monthly_price' => 0,
            'annual_price' => 0,
            'trial_days' => 0,
            'is_default' => true,
            'status' => 'active',
            'sort_order' => 0,
            'features' => [
                'max_links' => 5,
                'max_biolinks' => 1,
                'max_file_size_mb' => 5,
                'max_projects' => 1,
                'contacts_max' => 100,
                'contacts_google_sync' => false,
                'custom_domains' => false,
                'qr_customization' => false,
                'analytics' => 'basic',
                'pixels' => false,
                'utm_params' => false,
                'link_protection' => false,
                'seo_settings' => false,
                'teams' => false,
                'ecommerce' => false,
                'custom_forms' => false,
            ],
        ]);

        $proPlan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Everything you need to grow',
            'monthly_price' => 9.99,
            'annual_price' => 99.99,
            'trial_days' => 14,
            'status' => 'active',
            'sort_order' => 1,
            'features' => [
                'max_links' => 100,
                'max_biolinks' => 10,
                'max_file_size_mb' => 50,
                'max_projects' => 10,
                'contacts_max' => 5000,
                'contacts_google_sync' => true,
                'custom_domains' => true,
                'qr_customization' => true,
                'analytics' => 'advanced',
                'pixels' => true,
                'utm_params' => true,
                'link_protection' => true,
                'seo_settings' => true,
                'teams' => true,
                'ecommerce' => false,
                'custom_forms' => true,
            ],
        ]);

        $businessPlan = Plan::create([
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'For teams and businesses',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 14,
            'status' => 'active',
            'sort_order' => 2,
            'features' => [
                'max_links' => -1,
                'max_biolinks' => -1,
                'max_file_size_mb' => 200,
                'max_projects' => -1,
                'contacts_max' => -1,
                'contacts_google_sync' => true,
                'custom_domains' => true,
                'qr_customization' => true,
                'analytics' => 'advanced',
                'pixels' => true,
                'utm_params' => true,
                'link_protection' => true,
                'seo_settings' => true,
                'teams' => true,
                'ecommerce' => true,
                'custom_forms' => true,
            ],
        ]);

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
    }
}
