<?php

namespace App\Services\AI;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Modules\User\Models\Pixel;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\TemplateDefaultColors;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Task #3060 — applies a single Marketing Strategy suggestion, turning
 * the AI's `payload` into a real owned object:
 *
 *   create_link  → a new short link
 *   add_block    → a block appended to one of the creator's biolink pages
 *   attach_pixel → an owned pixel attached to an owned link
 *   draft_post   → a scheduled draft creator post
 *
 * Every action re-validates ownership against $user (the AI payload is
 * never trusted) and records the created object on the suggestion via
 * applied_ref_* so the UI can deep link and never double-apply.
 */
class MarketingSuggestionApplier
{
    /**
     * @return array{ref_type:string,ref_id:int,message:string,url:?string}
     */
    public function apply(User $user, MarketingStrategySuggestion $suggestion): array
    {
        if ($suggestion->status === MarketingStrategySuggestion::STATUS_APPLIED) {
            throw new RuntimeException('This suggestion has already been applied.');
        }

        $payload = is_array($suggestion->payload) ? $suggestion->payload : [];

        // Every read/write below is scoped to the strategy's workspace. The
        // Sanctum API path does NOT bind `current_workspace`, so the
        // BelongsToWorkspace global scope is inert there — relying on it would
        // let a multi-workspace owner's apply read/mutate the wrong workspace's
        // objects and create new ones with a NULL workspace_id (then hidden
        // from the workspace-scoped web views). We therefore pass the strategy
        // workspace explicitly and enforce it ourselves.
        $workspaceId = $suggestion->strategy?->workspace_id !== null
            ? (int) $suggestion->strategy->workspace_id
            : null;

        return match ($suggestion->type) {
            MarketingStrategySuggestion::TYPE_CREATE_LINK  => $this->applyCreateLink($user, $payload, $workspaceId),
            MarketingStrategySuggestion::TYPE_ADD_BLOCK    => $this->applyAddBlock($user, $payload, $workspaceId),
            MarketingStrategySuggestion::TYPE_ATTACH_PIXEL => $this->applyAttachPixel($user, $payload, $workspaceId),
            MarketingStrategySuggestion::TYPE_DRAFT_POST   => $this->applyDraftPost($user, $payload, $workspaceId),
            MarketingStrategySuggestion::TYPE_FUNNEL       => $this->applyFunnel($user, $suggestion, $workspaceId),
            default => throw new RuntimeException('Unknown suggestion type.'),
        };
    }

    /**
     * Task #3281 — apply an ordered multi-step funnel in one click.
     *
     * Each step is a single-action play ({create_link|add_block|attach_pixel|
     * draft_post}) run through the SAME per-action method above, so ownership,
     * plan caps and alias uniqueness are re-validated per step — the AI's step
     * list is never trusted. Steps run in order; a failing step is recorded and
     * the funnel continues (so a plan-cap on step 3 doesn't discard steps 1-2).
     *
     * Result semantics for {@see claimAndApply}:
     *   - all steps succeed  → status `applied`
     *   - some succeed       → status `partial`
     *   - none succeed       → throw (claim released to `error`)
     *
     * @return array{ref_type:string,ref_id:int,message:string,url:?string,status:string,step_results:array,error:?string}
     */
    protected function applyFunnel(User $user, MarketingStrategySuggestion $suggestion, ?int $workspaceId): array
    {
        $steps = is_array($suggestion->steps) ? array_values($suggestion->steps) : [];
        if (empty($steps)) {
            throw new RuntimeException('This funnel has no steps to apply.');
        }
        $steps = array_slice($steps, 0, 6); // bound the work per click

        $results   = [];
        $succeeded = 0;
        $firstRef  = null;
        $firstUrl  = null;

        foreach ($steps as $i => $step) {
            $type    = (string) ($step['type'] ?? '');
            $title   = mb_substr(trim((string) ($step['title'] ?? $this->stepLabel($type))), 0, 180);
            $payload = is_array($step['payload'] ?? null) ? $step['payload'] : [];

            $row = ['index' => $i + 1, 'type' => $type, 'title' => $title, 'status' => 'error', 'message' => '', 'url' => null];
            try {
                $res = match ($type) {
                    MarketingStrategySuggestion::TYPE_CREATE_LINK  => $this->applyCreateLink($user, $payload, $workspaceId),
                    MarketingStrategySuggestion::TYPE_ADD_BLOCK    => $this->applyAddBlock($user, $payload, $workspaceId),
                    MarketingStrategySuggestion::TYPE_ATTACH_PIXEL => $this->applyAttachPixel($user, $payload, $workspaceId),
                    MarketingStrategySuggestion::TYPE_DRAFT_POST   => $this->applyDraftPost($user, $payload, $workspaceId),
                    default => throw new RuntimeException('Unsupported funnel step type.'),
                };
                $row['status']  = 'applied';
                $row['message'] = (string) ($res['message'] ?? 'Done.');
                $row['url']     = $res['url'] ?? null;
                $row['ref_type'] = $res['ref_type'] ?? null;
                $row['ref_id']   = isset($res['ref_id']) ? (int) $res['ref_id'] : null;
                $succeeded++;
                if ($firstRef === null && isset($res['ref_id'])) {
                    $firstRef = (int) $res['ref_id'];
                    $firstUrl = $res['url'] ?? null;
                }
            } catch (\Throwable $e) {
                $row['status'] = 'error';
                $row['error']  = mb_substr($e->getMessage(), 0, 300);
            }
            $results[] = $row;
        }

        if ($succeeded === 0) {
            // Surface the first step's error so the funnel goes to `error`.
            $firstErr = $results[0]['error'] ?? 'None of the funnel steps could be applied.';
            throw new RuntimeException($firstErr);
        }

        $total  = count($results);
        $status = $succeeded === $total
            ? MarketingStrategySuggestion::STATUS_APPLIED
            : MarketingStrategySuggestion::STATUS_PARTIAL;
        $message = $succeeded === $total
            ? "Applied all {$total} funnel steps."
            : "Applied {$succeeded} of {$total} funnel steps — review the rest.";

        return [
            'ref_type'     => 'funnel',
            'ref_id'       => $firstRef ?? (int) $suggestion->id,
            'message'      => $message,
            'url'          => $firstUrl ?? route('user.dashboard'),
            'status'       => $status,
            'step_results' => $results,
            'error'        => null,
        ];
    }

