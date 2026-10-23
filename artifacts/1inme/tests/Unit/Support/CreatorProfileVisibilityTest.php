<?php
namespace Tests\Unit\Support;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\CreatorProfileVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class CreatorProfileVisibilityTest extends TestCase
{
    private $app;
    protected function setUp(): void
    {
        $this->app=require dirname(__DIR__,3).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $this->app['config']->set('database.default','sqlite');$this->app['config']->set('database.connections.sqlite.database',':memory:');$this->app['config']->set('cache.default','array');DB::purge('sqlite');
        Schema::create('app_settings',function($t){$t->id();$t->string('key');$t->text('value')->nullable();$t->string('group')->nullable();});
        $request=Request::create('/@example?tab=posts');$this->app->instance('request',$request);$this->app['url']->setRequest($request);$request->setLaravelSession($this->app['session']->driver());
    }
    protected function tearDown(): void
    {
        DB::disconnect('sqlite');$this->app->flush();Facade::clearResolvedInstances();restore_error_handler();restore_exception_handler();parent::tearDown();
    }
    private function creator(): User
    {
        $creator=new class extends User { public function isVerified():bool{return false;} };
        $creator->forceFill(['id'=>7,'name'=>'Example','handle'=>'example','bio'=>'Hidden biography sentinel','socials'=>['email'=>'contact-sentinel@example.test','website'=>'https://social-sentinel.test']]);
        $creator->setRelation('verificationTickType',null);
        return $creator;
    }
    private function render(array $sections): string
    {
        $creator=$this->creator();$creator->profile_section_visibility=$sections;
        $creator->profile_showcase=['cta'=>['primary'=>['kind'=>'email','label'=>'Email me','value'=>'contact-sentinel@example.test'],'secondary'=>[]]];
        $link=new Link(['title'=>'Featured sentinel','alias'=>'featured-sentinel','type'=>'biolink']);
        return view('public.creator-profile',[
            'creator'=>$creator,'isOwner'=>true,'viewer'=>$creator,'sectionsVisible'=>$creator->profileSectionVisibility(),'primaryBiolink'=>$link,
            'posts'=>new \Illuminate\Pagination\LengthAwarePaginator([],0,10),'reactionTotals'=>[],'myReactions'=>[],'commentsByPost'=>[],'reactionDefs'=>[],'tiers'=>collect(),'viewerSubscription'=>null,'accessByPost'=>[],'isFollowing'=>false,'relatedCreators'=>collect([new User(['name'=>'Related sentinel','handle'=>'related_sentinel'])]),'specialDates'=>[],'upcomingEvents'=>collect(),'ageGateRequired'=>false,'showcase'=>$creator->resolvedProfileShowcase(),'featuredLinks'=>[$link],'showcaseCards'=>[],'totalPublicLinks'=>3,
        ])->render();
    }
    public function test_all_optional_sections_off_remove_posts_tabs_contacts_and_hidden_metadata(): void
    {
        $html=$this->render(array_fill_keys(array_keys(User::PROFILE_DEFAULT_VISIBILITY),false));
        foreach (['Latest posts','>Posts<','<nav class="cp-tab-bar','Hidden biography sentinel','contact-sentinel@example.test','social-sentinel.test','featured-sentinel','aria-label="Share profile"','Related sentinel'] as $hidden) $this->assertStringNotContainsString($hidden,$html);
        $this->assertStringContainsString('Example',$html);
    }
    public function test_tabs_off_keeps_enabled_content_and_posts_off_rejects_stale_tab_choice(): void
    {
        $html=$this->render(['tabs'=>false,'posts'=>false]);
        $this->assertStringNotContainsString('<nav class="cp-tab-bar',$html);
        $this->assertStringNotContainsString('Latest posts',$html);
        $this->assertStringContainsString('Hidden biography sentinel',$html);
        $this->assertStringContainsString('Featured sentinel',$html);
        $this->assertStringContainsString('!tabsEnabled || activeTab',$html);
        $this->assertStringNotContainsString("activeTab: 'posts'",$html);
    }
    public function test_contact_and_statistics_masters_hide_their_public_surfaces(): void
    {
        $html=$this->render(['contact'=>false,'stats'=>false]);
        $this->assertStringNotContainsString('contact-sentinel@example.test',$html);
        $this->assertStringNotContainsString('>Save contact</a>',$html);
        $this->assertStringNotContainsString('cp-highlight-pill flex-1',$html);
    }
    public function test_unsaved_preview_overrides_require_the_owner_and_preview_flag(): void
    {
        $creator=$this->creator();
        $request=Request::create('/@example?cp_preview=1&cp_sections[posts]=0&cp_highlights[show_links]=0');
        CreatorProfileVisibility::preview($creator,$request,false);
        $this->assertTrue($creator->isSectionVisible('posts'));
        CreatorProfileVisibility::preview($creator,Request::create('/@example?cp_sections[posts]=0'),true);
        $this->assertTrue($creator->isSectionVisible('posts'));
        CreatorProfileVisibility::preview($creator,$request,true);
        $this->assertFalse($creator->isSectionVisible('posts'));
        $this->assertFalse($creator->profile_showcase['highlights']['show_links']);
        $this->assertSame('visibility',CreatorProfileVisibility::editorSection('sections.posts'));
        $this->assertSame('about',CreatorProfileVisibility::editorSection('bio'));
    }
    public function test_verification_preview_controls_are_available_only_in_authenticated_owner_preview(): void
    {
        $request=Request::create('/@example?cp_preview=1');$this->app->instance('request',$request);$this->app['url']->setRequest($request);$request->setLaravelSession($this->app['session']->driver());
        \Illuminate\Support\Facades\Auth::setUser($this->creator());
        $html=$this->render(array_fill_keys(array_keys(User::PROFILE_DEFAULT_VISIBILITY),false));
        $this->assertStringContainsString('data-cp-verification-strip',$html);
        $this->assertStringContainsString('data-cp-verification-tick',$html);
        $this->assertStringContainsString('d.verificationPreview === true',$html);
        $other=new User;$other->id=8;\Illuminate\Support\Facades\Auth::setUser($other);
        $html=$this->render(array_fill_keys(array_keys(User::PROFILE_DEFAULT_VISIBILITY),false));
        $this->assertStringNotContainsString('data-cp-verification-strip',$html);
        $this->assertStringNotContainsString('d.verificationPreview === true',$html);
    }

}
