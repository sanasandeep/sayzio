<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace creator profiles (Task #6618).
 *
 * Moves the public /@handle creator-profile surface off the `users` table
 * into a workspace-keyed `creator_profiles` store so every workspace gets
 * its own handle + public page. The legacy users.* columns are kept and
 * mirrored for PERSONAL workspaces (many legacy consumers still read them),
 * but creator_profiles is the authoritative store from now on.
 *
 * Backfill (additive-only, shared-DB safe):
 *  - Every user with a handle or any profile data gets a personal-workspace
 *    profile seeded from their users.* columns (personal workspace lazily
 *    created for the rare user who never got one).
 *  - Existing follows are re-pointed at the followed user's personal
 *    profile via the new follows.creator_profile_id column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_profiles')) {
            Schema::create('creator_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
                // Owner (denormalized from workspaces.owner_user_id) so
                // follower/feed surfaces can keep joining on a user id.
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('handle', 60)->nullable();
                $table->text('bio')->nullable();
                $table->string('tagline', 200)->nullable();
                $table->string('location', 120)->nullable();
                $table->json('niche_tags')->nullable();
                $table->json('socials')->nullable();
                $table->string('cover_image', 1024)->nullable();
                $table->string('creator_avatar', 1024)->nullable();
                $table->boolean('profile_published')->default(false);
                $table->json('profile_section_visibility')->nullable();
                $table->jsonb('profile_showcase')->nullable();
                $table->string('profile_theme_color', 7)->nullable();
                $table->unsignedInteger('posts_count')->default(0);
                $table->unsignedInteger('followers_count')->default(0);
                $table->timestamps();
                $table->index('user_id');
            });
            // Case-insensitive handle uniqueness across ALL workspace profiles,
            // mirroring how users.handle was compared (LOWER(handle) lookups).
            DB::statement('CREATE UNIQUE INDEX creator_profiles_handle_lower_unique ON creator_profiles (LOWER(handle)) WHERE handle IS NOT NULL');
        }

        if (!Schema::hasColumn('follows', 'creator_profile_id')) {
            Schema::table('follows', function (Blueprint $table) {
                $table->foreignId('creator_profile_id')->nullable()
                    ->constrained('creator_profiles')->nullOnDelete();
                $table->index('creator_profile_id');
            });
        }

        // ── Backfill ─────────────────────────────────────────────────
        // 1) Ensure every user holding profile data has a personal workspace.
        $needsProfile = DB::table('users')
            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where(function ($q) {
                $q->whereNotNull('handle')
                  ->orWhere('profile_published', true)
                  ->orWhereNotNull('tagline')
                  ->orWhereNotNull('cover_image')
                  ->orWhereNotNull('creator_avatar')
                  ->orWhereNotNull('profile_showcase')
                  ->orWhereNotNull('niche_tags')
                  ->orWhereNotNull('socials');
            })
            ->orderBy('id');

        $needsProfile->chunk(500, function ($users) {
            foreach ($users as $u) {
                $wsId = DB::table('workspaces')
                    ->where('owner_user_id', $u->id)
                    ->orderByDesc('is_personal')->orderBy('id')
                    ->value('id');
                if (!$wsId) {
                    $wsId = DB::table('workspaces')->insertGetId([
                        'owner_user_id' => $u->id,
                        'name'          => ($u->name ?: ('User ' . $u->id)) . "'s workspace",
                        'slug'          => 'ws-' . $u->id,
                        'is_personal'   => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
                $exists = DB::table('creator_profiles')->where('workspace_id', $wsId)->exists();
                if ($exists) continue;
                // Skip when the handle already migrated to another profile
                // (re-run safety on the shared DB).
                if ($u->handle !== null && DB::table('creator_profiles')
                        ->whereRaw('LOWER(handle) = ?', [strtolower($u->handle)])->exists()) {
                    continue;
                }
                DB::table('creator_profiles')->insert([
                    'workspace_id'               => $wsId,
                    'user_id'                    => $u->id,
                    'handle'                     => $u->handle,
                    'bio'                        => $u->bio,
                    'tagline'                    => $u->tagline,
                    'location'                   => $u->location,
                    'niche_tags'                 => $u->niche_tags,
                    'socials'                    => $u->socials,
                    'cover_image'                => $u->cover_image,
                    'creator_avatar'             => $u->creator_avatar,
                    'profile_published'          => (bool) $u->profile_published,
                    'profile_section_visibility' => $u->profile_section_visibility,
                    'profile_showcase'           => $u->profile_showcase,
                    'profile_theme_color'        => $u->profile_theme_color,
                    'posts_count'                => (int) ($u->posts_count ?? 0),
                    'followers_count'            => (int) ($u->followers_count ?? 0),
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);
            }
        });

        // 2) Re-point existing follows at the followed user's migrated profile.
        DB::statement('
            UPDATE follows f SET creator_profile_id = cp.id
            FROM creator_profiles cp
            WHERE f.creator_profile_id IS NULL AND cp.user_id = f.creator_id
        ');
    }

    public function down(): void
    {
        if (Schema::hasColumn('follows', 'creator_profile_id')) {
            Schema::table('follows', function (Blueprint $table) {
                $table->dropConstrainedForeignId('creator_profile_id');
            });
        }
        Schema::dropIfExists('creator_profiles');
    }
};