    protected function stepLabel(string $type): string
    {
        return match ($type) {
            MarketingStrategySuggestion::TYPE_CREATE_LINK  => 'Create link',
            MarketingStrategySuggestion::TYPE_ADD_BLOCK    => 'Add block',
            MarketingStrategySuggestion::TYPE_ATTACH_PIXEL => 'Attach pixel',
            MarketingStrategySuggestion::TYPE_DRAFT_POST   => 'Draft post',
            default                                        => 'Step',
        };
    }

    /**
     * Task #3095 — atomically claim and apply a pending suggestion.
     *
     * Without this, two near-simultaneous apply requests (double-tap, retry,
     * web+mobile) can both pass an `if (isPending())` read-then-write guard and
     * both run {@see self::apply()}, creating duplicate links/blocks/posts.
     *
     * We serialize with an atomic compare-and-set: a single
     * `UPDATE ... WHERE status = 'pending'` flips the row to `applied`. Under
     * Postgres READ COMMITTED exactly one concurrent request affects 1 row (the
     * winner); the losers see 0 affected and get a {@see SuggestionNotPendingException}.
     * Only the winner builds the owned object, so a suggestion can only ever
     * produce one object even under concurrent requests.
     *
     * On winning we keep the in-memory model reading `pending` (the raw UPDATE
     * doesn't touch it) so the apply() guard passes; we then flip the model to
     * `applied` (with the ref) on success or `error` on failure, mirroring the
     * state the callers previously wrote themselves.
     *
     * @return array{ref_type:string,ref_id:int,message:string,url:?string}
     *
     * @throws SuggestionNotPendingException when the row was already claimed.
     */
    public function claimAndApply(User $user, MarketingStrategySuggestion $suggestion): array
    {
        $claimed = MarketingStrategySuggestion::query()
            ->withoutGlobalScopes()
            ->whereKey($suggestion->getKey())
            ->where('status', MarketingStrategySuggestion::STATUS_PENDING)
            ->update([
                'status'     => MarketingStrategySuggestion::STATUS_APPLIED,
                'applied_at' => now(),
            ]);

        if ($claimed === 0) {
            $current = MarketingStrategySuggestion::query()
                ->withoutGlobalScopes()
                ->whereKey($suggestion->getKey())
                ->value('status');
            // Reflect the committed status on the caller's instance so it can
            // be reported back accurately.
            $suggestion->setAttribute('status', $current ?? $suggestion->status);
            $suggestion->syncOriginalAttribute('status');

            throw new SuggestionNotPendingException($current !== null ? (string) $current : null);
        }

        try {
            $result = $this->apply($user, $suggestion);
        } catch (\Throwable $e) {
            // We claimed the row; release it into the `error` state (not back to
            // `pending`) so the failure surfaces and is never silently retried.
            $suggestion->forceFill([
                'status'     => MarketingStrategySuggestion::STATUS_ERROR,
                'error'      => mb_substr($e->getMessage(), 0, 500),
                'applied_at' => null,
            ])->save();

            throw $e;
        }

        $suggestion->forceFill([
            'status'           => $result['status'] ?? MarketingStrategySuggestion::STATUS_APPLIED,
            'applied_ref_type' => $result['ref_type'],
            'applied_ref_id'   => $result['ref_id'],
            'step_results'     => $result['step_results'] ?? $suggestion->step_results,
            'error'            => $result['error'] ?? null,
            'applied_at'       => now(),
        ])->save();

        return $result;
    }

