<?php
namespace Tests\Feature;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BusinessEditorParityTest extends TestCase
{
    use RefreshDatabase;
    public function test_all_business_editors_have_shared_header_panes_and_preview(): void
    {
        $user=User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws=app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        foreach (['salon_spa'=>'service-booking','service_booking'=>'service-booking','contact_directory'=>'directory','real_estate'=>'catalog','education'=>'catalog'] as $type=>$route) {
            $link=$user->links()->create(['type'=>$type,'alias'=>'parity-'.$type,'workspace_id'=>$ws->id]);
            $response=$this->get(route('user.links.'.$route.'.editor',$link));
            $response->assertOk()->assertSee('How it looks')->assertSee('business-editor-preview')->assertSee('Mobile preview');
            $dom=new \DOMDocument();
            @$dom->loadHTML($response->getContent());
            $xpath=new \DOMXPath($dom);
            $this->assertSame(1,$xpath->query('//div[@class="business-editor-grid"]/aside[@class="business-editor-preview"]')->length);
            if ($link->isAppointmentPage()) $response->assertSee(route('user.links.service-booking.bookings',$link),false);
            elseif($link->isListingCatalog()) $response->assertSee(route('user.links.catalog.inquiries',$link),false);
        }
    }
    public function test_publish_shortcuts_are_scoped_and_do_not_change_details(): void
    {
        $user=User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws=app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        foreach(['education'=>'catalog','contact_directory'=>'directory'] as $type=>$route) {
            $link=$user->links()->create(['type'=>$type,'alias'=>'publish-'.$type,'workspace_id'=>$ws->id]);
            $this->post(route('user.links.business-starter.store',$link),['starter'=>'starter-1'])->assertRedirect();
            $config=$type==='education' ? $link->listingCatalog : $link->contactDirectory;
            $rel=$type==='education' ? 'entries' : 'contacts';
            $item=$config->{$rel}()->first();$details=$item->details;
            $this->post(route('user.links.'.$route.'.save',[$link,'publish']),['id'=>$item->id,'is_active'=>true,'editor_pane'=>'items'])->assertRedirect();
            $this->assertTrue($item->fresh()->is_active);$this->assertSame($details,$item->fresh()->details);
            $other=$user->links()->create(['type'=>$type,'alias'=>'other-'.$type,'workspace_id'=>$ws->id]);
            $this->post(route('user.links.'.$route.'.save',[$other,'publish']),['id'=>$item->id,'is_active'=>false])->assertNotFound();
        }
    }
}
