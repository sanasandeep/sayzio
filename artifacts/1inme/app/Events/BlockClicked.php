<?php

namespace App\Events;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired exactly once per real-human click on a biolink block.
 *
 * Bot/scraper hits are intentionally NOT broadcast — see {@see LinkClicked}
 * for the full rationale. Listeners (webhooks, notifications, social
 * proof, etc.) can subscribe without filtering on `is_bot`.
 */
class BlockClicked
{
    use Dispatchable;

    public function __construct(
        public Link $link,
        public BiolinkBlock $block,
        public LinkClick $click,
        public string $destinationUrl,
    ) {}
}
