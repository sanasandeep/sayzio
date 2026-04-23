<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\PollVoteResetSnapshot;
use App\Modules\User\Models\PollVoterErasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PollVoteController extends Controller
{
    /**
     * Authorize that the link belongs to the current user, the block
     * belongs to that link, and the block is actually a poll. Returns
     * the validated [link, block] pair so each action stays thin.
     */
    private function resolve(Request $request, Link $link, BiolinkBlock $block): array
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'poll', 404);

        return [$link, $block];
    }

    public function index(Request $request, Link $link, BiolinkBlock $block)
    {
        [$link, $block] = $this->resolve($request, $link, $block);

        $votes = $block->pollVotes()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Most-recent erasures by this creator (across all their polls) so
        // they can prove a takedown happened directly from the votes screen.
        $recentErasures = PollVoterErasure::query()
            ->where('creator_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Reset history for this poll block: each row records the per-option
        // counts that existed immediately before the creator wiped the votes.
        $resetSnapshots = PollVoteResetSnapshot::query()
            ->with('creator:id,name')
            ->where('block_id', $block->id)
            ->orderByDesc('reset_at')
            ->limit(10)
            ->get();

        $settings = $block->settings ?? [];
        $options = $settings['options'] ?? [];
        $question = $settings['question'] ?? 'Poll';

        // Tally per option, indexed by option_index so options without
        // any votes still show up at zero in the breakdown.
        $rawCounts = $block->pollVotes()
            ->selectRaw('option_index, COUNT(*) as c')
            ->groupBy('option_index')
            ->pluck('c', 'option_index')
            ->all();

        $total = array_sum($rawCounts);
        $breakdown = [];
        foreach ($options as $i => $label) {
            $count = (int) ($rawCounts[$i] ?? 0);
            $breakdown[] = [
                'index' => $i,
                'label' => $label,
                'count' => $count,
                'pct'   => $total > 0 ? round($count * 100 / $total) : 0,
            ];
        }

        return view('user.links.poll-votes', compact(
            'link', 'block', 'votes', 'breakdown', 'total', 'question',
            'recentErasures', 'resetSnapshots'
        ));
    }

    public function export(Request $request, Link $link, BiolinkBlock $block): StreamedResponse
    {
        [$link, $block] = $this->resolve($request, $link, $block);

        $filename = 'poll-votes-' . $link->alias . '-block' . $block->id
            . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($block) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Option index', 'Option label', 'Voter name', 'Voter email',
                'Voter fingerprint', 'Source', 'IP address', 'Submitted at',
            ]);
            $block->pollVotes()->with('user:id,name,email')
                ->orderBy('created_at')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $v) {
                        fputcsv($out, [
                            $v->option_index,
                            $v->option_label,
                            $v->user?->name,
                            $v->user?->email,
                            $v->voter_fingerprint,
                            $v->source,
                            $v->ip_address,
                            $v->created_at?->toDateTimeString(),
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Link $link, BiolinkBlock $block, PollVote $vote)
    {
        [$link, $block] = $this->resolve($request, $link, $block);
        abort_if($vote->block_id !== $block->id, 404);

        $vote->delete();

        return back()->with('success', 'Vote removed.');
    }

    /**
     * Erase every poll vote tied to a single voter (logged-in user_id,
     * email, or anonymous fingerprint) across ALL polls owned by the
     * current creator. Built for GDPR-style takedown requests.
     */
    public function eraseVoter(Request $request, Link $link, BiolinkBlock $block)
    {
        [$link, $block] = $this->resolve($request, $link, $block);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $needle = trim($data['identifier']);
        $creatorId = $request->user()->id;

        // Find every poll-vote row across every poll-block owned by this
        // creator that matches the supplied identifier. We match on:
        //   - exact user_id (numeric)
        //   - exact email (via users join)
        //   - exact voter_fingerprint
        // Match every poll-vote whose owning link belongs to the creator.
        // Skip the workspace global scope so the takedown reaches links
        // across all of the creator's workspaces (the auth check below is
        // simply link.user_id = current creator).
        $ownedLinkIds = Link::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creatorId)->pluck('id');

        $query = PollVote::query()
            ->whereIn('link_id', $ownedLinkIds)
            ->where(function ($q) use ($needle) {
                $q->where('voter_fingerprint', $needle);
                if (ctype_digit($needle)) {
                    $q->orWhere('user_id', (int) $needle);
                }
                if (filter_var($needle, FILTER_VALIDATE_EMAIL)) {
                    $q->orWhereIn('user_id', DB::table('users')
                        ->where('email', $needle)->pluck('id'));
                }
            });

        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', 'No poll votes matched “' . e($needle) . '”.');
        }

        // Wrap the delete + audit insert in a transaction so we can never
        // remove votes without leaving the matching proof-of-takedown row.
        DB::transaction(function () use ($query, $creatorId, $link, $block, $needle, $count, $request) {
            $query->delete();

            PollVoterErasure::create([
                'creator_id'    => $creatorId,
                'link_id'       => $link->id,
                'block_id'      => $block->id,
                'identifier'    => $needle,
                'removed_count' => $count,
                'ip_address'    => $request->ip(),
                'created_at'    => now(),
            ]);
        });

        Log::info('poll voter erased', [
            'creator_id' => $creatorId,
            'identifier' => $needle,
            'removed'    => $count,
            'from_block' => $block->id,
        ]);

        return back()->with('success', "Erased {$count} poll vote(s) tied to “{$needle}”.");
    }

    /**
     * Dedicated audit screen showing every voter-erasure this creator has
     * performed across all of their polls. Older entries page through here.
     */
    public function erasures(Request $request, Link $link, BiolinkBlock $block)
    {
        [$link, $block] = $this->resolve($request, $link, $block);

        $erasures = PollVoterErasure::query()
            ->with(['link:id,alias,title', 'block:id,link_id'])
            ->where('creator_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('user.links.poll-vote-erasures', compact('link', 'block', 'erasures'));
    }

    /**
     * Wipe every PollVote row for this poll block while leaving the
     * block (its id, settings, and analytics history) intact. Lets
     * creators clear test/typo votes without losing the block.
     */
    public function reset(Request $request, Link $link, BiolinkBlock $block)
    {
        [$link, $block] = $this->resolve($request, $link, $block);

        // Snapshot the current per-option counts BEFORE deleting so the
        // creator can review what was wiped and (optionally) undo it.
        $deleted = DB::transaction(function () use ($block, $link, $request) {
            $counts = $block->pollVotes()
                ->selectRaw('option_index, COUNT(*) as c')
                ->groupBy('option_index')
                ->pluck('c', 'option_index')
                ->map(fn ($c) => (int) $c)
                ->all();

            $total = (int) array_sum($counts);

            // Always record the snapshot — even for a zero-vote reset —
            // so the audit trail is complete and never silently skips a
            // click on the destructive button.
            PollVoteResetSnapshot::create([
                'creator_id' => $request->user()->id,
                'link_id'    => $link->id,
                'block_id'   => $block->id,
                'counts'     => $counts,
                'total'      => $total,
                'reset_at'   => now(),
                'ip_address' => $request->ip(),
            ]);

            return $block->pollVotes()->delete();
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'deleted' => $deleted]);
        }

        return back()->with('success', "Cleared {$deleted} poll vote(s).");
    }

    /**
     * Recreate anonymized PollVote rows from a snapshot taken just before
     * a reset. Each restored row is marked as a synthetic restore so it
     * can never be confused with a real voter (no fingerprint, no IP,
     * source = 'restored'). The snapshot is then marked as restored so
     * it can't be replayed twice.
     */
    public function undoReset(Request $request, Link $link, BiolinkBlock $block, PollVoteResetSnapshot $snapshot)
    {
        [$link, $block] = $this->resolve($request, $link, $block);
        abort_if($snapshot->block_id !== $block->id, 404);
        abort_if($snapshot->creator_id !== $request->user()->id, 403);

        if ($snapshot->restored_at !== null) {
            return back()->with('error', 'This snapshot has already been restored.');
        }

        // Only the newest still-unrestored snapshot for this block is
        // eligible. Enforced server-side so a hand-crafted POST can't
        // restore an older snapshot out of order.
        $newestUnrestoredId = PollVoteResetSnapshot::query()
            ->where('block_id', $block->id)
            ->whereNull('restored_at')
            ->orderByDesc('reset_at')
            ->value('id');

        if ($newestUnrestoredId !== $snapshot->id) {
            return back()->with('error', 'Only the most recent reset can be undone.');
        }

        $settings = $block->settings ?? [];
        $options = $settings['options'] ?? [];
        $now = now();

        $restored = DB::transaction(function () use ($snapshot, $block, $link, $options, $now) {
            // Atomic claim: only the request that flips restored_at from
            // NULL to now() does the insert. A concurrent click loses
            // the race and bails with zero rows restored.
            $claimed = PollVoteResetSnapshot::where('id', $snapshot->id)
                ->whereNull('restored_at')
                ->update(['restored_at' => $now]);

            if ($claimed === 0) {
                return 0;
            }

            $rows = [];
            foreach ((array) $snapshot->counts as $optionIndex => $count) {
                $idx = (int) $optionIndex;
                $label = $options[$idx] ?? null;
                for ($i = 0; $i < (int) $count; $i++) {
                    $rows[] = [
                        'link_id'           => $link->id,
                        'block_id'          => $block->id,
                        'option_index'      => $idx,
                        'option_label'      => $label,
                        'user_id'           => null,
                        'voter_fingerprint' => null,
                        'source'            => 'restored',
                        'ip_address'        => null,
                        'user_agent'        => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            // Chunk the inserts so a snapshot with thousands of votes
            // doesn't blow past MySQL's max placeholder count.
            foreach (array_chunk($rows, 500) as $chunk) {
                PollVote::insert($chunk);
            }

            return count($rows);
        });

        if ($restored === 0) {
            return back()->with('error', 'This snapshot has already been restored.');
        }

        return back()->with('success', "Restored {$restored} anonymized poll vote(s) from the snapshot.");
    }
}
