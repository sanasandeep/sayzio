<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Services\LeadAggregator;
use Illuminate\View\View;

/**
 * Shares the pending-lead count with the sidebar Leads nav entry (Task
 * #3728), matching {@see ContactsFollowUpsBadgeComposer}'s pattern for the
 * Contacts follow-ups badge.
 */
class LeadsBadgeComposer
{
    public const VIEWS = [
        'user.layouts.app',
    ];

    public function compose(View $view): void
    {
        $view->with('pendingLeadsCount', $this->count());
    }

    protected function count(): int
    {
        $owner = workspace_owner();
        if (!$owner) {
            return 0;
        }

        return (new LeadAggregator($owner->id))->pendingCount();
    }
}
