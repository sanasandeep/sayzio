<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ContactDirectory extends Model {
    protected $fillable = ['link_id', 'settings'];
    protected function casts(): array { return ['settings' => 'array']; }
    public function categories() { return $this->hasMany(DirectoryCategory::class, 'directory_id')->orderBy('sort_order')->orderBy('id'); }
    public function contacts() { return $this->hasMany(DirectoryContact::class, 'directory_id')->orderByDesc('is_pinned')->orderBy('sort_order')->orderBy('id'); }
}
