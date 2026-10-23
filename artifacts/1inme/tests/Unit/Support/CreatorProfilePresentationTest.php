<?php

namespace Tests\Unit\Support;

use App\Modules\User\Controllers\CreatorProfileController;
use App\Modules\User\Models\CreatorProfile;
use App\Modules\User\Models\User;
use App\Modules\User\Support\CreatorProfilePresentation as Presentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class CreatorProfilePresentationTest extends TestCase
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
        DB::purge('sqlite');
        Schema::create('links', function ($t) { $t->id(); $t->integer('user_id'); $t->integer('workspace_id')->nullable(); $t->string('type'); $t->boolean('is_active'); });
        DB::table('links')->insert([
            ['id'=>1,'user_id'=>7,'workspace_id'=>10,'type'=>'biolink','is_active'=>true],
            ['id'=>2,'user_id'=>7,'workspace_id'=>11,'type'=>'biolink','is_active'=>true],
            ['id'=>3,'user_id'=>7,'workspace_id'=>10,'type'=>'short','is_active'=>true],
            ['id'=>4,'user_id'=>7,'workspace_id'=>10,'type'=>'biolink','is_active'=>false],
            ['id'=>5,'user_id'=>8,'workspace_id'=>null,'type'=>'biolink','is_active'=>true],
        ]);
    }
    protected function tearDown(): void
    {
        DB::disconnect('sqlite'); $this->app->flush(); Facade::clearResolvedInstances();
        restore_error_handler(); restore_exception_handler(); parent::tearDown();
    }
    private function creator(): User
    {
        $user = new User(['name'=>"Public Name\nInjected",'handle'=>'example','email'=>'private@example.test','phone'=>'+919999999999']);
        $user->id = 7;
        return $user;
    }
    public function test_design_is_allowlisted_and_constrained(): void
    {
        $design = Presentation::resolve(['theme'=>'javascript:bad','avatar_shape'=>'url(bad)','avatar_size'=>900,'avatar_x'=>-10,'avatar_y'=>999]);
        $this->assertSame(['theme'=>'paper','avatar_shape'=>'rounded','avatar_size'=>96,'avatar_x'=>0,'avatar_y'=>100],$design);
        $this->assertSame('midnight',Presentation::resolve(['theme'=>'midnight'])['theme']);
    }
    public function test_vcard_never_uses_account_contacts_and_escapes_lines(): void
    {
        $user=$this->creator();
        $card=Presentation::vcard($user);
        $this->assertStringNotContainsString('private@example.test',$card);
        $this->assertStringNotContainsString('+919999999999',$card);
        $this->assertStringContainsString('FN:Public Name\\nInjected',$card);
        $user->socials=['email'=>'published@example.test'];
        $user->profile_showcase=['cta'=>['primary'=>['kind'=>'call','value'=>'+911234567890'],'secondary'=>[]]];
        $card=Presentation::vcard($user);
        $this->assertStringContainsString('EMAIL:published@example.test',$card);
        $this->assertStringContainsString('TEL:+911234567890',$card);
        $user->profile_section_visibility=['contact'=>false];
        $card=Presentation::vcard($user);
        $this->assertStringNotContainsString('EMAIL:',$card);
        $this->assertStringNotContainsString('TEL:',$card);
    }
    public function test_biolink_selection_is_scoped_and_presentation_survives_partial_saves(): void
    {
        $user=$this->creator();$profile=new CreatorProfile(['workspace_id'=>10,'user_id'=>7]);
        CreatorProfileController::saveShowcaseFields($user,['primary_biolink_id'=>1,'presentation'=>['theme'=>'mint','avatar_shape'=>'circle']],$profile);
        $this->assertSame(1,$profile->profile_showcase['primary_biolink_id']);
        $this->assertSame('mint',$profile->profile_showcase['presentation']['theme']);
        CreatorProfileController::saveShowcaseFields($user,[],$profile);
        $this->assertSame(1,$profile->profile_showcase['primary_biolink_id']);
        $this->assertSame('circle',$profile->profile_showcase['presentation']['avatar_shape']);
        foreach([2,3,4,5] as $invalid) {
            try { CreatorProfileController::saveShowcaseFields($user,['primary_biolink_id'=>$invalid],$profile);$this->fail('Must reject unavailable or other workspace link'); }
            catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('primary_biolink_id',$e->errors());}
        }
    }
    public function test_sharing_resources_preserve_page_gates_and_return_downloads(): void
    {
        $creator = $this->creator();
        $controller = new class extends \App\Modules\Common\Controllers\CreatorProfilePublicController {
            public $page;
            public function show(string $handle, \Illuminate\Http\Request $request) { return $this->page; }
        };
        $request = \Illuminate\Http\Request::create('/@example/contact.vcf');
        $controller->page = response('Region blocked', 451);
        $this->assertSame(451, $controller->contactCard('example', $request)->getStatusCode());
        $this->assertSame(451, $controller->shareQr('example', $request)->getStatusCode());
        $controller->page = view('public.creator-profile', ['creator'=>$creator, 'ageGateRequired'=>false]);
        $card = $controller->contactCard('example', $request);
        $this->assertTrue($card->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($card->headers->hasCacheControlDirective('private'));
        $this->assertStringContainsString('text/vcard', $card->headers->get('Content-Type'));
        $this->assertStringNotContainsString('private@example.test', $card->getContent());
        $qr = $controller->shareQr('example', $request);
        $this->assertSame('image/svg+xml', $qr->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $qr->getContent());
        $controller->page = view('public.creator-profile', ['creator'=>$creator, 'ageGateRequired'=>true]);
        try { $controller->contactCard('example',$request); $this->fail('Age gate must apply'); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }

    public function test_optional_monetization_is_preserved_when_older_clients_omit_it(): void
    {
        $user=$this->creator();$profile=new CreatorProfile(['profile_section_visibility'=>['monetization'=>false]]);
        CreatorProfileController::saveCoreProfileFields($user,['sections'=>['posts'=>false]],null,$profile);
        $this->assertFalse($profile->profile_section_visibility['monetization']);
        $this->assertFalse($profile->profile_section_visibility['posts']);
    }
}
