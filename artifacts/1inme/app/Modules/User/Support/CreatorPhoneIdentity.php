<?php
namespace App\Modules\User\Support;

use App\Modules\User\Models\{CreatorProfile, LinkedIdentifier, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exclusive caller-identity registry. Contact/WhatsApp links are not ownership claims. */
final class CreatorPhoneIdentity
{
    public static function normalize(string $raw): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($raw));
        if (str_starts_with($phone, '00')) $phone = '+'.substr($phone,2);
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone)) {
            throw ValidationException::withMessages(['phone'=>'Use the international number with + and country code, for example +919876543210.']);
        }
        return $phone;
    }

    public static function attach(CreatorProfile $profile, User $actor, string $raw): void
    {
        $phone = self::normalize($raw);
        $verified = LinkedIdentifier::where('user_id',$actor->id)->where('kind','phone')->whereNotNull('verified_at')->pluck('value')
            ->contains(function ($value) use ($phone) {
                try { return self::normalize($value) === $phone; } catch (ValidationException) { return false; }
            });
        if (!$verified) throw ValidationException::withMessages(['phone'=>'Verify this number in Connected Accounts first, or submit ownership evidence for admin review.']);
        self::assign($profile, $phone, (int)$actor->id, false);
    }

    public static function assign(CreatorProfile $profile, string $phone, int $actorId, bool $reviewed): void
    {
        $phone = self::normalize($phone);
        DB::transaction(function () use ($profile,$phone,$actorId,$reviewed) {
            // insertOrIgnore + UNIQUE(phone) makes simultaneous claims serialize on the same row.
            DB::table('creator_phone_assignments')->insertOrIgnore(['phone'=>$phone,'creator_profile_id'=>null,'source'=>'unassigned','created_at'=>now(),'updated_at'=>now()]);
            $row = DB::table('creator_phone_assignments')->where('phone',$phone)->lockForUpdate()->first();
            if (!$reviewed && $row->source === 'admin_revoked') throw ValidationException::withMessages(['phone'=>'Admin revoked this allocation. Submit an ownership request before attaching it again.']);
            if ($row->creator_profile_id && (int)$row->creator_profile_id !== (int)$profile->id) {
                if (!$reviewed) throw ValidationException::withMessages(['phone'=>'This number is already attached to another creator profile. Submit an ownership dispute for admin review.']);
                DB::table('verification_revocations')->insert(['actor_user_id'=>$actorId,'actor_guard'=>'admin','subject_kind'=>'phones','subject_id'=>$row->id,'reason'=>'Ownership dispute approved; previous creator profile '.$row->creator_profile_id.' revoked.','created_at'=>now()]);
            }
            DB::table('creator_phone_assignments')->where('id',$row->id)->update(['creator_profile_id'=>$profile->id,'attached_by'=>$actorId,'source'=>$reviewed ? 'admin_review' : 'verified_identifier','updated_at'=>now()]);
        });
    }
}
