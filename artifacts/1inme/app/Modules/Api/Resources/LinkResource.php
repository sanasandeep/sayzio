<?php

namespace App\Modules\Api\Resources;

use App\Modules\User\Models\Link;

class LinkResource
{
    public static function toArray(Link $l): array
    {
        return [
            'id'              => $l->id,
            'type'            => $l->type,
            'alias'           => $l->alias,
            'title'           => $l->title,
            'long_url'        => $l->long_url,
            'visibility'      => $l->visibility ?? 'public',
            'is_active'       => (bool) $l->is_active,
            'is_verified'     => (bool) $l->is_verified,
            'is_password_protected' => (bool) $l->is_password_protected,
            'expires_at'      => optional($l->expires_at)->toIso8601String(),
            'total_clicks'    => (int) $l->total_clicks,
            'unique_clicks'   => (int) $l->unique_clicks,
            'seo_title'       => $l->seo_title,
            'seo_description' => $l->seo_description,
            'seo_image'       => $l->seo_image,
            'short_url'       => url('/' . $l->alias),
            'created_at'      => optional($l->created_at)->toIso8601String(),
            'updated_at'      => optional($l->updated_at)->toIso8601String(),
        ];
    }
}
