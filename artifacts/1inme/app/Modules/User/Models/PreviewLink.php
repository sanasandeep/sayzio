<?php

namespace App\Modules\User\Models;

use Illuminate\Support\Collection;

/**
 * Unsaved Link variant used for rendering live template previews without
 * touching the database. Overrides the block-relation methods so the
 * biolink view (`common.biolink`) can call `$link->activeBiolinkBlocks()->get()`
 * and receive a pre-built in-memory collection instead of issuing a query.
 */
class PreviewLink extends Link
{
    public Collection $previewBlocks;
    public Collection $previewActiveBlocks;

    public function biolinkBlocks()
    {
        return $this->fakeRelation($this->previewBlocks ?? collect());
    }

    public function activeBiolinkBlocks()
    {
        return $this->fakeRelation($this->previewActiveBlocks ?? collect());
    }

    /**
     * Preview links are unsaved and have no rows in the `link_pixels` pivot
     * table. Returning the parent `belongsToMany(Pixel::class, 'link_pixels')`
     * here would query `link_pixels.preview_link_id` (Eloquent infers the FK
     * from the calling class basename) and crash the template preview.
     */
    public function pixels()
    {
        return $this->fakeRelation(collect());
    }

    private function fakeRelation(Collection $items): object
    {
        return new class($items) {
            public function __construct(private Collection $items) {}
            public function get(): Collection { return $this->items; }
        };
    }
}
