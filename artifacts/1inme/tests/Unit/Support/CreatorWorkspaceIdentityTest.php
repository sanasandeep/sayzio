<?php
namespace Tests\Unit\Support;
use App\Modules\User\Models\{CreatorProfile, User, Workspace, LinkedIdentifier};
use App\Modules\User\Support\CreatorPhoneIdentity;
use Illuminate\Support\Facades\{DB, Schema, Facade};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class CreatorWorkspaceIdentityTest extends TestCase
{
    private $app;
    protected function setUp(): void
    {
        $this->app=require dirname(__DIR__,3).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $this->app['config']->set('database.default','sqlite'); $this->app['config']->set('database.connections.sqlite.database',':memory:'); $this->app['config']->set('cache.default','array'); DB::purge('sqlite');
        Schema::create('users',function($t){$t->id();$t->string('name');$t->string('email')->nullable();$t->string('handle')->nullable();$t->string('profile_verification_status')->nullable();$t->timestamps();});
        Schema::create('workspaces',function($t){$t->id();$t->integer('owner_user_id');$t->string('name');$t->boolean('is_personal');$t->timestamps();});
        Schema::create('creator_profiles',function($t){$t->id();$t->integer('workspace_id')->unique();$t->integer('user_id');$t->string('handle')->nullable();$t->text('bio')->nullable();$t->boolean('profile_published')->default(false);$t->timestamps();});
        Schema::create('profile_verification_requests',function($t){$t->id();$t->integer('user_id');$t->string('status');$t->timestamps();});
        (require dirname(__DIR__,3).'/database/migrations/2029_11_11_000001_scope_creator_identity_and_verification.php')->up();
        (require dirname(__DIR__,3).'/database/migrations/2029_11_11_000002_create_creator_phone_assignments.php')->up();
        Schema::create('linked_identifiers',function($t){$t->id();$t->integer('user_id');$t->string('kind');$t->string('value');$t->timestamp('verified_at')->nullable();});
        DB::table('users')->insert(['id'=>1,'name'=>'Personal name','handle'=>'personal','profile_verification_status'=>'verified']);
        DB::table('workspaces')->insert([['id'=>1,'owner_user_id'=>1,'name'=>'Personal workspace','is_personal'=>true],['id'=>2,'owner_user_id'=>1,'name'=>'Business workspace','is_personal'=>false]]);
    }
    protected function tearDown(): void
    {
        DB::disconnect('sqlite'); $this->app->flush(); Facade::clearResolvedInstances(); restore_error_handler(); restore_exception_handler(); parent::tearDown();
    }
    private function profile(int $workspace): CreatorProfile { return CreatorProfile::forWorkspace(Workspace::find($workspace)); }
    private function verifiedPhone(string $phone): void { DB::table('linked_identifiers')->insert(['user_id'=>1,'kind'=>'phone','value'=>$phone,'verified_at'=>now()]); }
    public function test_fresh_workspace_never_inherits_personal_identity_content_or_badge(): void
    {
        $owner=User::find(1);$owner->forceFill(['bio'=>'Personal biography','avatar'=>'personal.jpg','creator_avatar'=>'private.jpg','profile_published'=>true]);
        $profile=$this->profile(2);$view=$profile->applyToUser(clone $owner);
        $this->assertSame('Business workspace',$view->name);$this->assertNull($view->handle);$this->assertNull($view->bio);$this->assertNull($view->avatar);$this->assertNull($view->creator_avatar);$this->assertFalse($view->isVerified());$this->assertFalse((bool)$view->profile_published);
        $this->assertSame('Personal name',$owner->name);$this->assertSame('personal',$owner->handle);
    }
    public function test_personal_settings_profile_is_not_switched_to_team(): void
    {
        $this->app->instance('current_workspace',Workspace::find(2));
        \Illuminate\Support\Facades\Auth::setUser(User::find(1));
        $method=new \ReflectionMethod(\App\Modules\User\Controllers\ProfileController::class,'activeCreatorProfile');
        $profile=$method->invoke(new \App\Modules\User\Controllers\ProfileController);
        $this->assertSame(1,(int)$profile->workspace_id);
    }
    public function test_numbers_are_exclusive_across_workspaces_and_format_variants(): void
    {
        $this->verifiedPhone('+919876543210'); $this->verifiedPhone('+919876543211');
        CreatorPhoneIdentity::attach($this->profile(1),User::find(1),'+91 (98765) 43210');
        CreatorPhoneIdentity::attach($this->profile(2),User::find(1),'+919876543211');
        CreatorPhoneIdentity::attach($this->profile(1),User::find(1),'00919876543210');
        $this->assertSame(2,DB::table('creator_phone_assignments')->count());
        try { CreatorPhoneIdentity::attach($this->profile(2),User::find(1),'+919876543210');$this->fail('Duplicate ownership allowed'); } catch(ValidationException $e) {$this->assertArrayHasKey('phone',$e->errors());}
        $this->assertSame($this->profile(1)->id,(int)DB::table('creator_phone_assignments')->where('phone','+919876543210')->value('creator_profile_id'));
    }
    public function test_unverified_number_cannot_be_claimed_without_review(): void
    {
        try { CreatorPhoneIdentity::attach($this->profile(1),User::find(1),'+919876543210');$this->fail('Unverified claim allowed'); } catch(ValidationException) {$this->assertSame(0,DB::table('creator_phone_assignments')->count());}
    }
    public function test_admin_review_reassigns_atomically_and_audits_prior_allocation(): void
    {
        $this->verifiedPhone('+919876543210');CreatorPhoneIdentity::attach($this->profile(1),User::find(1),'+919876543210');
        CreatorPhoneIdentity::assign($this->profile(2),'+919876543210',5,true);
        $this->assertSame(1,DB::table('creator_phone_assignments')->count());
        $this->assertSame($this->profile(2)->id,(int)DB::table('creator_phone_assignments')->value('creator_profile_id'));
        $this->assertSame('admin',DB::table('verification_revocations')->value('actor_guard'));
        $this->assertTrue(LinkedIdentifier::where('value','+919876543210')->where('user_id',1)->exists());
    }
    public function test_team_identity_edit_invalidates_only_team_verification(): void
    {
        $profile=$this->profile(2);$profile->update(['profile_verification_status'=>'verified']);$profile->update(['name'=>'Renamed business']);
        $this->assertSame('unverified',$profile->fresh()->profile_verification_status);$this->assertSame('verified',User::find(1)->profile_verification_status);
    }
    public function test_admin_revocation_is_scoped_and_records_reason(): void
    {
        $profile=$this->profile(2);$profile->update(['profile_verification_status'=>'verified']);
        DB::table('profile_verification_requests')->insert([['user_id'=>1,'creator_profile_id'=>$profile->id,'status'=>'pending'],['user_id'=>1,'creator_profile_id'=>null,'status'=>'pending']]);
        $admin=new \App\Modules\Admin\Models\Admin;$admin->id=9;
        \Illuminate\Support\Facades\Auth::guard('admin')->setUser($admin);
        $request=\Illuminate\Http\Request::create('/admin/verified-directory/workspaces/'.$profile->id.'/revoke','POST',['reason'=>'Wrong verification']);
        $request->setRouteResolver(fn()=>new \Illuminate\Routing\Route('POST','/revoke',['as'=>'admin.verified-directory.revoke']));
        $request->setLaravelSession($this->app['session']->driver());$this->app->instance('request',$request);$this->app['url']->setRequest($request);
        (new \App\Modules\User\Controllers\VerifiedDirectoryController)->revoke($request,'workspaces',$profile->id);
        $this->assertSame('unverified',$profile->fresh()->profile_verification_status);
        $this->assertSame('verified',User::find(1)->profile_verification_status);
        $this->assertSame('pending',DB::table('profile_verification_requests')->whereNull('creator_profile_id')->value('status'));
        $this->assertSame('superseded',DB::table('profile_verification_requests')->where('creator_profile_id',$profile->id)->value('status'));
        $this->assertSame('Wrong verification',DB::table('verification_revocations')->value('reason'));
    }
    public function test_phone_attachment_rejects_a_stale_or_forged_workspace_target(): void
    {
        $this->app->instance('current_workspace',Workspace::find(2));
        $request=\Illuminate\Http\Request::create('/phone','POST',['phone'=>'+919876543210','creator_profile_id'=>$this->profile(1)->id]);
        $request->setUserResolver(fn()=>User::find(1));
        try {(new \App\Modules\User\Controllers\CreatorPhoneController)->attach($request);$this->fail('Cross workspace attachment allowed');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
    }
    public function test_admin_number_and_verification_routes_require_review_permission(): void
    {
        foreach (['admin.verified-directory.index','admin.verified-directory.revoke','admin.creator-phone-claims.index','admin.creator-phone-claims.review','admin.creator-phone-claims.revoke'] as $name) {
            $route=app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains(\App\Modules\Admin\Middleware\AdminAuth::class,$route->gatherMiddleware());
            $this->assertContains(\App\Modules\Admin\Middleware\CheckPermission::class.':user.verifications.review',$route->gatherMiddleware());
        }
    }

    public function test_workspace_verification_status_and_requests_do_not_inherit_personal_approval(): void
    {
        Schema::create('verification_tick_types',function($t){$t->id();$t->string('name');$t->boolean('is_active');$t->boolean('admin_assigned_only');$t->integer('sort_order');});
        $profile=$this->profile(2);
        DB::table('profile_verification_requests')->insert([['user_id'=>1,'creator_profile_id'=>$profile->id,'status'=>'pending'],['user_id'=>1,'creator_profile_id'=>null,'status'=>'approved']]);
        $this->app->instance('current_workspace',Workspace::find(2));
        \Illuminate\Support\Facades\Auth::setUser(User::find(1));
        $view=(new \App\Modules\User\Controllers\ProfileVerificationController)->index();
        $this->assertFalse($view->getData()['user']->isVerified());
        $this->assertSame('Business workspace',$view->getData()['user']->name);
        $this->assertCount(1,$view->getData()['requests']);
        $this->assertSame($profile->id,(int)$view->getData()['requests']->first()->creator_profile_id);
    }
    public function test_superseded_review_cannot_restore_verification(): void
    {
        $id=DB::table('profile_verification_requests')->insertGetId(['user_id'=>1,'creator_profile_id'=>$this->profile(2)->id,'status'=>'superseded']);
        $req=\App\Modules\User\Models\ProfileVerificationRequest::find($id);
        try {\App\Modules\User\Support\ProfileVerificationModeration::reject($req,['admin_notes'=>'Old review'],9);$this->fail('Superseded review accepted');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $this->assertSame('superseded',$req->fresh()->status);
    }

    public function test_admin_revoked_number_requires_review_before_reattachment(): void
    {
        $this->verifiedPhone('+919876543210');
        DB::table('creator_phone_assignments')->insert(['phone'=>'+919876543210','creator_profile_id'=>null,'source'=>'admin_revoked','created_at'=>now(),'updated_at'=>now()]);
        try {CreatorPhoneIdentity::attach($this->profile(1),User::find(1),'+919876543210');$this->fail('Admin revocation bypassed');}
        catch(ValidationException){$this->assertNull(DB::table('creator_phone_assignments')->value('creator_profile_id'));}
    }

}
