<?php

namespace Tests\Unit\Support;

use App\Modules\Admin\Controllers\StaffFinanceController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\StaffPermissionCatalog as Catalog;
use App\Modules\Admin\Support\StaffPrivacy;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Isolated SQLite schema; never migrates or connects to the shared application database. */
class StaffPermissionBoundariesTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->app['config']->set('cache.default', 'array');
        $this->app['config']->set('session.driver', 'array');
        DB::purge('sqlite');
        foreach (['roles', 'permissions'] as $table) Schema::create($table, function ($t) use ($table) {
            $t->id(); $t->string('name')->nullable(); $t->string('slug')->unique();
            if ($table === 'roles') $t->string('guard')->default('admin'); else $t->string('group')->nullable();
            $t->timestamps();
        });
        Schema::create('role_permissions', function ($t) { $t->integer('role_id'); $t->integer('permission_id'); $t->unique(['role_id', 'permission_id']); });
        Schema::create('invoices', function ($t) {
            $t->id(); $t->string('number'); $t->integer('user_id')->nullable(); $t->string('kind')->nullable();
            $t->string('currency'); $t->string('status'); $t->integer('grand_total_minor'); $t->timestamps();
        });
        Schema::create('coin_purchase_allocations', function ($t) {
            $t->id(); $t->integer('user_id')->nullable(); $t->integer('amount_minor'); $t->integer('coins');
            $t->integer('api_budget_minor'); $t->integer('margin_minor'); $t->string('currency'); $t->timestamp('created_at');
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        $this->app->flush();
        Facade::clearResolvedInstances();
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    private function actor(array $permissions): BoundaryAdmin
    {
        $actor = new BoundaryAdmin($permissions);
        $this->app['auth']->guard('admin')->setUser($actor);
        return $actor;
    }

    private function denied(callable $operation): void
    {
        try { $operation(); $this->fail('Expected denial before any data access or mutation.'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_identity_projection_does_not_include_hidden_fields(): void
    {
        $user = new User;
        $user->setRawAttributes(['id' => 42, 'name' => 'Private Name', 'email' => 'private@example.test', 'phone' => '+911234567890', 'handle' => 'secret', 'status' => 'active']);
        $this->assertSame(['id' => 42, 'label' => 'Account #42'], Catalog::userRow($this->actor([]), $user));
        $emailOnly = Catalog::userRow($this->actor(['users.email.view']), $user);
        $this->assertSame('private@example.test', $emailOnly['email']);
        foreach (['name', 'handle', 'phone', 'status', 'plan'] as $field) $this->assertArrayNotHasKey($field, $emailOnly);
    }

    public function test_grant_and_deduct_are_independent_and_legacy_bundle_does_not_override_them(): void
    {
        $granter = $this->actor(['users.coins.grant', 'users.credits']);
        Catalog::assertCoins($granter, 50);
        $this->denied(fn () => Catalog::assertCoins($granter, -50));
        $deductor = $this->actor(['users.coins.deduct']);
        Catalog::assertCoins($deductor, -50);
        $this->denied(fn () => Catalog::assertCoins($deductor, 50));
        $this->denied(fn () => Catalog::assertCoins($this->actor(['users.credits']), 50));
    }

    public function test_hidden_email_cannot_be_searched_or_sorted_even_by_direct_request(): void
    {
        $this->actor(['users.search', 'users.search_identity']);
        $this->denied(fn () => (new UserManagementController)->index(Request::create('/admin/users', 'GET', ['search' => 'victim@example.test', 'field' => 'email'])));
        $this->denied(fn () => (new UserManagementController)->index(Request::create('/admin/users', 'GET', ['sort' => 'email'])));
        $this->denied(fn () => (new UserManagementController)->index(Request::create('/admin/users', 'GET', ['status' => 'active'])));
    }

    public function test_identity_restricted_staff_are_blocked_from_legacy_pages_downloads_and_impersonation(): void
    {
        $actor = $this->actor(['users.view', 'settings.manage']);
        foreach (['admin.users.index', 'admin.users.show', 'admin.finance.index', 'admin.finance.records'] as $route) $this->assertTrue(StaffPrivacy::allows($actor, $route));
        foreach (['admin.users.roles.edit', 'admin.coin-ledger.export', 'admin.payments.pending', 'admin.users.impersonate', 'admin.email-logs.index', 'admin.unknown-new-page'] as $route) $this->assertFalse(StaffPrivacy::allows($actor, $route));
        $this->assertTrue(StaffPrivacy::allows($this->actor(['users.identity.view', 'users.email.view', 'users.phone.view']), 'admin.coin-ledger.export'));
    }

    public function test_financial_totals_are_currency_separated_and_cannot_open_records(): void
    {
        $this->actor(['finance.invoices.totals']);
        foreach ([['USD', 1000, 'subscription'], ['USD', 2500, 'subscription'], ['INR', 7000, 'subscription'], ['USD', 99000, 'client']] as [$currency, $amount, $kind]) {
            DB::table('invoices')->insert(['number' => 'INV', 'currency' => $currency, 'grand_total_minor' => $amount, 'kind' => $kind, 'status' => 'paid', 'created_at' => now()]);
        }
        DB::enableQueryLog();
        $data = (new StaffFinanceController)->index(Request::create('/admin/finance'))->getData();
        $totals = $data['invoiceTotals']->keyBy('currency');
        $this->assertSame(3500, (int) $totals['USD']->total_minor);
        $this->assertSame(7000, (int) $totals['INR']->total_minor);
        $this->assertTrue($data['coinTotals']->isEmpty());
        $this->assertArrayNotHasKey('rows', $data);
        foreach (DB::getQueryLog() as $query) $this->assertStringNotContainsString('"users"', $query['query']);
        $this->denied(fn () => (new StaffFinanceController)->records(Request::create('/admin/finance/records', 'GET', ['section' => 'invoices'])));
    }

    public function test_record_read_does_not_imply_totals_or_other_financial_products(): void
    {
        $this->actor(['finance.coins.view']);
        $data = (new StaffFinanceController)->index(Request::create('/admin/finance'))->getData();
        $this->assertTrue($data['invoiceTotals']->isEmpty());
        $this->assertTrue($data['coinTotals']->isEmpty());
        $this->denied(fn () => (new StaffFinanceController)->records(Request::create('/admin/finance/records', 'GET', ['section' => 'invoices'])));
    }

    public function test_migration_preserves_existing_authority_once_and_does_not_regrant_removed_permissions(): void
    {
        $role = DB::table('roles')->insertGetId(['slug' => 'custom-finance', 'guard' => 'admin']);
        $legacy = DB::table('permissions')->insertGetId(['slug' => 'settings.manage']);
        DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $legacy]);
        $migration = require dirname(__DIR__, 3).'/database/migrations/2029_11_05_000001_add_staff_field_and_finance_permissions.php';
        $migration->up();
        $permission = DB::table('permissions')->where('slug', 'finance.invoices.totals')->value('id');
        $this->assertTrue(DB::table('role_permissions')->where('role_id', $role)->where('permission_id', $permission)->exists());
        $assignLink = DB::table('permissions')->where('slug', 'users.links.assign')->value('id');
        $this->assertFalse(DB::table('role_permissions')->where('role_id', $role)->where('permission_id', $assignLink)->exists());
        DB::table('role_permissions')->where('role_id', $role)->where('permission_id', $permission)->delete();
        $migration->up();
        $this->assertFalse(DB::table('role_permissions')->where('role_id', $role)->where('permission_id', $permission)->exists());
        $this->assertSame(count(Catalog::definitions()) + 1, DB::table('permissions')->count());
    }

    public function test_role_manager_cannot_delegate_super_admin_or_an_unheld_permission(): void
    {
        $actor = $this->actor(['roles.manage']);
        $role = new Role(['slug' => 'super-admin', 'guard' => 'admin']);
        $this->denied(fn () => Catalog::assertAssignable($actor, $role));
        $role = new Role(['slug' => 'finance', 'guard' => 'admin']);
        $role->setRelation('permissions', collect([(object) ['slug' => 'finance.refunds.create']]));
        $this->denied(fn () => Catalog::assertAssignable($actor, $role));
        Catalog::assertAssignable($this->actor(['finance.refunds.create']), $role);
        $this->assertTrue(true);
    }

    public function test_feature_finance_scope_excludes_other_products_and_separately_catalogued_children(): void
    {
        Schema::create('wallet_transactions', function ($t) { $t->id(); $t->json('meta'); $t->integer('delta_coins'); $t->integer('user_id')->nullable(); $t->string('type')->default('spend'); $t->timestamps(); });
        foreach (['marketing_strategist', 'marketing_strategist.suggestion', 'marketing_strategist.chat', 'marketing_strategist.chat.followup', 'marketing_strategist.report', 'brand_studio', 'marketingXstrategist.chat'] as $feature) {
            DB::table('wallet_transactions')->insert(['meta' => json_encode(['ai' => true, 'feature' => $feature]), 'delta_coins' => -10, 'created_at' => now()]);
        }
        $actor = $this->actor(['finance.ai.marketing_strategist.totals']);
        $scoped = \App\Modules\Admin\Support\StaffAiFinance::apply(DB::table('wallet_transactions'), $actor, 'marketing_strategist', 'totals');
        $this->assertSame(2, $scoped->count());
        $this->assertSame(-20, (int) $scoped->sum('delta_coins'));
        $data = (new StaffFinanceController)->ai(Request::create('/admin/finance/ai', 'GET', ['feature' => 'marketing_strategist', 'mode' => 'totals']))->getData();
        $this->assertSame(20, (int) $data['totals']->spent);
        $this->assertNull($data['rows']);
        $this->denied(fn () => \App\Modules\Admin\Support\StaffAiFinance::apply(DB::table('wallet_transactions'), $actor, 'marketing_strategist', 'records'));
        $this->denied(fn () => \App\Modules\Admin\Support\StaffAiFinance::apply(DB::table('wallet_transactions'), $actor, 'marketing_strategist.chat', 'totals'));
        $this->denied(fn () => \App\Modules\Admin\Support\StaffAiFinance::apply(DB::table('wallet_transactions'), $actor, 'brand_studio', 'totals'));
        $actor = $this->actor(['finance.ai.marketing_strategist.chat.records']);
        $scoped = \App\Modules\Admin\Support\StaffAiFinance::apply(DB::table('wallet_transactions'), $actor, 'marketing_strategist.chat', 'records');
        $this->assertSame(2, $scoped->count());
    }

    public function test_coin_records_omit_internal_margin_until_separately_granted(): void
    {
        DB::table('coin_purchase_allocations')->insert([
            'currency' => 'USD', 'amount_minor' => 5000, 'coins' => 1000,
            'api_budget_minor' => 2000, 'margin_minor' => 3000, 'created_at' => now(),
        ]);
        $this->actor(['finance.coins.view']);
        $request = Request::create('/admin/finance/records', 'GET', ['section' => 'coins']);
        $data = (new StaffFinanceController)->records($request)->getData();
        $record = $data['rows']->items()[0];
        $this->assertSame(5000, $record['amount_minor']);
        $this->assertArrayNotHasKey('margin_minor', $record);
        $this->assertArrayNotHasKey('api_budget_minor', $record);
        $this->assertSame(['label' => 'Deleted account'], $record['account']);
        $this->actor(['finance.coins.view', 'finance.margin.view']);
        $data = (new StaffFinanceController)->records($request)->getData();
        $this->assertSame(3000, $data['rows']->items()[0]['margin_minor']);
    }

    public function test_read_only_financial_role_cannot_submit_mutations_or_exports(): void
    {
        $this->actor(['finance.invoices.view', 'finance.payments.view', 'finance.refunds.view', 'finance.ledger.view']);
        $middleware = new \App\Modules\Admin\Middleware\CheckPermission;
        foreach (['finance.payments.confirm', 'finance.refunds.create', 'finance.refunds.confirm', 'finance.ledger.export'] as $permission) {
            $this->denied(fn () => $middleware->handle(Request::create('/admin/test', 'POST'), fn () => $this->fail('Mutation must not run.'), $permission));
        }
    }

    public function test_rendered_account_workbench_hides_identity_and_ungranted_actions(): void
    {
        Schema::create('app_settings', function ($t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->string('group')->nullable(); });
        $actor = $this->actor(['users.details', 'users.coins.grant']);
        $actor->setRelation('role', new Role(['name' => 'Anonymous support', 'slug' => 'anonymous-support', 'guard' => 'admin']));
        $actor->role->setRelation('permissions', collect());
        $request = Request::create('/admin/users/42');
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $html = view('admin.users.staff-show', [
            'actor' => $actor, 'account' => ['id' => 42, 'label' => 'Account #42'],
            'plans' => collect(), 'badges' => collect(), 'assignedBadges' => [], 'balance' => null, 'transactions' => collect(),
        ])->render();
        $this->assertStringContainsString('Account #42', $html);
        $this->assertStringContainsString('name="delta"', $html);
        foreach (['name="password"', 'name="plan_id"', 'name="badge_ids[]"', 'name="link_id"', 'Coin balance:', 'Recent coin transactions'] as $ungranted) $this->assertStringNotContainsString($ungranted, $html);
    }

    public function test_bearer_admin_routes_do_not_bypass_identity_restrictions(): void
    {
        $user = new BoundaryUser;
        $user->staff = $this->actor([]);
        $middleware = new \App\Modules\Api\Middleware\StaffPrivacy;
        $make = function ($controller, $action) use ($user) {
            $request = Request::create('/api/v1/admin/example');
            $request->setUserResolver(fn () => $user);
            $route = new \Illuminate\Routing\Route('GET', 'api/v1/admin/example', ['uses' => $controller.'@'.$action, 'controller' => $controller.'@'.$action]);
            $request->setRouteResolver(fn () => $route);
            return $request;
        };
        $this->denied(fn () => $middleware->handle($make(\App\Modules\Api\Controllers\EmailLogController::class, 'show'), fn () => true));
        $this->assertTrue($middleware->handle($make(\App\Modules\Api\Controllers\AdminAccessController::class, 'users'), fn () => true));
        // Another controller cannot become exempt merely by naming its action "users".
        $this->denied(fn () => $middleware->handle($make(\App\Modules\Api\Controllers\EmailLogController::class, 'users'), fn () => true));
    }

    public function test_routes_enforce_separate_read_write_and_export_powers(): void
    {
        $routes = $this->app['router']->getRoutes();
        foreach ([
            'admin.users.show' => 'users.details', 'admin.users.set-password' => 'users.password.set',
            'admin.users.wallet.adjust' => 'users.coins.grant|users.coins.deduct',
            'admin.users.badges.update' => 'users.badges.assign', 'admin.users.roles.audit.export' => 'users.audit.export',
            'admin.payments.pending' => 'finance.payments.view', 'admin.payments.mark-paid' => 'finance.payments.confirm',
            'admin.invoices.refund' => 'finance.refunds.create', 'admin.refunds.confirm' => 'finance.refunds.confirm',
            'admin.coin-ledger.export' => 'finance.ledger.export', 'admin.links.destroy' => 'links.delete',
        ] as $name => $slug) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains(\App\Modules\Admin\Middleware\CheckPermission::class.':'.$slug, $route->gatherMiddleware(), $name);
        }
    }
}

class BoundaryAdmin extends Admin
{
    public function __construct(private array $allowed = []) { parent::__construct(); $this->status = 'active'; }
    public function hasPermission(string $slug): bool { return in_array($slug, $this->allowed, true); }
    public function hasAnyPermission(array $slugs): bool { return (bool) array_intersect($slugs, $this->allowed); }
    public function isSuperAdmin(): bool { return false; }
    public function hasUserAccount(): bool { return false; }
    public function resolveAvatarUrl(): string { return ''; }
}

class BoundaryUser extends User
{
    public ?Admin $staff = null;
    public function adminAccount(): ?Admin { return $this->staff; }
}
