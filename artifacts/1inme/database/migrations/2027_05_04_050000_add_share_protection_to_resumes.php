<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds optional share-expiration + revocation versioning to resumes,
 * and creates a per-view audit log for the management UI.
 *
 * - `expires_at` — when set and visibility=password, public visitors
 *    are blocked after this moment (owner is unaffected). Null disables
 *    expiration. Mirrors how Link.expires_at works.
 * - `share_revision` — bumped when the owner clicks "Revoke share";
 *    the unlocked-session key embeds this revision so previously
 *    unlocked visitors are forced back to the password prompt without
 *    changing the public URL.
 *
 * The `resume_views` table records one row per unique daily visitor
 * (the same dedup we already use for view_count), so the management UI
 * can show an audit log without inflating count from refreshes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('password');
            $table->unsignedInteger('share_revision')->default(0)->after('expires_at');
        });

        Schema::create('resume_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            // When the visitor was logged into a Sayzio viewer-session
            // we capture both id and the (then-current) handle so the
            // log stays meaningful even if the user later renames.
            $table->unsignedBigInteger('viewer_user_id')->nullable();
            $table->string('viewer_handle', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->string('user_agent', 255)->nullable();
            // SHA-1 of the client IP — never the raw IP — so owners
            // can dedupe distinct visitors without us hoarding PII.
            $table->string('ip_hash', 40)->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['resume_id', 'viewed_at']);
            $table->index(['resume_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_views');
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'share_revision']);
        });
    }
};
