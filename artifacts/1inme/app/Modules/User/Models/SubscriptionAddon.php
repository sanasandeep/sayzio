<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Addon;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAddon extends Model
{
    protected $fillable = ['subscription_id', 'addon_id', 'qty'];

    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function addon()        { return $this->belongsTo(Addon::class); }
}
