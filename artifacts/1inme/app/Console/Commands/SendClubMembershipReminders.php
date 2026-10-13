<?php
namespace App\Console\Commands;
use App\Modules\User\Models\{ClubMembership,Link};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Mail,Log};
class SendClubMembershipReminders extends Command {
    protected $signature='clubs:send-membership-reminders';
    protected $description='Send configured membership expiry reminders, once per member, expiry date and lead time.';
    public function handle(): int {
        foreach(ClubMembership::where('status','active')->whereDate('expires_at','>=',today())->whereDate('expires_at','<=',today()->addDays(365))->cursor() as $member) {
            $link=Link::withoutGlobalScope('workspace')->find($member->link_id);
            $config=data_get($link?->settings,'membership',[]);
            if(!$link || empty($config['reminders_enabled'])) continue;
            $days=(int)today()->diffInDays($member->expires_at,false);
            if(!in_array($days,array_map('intval',$config['reminder_days']??[]),true)) continue;
            $key=['club_membership_id'=>$member->id,'expiry'=>$member->expires_at->toDateString(),'lead_days'=>$days];
            try {
                // Lock the membership to serialize overlapping scheduler runs. A failed send rolls back the stamp.
                DB::transaction(function()use($member,$link,$key){
                    $fresh=ClubMembership::lockForUpdate()->find($member->id);
                    if(!$fresh || $fresh->status!=='active' || $fresh->expires_at->toDateString()!==$key['expiry'] || DB::table('club_membership_reminders')->where($key)->exists()) return;
                    $body="Hello {$member->name},\n\nYour membership with ".($link->title?:$link->alias)." expires on {$key['expiry']}. Please contact the club to discuss your membership.\n\n".$link->getShortUrl();
                    Mail::raw($body,fn($mail)=>$mail->to($member->email)->subject('Membership expiry reminder'));
                    DB::table('club_membership_reminders')->insert([...$key,'sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
                });
            } catch(\Throwable $e) {Log::warning('Club reminder failed',['membership_id'=>$member->id,'exception'=>get_class($e)]);}
        }
        return self::SUCCESS;
    }
}