    /**
     * Scope a query to a specific workspace deterministically, independent of
     * any request-bound `current_workspace`. We drop the global workspace
     * scope and apply the strategy's workspace ourselves (NULL = personal).
     */
    protected function scopeWs(Builder $query, ?int $workspaceId): Builder
    {
        $query->withoutGlobalScope('workspace');

        return $workspaceId === null
            ? $query->whereNull('workspace_id')
            : $query->where('workspace_id', $workspaceId);
    }

    // ── create_link ────────────────────────────────────────────────

    protected function applyCreateLink(User $user, array $payload, ?int $workspaceId): array
    {
        $url = trim((string) ($payload['long_url'] ?? $payload['url'] ?? ''));
        if (!preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('The suggested link needs a valid destination URL.');
        }
        $url = mb_substr($url, 0, 2048);

        // Enforce the creator's plan link cap so applying a suggestion can
        // never silently push them past their allowance (-1/absent = unlimited).
        // The link quota feature key is `n` (matches CheckPlanLimit /
        // LinkController), counted within the strategy's workspace.
        $maxLinks = (int) $user->getPlanFeature('n', -1);
        if ($maxLinks > 0
            && $this->scopeWs(Link::where('user_id', $user->id), $workspaceId)->count() >= $maxLinks) {
            throw new RuntimeException("You've reached your plan's link limit. Upgrade your plan to create more links.");
        }

        // Alias uniqueness is GLOBAL (the public /{alias} URL is site-wide), so
        // this check must ignore workspace scoping entirely.
        $alias = $this->sanitizeAlias((string) ($payload['alias'] ?? ''));
        if ($alias === '' || Link::withoutGlobalScope('workspace')->where('alias', $alias)->exists()) {
            $alias = Link::generateAlias();
        }

        $title = mb_substr(trim((string) ($payload['title'] ?? '')), 0, 180) ?: 'New link';

        $link = new Link([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => $alias,
            'title'     => $title,
            'long_url'  => $url,
            'is_active' => true,
        ]);
        // workspace_id isn't mass-assignable; set it explicitly so the row
        // lands in the strategy's workspace regardless of request binding.
        $link->workspace_id = $workspaceId;
        $link->save();

        return [
            'ref_type' => 'link',
            'ref_id'   => (int) $link->id,
            'message'  => 'Created link "' . $title . '".',
            'url'      => route('user.dashboard'),
        ];
    }

    // ── add_block ──────────────────────────────────────────────────

