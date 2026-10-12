<?php
namespace App\Modules\User\Services;

use App\Modules\Admin\Models\AppSetting;

class BusinessStarterCatalog
{
    public static function all(): array
    {
        $defaults = json_decode(file_get_contents(__DIR__.'/business-starters.json'), true, 512, JSON_THROW_ON_ERROR);
        return array_replace($defaults, AppSetting::get('business_starters', []));
    }

    public static function forType(string $type): array
    {
        return self::all()[$type] ?? [];
    }
}
