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

    private function fakeRelation(Collection $items): object
    {
        return new class($items) {
            public function __construct(private Collection $items) {}
            public function get(): Collection { return $this->items; }
        };
    }
}
