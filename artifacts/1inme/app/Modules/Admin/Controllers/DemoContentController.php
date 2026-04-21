<?php

namespace App\Modules\Admin\Controllers;

use Database\Seeders\DemoContentSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Admin tools for one-click seeding/wiping of demo content.
 *
 * Demo content covers three visibility tiers (public, registered,
 * followers-only, subscribers-only) so the discover feed and biolink pages
 * can be demonstrated end-to-end.
 */
class DemoContentController
{
    /**
     * Destructive seed/wipe is restricted to super-admins. Other admin staff
     * may view the page but the action buttons return 403.
     */
    protected function requireSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();
        $role  = is_object($admin?->role) ? ($admin->role->slug ?? $admin->role->name ?? null) : ($admin->role ?? null);
        if (! in_array(strtolower((string) $role), ['super_admin', 'super-admin', 'superadmin'])) {
            throw new HttpException(403, 'Only super-admins can seed or wipe demo content.');
        }
    }

    public function index()
    {
        return view('admin.demo-content.index', [
            'stats' => DemoContentSeeder::demoContentStats(),
        ]);
    }

    public function seed(Request $request)
    {
        $this->requireSuperAdmin();
        try {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoContentSeeder', '--force' => true]);
            return back()->with('success', 'Demo content seeded successfully. ' . trim(Artisan::output()));
        } catch (\Throwable $e) {
            return back()->with('error', 'Seeding failed: ' . $e->getMessage());
        }
    }

    public function wipe(Request $request)
    {
        $this->requireSuperAdmin();
        try {
            $stats = DemoContentSeeder::wipeAllDemoContent();
            return back()->with('success', sprintf(
                'Removed %d demo creators, %d links, %d feed posts, %d follows, %d subscribers.',
                $stats['users'], $stats['links'], $stats['feed_events'], $stats['follows'], $stats['subscribers']
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Wipe failed: ' . $e->getMessage());
        }
    }
}
