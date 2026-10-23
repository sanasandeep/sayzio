<?php

namespace Tests\Unit\Support;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Support\VerificationEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;

class VerificationEvidenceTest extends TestCase
{
    private $app;
    protected function setUp(): void
    {
        $this->app=require dirname(__DIR__,3).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $this->app['config']->set('database.default','sqlite');
        $this->app['config']->set('database.connections.sqlite.database',':memory:');
        $this->app['config']->set('cache.default','array');
        DB::purge('sqlite');
        Schema::create('profile_verification_requests',function($t){$t->id();$t->integer('user_id');$t->integer('tick_type_id');$t->string('official_name');$t->text('purpose');$t->string('status');$t->string('kind');$t->string('logo_path')->nullable();$t->json('proof_files')->nullable();$t->json('updates')->nullable();$t->timestamps();});
        (require dirname(__DIR__,3).'/database/migrations/2029_11_10_000001_add_evidence_to_profile_verification_requests.php')->up();
        Schema::create('users',function($t){$t->id();$t->string('name');$t->string('email');$t->string('profile_verification_status')->nullable();$t->timestamps();});
        Schema::create('verification_tick_types',function($t){$t->id();$t->string('name');$t->string('slug');$t->boolean('is_active');$t->boolean('admin_assigned_only');$t->integer('sort_order');});
        DB::table('users')->insert(['id'=>7,'name'=>'Example','email'=>'private@example.test']);
        DB::table('verification_tick_types')->insert(['id'=>1,'name'=>'Creator','slug'=>'creator','is_active'=>true,'admin_assigned_only'=>false,'sort_order'=>1]);
    }
    protected function tearDown(): void
    {
        DB::disconnect('sqlite');$this->app->flush();Facade::clearResolvedInstances();restore_error_handler();restore_exception_handler();parent::tearDown();
    }
    public function test_evidence_accepts_http_urls_and_rejects_unsafe_or_excessive_input(): void
    {
        $this->assertFalse(Validator::make(['supporting_urls'=>['https://example.test/proof',null],'additional_info'=>'My public work'],VerificationEvidence::rules())->fails());
        foreach ([['supporting_urls'=>['javascript:alert(1)']],['supporting_urls'=>['data:text/html,proof']],['supporting_urls'=>array_fill(0,6,'https://example.test')],['additional_info'=>str_repeat('x',4001)]] as $input) {
            $this->assertTrue(Validator::make($input,VerificationEvidence::rules())->fails());
        }
        $this->assertSame(['additional_info'=>'Details','supporting_urls'=>['https://example.test']],VerificationEvidence::fields(['additional_info'=>' Details ','supporting_urls'=>['','https://example.test','https://example.test']]));
    }
    public function test_web_and_api_submissions_store_evidence_without_granting_verification(): void
    {
        foreach ([\App\Modules\User\Controllers\ProfileVerificationController::class,\App\Modules\Api\Controllers\ProfileVerificationApiController::class] as $controller) {
            $user=new class extends User { public function getPlanFeature(string $key,$default=null){return $default;} };
            $user->setTable('users');
            $user->setRawAttributes((array) DB::table('users')->where('id',7)->first(),true);$user->exists=true;
            \Illuminate\Support\Facades\Auth::setUser($user);
            $request=\Illuminate\Http\Request::create('/profile-verification','POST',['tick_type_id'=>1,'official_name'=>'Example','purpose'=>'Creator identity','message'=>'Please review','additional_info'=>'Official portfolio','supporting_urls'=>['https://example.test/proof',''],'profile_verification_status'=>'verified']);
            $request->setUserResolver(fn()=>$user);
            $this->app->instance('request',$request);
            $request->setLaravelSession($this->app['session']->driver());
            User::withoutEvents(fn()=>(new $controller)->store($request));
            $stored=ProfileVerificationRequest::latest('id')->first();
            $this->assertSame(['https://example.test/proof'],$stored->supporting_urls);
            $this->assertSame('Official portfolio',$stored->additional_info);
            $this->assertSame('Please review',$stored->updates[0]['message']);
            $this->assertSame('pending',$stored->status);
            $this->assertSame('pending',DB::table('users')->where('id',7)->value('profile_verification_status'));
            DB::table('users')->where('id',7)->update(['profile_verification_status'=>null]);
        }
    }
    public function test_application_preselects_only_active_requestable_types(): void
    {
        DB::table('verification_tick_types')->insert([
            ['id'=>2,'name'=>'Official','slug'=>'team','is_active'=>true,'admin_assigned_only'=>true,'sort_order'=>2],
            ['id'=>3,'name'=>'NGO','slug'=>'ngo','is_active'=>true,'admin_assigned_only'=>false,'sort_order'=>3],
            ['id'=>4,'name'=>'Inactive','slug'=>'inactive','is_active'=>false,'admin_assigned_only'=>false,'sort_order'=>4],
        ]);
        \Illuminate\Support\Facades\Auth::setUser(User::find(7));
        foreach ([3=>'3',2=>'1',4=>'1',999=>'1'] as $requested=>$expected) {
            $request=\Illuminate\Http\Request::create('/request?tick_type_id='.$requested);
            $this->app->instance('request',$request);$request->setLaravelSession($this->app['session']->driver());
            $view=(new \App\Modules\User\Controllers\ProfileVerificationController)->create($request);
            $this->assertSame($expected,$view->getData()['selectedTickTypeId']);
            $this->assertSame('Creator', $view->getData()['tickTypes']->first()->name);
        }
    }

