<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ClubMembership extends Model {
    protected $guarded = ['id'];
    protected $casts = ['expires_at'=>'date','joined_at'=>'datetime','custom_fields'=>'array','renewed_at'=>'datetime','invoice_history'=>'array'];
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->endOfDay()->isPast(); }
    public function profilePhoto() { return $this->belongsTo(UserFile::class,'profile_photo_file_id'); }
    public function link() { return $this->belongsTo(Link::class); }
}
