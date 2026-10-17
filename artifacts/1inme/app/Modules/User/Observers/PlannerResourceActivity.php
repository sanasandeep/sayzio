<?php

namespace App\Modules\User\Observers;

use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Services\Planner\PlannerResources;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who changed what, for every resource kind a folder can hold.
 *
 * Sana, 2026-10-17, on showing team activity on the folder map: "staffs
 * basically make changes does actions in corresponding modules".
 *
 * The workspace already had an activity ledger, but almost nothing wrote to
 * it. Of the nineteen kinds in PlannerResources::TYPES only `link` was
 * recorded; forms, QR codes, calendars, campaigns, Buzz, brand kits, files,
 * plans, minds, studios, strategies, budgets and boards recorded nothing at
 * all. A team member could delete a form or rewrite a campaign and the
 * workspace had no record of it.
 *
 * WHY AN OBSERVER AND NOT CALLS IN CONTROLLERS. Every other call to
 * WorkspaceActivityRecorder is written by hand at the point of the change,
 * which matches the house style -- but the guarantee worth having here is
 * that no change escapes, and hand-placed calls cannot give that. There are
 * web controllers, API controllers, bulk actions and services that all write
 * these models; one missed path is a silent hole, which is exactly the state
 * this fixes. An observer is bound to the model, so it cannot be bypassed by
 * adding a new route.
 *
 * The model-to-kind map is read from PlannerResources::TYPES rather than
 * written again here, so a kind added to the catalogue is recorded without
 * anyone remembering to come back.
 *
 * What it deliberately does NOT record:
 *  - changes with no signed-in user, which also covers console work (seeds,
 *    migrations, imports) and queued jobs: none of them carry an actor, and
 *    an unattributed row is noise in a ledger whose purpose is attribution
 *  - anything when no workspace is bound; the recorder already drops those
 *  - `link`, which LinkController already records by hand with a richer
 *    label and a URL. Observing it too would write every link change twice.
 *    The exclusion is a constant rather than a silent `if`, and the test
 *    holds it to kinds that really are recorded elsewhere, so it cannot
 *    quietly grow into a list of things nobody records at all.
 */
class PlannerResourceActivity
{
    /**
     * Kinds whose activity is already written by hand elsewhere, and would be
     * recorded twice if observed here.
     *
     * @var list<string>
     */
    public const ALREADY_RECORDED = ['link'];

    /** Cached model class => kind, built from the catalogue. */
    private static ?array $kinds = null;

    /** @return array<class-string, string> */
    public static function kindByModel(): array
    {
        if (self::$kinds === null) {
            self::$kinds = [];
            foreach (PlannerResources::TYPES as $kind => $class) {
                self::$kinds[ltrim((string) $class, '\\')] = $kind;
            }
        }

        return self::$kinds;
    }

    /** The resource kind this model is, or null when it is not one. */
    public static function kindFor(Model $model): ?string
    {
        return self::kindByModel()[ltrim($model::class, '\\')] ?? null;
    }

    public function created(Model $model): void
    {
        $this->note($model, 'create');
    }

    public function updated(Model $model): void
    {
        $this->note($model, 'update');
    }

    public function deleted(Model $model): void
    {
        $this->note($model, 'delete');
    }

    private function note(Model $model, string $verb): void
    {
        // A row that cannot say who did it is noise in a ledger that exists
        // to say who did it, so an unauthenticated write is skipped. That
        // one check is enough: console work -- seeds, migrations, imports,
        // queued jobs -- has no signed-in user either, because Laravel does
        // not carry the actor into a queued job.
        //
        // Do NOT add app()->runningInConsole() here. PHPUnit runs under the
        // CLI SAPI, so that check is true for the whole test suite: it makes
        // this observer a no-op in tests, and every test written against it
        // passes without ever exercising it. That is how the first version
        // of this class shipped green and recorded nothing.
        if (! auth()->check()) {
            return;
        }

        $kind = self::kindFor($model);
        if ($kind === null || in_array($kind, self::ALREADY_RECORDED, true)) {
            return;
        }

        WorkspaceActivityRecorder::record(
            null,
            $kind.'.'.$verb,
            $kind,
            $model->getKey(),
            self::label($model),
        );
    }

    /**
     * A human-readable name for the row, from whichever of the usual columns
     * this model happens to have. Falls back to the kind and id rather than
     * an empty label, so the ledger is still readable.
     */
    private static function label(Model $model): string
    {
        foreach (['name', 'title', 'label', 'alias', 'headline', 'subject'] as $column) {
            $value = $model->getAttribute($column);
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 250);
            }
        }

        return ucfirst((string) (self::kindFor($model) ?? 'item')).' #'.$model->getKey();
    }
}
