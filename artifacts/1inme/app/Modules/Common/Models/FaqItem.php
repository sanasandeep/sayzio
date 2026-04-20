<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class FaqItem extends Model
{
    protected $fillable = ['page_slug', 'question', 'answer', 'sort_order'];
}
