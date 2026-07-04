<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Contact;
use Illuminate\View\View;

/**
 * Shares the authenticated user's overdue follow-ups count with the surfaces
 * that show the "needs attention" badge: the sidebar Contacts nav entry (on
 * every page, since they all extend the user layout) and the Contacts "Quick
 * add" card. Overdue = a scheduled follow_up_at that is due already (<= now),
 * matching the consolidated Follow-ups list's "overdue" bucket.
 */
class ContactsFollowUpsBadgeComposer
{
    /** Views that render the overdue follow-ups badge. */
    public const VIEWS = [
        'user.layouts.app',
        'user.contacts.index',
    ];

    public function compose(View $view): void
    {
        $view->with('contactsOverdueFollowUps', $this->count());
    }

    protected function count(): int
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        return Contact::where('user_id', $user->id)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->count();
    }
}
