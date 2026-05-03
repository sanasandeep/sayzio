<?php

namespace App\Modules\User\Models;

use Illuminate\Support\Collection;

/**
 * Unsaved BiolinkBlock variant used by template-preview rendering.
 * Overrides the children relation methods so `$block->activeChildren()->get()`
 * works against an in-memory collection instead of querying the database.
 */
class PreviewBiolinkBlock extends BiolinkBlock
{
    public Collection $previewChildren;
    public Collection $previewActiveChildren;

    public function children()
    {
        return $this->fakeRelation($this->previewChildren ?? collect());
    }

    public function activeChildren()
    {
        return $this->fakeRelation($this->previewActiveChildren ?? collect());
    }

    private function fakeRelation(Collection $items): object
    {
        return new class($items) {
            public function __construct(private Collection $items) {}
            public function get(): Collection { return $this->items; }
        };
    }
}
