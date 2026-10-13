<?php
namespace App\Modules\User\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\User\Models\{Link, ClubMembership, Form};
use App\Modules\User\Services\ClubMembershipService;
use Illuminate\Http\Request;
use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class ClubMembershipController extends Controller {
    private function authorizeLink(Link $link): void {
        abort_unless((int)$link->user_id===(int)workspace_owner_id(),403);
        abort_unless($link->type===Link::TYPE_COMMUNITY,404);
    }
    public function index(Request $request, Link $link) {
        $this->authorizeLink($link);
        $settings=data_get($link->settings,'membership',[]);
        $forms=auth()->user()->canInWorkspace(app('current_workspace'),'inbox.view') ? Form::where('user_id',$link->user_id)->orderBy('title')->get() : collect();
        $query=ClubMembership::with('profilePhoto')->where('link_id',$link->id);
        $tab=$request->input('tab','all');
        $upcoming=(int)($settings['upcoming_days']??30);
        match($tab) {
            'new'=>$query->where('status','pending'),
            'active'=>$query->where('status','active')->where(fn($q)=>$q->whereNull('expires_at')->orWhereDate('expires_at','>=',today())),
            'expired'=>$query->where('status','active')->whereDate('expires_at','<',today()),
            'upcoming'=>$query->where('status','active')->whereBetween('expires_at',[today(),today()->addDays($upcoming)]),
            'cancelled'=>$query->where('status','cancelled'),default=>null,
        };
        $query->when($request->filled('q'),function($q)use($request){$term='%'.$request->string('q')->substr(0,150).'%';$q->where(fn($q)=>$q->where('name','ilike',$term)->orWhere('email','ilike',$term)->orWhere('phone','like',$term));});
        foreach(['from','to'] as $key) if($request->filled($key)) {$request->validate([$key=>'date']);$query->whereDate('created_at',$key==='from'?'>=':'<=',$request->input($key));}
        if($request->input('account')==='linked') $query->whereNotNull('account_id');
        if($request->input('account')==='unlinked') $query->whereNull('account_id');
        $sort=in_array($request->input('sort'),['name','expires_at','created_at'],true)?$request->input('sort'):'created_at';
        $members=$query->orderBy($sort,$sort==='created_at'?'desc':'asc')->paginate(20)->withQueryString();
        $membershipForm=$forms->firstWhere('id',$settings['form_id']??null);
        $companies=auth()->user()->canInWorkspace(app('current_workspace'),'tasks.view') ? BillingCompany::where('user_id',$link->user_id)->where('workspace_id',$link->workspace_id)->orderBy('name')->get():collect();
        $edit=$request->filled('edit')?ClubMembership::where('link_id',$link->id)->findOrFail($request->input('edit')):null;
        return view('user.links.memberships',compact('link','settings','forms','members','edit','membershipForm','companies','tab'));
    }
    public function save(Request $request, Link $link, ClubMembershipService $service) {
        $this->authorizeLink($link);
        $member=$request->filled('id')?ClubMembership::where('link_id',$link->id)->findOrFail($request->input('id')):null;
        if($member) $request->merge(['custom_fields'=>$member->custom_fields??[]]);
        $data=$request->validate([...$service->rules($link),'expires_at'=>'nullable|date','status'=>'required|in:pending,active,cancelled']);
        $allowed=array_column(data_get($link->settings,'membership.fields',[]),'key');
        $data['custom_fields']=$member ? ($member->custom_fields??[]) : array_intersect_key($data['custom_fields']??[],array_flip($allowed));
        $service->save($link,$data,$member);
        return redirect()->route('user.links.memberships',$link)->with('success','Membership saved. Active members are linked to a free or existing Sayzio account.');
    }
    public function settings(Request $request, Link $link) {
        $this->authorizeLink($link);
        $data=$request->validate(['fields'=>'nullable|array|max:20','fields.*.key'=>['required','distinct','regex:/^[a-z][a-z0-9_]{0,39}$/',Rule::notIn(['name','email','phone','consent'])],'fields.*.label'=>'required|string|max:100','fields.*.type'=>'required|in:text,textarea,number,date,email','fields.*.required'=>'required|boolean','form_id'=>'nullable|integer','mapping'=>'nullable|array','mapping.*'=>'nullable|string|max:100','photo_field'=>'nullable|string|max:100']);
        if($request->filled('form_id')) abort_unless(auth()->user()->canInWorkspace(app('current_workspace'),'inbox.edit'),403);
        $form=$request->filled('form_id')?Form::where('user_id',$link->user_id)->findOrFail($data['form_id']):null;
        $mapping=array_intersect_key($data['mapping']??[],array_flip(['name','email','phone',...array_column($data['fields']??[],'key')]));
        if($form) {
            $fields=collect($form->fields??[])->keyBy('id');
            foreach(['name','email','phone',...array_column(array_filter($data['fields']??[],fn($f)=>$f['required']),'key')] as $key) {
                $id=$mapping[$key]??$key;
                if(!$fields->has($id) || empty($fields[$id]['required'])) throw ValidationException::withMessages(['mapping'=>'Map each required membership field to a required field in the selected Sayzio Form.']);
            }
            if(!empty($data['photo_field']) && (!$fields->has($data['photo_field']) || ($fields[$data['photo_field']]['type']??'')!=='file')) throw ValidationException::withMessages(['photo_field'=>'Choose a file upload field for the profile picture.']);
            foreach($mapping as $id) if($id && !$fields->has($id)) throw ValidationException::withMessages(['mapping'=>'A mapped field does not exist on this form.']);
        }
        $settings=$link->settings??[];
        $settings['membership']=[...($settings['membership']??[]),'fields'=>$data['fields']??[],'form_id'=>$form?->id,'mapping'=>$mapping,'photo_field'=>array_key_exists('photo_field',$data)?($data['photo_field']??''):'profile_picture'];
        $link->update(['settings'=>$settings]);
        return back()->with('success','Membership form settings saved.');
    }
    public function import(Request $request, Link $link, \App\Modules\User\Services\ClubMembershipImportService $importer) {
        $this->authorizeLink($link);
        $request->validate(['csv'=>'required|file|mimes:csv,txt|max:5120','mode'=>'required|in:update,skip']);
        $report=$importer->import($link,$request->file('csv')->getRealPath(),$request->input('mode'));
        return back()->with('membership_import',$report)->with('success',"Import finished: {$report['created']} created, {$report['updated']} updated, {$report['skipped']} skipped.");
    }
    public function export(Request $request, Link $link) {
        $this->authorizeLink($link);
        return response()->streamDownload(function()use($link){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,\App\Modules\User\Services\ClubMembershipImportService::HEADERS,',','"','');
            foreach(ClubMembership::where('link_id',$link->id)->orderBy('id')->cursor() as $member) {
                $row=[$member->name,$member->email,$member->phone,$member->status,$member->expires_at?->toDateString()??'',json_encode((object)($member->custom_fields??[]),JSON_UNESCAPED_UNICODE)];
                fputcsv($out,array_map([\App\Modules\User\Services\ClubMembershipImportService::class,'csvCell'],$row),',','"','');
            }
            fclose($out);
        },'club-'.$link->id.'-members.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
    public function photo(Request $request, Link $link) {
        $this->authorizeLink($link);
        $request->validate(['id'=>'required|integer','photo'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120','remove'=>'nullable|boolean']);
        $member=ClubMembership::where('link_id',$link->id)->findOrFail($request->input('id'));
        if($request->boolean('remove')) $member->update(['profile_photo_file_id'=>null]);
        elseif($request->hasFile('photo')) {
            try {$file=\App\Modules\User\Models\UserFile::createFromUpload($request->file('photo'),$link->user);} catch(\RuntimeException $e) {throw ValidationException::withMessages(['photo'=>$e->getMessage()]);}
            $member->update(['profile_photo_file_id'=>$file->id]);
        }
        return back()->with('success','Member profile picture updated.');
    }
    public function configure(Request $request, Link $link) {
        $this->authorizeLink($link);
        $data=$request->validate([
            'approval'=>'required|in:manual,automatic','duration'=>'required|in:lifetime,fixed','duration_count'=>'required|integer|min:1|max:3650','duration_unit'=>'required|in:days,months,years','renewal'=>'required|in:one_time,renewable','upcoming_days'=>'required|integer|min:1|max:365',
            'reminders_enabled'=>'required|boolean','reminder_days'=>'nullable|string|max:100',
            'company_id'=>'nullable|integer','fee_minor'=>'required|integer|min:0|max:1000000000','currency'=>'required|in:INR,USD,EUR,GBP,AUD,CAD',
            'card_layout'=>'required|in:horizontal,vertical','card_color'=>'required|regex:/^#[0-9a-fA-F]{6}$/','card_title'=>'nullable|string|max:100','card_footer'=>'nullable|string|max:150','card_fields'=>'nullable|array|max:12','card_fields.*'=>'string|max:100',
        ]);
        $days=array_values(array_unique(array_filter(array_map('trim',explode(',',$data['reminder_days']??'')),fn($v)=>$v!=='')));
        foreach($days as $day) if(!ctype_digit($day)||(int)$day>365) throw ValidationException::withMessages(['reminder_days'=>'Use comma-separated days from 0 to 365.']);
        $data['reminder_days']=array_map('intval',$days);
        $data['card_fields']=$data['card_fields']??[];
        if(!empty($data['company_id'])) {
            abort_unless(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.edit'),403);
            BillingCompany::where('user_id',$link->user_id)->where('workspace_id',$link->workspace_id)->findOrFail($data['company_id']);
        }
        $settings=$link->settings??[];$settings['membership']=[...($settings['membership']??[]),...$data];$link->update(['settings'=>$settings]);
        return back()->with('success','Membership workflow, reminders, billing and card design saved.');
    }
    public function action(Request $request, Link $link, ClubMembershipService $service, string $action) {
        $this->authorizeLink($link);
        $request->validate(['id'=>'required|integer']);
        $member=ClubMembership::where('link_id',$link->id)->findOrFail($request->input('id'));
        if($action==='renew') $service->renew($link,$member);
        elseif($action==='approve') $service->save($link,['name'=>$member->name,'email'=>$member->email,'phone'=>$member->phone,'status'=>'active','custom_fields'=>$member->custom_fields],$member);
        elseif($action==='cancel') $member->update(['status'=>'cancelled']);
        elseif($action==='invoice') {
            abort_unless(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.edit'),403);
            $config=data_get($link->settings,'membership',[]);
            if(empty($config['fee_minor'])) throw ValidationException::withMessages(['invoice'=>'Configure a membership fee first.']);
            $invoice=DB::transaction(function()use($member,$link,$config){
                $member=ClubMembership::where('link_id',$link->id)->lockForUpdate()->findOrFail($member->id);
                if($member->invoice_id) return $member->invoice_id;
                if(!empty($config['company_id'])) BillingCompany::where('user_id',$link->user_id)->where('workspace_id',$link->workspace_id)->findOrFail($config['company_id']);
                $invoice=app(ClientInvoiceService::class)->createStandalone(['billing_company_id'=>$config['company_id']??null,'currency'=>$config['currency']??'INR','recipient_name'=>$member->name,'recipient_email'=>$member->email,'line_items'=>[['label'=>($link->title?:$link->alias).' membership','amount_minor'=>(int)$config['fee_minor'],'quantity'=>1]],'notes_md'=>'Membership #'.$member->id.'; approval and payment are managed separately.'],app('current_workspace'),$link->user_id);
                $member->update(['invoice_id'=>$invoice->id]);return $invoice->id;
            });
            return redirect()->route('user.client-invoices.edit',$invoice);
        }
        return back()->with('success','Membership updated.');
    }
    public function card(Link $link, int $member) {
        $this->authorizeLink($link);
        $member=ClubMembership::where('link_id',$link->id)->findOrFail($member);
        $settings=data_get($link->settings,'membership',[]);
        return view('user.links.membership-card',compact('link','member','settings'));
    }
    public function createForm(Link $link) {
        $this->authorizeLink($link);
        DB::transaction(function()use($link){
            $fields=[['id'=>'name','type'=>'text','label'=>'Full name','required'=>true],['id'=>'email','type'=>'email','label'=>'Email','required'=>true],['id'=>'phone','type'=>'text','label'=>'Phone (include country code)','required'=>true]];
            foreach(data_get($link->settings,'membership.fields',[]) as $field) $fields[]=['id'=>$field['key'],'type'=>$field['type'],'label'=>$field['label'],'required'=>(bool)$field['required']];
            $fields[]=['id'=>'profile_picture','type'=>'file','label'=>'Profile picture (optional)','required'=>false,'accept'=>'image/jpeg,image/png,image/webp'];
            $fields[]=['id'=>'consent','type'=>'checkbox','label'=>'I agree to join this club and have a free Sayzio account created when approved.','options'=>['I agree'],'required'=>true];
            $form=new Form(['title'=>($link->title?:$link->alias).' membership','description'=>'Membership applications are reviewed by the club. Approval creates a free Sayzio account or links your existing account.','fields'=>$fields,'design'=>Form::defaultDesign(),'settings'=>[],'is_active'=>true]);
            $form->forceFill(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id])->save();
            $settings=$link->settings??[]; $settings['membership']['form_id']=$form->id; $settings['membership']['mapping']=[]; $link->update(['settings'=>$settings]);
        });
        return back()->with('success','Sayzio membership form created. Copy its URL or add it as a Form block to your club page.');
    }
}
