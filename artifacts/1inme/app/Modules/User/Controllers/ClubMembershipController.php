<?php
namespace App\Modules\User\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\User\Models\{Link, ClubMembership, Form};
use App\Modules\User\Services\ClubMembershipService;
use Illuminate\Http\Request;
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
        $members=ClubMembership::where('link_id',$link->id)->when($request->filled('q'),function($q)use($request){$term='%'.$request->string('q')->substr(0,150).'%';$q->where(fn($q)=>$q->where('name','like',$term)->orWhere('email','like',$term)->orWhere('phone','like',$term));})->latest()->paginate(20)->withQueryString();
        $edit=$request->filled('edit')?ClubMembership::where('link_id',$link->id)->findOrFail($request->input('edit')):null;
        return view('user.links.memberships',compact('link','settings','forms','members','edit'));
    }
    public function save(Request $request, Link $link, ClubMembershipService $service) {
        $this->authorizeLink($link);
        $member=$request->filled('id')?ClubMembership::where('link_id',$link->id)->findOrFail($request->input('id')):null;
        $data=$request->validate([...$service->rules($link),'expires_at'=>'nullable|date','status'=>'required|in:pending,active,cancelled']);
        $allowed=array_column(data_get($link->settings,'membership.fields',[]),'key');
        $data['custom_fields']=array_intersect_key($data['custom_fields']??[],array_flip($allowed));
        $service->save($link,$data,$member);
        return redirect()->route('user.links.memberships',$link)->with('success','Membership saved. Active members are linked to a free or existing Sayzio account.');
    }
    public function settings(Request $request, Link $link) {
        $this->authorizeLink($link);
        $data=$request->validate(['fields'=>'nullable|array|max:20','fields.*.key'=>['required','distinct','regex:/^[a-z][a-z0-9_]{0,39}$/',Rule::notIn(['name','email','phone','consent'])],'fields.*.label'=>'required|string|max:100','fields.*.type'=>'required|in:text,textarea,number,date,email','fields.*.required'=>'required|boolean','form_id'=>'nullable|integer','mapping'=>'nullable|array','mapping.*'=>'nullable|string|max:100']);
        if($request->filled('form_id')) abort_unless(auth()->user()->canInWorkspace(app('current_workspace'),'inbox.edit'),403);
        $form=$request->filled('form_id')?Form::where('user_id',$link->user_id)->findOrFail($data['form_id']):null;
        $mapping=array_intersect_key($data['mapping']??[],array_flip(['name','email','phone',...array_column($data['fields']??[],'key')]));
        if($form) {
            $fields=collect($form->fields??[])->keyBy('id');
            foreach(['name','email','phone',...array_column(array_filter($data['fields']??[],fn($f)=>$f['required']),'key')] as $key) {
                $id=$mapping[$key]??$key;
                if(!$fields->has($id) || empty($fields[$id]['required'])) throw ValidationException::withMessages(['mapping'=>'Map each required membership field to a required field in the selected Sayzio Form.']);
            }
            foreach($mapping as $id) if($id && !$fields->has($id)) throw ValidationException::withMessages(['mapping'=>'A mapped field does not exist on this form.']);
        }
        $settings=$link->settings??[];
        $settings['membership']=['fields'=>$data['fields']??[],'form_id'=>$form?->id,'mapping'=>$mapping];
        $link->update(['settings'=>$settings]);
        return back()->with('success','Membership form settings saved.');
    }
    public function createForm(Link $link) {
        $this->authorizeLink($link);
        DB::transaction(function()use($link){
            $fields=[['id'=>'name','type'=>'text','label'=>'Full name','required'=>true],['id'=>'email','type'=>'email','label'=>'Email','required'=>true],['id'=>'phone','type'=>'text','label'=>'Phone (include country code)','required'=>true]];
            foreach(data_get($link->settings,'membership.fields',[]) as $field) $fields[]=['id'=>$field['key'],'type'=>$field['type'],'label'=>$field['label'],'required'=>(bool)$field['required']];
            $fields[]=['id'=>'consent','type'=>'checkbox','label'=>'I agree to join this club and have a free Sayzio account created when approved.','options'=>['I agree'],'required'=>true];
            $form=new Form(['title'=>($link->title?:$link->alias).' membership','description'=>'Membership applications are reviewed by the club. Approval creates a free Sayzio account or links your existing account.','fields'=>$fields,'design'=>Form::defaultDesign(),'settings'=>[],'is_active'=>true]);
            $form->forceFill(['user_id'=>$link->user_id,'workspace_id'=>$link->workspace_id])->save();
            $settings=$link->settings??[]; $settings['membership']['form_id']=$form->id; $settings['membership']['mapping']=[]; $link->update(['settings'=>$settings]);
        });
        return back()->with('success','Sayzio membership form created. Copy its URL or add it as a Form block to your club page.');
    }
}
