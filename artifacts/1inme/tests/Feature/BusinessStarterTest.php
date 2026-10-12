<?php
namespace Tests\Feature;
use App\Modules\User\Models\User;
use App\Modules\User\Services\{BusinessStarterCatalog, WorkspaceContext};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BusinessStarterTest extends TestCase
{
    use RefreshDatabase;
    public function test_all_types_import_drafts_without_overwriting_existing_edits(): void
    {
        $user = User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        foreach (BusinessStarterCatalog::all() as $type=>$starters) {
            $this->assertCount(5,$starters);
            $link = $user->links()->create(['workspace_id'=>$ws->id,'type'=>$type,'alias'=>'starter-'.$type,'is_active'=>true]);
            $url = route('user.links.business-starter.store',$link);
            $response = $this->post($url,['starter'=>'starter-1']);
            $response->assertRedirect()->assertSessionHasNoErrors();
            $this->get($response->headers->get('Location'))->assertOk()->assertSee('Add starter content');
            if ($link->isCommunityPage()) {
                $this->assertSame(count($starters[0]['items']), $link->biolinkBlocks()->count());
                $this->assertSame(0, $link->biolinkBlocks()->where('is_active',true)->count());
                $first = $link->biolinkBlocks()->first();
                $first->update(['settings'=>['text'=>'My edited content']]);
                $this->post($url,['starter'=>'starter-1'])->assertRedirect();
                $this->assertSame(count($starters[0]['items']), $link->biolinkBlocks()->count());
                $this->assertSame('My edited content', $first->fresh()->settings['text']);
                continue;
            }
            $config = match($type) {'salon_spa','service_booking'=>$link->serviceBooking,'restaurant_menu'=>$link->restaurantMenu,'contact_directory'=>$link->contactDirectory,default=>$link->listingCatalog};
            $relation = match($type) {'salon_spa','service_booking'=>'services','restaurant_menu'=>'items','contact_directory'=>'contacts',default=>'entries'};
            $this->assertSame(4,$config->{$relation}()->count());
            $this->assertSame(0,$config->{$relation}()->where('is_active',true)->count());
            $first = $config->{$relation}()->first();
            $first->update(in_array($relation,['services','items']) ? ['description'=>'My edited content','price'=>99] : ['details'=>['description'=>'My edited content']]);
            $this->post($url,['starter'=>'starter-1'])->assertRedirect();
            $this->assertSame(4,$config->{$relation}()->count());
            $this->assertSame('My edited content',in_array($relation,['services','items']) ? $first->fresh()->description : $first->fresh()->details['description']);
        }
    }
    public function test_invalid_starter_is_rejected(): void
    {
        $user = User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        $link=$user->links()->create(['workspace_id'=>$ws->id,'type'=>'salon_spa','alias'=>'invalid-starter']);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        $this->post(route('user.links.business-starter.store',$link),['starter'=>'invalid'])->assertStatus(422);
        $other=User::factory()->create();
        $foreign=$other->links()->create(['type'=>'salon_spa','alias'=>'foreign-starter']);
        $this->post(route('user.links.business-starter.store',$foreign),['starter'=>'starter-1'])->assertForbidden();
    }
    public function test_admin_saved_customization_is_available(): void
    {
        $catalog=BusinessStarterCatalog::all();
        $catalog['salon_spa'][0]['items'][0]['name']='Custom haircut';
        \App\Modules\Admin\Models\AppSetting::put('business_starters',$catalog);
        $this->assertSame('Custom haircut',BusinessStarterCatalog::forType('salon_spa')[0]['items'][0]['name']);
    }
    public function test_admin_editor_saves_templates_and_rejects_invalid_prices(): void
    {
        $role=\App\Modules\Admin\Models\Role::firstOrCreate(['slug'=>'super-admin'],['name'=>'Super Admin','guard'=>'admin']);
        $admin=\App\Modules\Admin\Models\Admin::create(['name'=>'Starter Admin','email'=>'starters@example.com','password'=>bcrypt('secret'),'role_id'=>$role->id,'status'=>'active']);
        $this->actingAs($admin,'admin')->get(route('admin.business-starters.edit'))->assertOk()->assertSee('Unisex Salon');
        $catalog=BusinessStarterCatalog::all();
        $catalog['salon_spa'][0]['label']='Premium Salon';
        $this->post(route('admin.business-starters.update'),['catalog'=>$catalog])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Premium Salon',BusinessStarterCatalog::forType('salon_spa')[0]['label']);
        $catalog['salon_spa'][0]['items'][0]['price']=-1;
        $this->post(route('admin.business-starters.update'),['catalog'=>$catalog])->assertSessionHasErrors('catalog.salon_spa.0.items.0.price');
    }
    public function test_service_draft_can_be_published_through_existing_editor_endpoint(): void
    {
        $user=User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws=app(WorkspaceContext::class)->resolve($user);
        $link=$user->links()->create(['workspace_id'=>$ws->id,'type'=>'salon_spa','alias'=>'publish-starter']);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        $this->post(route('user.links.business-starter.store',$link),['starter'=>'starter-1'])->assertRedirect();
        $service=$link->serviceBooking->services()->first();
        $this->put(route('user.links.service-booking.services.update',[$link,$service]),['is_active'=>true])->assertOk();
        $this->assertTrue($service->fresh()->is_active);
    }
    public function test_admin_without_settings_permission_cannot_edit_starters(): void
    {
        $role=\App\Modules\Admin\Models\Role::firstOrCreate(['slug'=>'starter-viewer'],['name'=>'Viewer','guard'=>'admin']);
        $admin=\App\Modules\Admin\Models\Admin::create(['name'=>'Viewer','email'=>'starter-viewer@example.com','password'=>bcrypt('secret'),'role_id'=>$role->id,'status'=>'active']);
        $this->actingAs($admin,'admin')->get(route('admin.business-starters.edit'))->assertForbidden();
        $this->post(route('admin.business-starters.update'),['catalog'=>BusinessStarterCatalog::all()])->assertForbidden();
    }
}
