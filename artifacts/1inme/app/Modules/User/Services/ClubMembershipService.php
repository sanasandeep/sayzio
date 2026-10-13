<?php
namespace App\Modules\User\Services;
use App\Modules\User\Models\{ClubMembership, Link, Form, FormSubmission, User, LinkedIdentifier};
use App\Modules\Admin\Models\Plan;
use Illuminate\Support\Facades\{DB, Hash, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClubMembershipService {
    public function rules(Link $link): array {
        $rules=['name'=>'required|string|max:150','email'=>'required|email|max:255','phone'=>['required','string','regex:/^\+?[0-9 ()\.\-]{7,30}$/', function($attribute,$value,$fail) { if(strlen(preg_replace('/\D/','',$value))<7) $fail('Enter a valid phone number.'); }],'custom_fields'=>'nullable|array'];
        foreach (data_get($link->settings,'membership.fields',[]) as $field) {
            $type=$field['type'];
            $rules['custom_fields.'.$field['key']]=[empty($field['required'])?'nullable':'required',match($type){'number'=>'numeric','date'=>'date','email'=>'email',default=>'string'},'max:'.($type==='number'?'1000000000':'2000')];
        }
        return $rules;
    }
    public function save(Link $link, array $data, ?ClubMembership $member=null): ClubMembership {
        return DB::transaction(function() use($link,$data,$member) {
            DB::table('links')->where('id',$link->id)->lockForUpdate()->first();
            $email=strtolower(trim($data['email']));
            $duplicate=ClubMembership::where('link_id',$link->id)->where('email',$email)->when($member,fn($q)=>$q->where('id','!=',$member->id))->exists();
            if($duplicate) throw ValidationException::withMessages(['email'=>'This email already has a membership. Edit the existing member.']);
            $member ??= new ClubMembership(['link_id'=>$link->id]);
            // Once linked, identity changes must go through the account owner.
            if($member->account_id && $member->email!==$email) throw ValidationException::withMessages(['email'=>'The linked account email cannot be changed here.']);
            $member->fill([...$data,'email'=>$email]);
            if($data['status']==='active' && !$member->account_id) {
                $account=User::whereRaw('lower(email) = ?',[$email])->first();
                $identity=LinkedIdentifier::where('kind','email')->where('value',$email)->first();
                if(!$account && $identity) $account=User::find($identity->user_id);
                if(!$account) {
                    $phone=LinkedIdentifier::normalize('phone',$data['phone']);
                    if(LinkedIdentifier::where('kind','phone')->where('value',$phone)->exists() || User::where('mobile',$phone)->exists()) throw ValidationException::withMessages(['phone'=>'This phone belongs to another Sayzio account. Use that account’s email.']);
                    $freePlan=Plan::defaultPlan();
                    if($freePlan && ((float)$freePlan->monthly_price>0 || (float)$freePlan->annual_price>0)) $freePlan=Plan::query()->where('monthly_price',0)->where('annual_price',0)->where('is_archived',false)->where('is_internal',false)->active()->ordered()->first();
                    $account=User::create(['name'=>$data['name'],'email'=>$email,'mobile'=>$phone,'password'=>Hash::make(Str::random(64)),'password_set_at'=>null,'plan_id'=>$freePlan?->id,'status'=>'active','referral_code'=>app(ReferralService::class)->generateUniqueCode()]);
                    // User creation hooks attach identifiers; imported details have not been verified.
                    LinkedIdentifier::where('user_id',$account->id)->update(['verified_at'=>null]);
                    $account->ensureDefaultWorkspace();
                }
                $member->account_id=$account->id;
            }
            if($data['status']==='active' && !$member->joined_at) {
                $member->joined_at=now();
                if(empty($data['expires_at'])) $member->expires_at=$this->expiry($link,now());
            }
            $member->save(); return $member;
        });
    }
    public function expiry(Link $link, $from) {
        $config=data_get($link->settings,'membership',[]);
        if(($config['duration']??'lifetime')==='lifetime') return null;
        return $from->copy()->{match($config['duration_unit']??'months') {'days'=>'addDays','years'=>'addYearsNoOverflow',default=>'addMonthsNoOverflow'}}((int)($config['duration_count']??12));
    }
    public function renew(Link $link, ClubMembership $member): ClubMembership {
        if(data_get($link->settings,'membership.renewal','renewable')!=='renewable' || data_get($link->settings,'membership.duration','lifetime')==='lifetime') throw ValidationException::withMessages(['renewal'=>'This membership does not allow renewal.']);
        return DB::transaction(function()use($link,$member) {
            $member=ClubMembership::where('link_id',$link->id)->lockForUpdate()->findOrFail($member->id);
            if($member->status!=='active') throw ValidationException::withMessages(['renewal'=>'Approve this member before renewing.']);
            $from=$member->expires_at && $member->expires_at->isFuture() ? $member->expires_at : now();
            $member->expires_at=$this->expiry($link,$from);
            $member->renewed_at=now();
            if($member->invoice_id) $member->invoice_history=[...($member->invoice_history??[]),$member->invoice_id];
            $member->invoice_id=null; $member->save(); return $member;
        });
    }
    public function capture(FormSubmission $submission): void {
        if($submission->is_spam || !in_array($submission->payment_status,['none','paid'],true)) return;
        $form=Form::withoutGlobalScope('workspace')->find($submission->form_id);
        if(!$form) return;
        $links=Link::withoutGlobalScope('workspace')->where('type',Link::TYPE_COMMUNITY)->where('user_id',$form->user_id)->where('workspace_id',$form->workspace_id)->where('settings->membership->form_id',$form->id)->get();
        foreach($links as $link) {
            $config=data_get($link->settings,'membership',[]);
            if((int)($config['form_id']??0)!==$form->id) continue;
            $values=$submission->data??[]; $map=$config['mapping']??[];
            $data=[]; foreach(['name','email','phone'] as $key) $data[$key]=$values[$map[$key]??$key]??null;
            $data['custom_fields']=$values;
            foreach($config['fields']??[] as $field) $data['custom_fields'][$field['key']]=$values[$map[$field['key']]??$field['key']]??null;
            $validator=Validator::make($data,$this->rules($link));
            if($validator->fails()) continue; // Original submission remains available for manual review.
            $email=strtolower(trim($data['email']));
            if(ClubMembership::where('link_id',$link->id)->where('email',$email)->exists()) continue;
            try {
                $this->save($link,[...$data,'email'=>$email,'status'=>($config['approval']??'manual')==='automatic'?'active':'pending','submission_id'=>$submission->id]);
            } catch (ValidationException $e) {
                // Keep the application reviewable when account identity conflicts prevent activation.
                ClubMembership::firstOrCreate(['link_id'=>$link->id,'email'=>$email],[...$data,'status'=>'pending','submission_id'=>$submission->id]);
            }
        }
    }
}
