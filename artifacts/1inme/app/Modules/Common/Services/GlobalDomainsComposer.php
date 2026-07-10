<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Domain;
use Illuminate\View\View;

/**
 * View composer that shares the live list of branded global domains with
 * the marketing home section and the /domains page, so the showcased
 * domains stay in sync with what admins manage (no copy edits needed).
 *
 * The list is sourced from active, verified admin-global domains and falls
 * back to a static branded list when none are configured. Caching and the
 * fallback both live in Domain::showcase().
 */
class GlobalDomainsComposer
{
    /** Views that should receive the $showcaseDomains array. */
    public const VIEWS = ['home', 'public.domains'];

    public function compose(View $view): void
    {
        $view->with('showcaseDomains', Domain::showcase(5));
    }
}
