<?php

namespace App\Modules\Common\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class NotificationBroadcast extends Model
{
    protected $fillable = [
        'admin_id', 'target_kind', 'target_value',
        'type', 'subject', 'body', 'target_url',
        'recipients_count',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
