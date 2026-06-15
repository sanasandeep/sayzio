<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewMedia extends Model
{
    protected $table = 'review_media';

    protected $fillable = ['review_id', 'type', 'url', 'meta', 'sort_order'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
