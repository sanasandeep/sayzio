<?php

namespace App\Modules\User\Support;

use App\Services\Planner\PlannerResources;
use Illuminate\Support\Facades\Route;

/**
 * Where each resource kind goes when you click its station on the dashboard.
 *
 * The Flow tab on a folder already knows how to reach one ROW -- that is
 * PlannerResources::url(), which needs a record in hand. The dashboard has no
 * record: it draws the whole catalogue for an account and needs the INDEX for
 * each kind, which existed nowhere.
 *
 * It lives in a class rather than inline in the blade for the reason
 * FolderFlowStages gives: a kind added to PlannerResources::TYPES and
 * forgotten here would simply vanish from the dashboard, and nothing would
 * fail. DashboardFlowDestinationsTest reads TYPES, reads this, and fails on
 * any kind that is in neither.
 *
 * Every route name here is asserted to exist by that test. Two kinds share a
 * destination on purpose, noted below -- that is a real property of the app,
 * not an oversight.
 */
class DashboardFlowDestinations
{
    /**
     * kind => [route name, label for the empty state's call to action].
     *
     * @return array<string, array{route: string, cta: string}>
     */
    public static function all(): array
    {
        return [
            // Prepare
            'file'      => ['route' => 'user.files.index',            'cta' => 'Upload a file'],
            'mind'      => ['route' => 'user.minds.index',            'cta' => 'Build a Mind'],
            // NoteActionPlan has no index of its own; its rows open through
            // user.ai.mind.show, so the Minds list is where they are found.
            // (user.plans.index is billing plan MANAGEMENT behind
            // user.can:user.plans.manage -- a different thing entirely.)
            'plan'      => ['route' => 'user.minds.index',            'cta' => 'Write a plan'],
            'budget'    => ['route' => 'user.marketing-plan.index',   'cta' => 'Plan a budget'],
            'brand'     => ['route' => 'user.brand-kits.index',       'cta' => 'Make a brand kit'],

            // Create
            'link'      => ['route' => 'user.links.index',            'cta' => 'Create a link'],
            'form'      => ['route' => 'user.forms.index',            'cta' => 'Build a form'],
            'qr'        => ['route' => 'user.qr-codes.index',         'cta' => 'Make a QR code'],
            'calendar'  => ['route' => 'user.calendars.mine',         'cta' => 'Open bookings'],

            // Serve
            'companion' => ['route' => 'user.ai-companions.index',    'cta' => 'Add a channel'],
            'agent'     => ['route' => 'user.ai-personas.index',      'cta' => 'Set up an agent'],
            'staff'     => ['route' => 'user.ai.staff.index',         'cta' => 'Hire AI staff'],
            'persona'   => ['route' => 'user.ai.persona.show',        'cta' => 'Create a persona'],

            // Reach
            'campaign'  => ['route' => 'user.marketing-studio.index', 'cta' => 'Start a campaign'],
            'studio'    => ['route' => 'user.brand-studio.index',     'cta' => 'Open the studio'],
            'buzz'      => ['route' => 'user.social-proofs.index',    'cta' => 'Add social proof'],
            'strategy'  => ['route' => 'user.ai.marketing-strategist.projects.index', 'cta' => 'Draft a strategy'],

            // Act -- a board and the cards on it live on the same screen.
            'task'      => ['route' => 'user.tasks.index',            'cta' => 'Add a task'],
            'board'     => ['route' => 'user.tasks.index',            'cta' => 'Open a board'],
        ];
    }

    /**
     * The resolved URL for a kind, or null when the route is not registered.
     *
     * Null rather than a thrown error: a missing route should cost that one
     * station its link, not the whole dashboard.
     */
    public static function urlFor(string $kind): ?string
    {
        $name = self::all()[$kind]['route'] ?? null;

        if ($name === null || ! Route::has($name)) {
            return null;
        }

        return route($name);
    }

    public static function ctaFor(string $kind): string
    {
        return self::all()[$kind]['cta'] ?? 'Open';
    }

    /** Kinds in TYPES with no destination recorded here. */
    public static function missingKinds(): array
    {
        return array_values(array_diff(
            array_keys(PlannerResources::TYPES),
            array_keys(self::all())
        ));
    }
}
