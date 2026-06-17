<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $updates = [
            'fa-users' => ['value' => '3.75 Lakh', 'suffix' => '+', 'label' => 'Users Worldwide'],
            'fa-link'  => ['value' => '1,05,000',  'suffix' => '+', 'label' => 'Biolinks Created'],
            'fa-bolt'  => ['value' => '1,43 Lakh', 'suffix' => '+', 'label' => 'Analytics Events Tracked'],
            'fa-globe' => ['value' => '67',        'suffix' => '+', 'label' => 'Countries Reached'],
        ];

        foreach ($updates as $icon => $data) {
            DB::table('site_stats')
                ->where('icon', $icon)
                ->update(array_merge($data, ['updated_at' => $now]));
        }
    }

    public function down(): void
    {
        // No-op: previous marketing values are not restored.
    }
};
