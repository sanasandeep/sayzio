<?php

namespace App\Services\Planner;

/**
 * Which kinds can connect to which -- the third kind of line on the Flow map.
 *
 * The map already draws two kinds of line, and they mean different things:
 *
 *   dashed, stage-coloured  a SUGGESTED order. Drawn whether or not anyone
 *                           works that way. Counts nothing.
 *   solid, numbered         a RECORD. Each one is item-to-item connections a
 *                           person saved under Resources, and the number is
 *                           how many.
 *
 * Sana, 2026-10-18: "Sayzio is like brain lek mesh network... not just a chain
 * system", with five examples -- performance feeding the AI brain, links
 * reaching everywhere, planning and studio touching every feature, calendars
 * and forms and buzz connecting in several places.
 *
 * That is a third thing again: a CAPABILITY. True of the product, true of
 * every folder, and counting nothing. A folder with no links still has the
 * ability to attach links to campaigns.
 *
 * WHERE THIS COMES FROM, AND WHERE IT DOES NOT. There is no whitelist in the
 * codebase to read this off: `folder_relationships` is free-form (any kind to
 * any kind, which is why FolderFlowEdges needs its off-rail and unplaced
 * buckets), and PlannerResources::TYPES is only a kind-to-model map. So this
 * table is product knowledge, given by Sana and written down here rather than
 * inferred from controllers -- inferring it would have produced a confident
 * claim about the product out of implementation details.
 *
 * Which means: if the product gains a connection, this file has to be edited.
 * It is not derived from anything and will not notice on its own. The test
 * holds it to naming only real kinds, not to being complete.
 */
class FolderFlowMesh
{
    /**
     * Read as "this kind can connect to these". Drawn undirected -- a
     * capability line has no arrow, because the point is that the two can be
     * joined, not which way the data runs.
     *
     * @var array<string, list<string>>
     */
    private const REACHES = [
        // Everything measurable feeds the AI brain: a mind can be trained on
        // what the folder's own work produced.
        'link'     => ['mind', 'form', 'qr', 'campaign', 'studio', 'buzz', 'calendar',
                       'task', 'board', 'companion', 'staff', 'persona', 'brand'],
        'qr'       => ['mind'],
        'form'     => ['mind', 'link', 'qr', 'campaign', 'persona', 'task'],
        'buzz'     => ['mind', 'link', 'campaign', 'studio', 'persona'],
        'campaign' => ['mind'],
        'task'     => ['mind'],

        // Planning and Studio touch every feature.
        'plan'     => ['brand', 'budget', 'mind', 'file', 'link', 'form', 'qr', 'calendar',
                       'campaign', 'studio', 'buzz', 'strategy', 'companion', 'agent',
                       'staff', 'persona', 'task', 'board'],
        'studio'   => ['brand', 'budget', 'plan', 'mind', 'file', 'link', 'form', 'qr',
                       'calendar', 'campaign', 'buzz', 'strategy', 'companion', 'agent',
                       'staff', 'persona', 'task', 'board'],

        // Calendars sit against several things at once.
        'calendar' => ['task', 'board', 'campaign', 'plan', 'buzz', 'staff'],
    ];

    /**
     * Every capability pair, each once, as unordered pairs.
     *
     * @return list<array{from:string,to:string}>
     */
    public static function pairs(): array
    {
        $seen = [];
        $out  = [];

        foreach (self::REACHES as $from => $targets) {
            foreach ($targets as $to) {
                if ($from === $to) {
                    continue;
                }
                $key = $from < $to ? $from.'|'.$to : $to.'|'.$from;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['from' => $from, 'to' => $to];
            }
        }

        return $out;
    }

    /**
     * The map the view hands to the browser: kind => kinds it can reach,
     * symmetric, so hovering either end lights the same line.
     *
     * @return array<string, list<string>>
     */
    public static function reach(): array
    {
        $out = array_fill_keys(array_keys(FolderFlowNetwork::stations()), []);

        foreach (self::pairs() as $pair) {
            $out[$pair['from']][] = $pair['to'];
            $out[$pair['to']][]   = $pair['from'];
        }

        foreach ($out as $kind => $list) {
            sort($list);
            $out[$kind] = array_values(array_unique($list));
        }

        return $out;
    }

    /** Every kind this table names, for the test that holds them to being real. */
    public static function kindsNamed(): array
    {
        $kinds = array_keys(self::REACHES);
        foreach (self::REACHES as $targets) {
            $kinds = array_merge($kinds, $targets);
        }

        return array_values(array_unique($kinds));
    }
}
