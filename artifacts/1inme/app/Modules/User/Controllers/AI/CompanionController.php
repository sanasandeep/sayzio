<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindDefault;
use App\Modules\User\Models\CompanionMessage;
use App\Modules\User\Models\CompanionThread;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Companion — multi-turn chat assistant. Conversations live in the
 * `companion_threads` / `companion_messages` tables, scoped by user (and,
 * when present, the active workspace) so they survive logout, browser
 * switches, and workspace changes.
 *
 *   GET    /user/ai/companion                 chat UI + thread sidebar
 *   GET    /user/ai/companion/{thread}        open a specific thread
 *   POST   /user/ai/companion                 start a brand-new thread
 *   POST   /user/ai/companion/{thread}/send   append a turn (+ AI reply)
 *   POST   /user/ai/companion/{thread}/rename rename a thread
 *   DELETE /user/ai/companion/{thread}        delete a thread
 *
 * Spend is tagged `feature => 'companion.chat'` for admin reporting.
 */
class CompanionController extends Controller
{
    /** Cap turns sent to the model to keep prompt size — and per-call cost
     *  — predictable. Older turns stay in the DB but aren't replayed. */
    protected const MAX_PROMPT_TURNS = 12;

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
        protected AiMindQueryService $minds,
    ) {}

    /** Sidebar page size. Older threads are reachable via pagination. */
    protected const THREADS_PER_PAGE = 50;

    public function show(Request $request, ?int $thread = null)
    {
        $this->ensureEnabled();
        $user = $request->user();
        AiMindProvisioner::ensureForUser($user);
        $wsId = $this->workspaceId();

        // `?compose=1` forces the empty-state composer card to render even
        // when the user has saved threads, so they can manage their default
        // Mind selection (or pick a one-off set) before starting a new chat.
        $compose = (bool) $request->boolean('compose');

        $search = trim((string) $request->query('q', ''));

        $threadsQuery = $this->threadQuery($user->id, $wsId);
        $snippets = [];
        $titles = [];
        $matchCounts = [];

        if ($search !== '') {
            // Escape LIKE wildcards so a stray % or _ in the query doesn't
            // turn into "match anything". MySQL/SQLite both accept the \
            // escape and Laravel passes the LIKE pattern through verbatim.
            $like = '%' . addcslashes($search, '%_\\') . '%';

            // Threads whose messages contain the term — collected first so
            // the title/body OR is a single indexable WHERE. Prefer the
            // fulltext / tsvector index when the connection supports it so
            // search stays fast on tens of thousands of stored turns.
            $matchedThreadIds = $this->applyContentSearch(
                CompanionMessage::query()
                    ->whereIn('thread_id', (clone $threadsQuery)->select('id')),
                $search,
                $like,
            )
                ->distinct()
                ->pluck('thread_id')
                ->all();

            $threadsQuery->where(function ($w) use ($search, $like, $matchedThreadIds) {
                $this->applyTitleSearch($w, $search, $like);
                if (!empty($matchedThreadIds)) {
                    $w->orWhereIn('id', $matchedThreadIds);
                }
            });
        }

        $threads = $threadsQuery
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(self::THREADS_PER_PAGE)
            ->withQueryString();

        if ($search !== '' && $threads->isNotEmpty()) {
            // Pull one matching message per thread on this page to render
            // a snippet beside the title. Earliest match wins (stable id
            // order) so users see consistent context across reloads.
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $pageThreadIds = $threads->pluck('id')->all();
            $matches = $this->applyContentSearch(
                CompanionMessage::query()->whereIn('thread_id', $pageThreadIds),
                $search,
                $like,
            )
                ->orderBy('thread_id')
                ->orderBy('id')
                ->get(['thread_id', 'content']);
            foreach ($matches as $m) {
                if (!isset($snippets[$m->thread_id])) {
                    $snippets[$m->thread_id] = $this->highlight(
                        $this->snippet($m->content, $search), $search
                    );
                }
                $matchCounts[$m->thread_id] = ($matchCounts[$m->thread_id] ?? 0) + 1;
            }
            foreach ($threads as $t) {
                $titles[$t->id] = $this->highlight((string) $t->title, $search);
            }
        }

        $active = null;
        if ($thread) {
            $active = $this->threadQuery($user->id, $wsId)->find($thread);
            if (!$active) abort(404);
        } elseif (
            $threads->isNotEmpty()
            && $search === ''
            && $request->query('page') === null
            && !$compose
        ) {
            // Only auto-open the newest thread on the default landing view.
            // While searching, paginating, or composing a new thread, leave
            // the right pane empty so we don't yank the user into an
            // unrelated thread.
            $active = $threads->first();
        }

        // When a search term is in play we also pre-render a highlighted
        // copy of each message body so the open thread shows users *where*
        // the term appears, not just that the thread matched. The first
        // <mark> in the transcript gets an anchor id so the view can
        // scroll it into view on load.
        $firstMarkAssigned = false;
        $history = $active
            ? $active->messages()->get()->map(function ($m) use ($search, &$firstMarkAssigned) {
                $row = [
                    'role'    => $m->role,
                    'content' => $m->content,
                    'meta'    => $m->meta ?? [],
                    'html'    => null,
                ];
                if ($search !== '' && mb_stripos((string) $m->content, $search) !== false) {
                    $html = $this->highlight((string) $m->content, $search);
                    if (!$firstMarkAssigned) {
                        $html = preg_replace('/<mark /', '<mark id="companion-first-match" ', $html, 1);
                        $firstMarkAssigned = true;
                    }
                    $row['html'] = $html;
                }
                return $row;
            })->all()
            : [];

        // Pre-populate Mind selection for the new-conversation composer.
        // When viewing an existing thread, surface that thread's saved
        // selection so the user can see what Companion is grounding in;
        // otherwise fall back to the user's saved default for Companion.
        $default = AiMindDefault::forUserFeature($user->id, 'companion');
        if ($active) {
            $composerSelectedIds = array_map('intval', $active->mind_ids ?? []);
            $composerPlatformOptIn = (bool) $active->include_platform;
        } elseif ($default) {
            $composerSelectedIds = array_map('intval', $default->mind_ids ?? []);
            $composerPlatformOptIn = (bool) $default->include_platform;
        } else {
            $composerSelectedIds = [];
            $composerPlatformOptIn = false;
        }

        // Resolve the active thread's Minds (if any) so the chat header
        // can display a "Grounded in:" hint with the human-readable names.
        $activeMinds = [];
        if ($active && (!empty($active->mind_ids) || $active->include_platform)) {
            $activeMinds = $this->minds->resolveMindsForUser(
                $user,
                $active->mind_ids ?? [],
                (bool) $active->include_platform,
            );
        }

        return view('user.ai.companion', [
            'balance'  => $this->credits->getBalance($user),
            'threads'  => $threads,
            'active'   => $active,
            'history'  => $history,
            'search'   => $search,
            'snippets' => $snippets,
            'titles'   => $titles,
            'matchCounts' => $matchCounts,
            'compose'           => $compose,
            'mineMinds'         => $this->userMinds($user),
            'platformMind'      => $this->platformMind(),
            'composerSelectedIds'   => $composerSelectedIds,
            'composerPlatformOptIn' => $composerPlatformOptIn,
            'hasDefault'        => (bool) $default,
            'defaultFeature'    => 'companion',
            'activeMinds'       => $activeMinds,
        ]);
    }

    /**
     * Save the current Mind selection (from the composer form) as this
     * user's default for Companion. Subsequent visits and new threads
     * pre-populate the picker.
     */
    public function saveDefaults(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $mindIds = $this->sanitizeMindIds($user, $data['mind_ids'] ?? []);

        AiMindDefault::updateOrCreate(
            ['user_id' => $user->id, 'feature' => 'companion'],
            [
                'mind_ids'         => $mindIds,
                'include_platform' => (bool) ($data['include_platform'] ?? false),
            ],
        );

        return redirect()->route('user.ai.companion.show', ['compose' => 1])
            ->with('status', 'Saved as your default Mind selection for Companion.');
    }

    /**
     * Forget this user's default Mind selection for Companion.
     */
    public function clearDefaults(Request $request)
    {
        $this->ensureEnabled();
        AiMindDefault::where('user_id', $request->user()->id)
            ->where('feature', 'companion')
            ->delete();

        return redirect()->route('user.ai.companion.show', ['compose' => 1])
            ->with('status', 'Cleared your default Mind selection for Companion.');
    }

    /** Build a short, centered snippet around the first occurrence of
     *  the search term so the sidebar can show why a thread matched. */
    protected function snippet(string $content, string $term, int $radius = 60): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $content));
        if ($term === '') return Str::limit($clean, $radius * 2, '…');

        $pos = mb_stripos($clean, $term);
        if ($pos === false) return Str::limit($clean, $radius * 2, '…');

        $start = max(0, $pos - $radius);
        $len   = mb_strlen($term) + ($radius * 2);
        $slice = mb_substr($clean, $start, $len);

        if ($start > 0)                               $slice = '…' . $slice;
        if ($start + $len < mb_strlen($clean))        $slice = $slice . '…';
        return $slice;
    }

    /** Wrap every (case-insensitive) occurrence of $term in <mark>, after
     *  HTML-escaping both the haystack and the needle so a hostile thread
     *  title or message can't inject markup. The returned string is safe
     *  to print with {!! !!} in Blade. */
    protected function highlight(string $text, string $term): string
    {
        $escaped = e($text);
        if ($term === '') return $escaped;
        $pattern = '/' . preg_quote(e($term), '/') . '/iu';
        $result = preg_replace_callback(
            $pattern,
            fn($m) => '<mark class="bg-yellow-300/30 text-white rounded px-0.5">' . $m[0] . '</mark>',
            $escaped
        );
        return $result ?? $escaped;
    }

    /** Create a new (empty) thread and redirect to it.
     *
     * Accepts an optional Mind selection (own ids + platform opt-in) so
     * the new thread is grounded in the user's selected knowledge bases
     * for every turn. When the form omits both, we fall back to the
     * user's saved Companion default so the per-user default is always
     * honored — even when threads are spun up from the simple sidebar
     * button.
     */
    public function store(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();

        $mindIdsProvided = $request->has('mind_ids');
        $platformProvided = $request->has('include_platform');

        if ($mindIdsProvided || $platformProvided) {
            $mindIds = $this->sanitizeMindIds($user, $data['mind_ids'] ?? []);
            $includePlatform = (bool) ($data['include_platform'] ?? false);
        } else {
            $default = AiMindDefault::forUserFeature($user->id, 'companion');
            $mindIds = $default
                ? $this->sanitizeMindIds($user, $default->mind_ids ?? [])
                : [];
            $includePlatform = $default ? (bool) $default->include_platform : false;
        }

        $thread = CompanionThread::create([
            'user_id'          => $user->id,
            'workspace_id'     => $this->workspaceId(),
            'title'            => 'New conversation',
            'mind_ids'         => $mindIds,
            'include_platform' => $includePlatform,
        ]);
        return redirect()->route('user.ai.companion.thread', $thread->id);
    }

    public function send(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = $request->user();
        $wsId = $this->workspaceId();

        $threadModel = $this->threadQuery($user->id, $wsId)->find($thread);
        if (!$threadModel) abort(404);

        // Append the user's turn first so a model failure still leaves a
        // visible record they can retry from. We set `created_at`
        // explicitly (instead of leaning on the DB default) so the
        // in-memory model carries a real timestamp we can use below.
        $now = now();
        $userMsg = CompanionMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'user',
            'content'    => $data['message'],
            'created_at' => $now,
        ]);

        // Build the rolling window for the model: most recent N turns
        // including the message we just stored. Use a fresh query (rather
        // than the relation, which carries a default ascending order) so
        // the ORDER BY for the limit is unambiguous.
        $recent = CompanionMessage::query()
            ->where('thread_id', $threadModel->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get()
            ->reverse()
            ->values();

        // If this thread was opened with a Mind selection, retrieve
        // matching context now (using the latest user turn as the query)
        // and append it to the system prompt so replies stay grounded.
        // Embedding spend is on the asking user, just like Persona/Coach.
        $kbCreditsSpent = 0;
        $kbContext      = '';
        $selectedMinds  = $this->minds->resolveMindsForUser(
            $user,
            $threadModel->mind_ids ?? [],
            (bool) $threadModel->include_platform,
        );
        if ($selectedMinds) {
            try {
                $retrieved = $this->minds->retrieveContext(
                    $user, $selectedMinds, $data['message']
                );
                $kbContext      = $retrieved['context'];
                $kbCreditsSpent = (int) $retrieved['credits_spent'];
            } catch (InsufficientAiCreditsException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Companion Mind retrieval failed: ' . $e->getMessage());
                // Fall through with no context rather than failing the
                // turn — Companion still works without KB grounding.
            }
        }

        $systemPrompt = "You are Companion, a friendly and concise assistant. "
            . "Keep replies clear and short unless the user asks for depth.";
        if ($kbContext !== '') {
            $systemPrompt .= "\n\nWhen relevant, ground your answer in the Knowledge Base context "
                . "below — reuse its terminology, products and audience details. Do not invent "
                . "facts that are not in the context.\n\n"
                . "Knowledge Base context:\n" . $kbContext;
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        try {
            $out = $this->ai->chat($user, AiEngineSettings::featureModel('companion'), $messages, [
                'feature'     => 'companion.chat',
                'temperature' => 0.7,
                'max_tokens'  => 600,
                'reason'      => 'Companion: chat reply',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Companion AI call failed: ' . $e->getMessage());
            // Touch the thread so the user can see their saved turn.
            $threadModel->forceFill(['last_message_at' => $now])->save();
            return back()->with('error',
                'Companion could not reply right now. Please try again.');
        }

        $assistant = CompanionMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => [
                'credits_spent' => (int) $out['credits_spent'] + $kbCreditsSpent,
            ],
            'created_at' => now(),
        ]);

        $updates = ['last_message_at' => $assistant->created_at];
        // Auto-title brand new conversations from the user's first prompt
        // so the sidebar isn't a wall of "New conversation" entries.
        if ($threadModel->title === 'New conversation') {
            $updates['title'] = $this->autoTitle($data['message']);
        }
        $threadModel->forceFill($updates)->save();

        return redirect()->route('user.ai.companion.thread', $threadModel->id);
    }

    /**
     * Stream the full transcript of a thread as a markdown (default) or
     * plain-text download. Streamed + chunked so threads with thousands of
     * turns don't have to be held in memory at once.
     */
    public function export(Request $request, int $thread): StreamedResponse
    {
        $this->ensureEnabled();
        $format = $request->query('format') === 'txt' ? 'txt' : 'md';

        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);

        $filename = $this->exportFilename($threadModel->title) . '.' . $format;
        $mime = $format === 'md' ? 'text/markdown; charset=UTF-8' : 'text/plain; charset=UTF-8';

        return response()->streamDownload(function () use ($threadModel, $format) {
            $out = fopen('php://output', 'w');

            if ($format === 'md') {
                fwrite($out, '# ' . $threadModel->title . "\n\n");
                fwrite($out, '_Exported ' . now()->toDayDateTimeString() . "_\n\n---\n\n");
            } else {
                fwrite($out, $threadModel->title . "\n");
                fwrite($out, str_repeat('=', max(3, mb_strlen($threadModel->title))) . "\n");
                fwrite($out, 'Exported ' . now()->toDayDateTimeString() . "\n\n");
            }

            CompanionMessage::query()
                ->where('thread_id', $threadModel->id)
                ->orderBy('id')
                ->chunk(200, function ($messages) use ($out, $format) {
                    foreach ($messages as $m) {
                        $label = $m->role === 'user' ? 'You'
                            : ($m->role === 'assistant' ? 'Companion' : ucfirst($m->role));
                        $ts = $m->created_at
                            ? $m->created_at->toDayDateTimeString()
                            : '';
                        if ($format === 'md') {
                            fwrite($out, '## ' . $label . ($ts ? ' · ' . $ts : '') . "\n\n");
                            fwrite($out, rtrim((string) $m->content) . "\n\n");
                        } else {
                            fwrite($out, '[' . $label . ($ts ? ' · ' . $ts : '') . "]\n");
                            fwrite($out, rtrim((string) $m->content) . "\n\n");
                        }
                    }
                    if (function_exists('ob_get_level') && ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                });

            fclose($out);
        }, $filename, [
            'Content-Type'        => $mime,
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    public function rename(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120',
                function ($attr, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('The title cannot be blank.');
                    }
                },
            ],
        ]);
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->update(['title' => trim($data['title'])]);
        return redirect()->route('user.ai.companion.thread', $threadModel->id)
            ->with('status', 'Conversation renamed.');
    }

    public function destroy(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->delete();
        return redirect()->route('user.ai.companion.show')
            ->with('status', 'Conversation deleted.');
    }

    protected function threadQuery(int $userId, ?int $workspaceId): Builder
    {
        $q = CompanionThread::query()->where('user_id', $userId);
        if ($workspaceId === null) {
            $q->whereNull('workspace_id');
        } else {
            $q->where('workspace_id', $workspaceId);
        }
        return $q;
    }

    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace')
            ? (int) app('current_workspace')->id
            : null;
    }

    protected function exportFilename(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') $slug = 'companion-conversation';
        return $slug . '-' . now()->format('Ymd-His');
    }

    protected function autoTitle(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message));
        return Str::limit($clean, 60, '…') ?: 'New conversation';
    }

    /**
     * Apply a content search predicate to a CompanionMessage query, using
     * the connection's fulltext index when supported and falling back to
     * a wildcarded LIKE everywhere else (sqlite, older MySQL without FT,
     * etc). The LIKE expression is still pre-escaped by the caller.
     */
    protected function applyContentSearch(Builder $q, string $term, string $like): Builder
    {
        $driver = $q->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $q->whereRaw(
                "to_tsvector('simple', content) @@ plainto_tsquery('simple', ?)",
                [$term],
            );
        }
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $q->whereRaw(
                'MATCH(content) AGAINST(? IN BOOLEAN MODE)',
                [$this->booleanFulltextTerm($term)],
            );
        }
        return $q->where('content', 'like', $like);
    }

    /**
     * Same as applyContentSearch but for the threads.title column. Accepts
     * a generic query/builder so it composes cleanly inside the
     * sub-closures used by the sidebar query.
     */
    protected function applyTitleSearch($q, string $term, string $like)
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return $q->whereRaw(
                "to_tsvector('simple', title) @@ plainto_tsquery('simple', ?)",
                [$term],
            );
        }
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $q->whereRaw(
                'MATCH(title) AGAINST(? IN BOOLEAN MODE)',
                [$this->booleanFulltextTerm($term)],
            );
        }
        return $q->where('title', 'like', $like);
    }

    /**
     * Turn a free-text search term into a MySQL boolean-mode expression:
     * each whitespace-separated token gets a leading `+` so all words must
     * match, and the trailing `*` makes it a prefix search ("compan" finds
     * "companion"). Boolean operators in the user input are stripped so
     * they can't break the parse.
     */
    protected function booleanFulltextTerm(string $term): string
    {
        $clean = preg_replace('/[+\-><()~*"@]+/', ' ', $term) ?? '';
        $tokens = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($tokens)) return '';
        return implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }

    /** @return \Illuminate\Support\Collection<int,AiMind> */
    protected function userMinds($user)
    {
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function platformMind(): ?AiMind
    {
        return AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);
    }

    /**
     * Filter a posted mind_ids array down to ids the asking user
     * actually owns and has not disabled, so we never persist stale or
     * cross-user references in defaults / threads.
     *
     * @param  array<int,int|string> $ids
     * @return array<int,int>
     */
    protected function sanitizeMindIds($user, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }
}
