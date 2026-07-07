<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSL/TLS certificate automation state for custom + global domains.
 *
 * The EC2 deployment issues a Let's Encrypt certificate per verified
 * domain via the scheduled `domains:issue-certificates` command (see
 * SslCertificateIssuer). These columns track per-domain issuance state,
 * retry backoff and admin-alert dedup. Additive-only and hasColumn-guarded
 * (shared-RDS safety).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            if (!Schema::hasColumn('domains', 'ssl_status')) {
                // null = never attempted, 'pending' = queued after verify,
                // 'issued' = certificate live, 'failed' = last attempt failed.
                $table->string('ssl_status', 20)->nullable();
            }
            if (!Schema::hasColumn('domains', 'ssl_attempts')) {
                $table->unsignedInteger('ssl_attempts')->default(0);
            }
            if (!Schema::hasColumn('domains', 'ssl_last_attempted_at')) {
                $table->timestamp('ssl_last_attempted_at')->nullable();
            }
            if (!Schema::hasColumn('domains', 'ssl_issued_at')) {
                $table->timestamp('ssl_issued_at')->nullable();
            }
            if (!Schema::hasColumn('domains', 'ssl_last_error')) {
                $table->text('ssl_last_error')->nullable();
            }
            if (!Schema::hasColumn('domains', 'ssl_alerted_at')) {
                $table->timestamp('ssl_alerted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            foreach (['ssl_status', 'ssl_attempts', 'ssl_last_attempted_at', 'ssl_issued_at', 'ssl_last_error', 'ssl_alerted_at'] as $col) {
                if (Schema::hasColumn('domains', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
