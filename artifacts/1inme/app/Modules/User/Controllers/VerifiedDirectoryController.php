<?php
namespace App\Modules\User\Controllers;

use App\Modules\User\Models\{User, CreatorProfile, Link, BiolinkBlock, ProfileVerificationRequest, VerificationRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifiedDirectoryController
{
    public function index(Request $request)
    {
        $kind = in_array($request->query('kind'), ['accounts','workspaces','links'], true) ? $request->query('kind') : 'accounts';
        $query = match ($kind) {
            'links' => Link::withoutGlobalScope('workspace')->where('is_verified',true),
            'workspaces' => CreatorProfile::with('workspace')->whereIn('profile_verification_status',['verified','pending_reverification']),
            default => User::whereIn('profile_verification_status',['verified','pending_reverification']),
        };
        if ($request->filled('q')) {
            $term = '%'.mb_substr($request->string('q')->toString(),0,100).'%';
            $query->where(function ($q) use ($kind,$term) {
                if ($kind === 'links') $q->where('title','like',$term)->orWhere('alias','like',$term);
                else $q->where('name','like',$term)->orWhere('handle','like',$term);
            });
        }
        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $directoryPrefix = $request->routeIs('admin.*') ? 'admin.verified-directory' : 'user.verified-directory';
        $directoryLayout = $request->routeIs('admin.*') ? 'admin.layouts.app' : 'user.layouts.app';
        return view('user.verification.verified-directory',compact('kind','items','directoryPrefix','directoryLayout'));
    }

    public function revoke(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind,['accounts','workspaces','links'],true),404);
        $data = $request->validate(['reason'=>'required|string|max:2000']);
        DB::transaction(function () use ($kind,$id,$data) {
            $model = match ($kind) {
                'links' => Link::withoutGlobalScope('workspace')->lockForUpdate()->findOrFail($id),
                'workspaces' => CreatorProfile::lockForUpdate()->findOrFail($id),
                default => User::lockForUpdate()->findOrFail($id),
            };
            if ($kind === 'links') {
                $model->update(['is_verified'=>false,'verified_name'=>null,'verified_logo'=>null]);
                VerificationRequest::where('link_id',$id)->where('status','pending')->update(['status'=>'superseded']);
                BiolinkBlock::where('link_id',$id)->whereIn('type',['verified_heading','verified_avatar','verified_image'])->each(function ($block) {
                    $settings = $block->settings ?? [];
                    $settings['verified'] = false;
                    $settings['url'] = $settings['image_url'] ?? $settings['url'] ?? null;
                    $settings['locked_text'] = false;
                    $settings['locked_image'] = false;
                    $block->update(['type'=>match($block->type) {'verified_heading'=>'heading','verified_avatar'=>'avatar',default=>'image'},'settings'=>$settings]);
                });
            } else {
                $model->update(['profile_verification_status'=>'unverified','profile_verification_type_id'=>null,'profile_verified_at'=>null,'profile_verified_name'=>null,'profile_verified_avatar'=>null]);
                $pending = ProfileVerificationRequest::where('status','pending');
                if ($kind === 'workspaces') $pending->where('creator_profile_id',$id);
                else $pending->where('user_id',$id)->whereNull('creator_profile_id');
                $pending->update(['status'=>'superseded']);
            }
            DB::table('verification_revocations')->insert(['actor_user_id'=>request()->routeIs('admin.*') ? auth('admin')->id() : auth()->id(),'actor_guard'=>request()->routeIs('admin.*') ? 'admin' : 'web','subject_kind'=>$kind,'subject_id'=>$id,'reason'=>$data['reason'],'created_at'=>now()]);
        });
        return back()->with('success','Verification removed. The profile or link remains available without verification.');
    }
}
