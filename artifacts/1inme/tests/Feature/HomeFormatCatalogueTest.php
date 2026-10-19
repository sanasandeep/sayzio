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

    /**
     * Every format in the catalogue must render a scene built for it.
     *
     * link-type-scene has a @default branch, and for an unknown stretch eight
     * formats quietly landed on it -- Real Estate, Education & Training,
     * Communities & Clubs, Nonprofits & Campaigns, Contact Directory, Salon &
     * Spa, Service Booking and Updates Page all showed the same avatar, three
     * grey bars and an "Open" button. Nothing caught it: the count was right,
     * every card rendered, every template existed. The placeholder is only
     * visible as a placeholder once you know what the others look like.
     *
     * So the fallback is tagged `lts-generic` and this asserts no SHIPPED
     * format reaches it. The branch stays -- a type added to the catalogue
     * before someone draws its scene should degrade, not crash -- but it may
     * never again be what a visitor is shown.
     */
    public function test_no_format_falls_back_to_the_generic_preview_scene(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $items = HomeFormatCatalogue::items();
        $templates = $xpath->query('//*[@data-sf-template]');
        $this->assertSame(
            count($items),
            $templates->length,
            'every format needs a preview template'
        );

        $generic = [];
        foreach ($templates as $i => $template) {
            if (str_contains($dom->saveHTML($template), 'lts-generic')) {
                $generic[] = $items[$i]['name'] ?? ('#' . $i);
            }
        }

        $this->assertSame(
            [],
            $generic,
            "these formats render the generic placeholder instead of a scene of their own:\n  "
            . implode("\n  ", $generic)
        );
    }

    /**
     * The usage line is keyed by the same slug as the scene, so the two gaps
     * opened and closed together. Asserted together for the same reason.
     */
    public function test_every_format_has_a_usage_line(): void
    {
        $missing = array_values(array_filter(
            array_column(HomeFormatCatalogue::items(), 'name'),
            fn (string $name) => \App\Modules\Common\Support\LinkTypeUsage::forName($name) === ''
        ));

        $this->assertSame(
            [],
            $missing,
            "these formats show an empty \"How you could use it\" block:\n  " . implode("\n  ", $missing)
        );
    }
}
