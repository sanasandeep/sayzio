<?php
namespace App\Modules\User\Services;
use App\Modules\User\Models\{ClubMembership,Link,LinkedIdentifier,User};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class ClubMembershipImportService {
    public const HEADERS=['name','email','phone','status','expires_at','custom_fields'];
    public function import(Link $link, string $path, string $mode='update'): array {
        $handle=fopen($path,'r');
        try {
            $headers=fgetcsv($handle,0,',','"','');
            if(!$headers) throw ValidationException::withMessages(['csv'=>'The CSV is empty.']);
            $headers=array_map(fn($v)=>strtolower(trim(ltrim($v,"\xEF\xBB\xBF"))),$headers);
            if(count($headers)!==count(array_unique($headers)) || array_diff(['name','email','phone'],$headers)) throw ValidationException::withMessages(['csv'=>'Include unique name, email and phone column headings.']);
            $rows=[];
            while(($row=fgetcsv($handle,0,',','"',''))!==false) {
                if(count($row)===1 && trim((string)$row[0])==='') continue;
                $rows[]=$row;
                if(count($rows)>2000) throw ValidationException::withMessages(['csv'=>'Import at most 2,000 members per file.']);
            }
        } finally {fclose($handle);}
        $report=['created'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];$seenEmail=[];$seenPhone=[];
        foreach($rows as $index=>$row) {
            try {
                if(count($row)!==count($headers)) throw ValidationException::withMessages(['row'=>'Column count does not match the headings.']);
                $input=array_combine($headers,array_map('trim',$row));
                // Reverse the spreadsheet-safety prefix used by our own exporter.
                foreach($input as &$cell) if(preg_match("/^'[=+@\\-]/",$cell)) $cell=substr($cell,1);
                unset($cell);
                $email=strtolower($input['email']);$phone=LinkedIdentifier::normalize('phone',$input['phone']);
                if(isset($seenEmail[$email]) || ($phone && isset($seenPhone[$phone]))) throw ValidationException::withMessages(['row'=>'Duplicate email or phone from an earlier row in this file; review these rows together.']);
                $seenEmail[$email]=true;if($phone) $seenPhone[$phone]=true;
                $outcome=DB::transaction(function()use($link,$input,$email,$phone,$mode){
                    DB::table('links')->where('id',$link->id)->lockForUpdate()->first();
                    $member=ClubMembership::where('link_id',$link->id)->whereRaw('lower(email) = ?',[$email])->first();
                    if($member && $mode==='skip') return 'skipped';
                    // A phone-only match must not silently change identity or merge members.
                    if($phone && ClubMembership::where('link_id',$link->id)->when($member,fn($q)=>$q->whereKeyNot($member->id))->get(['phone'])->contains(fn($m)=>LinkedIdentifier::normalize('phone',$m->phone)===$phone)) throw ValidationException::withMessages(['phone'=>'This phone matches another club member. Review the email/phone conflict manually.']);
                    if($phone) {
                        $account=User::whereRaw('lower(email) = ?',[$email])->first();
                        $emailIdentity=LinkedIdentifier::where('kind','email')->where('value',$email)->first();
                        $accountId=$member?->account_id??$account?->id??$emailIdentity?->user_id;
                        $phoneOwner=LinkedIdentifier::where('kind','phone')->where('value',$phone)->first();
                        $mobileOwner=User::where('mobile',$phone)->first();
                        if(($phoneOwner && $phoneOwner->user_id!==$accountId)||($mobileOwner && $mobileOwner->id!==$accountId)) throw ValidationException::withMessages(['phone'=>'This phone belongs to a different Sayzio account. Review the identity conflict manually.']);
                    }
                    $data=['name'=>$member?->name,'email'=>$email,'phone'=>$member?->phone,'status'=>$member?->status??(data_get($link->settings,'membership.approval','manual')==='automatic'?'active':'pending'),'expires_at'=>$member?->expires_at?->toDateString(),'custom_fields'=>$member?->custom_fields??[]];
                    foreach(['name','phone','status','expires_at'] as $key) if(($input[$key]??'')!=='') $data[$key]=$input[$key];
                    if(($input['custom_fields']??'')!=='') {
                        try {$custom=json_decode($input['custom_fields'],true,32,JSON_THROW_ON_ERROR);} catch(\JsonException $e) {throw ValidationException::withMessages(['custom_fields'=>'Custom fields must be a JSON object.']);}
                        if(!is_array($custom)||array_is_list($custom) && $custom!==[]) throw ValidationException::withMessages(['custom_fields'=>'Custom fields must be a JSON object.']);
                        $data['custom_fields']=array_replace($data['custom_fields'],$custom);
                    }
                    $service=app(ClubMembershipService::class);
                    Validator::make($data,[...$service->rules($link),'status'=>'required|in:pending,active,cancelled','expires_at'=>'nullable|date'])->validate();
                    $service->save($link,$data,$member);
                    return $member?'updated':'created';
                });
                $report[$outcome]++;
            } catch(ValidationException $e) {
                $report['skipped']++;
                if(count($report['errors'])<100) $report['errors'][]=['row'=>$index+2,'message'=>implode(' ',array_merge(...array_values($e->errors())))];
            }
        }
        return $report;
    }
}
