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
            default => throw new RuntimeException('Unknown suggestion type.'),
        };
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

        $settings['_style'] = BlockDefaults::styleForType($type);

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
