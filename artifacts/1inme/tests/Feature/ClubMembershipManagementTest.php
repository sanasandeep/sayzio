<?php
namespace Tests\Feature;
use App\Modules\User\Models\{User, Link, ClubMembership, Form, FormSubmission, LinkedIdentifier};
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ClubMembershipManagementTest extends TestCase {
    use RefreshDatabase;
    private function club(): Link {
        $user=User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws=app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        return Link::create(['user_id'=>$user->id,'workspace_id'=>$ws->id,'type'=>'community_club','alias'=>'test-club','title'=>'Our club','settings'=>[]]);
    }
    private function member(array $extra=[]): array { return [...['name'=>'Alex Member','email'=>'member@example.com','phone'=>'+919876543210','status'=>'active','expires_at'=>now()->addYear()->toDateString()],...$extra]; }
    public function test_owner_can_add_edit_and_expire_member_with_unverified_free_account(): void {
        $link=$this->club();
        $this->get(route('user.links.memberships',$link))->assertOk()->assertSee('Membership form settings');
        $this->post(route('user.links.memberships.save',$link),$this->member())->assertRedirect();
        $member=ClubMembership::firstOrFail(); $account=User::findOrFail($member->account_id);
        $this->assertSame('member@example.com',$account->email);
        $this->assertTrue(!$account->plan || ((float)$account->plan->monthly_price===0.0 && (float)$account->plan->annual_price===0.0));
        $this->assertNull($account->email_verified_at); $this->assertNull($account->password_set_at);
        $this->assertFalse(LinkedIdentifier::where('user_id',$account->id)->whereNotNull('verified_at')->exists());
        $this->assertTrue($account->ownedWorkspaces()->exists());
        $this->post(route('user.links.memberships.save',$link),$this->member(['id'=>$member->id,'expires_at'=>now()->subDay()->toDateString()]))->assertRedirect();
        $this->assertTrue($member->fresh()->isExpired());
        $this->get(route('user.links.memberships',$link))->assertOk()->assertSee('Expired');
    }
    public function test_required_identity_and_custom_fields_are_validated(): void {
        $link=$this->club(); $link->update(['settings'=>['membership'=>['fields'=>[['key'=>'member_number','label'=>'Member number','type'=>'text','required'=>true]]]]]);
        $this->post(route('user.links.memberships.save',$link),$this->member(['phone'=>'       ']))->assertSessionHasErrors(['phone','custom_fields.member_number']);
        $this->assertDatabaseCount('club_memberships',0);
        $this->post(route('user.links.memberships.save',$link),$this->member(['custom_fields'=>['member_number'=>'ABC']]))->assertRedirect();
        $this->assertSame('ABC',ClubMembership::first()->custom_fields['member_number']);
    }
    public function test_existing_account_is_reused_without_changing_credentials_or_details(): void {
        $link=$this->club(); $existing=User::factory()->create(['email'=>'member@example.com','name'=>'Original']); $password=$existing->password;
        $this->post(route('user.links.memberships.save',$link),$this->member())->assertRedirect();
        $this->assertSame($existing->id,ClubMembership::first()->account_id);
        $this->assertSame($password,$existing->fresh()->password); $this->assertSame('Original',$existing->fresh()->name);
        $this->post(route('user.links.memberships.save',$link),$this->member())->assertSessionHasErrors('email');
    }
    public function test_other_owners_and_other_link_types_cannot_manage_members(): void {
        $link=$this->club(); $other=User::factory()->create();
        $foreign=Link::create(['user_id'=>$other->id,'workspace_id'=>$link->workspace_id,'type'=>'community_club','alias'=>'foreign-club']);
        $this->get(route('user.links.memberships',$foreign))->assertForbidden();
        $this->post(route('user.links.memberships.save',$foreign),$this->member())->assertForbidden();
        $link->update(['type'=>'biolink']); $this->get(route('user.links.memberships',$link))->assertNotFound();
    }
    public function test_form_submissions_create_pending_requests_and_approval_provisions_account(): void {
        $link=$this->club(); $form=new Form(['title'=>'Join','fields'=>[['id'=>'fullname','type'=>'text','required'=>true],['id'=>'email','type'=>'email','required'=>true],['id'=>'tel','type'=>'text','required'=>true]],'is_active'=>true]);
        $form->forceFill(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id])->save();
        $this->post(route('user.links.memberships.settings',$link),['form_id'=>$form->id,'mapping'=>['name'=>'fullname','email'=>'email','phone'=>'tel']])->assertRedirect();
        $submission=$form->submissions()->create(['data'=>['fullname'=>'Applicant','email'=>'member@example.com','tel'=>'+919876543210'],'is_spam'=>false,'payment_status'=>'none']);
        $member=ClubMembership::firstOrFail();$this->assertSame('pending',$member->status);$this->assertNull($member->account_id);$this->assertSame($submission->id,$member->submission_id);
        $submission->update(['is_read'=>true]);$this->assertDatabaseCount('club_memberships',1);
        $this->post(route('user.links.memberships.save',$link),$this->member(['id'=>$member->id]))->assertRedirect();$this->assertNotNull($member->fresh()->account_id);
    }
    public function test_spam_and_unpaid_submissions_are_not_imported_until_paid(): void {
        $link=$this->club();$form=new Form(['title'=>'Join','is_active'=>true]);$form->forceFill(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id])->save();
        $link->update(['settings'=>['membership'=>['form_id'=>$form->id]]]);
        $data=['name'=>'Test','email'=>'member@example.com','phone'=>'+919876543210'];
        $spam=$form->submissions()->create(['data'=>$data,'is_spam'=>true,'payment_status'=>'none']);
        $pending=$form->submissions()->create(['data'=>$data,'is_spam'=>false,'payment_status'=>'pending']);
        $this->assertDatabaseCount('club_memberships',0);$pending->update(['payment_status'=>'paid']);$this->assertDatabaseCount('club_memberships',1);
    }
    public function test_foreign_forms_and_invalid_field_keys_cannot_be_connected(): void {
        $link=$this->club();$other=User::factory()->create();$form=new Form(['title'=>'Private']);$form->forceFill(['user_id'=>$other->id,'workspace_id'=>$link->workspace_id])->save();
        $this->post(route('user.links.memberships.settings',$link),['form_id'=>$form->id])->assertNotFound();
        $this->post(route('user.links.memberships.settings',$link),['fields'=>[['key'=>'email','label'=>'Bad','type'=>'text','required'=>true]]])->assertSessionHasErrors('fields.0.key');
    }
    public function test_owner_can_create_connected_sayzio_form_with_custom_fields(): void {
        $link=$this->club();$link->update(['settings'=>['membership'=>['fields'=>[['key'=>'address','label'=>'Address','type'=>'textarea','required'=>true]]]]]);
        $this->post(route('user.links.memberships.create-form',$link))->assertRedirect();
        $form=Form::firstOrFail();$this->assertSame($form->id,data_get($link->fresh()->settings,'membership.form_id'));
        $this->assertContains('address',array_column($form->fields,'id'));$this->assertContains('consent',array_column($form->fields,'id'));
        $this->get(route('user.links.memberships',$link))->assertOk()->assertSee('Open form');
    }
    public function test_public_connected_form_submission_enters_membership_queue(): void {
        $link=$this->club();
        $this->post(route('user.links.memberships.create-form',$link))->assertRedirect();
        $form=Form::firstOrFail();
        $this->postJson(route('forms.public.submit',$form->slug),['name'=>'Public Applicant','email'=>'member@example.com','phone'=>'+919876543210','consent'=>['I agree']])->assertOk();
        $this->assertDatabaseHas('club_memberships',['link_id'=>$link->id,'status'=>'pending','email'=>'member@example.com','account_id'=>null]);
    }
    public function test_conflicting_phone_does_not_create_or_reassign_account(): void {
        $link=$this->club();$existing=User::factory()->create(['mobile'=>'+919876543210']);
        $this->post(route('user.links.memberships.save',$link),$this->member())->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('club_memberships',0);
        $this->assertDatabaseMissing('users',['email'=>'member@example.com']);
        $this->assertSame('+919876543210',$existing->fresh()->mobile);
    }

    public function test_member_updates_cannot_cross_clubs(): void {
        $link=$this->club();$other=Link::create(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id,'type'=>'community_club','alias'=>'another-club']);
        $member=ClubMembership::create(['link_id'=>$other->id,...$this->member()]);
        $this->post(route('user.links.memberships.save',$link),$this->member(['id'=>$member->id]))->assertNotFound();
        $this->assertNull($member->fresh()->account_id);
    }

    public function test_club_blocks_render_when_membership_route_is_missing_from_old_cache(): void {
        $link=$this->club();
        $url=route('user.links.blocks.editor',$link);
        $routes=new \Illuminate\Routing\RouteCollection();
        foreach(app('router')->getRoutes() as $route) {
            if($route->getName()!=='user.links.memberships') $routes->add($route);
        }
        app('router')->setRoutes($routes);
        $this->get($url)->assertOk()->assertSee('Bring your community together')->assertDontSee('Manage members');
    }

    private function config(array $extra=[]): array {
        return [...['approval'=>'manual','duration'=>'fixed','duration_count'=>12,'duration_unit'=>'months','renewal'=>'renewable','upcoming_days'=>30,'reminders_enabled'=>1,'reminder_days'=>'30, 7, 1','fee_minor'=>10000,'currency'=>'INR','card_layout'=>'horizontal','card_color'=>'#4255bc','card_title'=>'Club member'],...$extra];
    }
    public function test_native_form_values_and_automatic_approval_use_duration(): void {
        $link=$this->club();
        $this->post(route('user.links.memberships.configure',$link),$this->config(['approval'=>'automatic']))->assertRedirect();
        $this->post(route('user.links.memberships.create-form',$link))->assertRedirect();
        $form=Form::firstOrFail();
        $form->submissions()->create(['data'=>['name'=>'Alex','email'=>'member@example.com','phone'=>'+919876543210','choices'=>['A','B'],'signature'=>'signature-file-reference','time'=>'09:30'],'is_spam'=>false,'payment_status'=>'none']);
        $member=ClubMembership::firstOrFail();
        $this->assertSame('active',$member->status);$this->assertNotNull($member->account_id);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateString(),$member->expires_at->toDateString());
        $this->assertSame(['A','B'],$member->custom_fields['choices']);
        $this->assertSame('signature-file-reference',$member->custom_fields['signature']);
        $this->get(route('user.links.memberships',$link))->assertOk()->assertSee('Sayzio membership form')->assertSee('/iframe')->assertSee('ID card layout builder');
    }
    public function test_renewal_extends_current_period_and_one_time_rejects(): void {
        $link=$this->club();$link->update(['settings'=>['membership'=>$this->config()]]);
        $member=ClubMembership::create(['link_id'=>$link->id,...$this->member()]);
        $date=$member->expires_at->copy()->addMonthsNoOverflow(12)->toDateString();
        $this->post(route('user.links.memberships.action',['link'=>$link,'action'=>'renew']),['id'=>$member->id])->assertRedirect();
        $this->assertSame($date,$member->fresh()->expires_at->toDateString());
        $settings=$link->settings;$settings['membership']['renewal']='one_time';$link->update(['settings'=>$settings]);
        $this->post(route('user.links.memberships.action',['link'=>$link,'action'=>'renew']),['id'=>$member->id])->assertSessionHasErrors('renewal');
    }
    public function test_member_tabs_and_private_card_layouts(): void {
        $link=$this->club();
        ClubMembership::create(['link_id'=>$link->id,...$this->member(['name'=>'Expired Person','expires_at'=>now()->subDay()])]);
        $member=ClubMembership::create(['link_id'=>$link->id,...$this->member(['name'=>'Upcoming Person','email'=>'next@example.com','expires_at'=>now()->addDays(7)])]);
        $this->get(route('user.links.memberships',['link'=>$link,'tab'=>'expired']))->assertOk()->assertSee('Expired Person')->assertDontSee('Upcoming Person');
        $this->get(route('user.links.memberships',['link'=>$link,'tab'=>'upcoming']))->assertOk()->assertSee('Upcoming Person')->assertDontSee('Expired Person');
        $this->get(route('user.links.memberships.card',['link'=>$link,'member'=>$member->id]))->assertOk()->assertSee('Upcoming Person')->assertSee('Print / save PDF');
        $other=Link::create(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id,'type'=>'community_club','alias'=>'other']);
        $this->get(route('user.links.memberships.card',['link'=>$other,'member'=>$member->id]))->assertNotFound();
    }
    public function test_reminders_send_once_per_expiry_date(): void {
        \Illuminate\Support\Facades\Mail::fake();
        $link=$this->club();$link->update(['settings'=>['membership'=>['reminders_enabled'=>true,'reminder_days'=>[7]]]]);
        $member=ClubMembership::create(['link_id'=>$link->id,...$this->member(['expires_at'=>today()->addDays(7)])]);
        $this->artisan('clubs:send-membership-reminders')->assertSuccessful();
        $this->artisan('clubs:send-membership-reminders')->assertSuccessful();
        $this->assertDatabaseCount('club_membership_reminders',1);
        $this->assertDatabaseHas('club_membership_reminders',['club_membership_id'=>$member->id,'lead_days'=>7]);
    }
    public function test_invoice_draft_is_member_scoped_and_does_not_approve(): void {
        $link=$this->club();$link->update(['settings'=>['membership'=>$this->config()]]);
        $member=ClubMembership::create(['link_id'=>$link->id,...$this->member(['status'=>'pending'])]);
        $this->post(route('user.links.memberships.action',['link'=>$link,'action'=>'invoice']),['id'=>$member->id])->assertRedirect();
        $invoice=\App\Modules\User\Models\Invoice::findOrFail($member->fresh()->invoice_id);
        $this->assertSame('member@example.com',$invoice->recipient_email);
        $this->assertSame(10000,$invoice->grand_total_minor);
        $this->assertSame('pending',$member->fresh()->status);
        $this->post(route('user.links.memberships.action',['link'=>$link,'action'=>'invoice']),['id'=>$member->id])->assertRedirect();
        $this->assertSame($invoice->id,$member->fresh()->invoice_id);
    }
    public function test_invalid_reminders_and_foreign_company_are_rejected(): void {
        $link=$this->club();
        $this->post(route('user.links.memberships.configure',$link),$this->config(['reminder_days'=>'-7, bad']))->assertSessionHasErrors('reminder_days');
        $this->post(route('user.links.memberships.configure',$link),$this->config(['company_id'=>999999]))->assertNotFound();
    }

}
