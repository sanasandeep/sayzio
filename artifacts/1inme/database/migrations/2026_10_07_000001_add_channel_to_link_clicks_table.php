<?php

use App\Modules\Common\Services\ChannelClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->string('channel', 32)->nullable()->after('user_agent');
            $table->index(['link_id', 'channel']);
        });

        // Backfill: derive a normalized channel from the stored UA for any
        // existing row that already has one. Done in chunks so this still
        // completes on large click logs without exhausting memory.
        DB::table('link_clicks')
            ->whereNotNull('user_agent')
            ->whereNull('channel')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) {
                $byChannel = [];
                foreach ($rows as $r) {
                    $byChannel[ChannelClassifier::classify($r->user_agent)][] = $r->id;
                }
                foreach ($byChannel as $channel => $ids) {
                    DB::table('link_clicks')->whereIn('id', $ids)->update(['channel' => $channel]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'channel']);
            $table->dropColumn('channel');
        });
    }
};
