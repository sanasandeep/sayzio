<?php

namespace App\Services\Planner;

/**
 * The stages a folder's resources are grouped into on the Flow tab.
 *
 * This lived as a literal array inside one line of folder-map.blade.php, which
 * had two costs. Six of the nineteen kinds in PlannerResources::TYPES appeared
 * in no stage at all -- calendars, task boards, personas, AI staff, agents and
 * channels could be assigned to a folder and then be counted nowhere -- and
 * nothing failed when that happened. A kind added to TYPES and forgotten here
 * simply vanishes from the Flow tab.
 *
 * Moving it into a class is what makes FolderFlowMapTest possible: the guard
 * reads TYPES, reads this, and fails on any kind that is in neither a stage
 * nor the deliberately-unplaced list below.
 *
 * The stage ORDER is the order the flow reads in, left to right.
 */
class FolderFlowStages
{
    /**
     * Kinds that are deliberately not on the Flow tab, each with a reason.
     *
     * Empty today: every kind a folder can hold is placed. Anything added
     * here needs a reason good enough to write down, because the alternative
     * -- a kind the tab silently ignores -- is what this class exists to stop.
     *
     * @var list<string>
     */
    public const UNPLACED = [];

    /**
     * @return array<string, array{eyebrow: string, title: string, kinds: list<string>, tab: string, note: ?string}>
     */
    public static function all(): array
    {
        return [
            'prepare' => [
                'color'   => '#8b5cf6',
                'eyebrow' => 'Prepare',
                'title'   => 'Knowledge & plans',
                'kinds'   => ['file', 'mind', 'plan', 'budget', 'brand'],
                'tab'     => 'resources',
                'note'    => null,
            ],
            'create' => [
                'color'   => '#3b82f6',
                'eyebrow' => 'Create',
                'title'   => 'Content & pages',
                // Calendars sit here rather than in Act: a booking calendar is
                // a page a visitor lands on, not work the owner does.
                'kinds'   => ['link', 'form', 'qr', 'calendar'],
                'tab'     => 'links',
                'note'    => null,
            ],
            'serve' => [
                'color'   => '#0d9488',
                'eyebrow' => 'Serve',
                'title'   => 'Agents & channels',
                // These four were in no stage. They are not content and they
                // are not outreach -- they answer whoever the outreach brought
                // in -- so folding them into Create would have been a lie told
                // to make the array shorter.
                'kinds'   => ['companion', 'agent', 'staff', 'persona'],
                'tab'     => 'resources',
                'note'    => null,
            ],
            'reach' => [
                'color'   => '#f59e0b',
                'eyebrow' => 'Reach',
                'title'   => 'Marketing & audience',
                'kinds'   => ['campaign', 'studio', 'buzz', 'strategy'],
                'tab'     => 'marketing',
                'note'    => null,
            ],
            'act' => [
                'color'   => '#e0457b',
                'eyebrow' => 'Act',
                'title'   => 'Tasks & outcomes',
                // A board belongs with the tasks it holds. Its tasks already
                // reach the folder through PlannerResources::folder(); the
                // board itself was the part going uncounted.
                'kinds'   => ['task', 'board'],
                'tab'     => 'tasks',
                'note'    => null,
            ],
        ];
    }

    /**
     * Which stage a resource kind belongs to, or null when it is unplaced.
     */
    public static function stageFor(string $kind): ?string
    {
        foreach (self::all() as $key => $stage) {
            if (in_array($kind, $stage['kinds'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Every kind that appears in some stage.
     *
     * @return list<string>
     */
    public static function placedKinds(): array
    {
        return array_values(array_merge(...array_column(self::all(), 'kinds')));
    }
}
