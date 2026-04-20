<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Services\BannedNameChecker;
use Illuminate\Database\Seeder;

/**
 * Seeds a curated default set of reserved handles so fresh installs are
 * protected from day one without admins needing to populate the list
 * manually. Re-running is safe: existing entries (case-insensitive) are
 * left untouched, only missing ones are inserted.
 *
 * Admins can still freely add, edit, or remove any of these afterwards
 * via the standard banned-names CRUD — this seeder only ever inserts,
 * never updates or deletes.
 */
class BannedNamesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Admin / staff surfaces
            'admin', 'administrator', 'root', 'superuser', 'sysadmin', 'staff',
            'moderator', 'mod', 'owner', 'webmaster',

            // Auth / account flows
            'login', 'logout', 'signin', 'signup', 'register', 'auth',
            'oauth', 'sso', 'password', 'reset', 'verify', 'activate',
            'account', 'accounts', 'profile', 'settings', 'preferences',
            'security', 'privacy', 'terms', 'tos', 'legal', 'gdpr', 'dmca',

            // Support / contact
            'support', 'help', 'helpdesk', 'contact', 'feedback', 'about',
            'team', 'jobs', 'careers', 'press', 'blog', 'news',

            // Billing / commerce
            'billing', 'pay', 'payment', 'payments', 'checkout', 'pricing',
            'plans', 'subscribe', 'subscription', 'invoice', 'invoices',
            'refund', 'refunds', 'order', 'orders',

            // API / system
            'api', 'apis', 'graphql', 'rest', 'webhook', 'webhooks',
            'callback', 'callbacks', 'public', 'private', 'static',
            'assets', 'cdn', 'media', 'uploads', 'files', 'download',
            'downloads', 'images', 'img', 'css', 'js', 'fonts',

            // App / product surfaces
            'app', 'apps', 'home', 'index', 'dashboard', 'admin-panel',
            'console', 'panel', 'portal', 'studio', 'workspace',
            'explore', 'discover', 'search', 'trending', 'popular',
            'new', 'create', 'edit', 'delete', 'update',

            // Common service paths
            'mail', 'email', 'inbox', 'notifications', 'messages',
            'chat', 'sms', 'calendar', 'docs', 'documentation', 'status',
            'health', 'ping', 'robots', 'sitemap', 'favicon', 'manifest',
            'security-txt', 'well-known',

            // Brand
            '1inme', 'oneinme', 'onein.me',

            // Misc reserved
            'null', 'undefined', 'true', 'false', 'me', 'self', 'you',
            'user', 'users', 'guest', 'anonymous', 'system', 'official',
            'verified', 'test', 'demo', 'example', 'sample',
        ];

        // Snapshot existing names once (case-insensitive) so we can skip
        // anything already on the list without an INSERT-per-row probe.
        $existing = BannedName::pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->flip()
            ->all();

        $seen     = [];
        $inserted = 0;

        foreach ($defaults as $name) {
            $lower = mb_strtolower($name);
            if (isset($seen[$lower]) || isset($existing[$lower])) {
                continue;
            }
            $seen[$lower] = true;

            BannedName::create([
                'name'       => $name,
                'note'       => 'Reserved by default install.',
                'created_by' => null,
            ]);
            BannedNameChecker::flush($name);
            $inserted++;
        }

        $this->command?->info("BannedNamesSeeder: inserted {$inserted} of " . count($defaults) . ' default reserved names.');
    }
}
