<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promote `resumes` from one-row-per-user to many-rows-per-user so the
 * owner can keep multiple named versions (e.g. "Design", "Eng Manager")
 * and pick which one is the default that powers /{handle}/resume.
 *
 * Approach: each existing Resume row stays as-is and becomes a single
 * "default" version. We drop the `unique(user_id)` constraint, add
 * `name` / `slug` / `is_default` columns, and backfill existing rows
 * with name="Default", slug="default", is_default=true so the public
 * URL keeps resolving to the same content with no UX change.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            // Friendly label shown in the version switcher.
            $table->string('name', 80)->nullable()->after('color_theme_id');
            // URL-safe identifier used in /{handle}/resume/v/{slug}.
            // Nullable so we can backfill before enforcing the unique
            // index; in practice every row ends up with a slug.
            $table->string('slug', 60)->nullable()->after('name');
            // Exactly one row per user should have this true; enforced
            // in application code (controller) since Postgres partial
            // unique indexes are awkward to express portably here.
            $table->boolean('is_default')->default(false)->after('slug');
        });

        // Backfill — every pre-existing row becomes that user's
        // "Default" version so existing share URLs keep working.
        DB::table('resumes')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('resumes')->where('id', $row->id)->update([
                    'name'       => 'Default',
                    'slug'       => 'default',
                    'is_default' => true,
                ]);
            }
        });

        // Drop the old uniqueness on user_id — multiple rows per user are
        // now allowed. A try/catch INSIDE a Schema::table() closure can't
        // swallow this: Blueprint defers the `ALTER ... DROP CONSTRAINT` until
        // after the closure returns, so a missing-constraint error (42704 on a
        // drifted/partially-applied shared schema) escapes the catch. Use a
        // native IF EXISTS drop so it is a true no-op when already gone.
        DB::statement('ALTER TABLE resumes DROP CONSTRAINT IF EXISTS resumes_user_id_unique');
        DB::statement('DROP INDEX IF EXISTS resumes_user_id_unique');

        // Replace with a per-user-per-slug unique so share URLs stay
        // collision-free, plus a default-lookup index. These are additive; a
        // re-run over an orphaned schema surfaces an "already exists" error that
        // db:reconcile-migrations heals.
        Schema::table('resumes', function (Blueprint $table) {
            $table->index(['user_id', 'is_default'], 'resumes_user_default_idx');
            $table->unique(['user_id', 'slug'], 'resumes_user_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            try { $table->dropUnique('resumes_user_slug_unique'); } catch (\Throwable $e) { /* */ }
            try { $table->dropIndex('resumes_user_default_idx'); } catch (\Throwable $e) { /* */ }
            $table->dropColumn(['name', 'slug', 'is_default']);
            // We do NOT re-add the user_id unique constraint here — if
            // the user accumulated multiple versions before rolling
            // back, re-adding it would fail. Operators that need it
            // back must clean up first.
        });
    }
};
