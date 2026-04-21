<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class SplashPage extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'project_id', 'name', 'title', 'description',
        'cta_label', 'cta_url', 'auto_redirect', 'countdown',
        'logo', 'favicon', 'og_image', 'custom_css', 'custom_js',
    ];

    protected function casts(): array
    {
        return [
            'auto_redirect' => 'boolean',
            'countdown'     => 'integer',
        ];
    }

    public function user()    { return $this->belongsTo(User::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function links()   { return $this->hasMany(Link::class); }

    /** Array shape consumed by resources/views/common/splash.blade.php */
    public function toRenderArray(): array
    {
        return [
            'enabled'       => true,
            'title'         => $this->title,
            'description'   => $this->description,
            'cta_label'     => $this->cta_label,
            'cta_url'       => $this->cta_url,
            'auto_redirect' => (bool) $this->auto_redirect,
            'countdown'     => (int) $this->countdown,
            'logo'          => $this->logo,
            'favicon'       => $this->favicon,
            'og_image'      => $this->og_image,
            'custom_css'    => $this->custom_css,
            'custom_js'     => $this->custom_js,
        ];
    }
}
