<?php

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\SitemapController;
use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $map = AppSetting::get(MarketingSeo::SETTING_KEY, []);
        if (! is_array($map) || ! is_array($map['home'] ?? null)) {
            return;
        }
        $original = $map;
        $defaults = MarketingSeo::codeDrivenDefaults()['home'];
        if (in_array($map['home']['title'] ?? '', ['Sayzio – All-in-One Link in Bio, QR Codes & Short Links', 'Sayzio — All-in-One Link in Bio, QR Codes & Short Links', 'Link in Bio, Short Links & QR Codes'], true)) {
            $map['home']['title'] = $defaults['title'];
        }
        if (($map['home']['description'] ?? '') === 'Sayzio is the AI-powered digital presence platform: build your Link in Bio, short links and QR codes, then let Zio answer visitors and capture leads.') {
            $map['home']['description'] = $defaults['description'];
        }
        if ($map !== $original) {
            AppSetting::put(MarketingSeo::SETTING_KEY, $map);
            SitemapController::flushPublicCaches();
        }
    }

    public function down(): void {}
};
