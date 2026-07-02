<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class LinkAlias extends Model
{
    protected $fillable = ['link_id', 'alias', 'domain_id'];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
