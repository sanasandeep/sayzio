<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class ListingInquiry extends Model {
    protected $fillable = ['catalog_id','entry_id','entry_title','name','email','phone','message','preferred_date','batch','status'];
    protected function casts(): array { return ['preferred_date'=>'date']; }
}
