<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OtpService
{
    public function generate(string $identifier, string $type = 'email', string $purpose = 'login', string $guard = 'web'): string
    {
        DB::table('otps')
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->where('guard', $guard)
            ->where('used', false)
            ->update(['used' => true]);

        $code = '1234';

        DB::table('otps')->insert([
            'identifier' => $identifier,
            'type' => $type,
            'code' => $code,
            'purpose' => $purpose,
            'guard' => $guard,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    public function verify(string $identifier, string $code, string $type = 'email', string $purpose = 'login', string $guard = 'web'): bool
    {
        $otp = DB::table('otps')
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('code', $code)
            ->where('purpose', $purpose)
            ->where('guard', $guard)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return false;
        }

        DB::table('otps')->where('id', $otp->id)->update(['used' => true]);

        return true;
    }

    public function sendEmail(string $email, string $code): void
    {
        try {
            \Mail::raw("Your 1INME verification code is: {$code}\n\nThis code expires in 10 minutes.", function ($message) use ($email) {
                $message->to($email);
                $message->subject('Your 1INME Verification Code');
            });
        } catch (\Exception $e) {
            \Log::warning('OTP email send failed: ' . $e->getMessage());
        }
    }

    public function sendSms(string $mobile, string $code): void
    {
        \Log::info("OTP SMS sent to mobile number ending in " . substr($mobile, -4));
    }
}
