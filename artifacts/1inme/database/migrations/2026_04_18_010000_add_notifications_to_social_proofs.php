<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_proofs', function (Blueprint $table) {
            // List of notifications belonging to this campaign. Each entry:
            // {id, type, name, settings, triggers, triggers_logic, design_override, is_active, sort_order}
            $table->json('notifications')->nullable()->after('settings');
        });

        Schema::table('social_proof_events', function (Blueprint $table) {
            // Optional per-notification id (uuid string from the notifications array)
            $table->string('notification_id', 64)->nullable()->after('social_proof_id');
            $table->index(['social_proof_id', 'notification_id']);
        });

        // Backfill: move each existing campaign's single-type config into a
        // one-element notifications array so the new runtime can read it.
        $rows = DB::table('social_proofs')->select('id', 'type', 'settings', 'name')->get();
        foreach ($rows as $r) {
            $settings = $r->settings ? json_decode($r->settings, true) : [];

            // For recent_activity we used to keep the item pool in the related
            // social_proof_items table. Move those into settings.pool inline.
            if ($r->type === 'recent_activity') {
                $items = DB::table('social_proof_items')
                    ->where('social_proof_id', $r->id)
                    ->orderBy('sort_order')
                    ->get();
                if ($items->isNotEmpty()) {
                    $settings['pool'] = $items->map(fn($i) => [
                        'name'       => $i->name,
                        'location'   => $i->location,
                        'action'     => $i->action,
                        'image_url'  => $i->image_url,
                        'link_url'   => $i->link_url,
                        'time_label' => $i->time_label,
                    ])->values()->toArray();
                }
            }

            $notifications = [[
                'id'              => (string) Str::uuid(),
                'type'            => $r->type ?: 'recent_activity',
                'name'            => $r->name ?: 'Notification',
                'settings'        => $settings,
                'design_override' => new stdClass(), // empty object — falls back to campaign design
                'triggers'        => [['kind' => 'on_load', 'params' => new stdClass()]],
                'triggers_logic'  => 'or',
                'is_active'       => true,
                'sort_order'      => 0,
            ]];

            DB::table('social_proofs')
                ->where('id', $r->id)
                ->update(['notifications' => json_encode($notifications)]);
        }
    }

    public function down(): void
    {
        Schema::table('social_proof_events', function (Blueprint $table) {
            $table->dropIndex(['social_proof_id', 'notification_id']);
            $table->dropColumn('notification_id');
        });
        Schema::table('social_proofs', function (Blueprint $table) {
            $table->dropColumn('notifications');
        });
    }
};
