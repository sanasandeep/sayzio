<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_files', function (Blueprint $t) {
            // pending: scan not finished yet (file isn't downloadable)
            // clean:   passed virus + phishing heuristics
            // flagged: failed at least one check, gated behind explicit confirm
            // skipped: scan couldn't run (e.g. file removed) — treated as clean
            $t->string('scan_status', 16)->default('pending')->index();
            $t->string('scan_reason', 64)->nullable();
            $t->json('scan_meta')->nullable();
            $t->timestamp('scanned_at')->nullable();
            $t->timestamp('quarantined_at')->nullable();
            $t->boolean('scan_admin_reviewed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $t) {
            $t->dropColumn([
                'scan_status', 'scan_reason', 'scan_meta',
                'scanned_at', 'quarantined_at', 'scan_admin_reviewed',
            ]);
        });
    }
};
