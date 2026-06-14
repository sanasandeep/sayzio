<?php

namespace App\Modules\Common\Models;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageRevision extends Model
{
    protected $fillable = [
        'site_page_id', 'slug', 'title', 'meta_description', 'meta_keywords', 'intro',
        'last_updated_at', 'show_toc', 'sections', 'extra', 'cta_label', 'cta_url',
        'summary', 'editor_id', 'editor_type', 'editor_name',
    ];

    protected function casts(): array
    {
        return [
            'sections'        => 'array',
            'extra'           => 'array',
            'last_updated_at' => 'date',
            'show_toc'        => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }

    /**
     * Resolve the editor record for this revision. Editors can be either
     * an admin (typical case for policy edits) or an end user, so we
     * dispatch on the cached `editor_type` discriminator.
     */
    public function editor()
    {
        if (!$this->editor_id) {
            return null;
        }
        $class = match ($this->editor_type) {
            'admin' => Admin::class,
            'user'  => User::class,
            default => null,
        };
        if (!$class) {
            return null;
        }
        return $class::find($this->editor_id);
    }

    /**
     * Persist the *previous* state of a SitePage as a new revision row,
     * recording who triggered the replacing save and a short human
     * summary describing how that previous content differs from the
     * new state being saved. The latest revision row therefore always
     * represents the version that was just overwritten and is what
     * "Restore" reverts to.
     */
    public static function snapshot(SitePage $page, array $previousState, array $newState, ?int $editorId, ?string $editorType, ?string $editorName): self
    {
        $summary = static::buildSummary($previousState, $newState);

        return static::create([
            'site_page_id'    => $page->id,
            'slug'            => $page->slug,
            'title'           => ($previousState['title'] ?? '') !== '' ? $previousState['title'] : null,
            'meta_description'=> ($previousState['meta_description'] ?? '') !== '' ? $previousState['meta_description'] : null,
            'meta_keywords'   => ($previousState['meta_keywords'] ?? '') !== '' ? $previousState['meta_keywords'] : null,
            'intro'           => ($previousState['intro'] ?? '') !== '' ? $previousState['intro'] : null,
            'last_updated_at' => $previousState['last_updated_at'] ?? null,
            'show_toc'        => (bool) ($previousState['show_toc'] ?? true),
            'sections'        => $previousState['sections'] ?? [],
            'extra'           => $previousState['extra'] ?? null,
            'cta_label'       => ($previousState['cta_label'] ?? '') !== '' ? $previousState['cta_label'] : null,
            'cta_url'         => ($previousState['cta_url'] ?? '') !== '' ? $previousState['cta_url'] : null,
            'summary'         => $summary,
            'editor_id'       => $editorId,
            'editor_type'     => $editorId ? $editorType : null,
            'editor_name'     => $editorName,
        ]);
    }

    /**
     * Build a short, human-readable summary of what changed between the
     * previous snapshot state and the new one. Returns "Initial version."
     * when there is no prior state.
     */
    public static function buildSummary(?array $prev, array $next): string
    {
        if (!$prev) {
            return 'Initial version.';
        }
        $changes = [];
        foreach (['title', 'meta_description', 'meta_keywords', 'intro', 'cta_label', 'cta_url'] as $field) {
            if ((string)($prev[$field] ?? '') !== (string)($next[$field] ?? '')) {
                $changes[] = str_replace('_', ' ', $field);
            }
        }
        if (($prev['last_updated_at'] ?? null) !== ($next['last_updated_at'] ?? null)) {
            $changes[] = 'last updated date';
        }
        if ((bool)($prev['show_toc'] ?? true) !== (bool)($next['show_toc'] ?? true)) {
            $changes[] = 'table of contents';
        }
        $prevS = $prev['sections'] ?? [];
        $nextS = $next['sections'] ?? [];
        $secChanges = [];
        if (count($nextS) > count($prevS)) {
            $secChanges[] = (count($nextS) - count($prevS)) . ' added';
        } elseif (count($nextS) < count($prevS)) {
            $secChanges[] = (count($prevS) - count($nextS)) . ' removed';
        }
        $edited = 0;
        $shared = min(count($prevS), count($nextS));
        for ($i = 0; $i < $shared; $i++) {
            if (json_encode($prevS[$i] ?? null) !== json_encode($nextS[$i] ?? null)) {
                $edited++;
            }
        }
        if ($edited > 0) {
            $secChanges[] = $edited . ' edited';
        }
        if (!empty($secChanges)) {
            $changes[] = 'sections (' . implode(', ', $secChanges) . ')';
        }
        if (empty($changes)) {
            return 'Saved with no visible changes.';
        }
        return 'Updated ' . implode(', ', $changes) . '.';
    }
}
