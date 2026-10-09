<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ListingCatalog extends Model {
    protected $fillable = ['link_id','settings'];
    protected function casts(): array { return ['settings'=>'array']; }
    public function categories() { return $this->hasMany(ListingCategory::class,'catalog_id')->orderBy('sort_order')->orderBy('id'); }
    public function entries() { return $this->hasMany(ListingEntry::class,'catalog_id')->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'); }
    public function inquiries() { return $this->hasMany(ListingInquiry::class,'catalog_id')->latest(); }
}
