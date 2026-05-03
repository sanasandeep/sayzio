<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            // Whether the public /{handle}/resume URL serves this resume.
            // Off by default so existing rows are not unintentionally exposed.
            $table->boolean('is_public')->default(false)->after('color_theme_id');

            // Reuses the same vocabulary as Link.visibility: public,
            // registered, followers, subscribers, password.
            $table->string('visibility', 20)->default('public')->after('is_public');

            // Hashed password for visibility=password (Hash::make on write,
            // Hash::check on read). Nullable when not password-protected.
            $table->string('password')->nullable()->after('visibility');

            // Per-user noindex toggle. When false we emit
            // <meta name="robots" content="noindex,nofollow"> on the
            // public page so search engines drop it.
            $table->boolean('allow_indexing')->default(true)->after('password');

            // Page-view counter mirroring Link.total_clicks. Owners viewing
            // their own page do not increment this.
            $table->unsignedInteger('view_count')->default(0)->after('allow_indexing');

            // Optional SEO description override; falls back to the resume
            // headline / summary when blank.
            $table->string('meta_description', 240)->nullable()->after('view_count');

            $table->index(['is_public', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'visibility']);
            $table->dropColumn([
                'is_public', 'visibility', 'password',
                'allow_indexing', 'view_count', 'meta_description',
            ]);
        });
    }
};
