<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $authKey;
    protected string $senderId;
    protected string $route;

    public function __construct()
    {
        $this->authKey = config('services.msg91.auth_key', '');
        $this->senderId = config('services.msg91.sender_id', 'Sayzio');
        $this->route = config('services.msg91.route', '4');
    }

    public function send(string $phone, string $message): bool
    {
        if (empty($this->authKey)) {
            Log::warning('MSG91 auth key not configured. SMS not sent.');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'authkey' => $this->authKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.msg91.com/api/v5/flow/', [
                'sender' => $this->senderId,
                'route' => $this->route,
                'mobiles' => $phone,
                'message' => $message,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }
}
