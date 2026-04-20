<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'sections'];

    protected function casts(): array
    {
        return ['sections' => 'array'];
    }

    public function faqs()
    {
        return FaqItem::where('page_slug', $this->slug)->orderBy('sort_order')->orderBy('id')->get();
    }
}
