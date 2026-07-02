<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A visitor who left their email in the mobile-app "coming soon" modal to be
 * notified when the app ships to the stores. Public signup — no account
 * required — so rows carry only the email plus light context (which store
 * badge they clicked, ip/user-agent for abuse triage).
 */
class AppLaunchSignup extends Model
{
    protected $fillable = ['email', 'store', 'ip', 'user_agent', 'notified_at'];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
