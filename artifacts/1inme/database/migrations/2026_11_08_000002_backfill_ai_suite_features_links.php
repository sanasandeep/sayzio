<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Modules\Common\Support\SitePagesContent;

return new class extends Migration {
    public function up(): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row) {
            return;
        }
        $sections = json_decode((string) $row->sections, true);
        if (!is_array($sections)) {
            return;
        }
        $changed = false;
        foreach ($sections as $i => $cat) {
            if (!is_array($cat) || ($cat['id'] ?? '') !== 'ai-suite') {
                continue;
            }
            $needsLinks = false;
            foreach ((array) ($cat['features'] ?? []) as $f) {
                if (!is_array($f) || trim((string) ($f['link'] ?? '')) === '') {
                    $needsLinks = true;
                    break;
                }
            }
            if ($needsLinks) {
                $sections[$i] = SitePagesContent::aiSuiteFeaturesCategory();
                $changed = true;
            }
        }
        if ($changed) {
            DB::table('site_pages')->where('id', $row->id)->update([
                'sections' => json_encode($sections),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: forward-only data fix.
    }
};