    public function test_identity_changes_require_a_fresh_verification_application(): void
    {
        foreach (['name', 'handle'] as $field) {
            $user = new User;
            $user->setRawAttributes(['name'=>'Example','handle'=>'example','profile_verification_status'=>'verified'], true);
            $this->assertFalse($user->invalidateVerificationForIdentityChange());
            $user->{$field} = 'changed';
            $this->assertTrue($user->invalidateVerificationForIdentityChange());
            $this->assertFalse($user->isVerified());
            $this->assertSame('unverified', $user->profile_verification_status);
        }
    }

    public function test_saved_identity_change_invalidates_badge_and_pending_review(): void
    {
        DB::table('users')->where('id',7)->update(['profile_verification_status'=>'verified']);
        DB::table('profile_verification_requests')->insert(['user_id'=>7,'tick_type_id'=>1,'official_name'=>'Example','purpose'=>'Review','status'=>'pending','kind'=>'reverification']);
        $user = User::find(7);
        $user->name = 'Changed';
        $user->save();
        $this->assertSame('unverified', User::find(7)->profile_verification_status);
        $this->assertSame('superseded', DB::table('profile_verification_requests')->value('status'));
    }

    public function test_verified_bar_requires_approved_status_and_uses_admin_color(): void
    {
        $creator=new User(['profile_verification_status'=>'unverified']);
        $creator->setRelation('verificationTickType',new \App\Modules\User\Models\VerificationTickType(['name'=>'NGO','color'=>'#00ba7c','icon'=>'fa-heart']));
        $this->assertStringNotContainsString('cp-verification-bar',view('public.partials.creator-verification-bar',compact('creator'))->render());
        $creator->profile_verification_status='verified';
        $html=view('public.partials.creator-verification-bar',compact('creator'))->render();
        $this->assertStringNotContainsString('<span',$html);
        $this->assertStringContainsString('--cp-verified:#00ba7c',$html);
        $creator->profile_verification_status='pending_reverification';
        $this->assertStringContainsString('cp-verification-bar',view('public.partials.creator-verification-bar',compact('creator'))->render());
    }

    public function test_evidence_is_escaped_in_admin_review(): void
    {
        $req=new ProfileVerificationRequest(['additional_info'=>'<script>bad()</script>','supporting_urls'=>['https://example.test/?x=1&y=2']]);
        $html=view('user.verification.evidence-review',compact('req'))->render();
        $this->assertStringNotContainsString('<script>bad()',$html);
        $this->assertStringContainsString('&lt;script&gt;',$html);
        $this->assertStringContainsString('rel="noopener noreferrer"',$html);
    }
}
