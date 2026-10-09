<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class DirectoryCategory extends Model {
    protected $fillable = ['directory_id', 'parent_id', 'name', 'sort_order'];
}
