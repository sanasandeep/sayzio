<?php

namespace Tests\Feature;

use App\Jobs\ProcessPrivacyDeletionJob;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\PlatformPricing;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformPricingAndAccountDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_round_major_units_and_overrides_do_not_change_web(): void
    {
        $p = Plan::create(['name' => 'Paid', 'slug' => 'platform-paid', 'status' => 'active', 'features' => []]);
        PricingResolver::upsertFromMinor($p, 'USD', 'monthly', 450);
        PricingResolver::upsertFromMinor($p, 'INR', 'monthly', 18700);
        $this->assertSame(600, PlatformPricing::price($p, 'USD', 'monthly', 'ios')['amount_minor']);
        $this->assertSame(26700, PlatformPricing::price($p, 'INR', 'monthly', 'android')['amount_minor']);
        PlatformPricing::save($p, ['USD' => ['monthly' => ['ios' => 799, 'android' => 0]]]);
        $this->assertSame(799, PlatformPricing::price($p, 'USD', 'monthly', 'ios')['amount_minor']);
        $this->assertSame(0, PlatformPricing::price($p, 'USD', 'monthly', 'android')['amount_minor']);
        $this->assertSame(450, PricingResolver::priceForCurrency($p, 'USD')['amount_minor']);
        PlatformPricing::save($p, ['USD' => ['monthly' => ['ios' => null]]]);
        $this->assertSame(600, PlatformPricing::price($p, 'USD', 'monthly', 'ios')['amount_minor']);
        $this->assertSame(0, PlatformPricing::defaultMinor(0));
    }

    public function test_plan_writer_and_api_keep_web_and_store_prices_separate(): void
    {
        $request = Request::create('/', 'POST', ['name' => 'Platform paid', 'monthly_price' => 450, 'annual_price' => 4500, 'monthly_price_secondary' => 18700, 'annual_price_secondary' => 187000, 'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0, 'status' => 'active', 'platform_prices' => ['USD' => ['monthly' => ['ios' => 899]]]]);
        $p = app(PlanWriter::class)->createFromRequest($request);
        $u = User::factory()->create(['country' => 'US']);
        $this->withToken($u->createToken('test')->plainTextToken);
        $web = collect($this->getJson('/api/v1/billing/plans')->assertOk()->json('data.plans'))->firstWhere('id', $p->id);
        $ios = collect($this->getJson('/api/v1/billing/plans?platform=ios')->assertOk()->json('data.plans'))->firstWhere('id', $p->id);
        $this->assertSame(450, $web['monthly']['amount_minor']);
        $this->assertSame(899, $ios['monthly']['amount_minor']);
        $this->assertSame(26700, $ios['prices']['INR']['monthly']['amount_minor']);
        $this->getJson('/api/v1/billing/plans?platform=invalid')->assertUnprocessable();
    }

    public function test_personal_privacy_requests_require_auth_and_confirmation_and_stay_owner_scoped(): void
    {
        Mail::fake();
        $u = User::factory()->create();
        $other = User::factory()->create();
        $this->getJson('/api/v1/me/data-requests')->assertUnauthorized();
        $this->withToken($u->createToken('test')->plainTextToken);
        $this->postJson('/api/v1/me/data-requests', ['type' => 'deletion'])->assertUnprocessable();
        $this->postJson('/api/v1/me/data-requests', ['type' => 'data_deletion', 'confirmation' => 'DELETE DATA', 'data_scopes' => ['billing']])->assertUnprocessable();
        $this->postJson('/api/v1/me/data-requests', ['type' => 'data_deletion', 'confirmation' => 'DELETE DATA', 'data_scopes' => ['notifications']])->assertCreated()->assertJsonPath('data.request.type', 'data_deletion');
        $this->postJson('/api/v1/me/data-requests', ['type' => 'export', 'email' => $other->email])->assertCreated();
        $this->postJson('/api/v1/me/data-requests', ['type' => 'export'])->assertStatus(409);
        $this->getJson('/api/v1/me/data-requests')->assertOk()->assertJsonCount(2, 'data.items')->assertDontSee('download_token')->assertDontSee('archive_path');
        $this->assertDatabaseHas('privacy_requests', ['user_id' => $u->id, 'email' => strtolower($u->email), 'type' => 'export']);
        $foreign = PrivacyRequest::create(['user_id' => $other->id, 'email' => $other->email, 'type' => 'export', 'status' => 'completed']);
        $this->getJson('/api/v1/me/data-requests/'.$foreign->id.'/download')->assertNotFound();
    }

    public function test_export_download_is_authenticated_owner_only_and_expires(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Storage::disk('local')->put('privacy-exports/test.zip', 'fixture');
        $p = PrivacyRequest::create(['user_id' => $u->id, 'email' => $u->email, 'type' => 'export', 'status' => 'completed', 'archive_path' => 'privacy-exports/test.zip', 'download_token' => 'secret', 'download_expires_at' => now()->addHour()]);
        $this->withToken($u->createToken('test')->plainTextToken)->get('/api/v1/me/data-requests/'.$p->id.'/download')->assertOk();
        $p->update(['download_expires_at' => now()->subMinute()]);
        $this->get('/api/v1/me/data-requests/'.$p->id.'/download')->assertNotFound();
    }

    public function test_selected_data_job_keeps_account_and_others_data_and_respects_grace(): void
    {
        Mail::fake();
        $u = User::factory()->create();
        $other = User::factory()->create();
        $mine = UserNotification::create(['user_id' => $u->id, 'type' => 'test', 'data' => []]);
        $theirs = UserNotification::create(['user_id' => $other->id, 'type' => 'test', 'data' => []]);
        $p = PrivacyRequest::create(['user_id' => $u->id, 'email' => $u->email, 'type' => 'data_deletion', 'data_scopes' => ['notifications'], 'status' => 'approved', 'scheduled_at' => now()->addDay()]);
        $job = new ProcessPrivacyDeletionJob($p->id);
        $job->handle(app(PrivacyRequestNotifier::class));
        $this->assertNotNull($mine->fresh());
        $p->update(['scheduled_at' => now()->subMinute()]);
        $job->handle(app(PrivacyRequestNotifier::class));
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertDatabaseHas('user_notifications', ['id' => $theirs->id]);
        $this->assertDatabaseMissing('user_notifications', ['id' => $mine->id]);
        $this->assertSame('completed', $p->fresh()->status);
    }

    public function test_apple_disabled_is_enforced_without_contacting_provider(): void
    {
        AppSetting::put('apple_login_enabled', false);
        Http::fake();
        $this->postJson('/api/v1/auth/social',['provider' => 'apple', 'id_token' => 'not-a-token'])->assertForbidden();
        $this->getJson('/api/v1/auth/apple/nonce')->assertNotFound();
        Http::assertNothingSent();
    }
    public function test_admin_forms_render_platform_fields_and_coin_overrides_persist(): void
    {
        $role=\App\Modules\Admin\Models\Role::firstOrCreate(['slug'=>'super-admin'],['name'=>'Super Admin','guard'=>'admin']);
        $admin=\App\Modules\Admin\Models\Admin::create(['name'=>'Test','email'=>'platform-admin@example.test','password'=>bcrypt('test-password'),'role_id'=>$role->id,'status'=>'active']);
        $this->actingAs($admin,'admin')->get('/admin/plans/create')->assertOk()->assertSee('Apple &amp; Android pricing',false);
        $this->get('/admin/auth-settings')->assertOk()->assertSee('Sign in with Apple');
        $this->post('/admin/coin-packages',['name'=>'Coin test','coin_amount'=>1000,'status'=>'active','price_usd'=>1000,'price_inr'=>100000,'platform_prices'=>['USD'=>['monthly'=>['ios'=>1599]]]])->assertRedirect()->assertSessionHasNoErrors();
        $coin=\App\Modules\Admin\Models\CoinPackage::where('name','Coin test')->firstOrFail();
        $this->assertSame(1599,PlatformPricing::price($coin,'USD','monthly','ios')['amount_minor']);
        $this->assertSame(1000,PlatformPricing::price($coin,'USD','monthly','web')['amount_minor']);
        AppSetting::put(\App\Services\Billing\WalletService::FEATURE_KEY,true);
        $user=User::factory()->create(['country'=>'US']);
        $this->withToken($user->createToken('test')->plainTextToken);
        $this->getJson('/api/v1/wallet/packages?platform=ios')->assertOk()->assertJsonPath('data.items.0.amount_minor',1599);
        $this->getJson('/api/v1/wallet/packages')->assertOk()->assertJsonPath('data.items.0.amount_minor',1000);
    }

    public function test_generated_export_includes_owned_minds_but_not_another_users_minds(): void
    {
        Mail::fake();Storage::fake('local');$user=User::factory()->create();$other=User::factory()->create();
        \App\Modules\User\Models\AiMind::create(['user_id'=>$user->id,'name'=>'My private Mind']);
        \App\Modules\User\Models\AiMind::create(['user_id'=>$other->id,'name'=>'Someone elses Mind']);
        $p=PrivacyRequest::create(['user_id'=>$user->id,'email'=>$user->email,'type'=>'export','status'=>'approved']);
        (new \App\Jobs\ProcessPrivacyExportJob($p->id))->handle(app(\App\Modules\Common\Services\PrivacyRequestNotifier::class));
        $p->refresh();$this->assertSame('completed',$p->status);
        $zip=new \ZipArchive;$zip->open(Storage::disk('local')->path($p->archive_path));
        $data=$zip->getFromName('data/ai_minds.json');$zip->close();
        $this->assertStringContainsString('My private Mind',$data);
        $this->assertStringNotContainsString('Someone elses Mind',$data);
    }

}