    protected function applyAddBlock(User $user, array $payload, ?int $workspaceId): array
    {
        $alias = $this->sanitizeAlias((string) ($payload['target_alias'] ?? $payload['alias'] ?? ''));
        if ($alias === '') {
            throw new RuntimeException('The suggestion is missing the target Link-in-Bio page.');
        }

        $link = $this->scopeWs(Link::where('user_id', $user->id), $workspaceId)
            ->where('alias', $alias)
            ->biolinkFamily()
            ->first();
        if (!$link) {
            throw new RuntimeException('Couldn\'t find a Link-in-Bio page with that address on your account.');
        }

        $requested = (string) ($payload['block_type'] ?? 'link');
        $type = match ($requested) {
            'heading'        => 'heading',
            'text', 'paragraph' => 'paragraph',
            default          => 'link',
        };

        $content = trim((string) ($payload['content'] ?? $payload['text'] ?? ''));

        $settings = match ($type) {
            'heading'   => ['text' => mb_substr($content ?: 'New heading', 0, 200), 'size' => 'h2', 'align' => 'center', 'style' => 'plain'],
            'paragraph' => ['text' => mb_substr($content ?: 'New text block', 0, 1500), 'align' => 'center'],
            default     => $this->linkBlockSettings($content, (string) ($payload['url'] ?? '')),
        };

        // Seed `_style` the same way as the web editor / mobile API store
        // paths: platform defaults layered with the type's defaults and the
        // page's template default colors (Task #6039/#6042), so AI-added
        // blocks look on-theme on template-derived pages. Only when the
        // suggestion supplied no `_style` of its own.
        if (!isset($settings['_style']) || !is_array($settings['_style']) || $settings['_style'] === []) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($type),
                TemplateDefaultColors::styleFor($link, $type)
            );
        }

        $sortOrder = (int) BiolinkBlock::where('link_id', $link->id)
            ->whereNull('parent_id')
            ->max('sort_order');

        $block = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $type,
            'settings'   => $settings,
            'sort_order' => $sortOrder + 1,
            'is_active'  => true,
            'parent_id'  => null,
        ]);

        return [
            'ref_type' => 'biolink_block',
            'ref_id'   => (int) $block->id,
            'message'  => 'Added a ' . str_replace('_', ' ', $type) . ' block to "' . ($link->title ?: $alias) . '".',
            'url'      => route('user.links.blocks.editor', $link->id),
        ];
    }

    protected function linkBlockSettings(string $label, string $url): array
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://example.com';
        }
        return [
            'url'       => mb_substr($url, 0, 2048),
            'text'      => mb_substr($label ?: 'My link', 0, 200),
            'icon'      => '',
            'thumbnail' => '',
        ];
    }

    // ── attach_pixel ───────────────────────────────────────────────

    protected function applyAttachPixel(User $user, array $payload, ?int $workspaceId): array
    {
        // Be strict: a tracking pixel attaches to a SPECIFIC link, so both the
        // pixel and the target link must be named explicitly. Never silently
        // fall back to "the first owned pixel" / "the most-clicked link" — a
        // one-click apply could otherwise bind a pixel to an unintended link.
        // Both lookups are scoped to the strategy's workspace.
        $pixelName = trim((string) ($payload['pixel_name'] ?? $payload['pixel'] ?? ''));
        if ($pixelName === '') {
            throw new RuntimeException('The suggestion is missing which tracking pixel to attach.');
        }
        $pixel = $this->scopeWs(Pixel::where('user_id', $user->id), $workspaceId)
            ->where('name', $pixelName)
            ->orderBy('id')
            ->get();
        if ($pixel->count() > 1) {
            throw new RuntimeException('You have more than one pixel named "' . $pixelName . '"; please attach it manually.');
        }
        $pixel = $pixel->first();
        if (!$pixel) {
            throw new RuntimeException('Couldn\'t find a tracking pixel named "' . $pixelName . '" on your account.');
        }

        $alias = $this->sanitizeAlias((string) ($payload['target_alias'] ?? $payload['alias'] ?? ''));
        if ($alias === '') {
            throw new RuntimeException('The suggestion is missing the target link to attach the pixel to.');
        }
        $link = $this->scopeWs(Link::where('user_id', $user->id), $workspaceId)
            ->where('alias', $alias)
            ->first();
        if (!$link) {
            throw new RuntimeException('Couldn\'t find a link with that address on your account.');
        }

        $link->pixels()->syncWithoutDetaching([$pixel->id]);

        return [
            'ref_type' => 'link',
            'ref_id'   => (int) $link->id,
            'message'  => 'Attached pixel "' . $pixel->name . '" to "' . ($link->title ?: $link->alias) . '".',
            'url'      => route('user.dashboard'),
        ];
    }

    // ── draft_post ─────────────────────────────────────────────────

    protected function applyDraftPost(User $user, array $payload, ?int $workspaceId): array
    {
        $title = mb_substr(trim((string) ($payload['title'] ?? '')), 0, 180);
        $body  = trim((string) ($payload['body'] ?? $payload['content'] ?? ''));
        if ($body === '' && $title === '') {
            throw new RuntimeException('The suggested post has no content.');
        }
        $body = mb_substr($body, 0, 5000);

        $days = (int) ($payload['schedule_in_days'] ?? 3);
        if ($days < 1)  $days = 1;
        if ($days > 90) $days = 90;
        $scheduledAt = Carbon::now()->addDays($days);

        $post = new CreatorPost([
            'user_id'        => $user->id,
            'title'          => $title ?: null,
            'body'           => $body ?: $title,
            'post_type'      => CreatorPost::TYPE_TEXT,
            'visibility'     => CreatorPost::VISIBILITY_FREE,
            'scheduled_at'   => $scheduledAt,
            'published_at'   => null,
            'approval_status'=> null,
        ]);
        // workspace_id isn't mass-assignable; set it explicitly so the draft
        // lands in the strategy's workspace regardless of request binding.
        $post->workspace_id = $workspaceId;
        $post->save();

        return [
            'ref_type' => 'creator_post',
            'ref_id'   => (int) $post->id,
            'message'  => 'Drafted a post scheduled for ' . $scheduledAt->toFormattedDateString() . '.',
            'url'      => route('user.posts.index'),
        ];
    }

    // ── helpers ────────────────────────────────────────────────────

    protected function sanitizeAlias(string $alias): string
    {
        $alias = ltrim(trim($alias), '@/');
        // Strip a full URL down to its last path segment if the model
        // returned one instead of a bare alias.
        if (Str::contains($alias, '/')) {
            $alias = (string) Str::afterLast(rtrim($alias, '/'), '/');
        }
        $alias = preg_replace('/[^A-Za-z0-9\-_.]/', '', $alias) ?? '';
        return mb_substr($alias, 0, 191);
    }
}
