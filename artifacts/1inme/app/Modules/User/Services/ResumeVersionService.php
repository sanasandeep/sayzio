<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner-side mutations for the multi-version resume model.
 *
 * Centralises the create / rename / duplicate / delete / set-default
 * logic so the web controller, the mobile API controller, and any
 * future automation share the exact same semantics — most importantly
 * the "exactly one default per user" invariant, which is enforced
 * inside transactions here rather than scattered across call sites.
 */
class ResumeVersionService
{
    /** Hard cap on the number of versions one user can keep. */
    public const MAX_VERSIONS_PER_USER = 20;

    /**
     * Create a brand-new empty version for the user. Never marked as
     * default — owners explicitly promote a version via setDefault().
     *
     * @param array{template_id?:?string, color_theme_id?:?string} $opts
     */
    public function create(User $user, string $name, array $opts = []): Resume
    {
        $this->assertWithinCap($user);
        $name = $this->normalizeName($name);

        return DB::transaction(function () use ($user, $name, $opts) {
            $slug = $this->uniqueSlug($user, $name);

            return $user->resumes()->create([
                'template_id'    => $opts['template_id']
                    ?? ResumeTemplateRegistry::defaultId(),
                'color_theme_id' => $opts['color_theme_id']
                    ?? ResumeColorThemeRegistry::defaultId(),
                'sections'       => Resume::defaultSections(),
                'name'           => $name,
                'slug'           => $slug,
                'is_default'     => false,
            ]);
        });
    }

    /** Deep-copy an existing version (sections + items) under a new name. */
    public function duplicate(User $user, Resume $source, ?string $name = null): Resume
    {
        abort_if($source->user_id !== $user->id, 403);
        $this->assertWithinCap($user);

        $name = $this->normalizeName($name ?: ($source->displayName() . ' copy'));

        return DB::transaction(function () use ($user, $source, $name) {
            $slug = $this->uniqueSlug($user, $name);

            $copy = $user->resumes()->create([
                'template_id'      => $source->template_id,
                'color_theme_id'   => $source->color_theme_id,
                'sections'         => $source->sections,
                'name'             => $name,
                'slug'             => $slug,
                'is_default'       => false,
                // Sharing settings are intentionally NOT copied — the
                // duplicate starts unpublished so the owner doesn't
                // accidentally expose draft content under a new URL.
                'is_public'        => false,
                'visibility'       => 'public',
                'allow_indexing'   => true,
                'is_public_pdf'    => false,
                'meta_description' => $source->meta_description,
                'view_count'       => 0,
            ]);

            // Clone every item, preserving order. We re-create rather
            // than insert-select so the JSON `data` column survives any
            // Eloquent-side casts in either direction.
            foreach ($source->items()->get() as $item) {
                $copy->items()->create([
                    'section_type' => $item->section_type,
                    'position'     => $item->position,
                    'data'         => $item->data,
                ]);
            }

            return $copy->fresh('items');
        });
    }

    /** Rename a version. Recomputes the slug only if it would still be unique. */
    public function rename(User $user, Resume $version, string $name): Resume
    {
        abort_if($version->user_id !== $user->id, 403);
        $name = $this->normalizeName($name);
        $version->update(['name' => $name]);
        return $version;
    }

    /**
     * Promote `$version` to the user's default. Atomically demotes the
     * previous default in the same transaction so the invariant
     * "exactly one default per user" never observes a gap.
     */
    public function setDefault(User $user, Resume $version): Resume
    {
        abort_if($version->user_id !== $user->id, 403);

        DB::transaction(function () use ($user, $version) {
            $user->resumes()
                ->where('id', '!=', $version->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $version->forceFill(['is_default' => true])->save();
        });

        return $version->fresh();
    }

    /**
     * Delete a version. Refuses to delete the default — owners must
     * promote another version first so the public URL never points at
     * nothing.
     */
    public function delete(User $user, Resume $version): void
    {
        abort_if($version->user_id !== $user->id, 403);
        abort_if($version->is_default, 422, 'Promote another version to default first.');
        $version->delete();
    }

    /** Trim + cap the user-supplied name; never returns an empty string. */
    private function normalizeName(string $raw): string
    {
        $clean = mb_substr(trim($raw), 0, 80);
        return $clean !== '' ? $clean : 'Untitled version';
    }

    /**
     * Generate a slug that's unique among the user's existing versions.
     * Falls back to a numeric suffix on collision so two versions named
     * "Design" land on "design" + "design-2" rather than failing the
     * unique-index write.
     */
    private function uniqueSlug(User $user, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '' || $base === Resume::DEFAULT_SLUG) $base = 'v';
        $base = mb_substr($base, 0, 50);

        $taken = $user->resumes()->pluck('slug')->filter()->all();
        $taken = array_flip($taken);
        if (!isset($taken[$base])) return $base;

        for ($i = 2; $i < 1000; $i++) {
            $candidate = $base . '-' . $i;
            if (!isset($taken[$candidate])) return $candidate;
        }
        // Astronomically unlikely fallback — keep deterministic so
        // tests don't get flaky on retry.
        return $base . '-' . substr(sha1((string) microtime(true)), 0, 6);
    }

    private function assertWithinCap(User $user): void
    {
        $count = $user->resumes()->count();
        abort_if(
            $count >= self::MAX_VERSIONS_PER_USER,
            422,
            'You\'ve reached the limit of ' . self::MAX_VERSIONS_PER_USER . ' resume versions.'
        );
    }
}
