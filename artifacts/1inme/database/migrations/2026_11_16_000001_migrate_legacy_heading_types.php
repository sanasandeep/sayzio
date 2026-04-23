<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'heading_gradient' => 'gradient',
            'heading_morph'    => 'animated',
        ];

        foreach ($map as $legacyType => $style) {
            DB::table('biolink_blocks')
                ->where('type', $legacyType)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($legacyType, $style) {
                    foreach ($rows as $row) {
                        $settings = json_decode($row->settings ?? '[]', true);
                        if (!is_array($settings)) {
                            $settings = [];
                        }
                        if (empty($settings['style'])) {
                            $settings['style'] = $style;
                        }
                        DB::table('biolink_blocks')
                            ->where('id', $row->id)
                            ->update([
                                'type'     => 'heading',
                                'settings' => json_encode($settings),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Irreversible: legacy heading types are no longer supported.
    }
};
