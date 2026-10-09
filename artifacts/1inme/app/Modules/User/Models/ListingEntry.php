<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ListingEntry extends Model {
    protected $fillable = ['catalog_id','category_id','title','details','is_active','is_featured','sort_order'];
    protected function casts(): array { return ['details'=>'array','is_active'=>'boolean','is_featured'=>'boolean']; }
    public function acceptsInquiries(bool $property): bool {
        return $this->is_active && in_array($this->details['status'] ?? '', $property ? ['available'] : ['enrolling'], true);
    }
}
