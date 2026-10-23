<?php
namespace App\Modules\User\Controllers;
use App\Modules\User\Models\{CreatorProfile, UserFile};
use App\Modules\User\Support\CreatorPhoneIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorPhoneController
{
    private function profile(Request $request): CreatorProfile
    {
        $workspace = app('current_workspace');
        $profile = CreatorProfile::forWorkspace($workspace);
        abort_unless((int)$request->input('creator_profile_id') === (int)$profile->id, 409, 'Workspace changed. Reload the creator profile before continuing.');
        return $profile;
    }
    public function attach(Request $request)
    {
        $data = $request->validate(['phone'=>'required|string|max:60','creator_profile_id'=>'required|integer']);
        CreatorPhoneIdentity::attach($this->profile($request),$request->user(),$data['phone']);
        return back()->with('success','Number attached exclusively to this workspace creator profile.');
    }
    public function dispute(Request $request)
    {
        $data = $request->validate([
            'creator_profile_id'=>'required|integer','phone'=>'required|string|max:60','message'=>'required|string|max:3000',
            'supporting_urls'=>'nullable|string|max:6000','proof_files'=>'nullable|array|max:5',
            'proof_files.*'=>\App\Services\UploadPolicy::rule('verification.proof',$request->user()),
        ]);
        $profile = $this->profile($request);
        $phone = CreatorPhoneIdentity::normalize($data['phone']);
        $urls = array_values(array_filter(preg_split('/\r?\n/',trim($data['supporting_urls'] ?? ''))));
        validator(['supporting_urls'=>$urls], ['supporting_urls'=>'array|max:5','supporting_urls.*'=>'url:http,https|max:2048'])->validate();
        if (DB::table('creator_phone_claims')->where('creator_profile_id',$profile->id)->where('phone',$phone)->where('status','pending')->exists()) return back()->with('info','A claim for this number is already awaiting review.');
        $files = [];
        foreach ($request->file('proof_files',[]) as $file) $files[] = UserFile::createFromUpload($file,$request->user(),['enforce_allowlist'=>false,'upload_key'=>'verification.proof'])->url_path;
        DB::table('creator_phone_claims')->insert(['creator_profile_id'=>$profile->id,'user_id'=>$request->user()->id,'phone'=>$phone,'message'=>$data['message'],'proof_files'=>json_encode($files),'supporting_urls'=>json_encode($urls),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Number ownership request sent to admin. The existing attachment remains until review.');
    }
    public function adminIndex()
    {
        $claims = DB::table('creator_phone_claims')->join('creator_profiles','creator_profiles.id','=','creator_phone_claims.creator_profile_id')->join('workspaces','workspaces.id','=','creator_profiles.workspace_id')
            ->leftJoin('creator_phone_assignments as allocation','allocation.phone','=','creator_phone_claims.phone')->leftJoin('creator_profiles as held_profile','held_profile.id','=','allocation.creator_profile_id')->leftJoin('workspaces as held_workspace','held_workspace.id','=','held_profile.workspace_id')->select('creator_phone_claims.*','creator_profiles.handle','workspaces.name as workspace_name','held_profile.handle as held_handle','held_workspace.name as held_workspace_name')->orderByRaw("CASE WHEN creator_phone_claims.status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('creator_phone_claims.id')->paginate(20);
        $numbers = DB::table('creator_phone_assignments')->join('creator_profiles','creator_profiles.id','=','creator_phone_assignments.creator_profile_id')->join('workspaces','workspaces.id','=','creator_profiles.workspace_id')->select('creator_phone_assignments.*','creator_profiles.handle','workspaces.name as workspace_name')->orderByDesc('creator_phone_assignments.id')->paginate(20,['*'],'numbers_page');
        return view('admin.creator-phone-claims',compact('claims','numbers'));
    }
    public function review(Request $request, int $id)
    {
        $data = $request->validate(['decision'=>'required|in:approve,reject','admin_notes'=>'required|string|max:2000']);
        DB::transaction(function () use ($id,$data) {
            $claim = DB::table('creator_phone_claims')->where('id',$id)->lockForUpdate()->first();
            abort_unless($claim && $claim->status === 'pending',409,'This claim has already been reviewed.');
            if ($data['decision'] === 'approve') CreatorPhoneIdentity::assign(CreatorProfile::findOrFail($claim->creator_profile_id),$claim->phone,(int)auth('admin')->id(),true);
            if ($data['decision'] === 'approve') DB::table('creator_phone_claims')->where('phone',$claim->phone)->where('id','!=',$id)->where('status','pending')->update(['status'=>'superseded','updated_at'=>now()]);
            DB::table('creator_phone_claims')->where('id',$id)->update(['status'=>$data['decision'] === 'approve' ? 'approved' : 'rejected','admin_notes'=>$data['admin_notes'],'reviewed_by'=>auth('admin')->id(),'reviewed_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Ownership review saved.');
    }
    public function revoke(Request $request, int $id)
    {
        $data = $request->validate(['reason'=>'required|string|max:2000']);
        DB::transaction(function () use ($id,$data) {
            $row = DB::table('creator_phone_assignments')->where('id',$id)->lockForUpdate()->first(); abort_unless($row,404);
            DB::table('creator_phone_assignments')->where('id',$id)->update(['creator_profile_id'=>null,'source'=>'admin_revoked','updated_at'=>now()]);
            // A queued claim cannot silently restore an admin-revoked allocation.
            DB::table('creator_phone_claims')->where('phone',$row->phone)->where('status','pending')->update(['status'=>'superseded','updated_at'=>now()]);
            DB::table('verification_revocations')->insert(['actor_user_id'=>auth('admin')->id(),'actor_guard'=>'admin','subject_kind'=>'phones','subject_id'=>$id,'reason'=>$data['reason'],'created_at'=>now()]);
        });
        return back()->with('success','Number attachment revoked. Login phone numbers are unchanged.');
    }
}
