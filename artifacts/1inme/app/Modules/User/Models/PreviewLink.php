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

    /**
     * Eloquent's `__get` triggers relationship resolution when a property
     * matching a method name is accessed (e.g. `$link->biolinkBlocks` in
     * blade views). It then asserts the method returned an
     * `Illuminate\Database\Eloquent\Relations\Relation` instance and throws
     * `LogicException` otherwise. Our `fakeRelation()` returns an anonymous
     * stub, so we short-circuit property access here and hand back the
     * pre-built in-memory collections directly.
     */
    public function __get($key)
    {
        if ($key === 'biolinkBlocks') return $this->previewBlocks ?? collect();
        if ($key === 'activeBiolinkBlocks') return $this->previewActiveBlocks ?? collect();
        if ($key === 'pixels') return collect();
        return parent::__get($key);
    }

    private function fakeRelation(Collection $items): object
    {
        return new class($items) {
            public function __construct(private Collection $items) {}
            public function get(): Collection { return $this->items; }
        };
    }
}
