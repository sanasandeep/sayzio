<?php
namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListingCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(string $type='real_estate'): array
    {
        $user=User::factory()->create(['onboarded_at'=>now()]);
        app(WorkspaceContext::class)->resolve($user);
        $link=Link::create(['user_id'=>$user->id,'type'=>$type,'alias'=>'catalog'.fake()->unique()->numerify('#####'),'title'=>'Test catalog','is_active'=>true,'visibility'=>'public']);
        $catalog=$link->listingCatalog()->create(['settings'=>['currency'=>'INR','inquiries_enabled'=>true]]);
        return [$user,$link,$catalog];
    }

    public function test_both_empty_pages_and_owner_editors_render(): void
    {
        foreach (['real_estate','education'] as $type) {
            [$user,$link]=$this->catalog($type);
            $this->get('/'.$link->alias)->assertOk()->assertSee($type==='real_estate' ? 'Our property collection is being prepared' : 'Our course catalog is being prepared');
            $this->actingAs($user)->get(route('user.links.catalog.editor',$link))->assertOk()->assertSee($type==='real_estate' ? 'Property title' : 'Course title');
            $this->get(route('user.links.catalog.inquiries',$link))->assertOk()->assertSee('No inquiries yet');
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_property_fields_and_block_slots_render_and_drafts_stay_hidden(): void
    {
        [$user,$link,$catalog]=$this->catalog();
        $category=$catalog->categories()->create(['name'=>'City homes']);
        $catalog->entries()->create(['title'=>'Garden apartment','category_id'=>$category->id,'is_active'=>true,'details'=>['purpose'=>'rent','property_type'=>'apartment','status'=>'available','price'=>25000,'bedrooms'=>2,'bathrooms'=>2,'area'=>1200,'area_unit'=>'sq_ft','location'=>'Hyderabad','amenities'=>"Parking\nBalcony",'photos'=>['https://example.com/home.jpg']]]);
        $catalog->entries()->create(['title'=>'Unpublished property','is_active'=>false]);
        foreach (['top','above','section:'.$category->id,'below'] as $i=>$slot) $link->biolinkBlocks()->create(['user_id'=>$user->id,'type'=>'paragraph','settings'=>['text'=>'CATBLOCK'.$i,'_style'=>['_menu_slot'=>$slot]],'position'=>$i,'is_active'=>true]);
        $this->get('/'.$link->alias)->assertOk()->assertSee('Garden apartment')->assertSee('2 beds')->assertSee('Parking')->assertSee('INR 25,000.00')->assertDontSee('Unpublished property')->assertSeeInOrder(['CATBLOCK0','Test catalog','CATBLOCK1','Garden apartment','CATBLOCK2','CATBLOCK3']);
    }

    public function test_education_renders_curriculum_instructor_and_batches(): void
    {
        [, $link,$catalog]=$this->catalog('education');
        $catalog->entries()->create(['title'=>'Design foundations','is_active'=>true,'details'=>['status'=>'enrolling','mode'=>'online','level'=>'beginner','instructor'=>'Asha','syllabus'=>"Typography\nComposition",'certificate'=>'Completion certificate','batches'=>[['id'=>(string)Str::uuid(),'name'=>'Evening cohort','schedule'=>'Tue 6 PM','status'=>'open','seats'=>10]]]]);
        $this->get('/'.$link->alias)->assertOk()->assertSee('Learn with Asha')->assertSee('Typography')->assertSee('Evening cohort')->assertSee('10 remaining seats')->assertSee('Enrollment inquiry');
    }

    public function test_viewing_request_is_stored_without_confirming_a_booking(): void
    {
        [, $link,$catalog]=$this->catalog();
        $entry=$catalog->entries()->create(['title'=>'Lake house','is_active'=>true,'details'=>['status'=>'available']]);
        $this->post(route('public.catalog.inquiry',$link->alias),['entry_id'=>$entry->id,'name'=>'Visitor','email'=>'visitor@example.com','message'=>'Viewing please','consent'=>1,'preferred_date'=>now()->addDay()->format('Y-m-d')])->assertRedirect($link->getShortUrl().'#listing-'.$entry->id)->assertSessionHas('catalog_success');
        $this->assertDatabaseHas('listing_inquiries',['catalog_id'=>$catalog->id,'entry_id'=>$entry->id,'name'=>'Visitor','status'=>'new']);
    }

    public function test_private_password_and_unavailable_listings_reject_inquiries(): void
    {
        [, $link,$catalog]=$this->catalog();
        $entry=$catalog->entries()->create(['title'=>'Hidden home','is_active'=>false,'details'=>['status'=>'available']]);
        $data=['entry_id'=>$entry->id,'name'=>'Visitor','email'=>'visitor@example.com','consent'=>1];
        $this->post(route('public.catalog.inquiry',$link->alias),$data)->assertNotFound();
        $entry->update(['is_active'=>true,'details'=>['status'=>'sold']]);
        $this->post(route('public.catalog.inquiry',$link->alias),$data)->assertStatus(422);
        $entry->update(['details'=>['status'=>'available']]);
        $link->update(['is_password_protected'=>true]);
        $this->post(route('public.catalog.inquiry',$link->alias),$data)->assertForbidden();
        $link->update(['is_password_protected'=>false,'visibility'=>'registered']);
        $response=$this->post(route('public.catalog.inquiry',$link->alias),$data);
        $this->assertTrue($response->status()>=300);
        $this->assertDatabaseCount('listing_inquiries',0);
    }

    public function test_entry_and_category_ids_are_scoped_to_the_owner_catalog(): void
    {
        [$user,$link,$catalog]=$this->catalog();
        [,,$other]=$this->catalog();
        $category=$other->categories()->create(['name'=>'Foreign']);
        $entry=$other->entries()->create(['title'=>'Foreign listing','is_active'=>true,'details'=>['status'=>'available']]);
        app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->post(route('user.links.catalog.save',[$link,'delete-entry']),['id'=>$entry->id])->assertNotFound();
        $this->post(route('user.links.catalog.save',[$link,'entry']),['title'=>'Wrong category','category_id'=>$category->id,'sort_order'=>0,'details'=>['purpose'=>'sale','property_type'=>'house','area_unit'=>'sq_ft','status'=>'available']])->assertSessionHasErrors('category_id');
        $this->assertSame(0,$catalog->entries()->count());
        $this->post(route('public.catalog.inquiry',$link->alias),['entry_id'=>$entry->id,'name'=>'Visitor','email'=>'visitor@example.com','consent'=>1])->assertNotFound();
    }

    public function test_closed_batch_cannot_receive_enrollment_and_open_batch_uses_stable_id(): void
    {
        [, $link,$catalog]=$this->catalog('education');
        $closed=(string)Str::uuid();$open=(string)Str::uuid();
        $entry=$catalog->entries()->create(['title'=>'Course','is_active'=>true,'details'=>['status'=>'enrolling','batches'=>[['id'=>$closed,'name'=>'Old batch','status'=>'closed'],['id'=>$open,'name'=>'New batch','status'=>'open','seats'=>2]]]]);
        $data=['entry_id'=>$entry->id,'name'=>'Student','email'=>'student@example.com','consent'=>1,'batch'=>$closed];
        $this->post(route('public.catalog.inquiry',$link->alias),$data)->assertStatus(422);
        $data['batch']=$open;
        $this->post(route('public.catalog.inquiry',$link->alias),$data)->assertRedirect();
        $this->assertDatabaseHas('listing_inquiries',['batch'=>'New batch','status'=>'new']);
        $this->assertSame(2,$entry->fresh()->details['batches'][1]['seats']);
    }

    public function test_owner_can_save_type_specific_details_and_cannot_inject_markup_or_urls(): void
    {
        [$user,$link,$catalog]=$this->catalog('education');
        $data=['title'=>'Skills','sort_order'=>0,'is_active'=>1,'details'=>['status'=>'enrolling','mode'=>'online','level'=>'beginner','batches'=>[['name'=>'March','start_date'=>'2027-03-01','end_date'=>'2027-03-20','status'=>'open']]]];
        $this->actingAs($user)->post(route('user.links.catalog.save',[$link,'entry']),$data)->assertSessionHasNoErrors()->assertRedirect();
        $entry=$catalog->entries()->firstOrFail();
        $this->assertTrue(Str::isUuid($entry->details['batches'][0]['id']));
        $data['details']['external_url']='javascript:alert(1)';
        $this->post(route('user.links.catalog.save',[$link,'entry']),$data)->assertSessionHasErrors('details.external_url');
        $this->assertSame(1,$catalog->entries()->count());
    }
}
