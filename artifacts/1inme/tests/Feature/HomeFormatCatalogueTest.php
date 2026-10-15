<?php
namespace Tests\Feature;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EventsModule;
use App\Modules\Common\Support\HomeFormatCatalogue;
use App\Modules\User\Models\User;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeFormatCatalogueTest extends TestCase
{
    use RefreshDatabase;
    public function test_counts_and_actions_match_the_enabled_catalogue(): void
    {
        $items=HomeFormatCatalogue::items();
        $ids=collect(LinkTypeCategories::categories())->flatMap(fn($c)=>array_column($c['types'],'value'))->push('qr','form')->all();
        $this->assertSame($ids,array_column($items,'id'));$this->assertCount(count(array_unique($ids)),$items);
        foreach($items as $item)$this->assertStringContainsString('/user/',$item['create']);
        $this->assertStringContainsString('type=restaurant_menu',collect($items)->firstWhere('id','restaurant_menu')['create']);
        $r=$this->get('/')->assertOk()->assertSee('What would you like to create?')->assertSee('Explore '.count($items).' formats')->assertDontSee('kinds of link.');
        $dom=new \DOMDocument();@$dom->loadHTML($r->getContent());$xpath=new \DOMXPath($dom);$this->assertSame(count($items),$xpath->query('//*[@data-sf-card]')->length);
        if(getenv('FORMAT_UI_FIXTURE'))file_put_contents('/tmp/format-showcase-guest.html',$r->getContent());
    }
    public function test_events_toggle_changes_public_count(): void
    {
        $before=HomeFormatCatalogue::items();AppSetting::put(EventsModule::KEY,false);$after=HomeFormatCatalogue::items();
        $this->assertCount(count($before)-1,$after);$this->assertNotContains('ics',array_column($after,'id'));$this->get('/')->assertOk()->assertSee('Explore '.count($after).' formats');
    }
    public function test_marketing_copy_cannot_add_unsupported_formats_or_unsafe_colors(): void
    {
        $base=HomeFormatCatalogue::items();$items=HomeFormatCatalogue::items([['name'=>'Short Link','desc'=>'Custom description','color'=>'javascript:alert(1)'],['name'=>'Unsupported product']]);
        $this->assertCount(count($base),$items);$this->assertSame('Custom description',$items[0]['desc']);$this->assertSame('#5865ef',$items[0]['color']);
    }
    public function test_authenticated_creation_uses_native_route(): void
    {
        $r=$this->actingAs(User::factory()->create())->get('/')->assertOk();$this->assertStringContainsString('data-sf-create href="'.e(route('user.links.create',['type'=>'url'])).'"',$r->getContent());
        if(getenv('FORMAT_UI_FIXTURE'))file_put_contents('/tmp/format-showcase-auth.html',$r->getContent());
    }
}
