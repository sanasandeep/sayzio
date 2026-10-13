<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ClubMembership extends Model {
    protected $guarded = ['id'];
    protected $casts = ['expires_at'=>'date','joined_at'=>'datetime','custom_fields'=>'array'];
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->endOfDay()->isPast(); }
    public function link() { return $this->belongsTo(Link::class); }
}
