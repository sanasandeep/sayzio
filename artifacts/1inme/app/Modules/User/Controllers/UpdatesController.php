<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UpdateEntry;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Purifier;

/**
 * Owner-facing management for the Creator Updates / Changelog page type:
 * the editor view, entry CRUD, settings save, and follower notification
 * dispatch on first publish.
 *
 * Public rendering lives in RedirectController::handleUpdatesPage().
 */
class UpdatesController extends Controller
{
    /** Default settings for a new Updates-page link. */
    public const DEFAULT_SETTINGS = [
        'heading'    => 'Updates',
        'subheading' => 'Stay in the loop with the latest news and changes.',
        'per_page'   => 10,
    ];

    private function ownLinkOrFail(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== Link::TYPE_UPDATES, 404);
    }

    private function ownEntryOrFail(UpdateEntry $entry): void
    {
        abort_if($entry->user_id !== workspace_owner_id(), 403);
    }

    /** Editor view — list of entries + settings panel. */
    public function editor(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $settings = array_merge(self::DEFAULT_SETTINGS, $link->settings['updates'] ?? []);

        $entries = UpdateEntry::where('link_id', $link->id)
            ->orderByDesc('published_date')
            ->orderByDesc('id')
            ->get();

        $allowedTags = UpdateEntry::allowedTags();

        return view('user.links.updates-editor', compact('link', 'settings', 'entries', 'allowedTags'));
    }

    /** Save appearance / meta settings for the Updates page. */
    public function updateSettings(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $data = $request->validate([
            'heading'    => 'nullable|string|max:120',
            'subheading' => 'nullable|string|max:255',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $data['heading']    = $data['heading']    ?? self::DEFAULT_SETTINGS['heading'];
        $data['subheading'] = $data['subheading'] ?? self::DEFAULT_SETTINGS['subheading'];
        $data['per_page']   = (int) ($data['per_page'] ?? self::DEFAULT_SETTINGS['per_page']);

        $settings = $link->settings ?? [];
        $settings['updates'] = $data;
        $link->settings = $settings;
        $link->save();

        return redirect()->back()->with('success', 'Settings saved.');
    }

    /** Create a new entry (draft by default). */
    public function storeEntry(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'body'           => 'nullable|string|max:50000',
            'tag'            => 'nullable|string|in:' . implode(',', UpdateEntry::allowedTags()),
            'published_date' => 'nullable|date',
            'status'         => 'nullable|in:draft,published',
            'image'          => 'nullable|image|max:5120',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            try {
                $imageUrl = UserFile::createFromUpload($request->file('image'), $request->user(), [
                    'compress_image' => true,
                    'max_width'      => 1200,
                    'max_height'     => 800,
                    'quality'        => 85,
                ])->url;
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        $status = $data['status'] ?? 'draft';

        $entry = UpdateEntry::create([
            'link_id'        => $link->id,
            'user_id'        => workspace_owner_id(),
            'title'          => $data['title'],
            'body'           => $data['body'] ? $this->sanitizeBody($data['body']) : null,
            'image'          => $imageUrl,
            'tag'            => $data['tag'] ?? null,
            'published_date' => $data['published_date'] ?? now()->toDateString(),
            'status'         => $status,
            'sort_order'     => 0,
        ]);

        if ($entry->needsFollowerNotification()) {
            $this->notifyFollowers($link, $entry);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->entryJson($entry)], 201);
        }

        return redirect()
            ->route('user.links.updates.editor', $link)
            ->with('success', 'Entry created.');
    }

    /** Update an existing entry. */
    public function updateEntry(Request $request, Link $link, UpdateEntry $entry)
    {
        $this->ownLinkOrFail($link);
        $this->ownEntryOrFail($entry);

        $data = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'body'           => 'nullable|string|max:50000',
            'tag'            => 'nullable|string|in:' . implode(',', UpdateEntry::allowedTags()),
            'published_date' => 'nullable|date',
            'status'         => 'nullable|in:draft,published',
            'image'          => 'nullable|image|max:5120',
            'remove_image'   => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            try {
                $data['image'] = UserFile::createFromUpload($request->file('image'), $request->user(), [
                    'compress_image' => true,
                    'max_width'      => 1200,
                    'max_height'     => 800,
                    'quality'        => 85,
                ])->url;
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        } elseif (!empty($data['remove_image'])) {
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        if (isset($data['body'])) {
            $data['body'] = $data['body'] ? $this->sanitizeBody($data['body']) : null;
        }

        $wasUnnotified = $entry->notified_at === null;
        $entry->fill($data)->save();

        if ($wasUnnotified && $entry->needsFollowerNotification()) {
            $this->notifyFollowers($link, $entry);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->entryJson($entry->fresh())]);
        }

        return redirect()
            ->route('user.links.updates.editor', $link)
            ->with('success', 'Entry updated.');
    }

    /** Delete an entry. */
    public function destroyEntry(Request $request, Link $link, UpdateEntry $entry)
    {
        $this->ownLinkOrFail($link);
        $this->ownEntryOrFail($entry);

        $entry->delete();

        if ($request->wantsJson()) {
            return response()->json(['data' => ['deleted' => true]]);
        }

        return redirect()
            ->route('user.links.updates.editor', $link)
            ->with('success', 'Entry deleted.');
    }

    /**
     * Notify the creator's followers about a newly-published entry.
     * Deduplication is enforced by stamping notified_at — this method is
     * idempotent and safe to call on any publish path.
     */
    private function notifyFollowers(Link $link, UpdateEntry $entry): void
    {
        $creator = \App\Modules\User\Models\User::find($link->user_id);
        if (!$creator) {
            return;
        }

        $shortUrl = $link->getShortUrl();
        $anchor   = $entry->anchorId();
        $message  = $creator->name . ' posted a new update: ' . $entry->title;

        // Build a deep-link URL directly to the entry anchor.
        $entryUrl = $shortUrl . '#' . $anchor;

        \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced($creator, $message);

        $entry->forceFill(['notified_at' => now()])->save();

        unset($entryUrl); // available for future richer notification payloads
    }

    /**
     * Sanitize user-supplied rich-text HTML. Allows common formatting tags
     * (p, b, i, ul, ol, li, a, blockquote, code, pre) and strips everything
     * else to prevent XSS. Falls back to a simple strip_tags if HTMLPurifier
     * isn't available (dev/CI environments).
     */
    private function sanitizeBody(string $html): string
    {
        if (class_exists(Purifier::class)) {
            return Purifier::clean($html, [
                'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,ul,ol,li,a[href|target|rel],blockquote,code,pre,h1,h2,h3,h4',
                'AutoFormat.AutoParagraph' => false,
                'AutoFormat.RemoveEmpty' => true,
                'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            ]);
        }

        $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><blockquote><code><pre><h1><h2><h3><h4>';
        return strip_tags($html, $allowed);
    }

    /** Minimal JSON shape returned by JSON endpoints. */
    private function entryJson(UpdateEntry $entry): array
    {
        return [
            'id'             => $entry->id,
            'title'          => $entry->title,
            'body'           => $entry->body,
            'image'          => $entry->image ? \App\Support\PublicStorageUrl::resolve($entry->image) : null,
            'tag'            => $entry->tag,
            'published_date' => $entry->published_date?->toDateString(),
            'status'         => $entry->status,
            'notified_at'    => $entry->notified_at?->toIso8601String(),
            'anchor_id'      => $entry->anchorId(),
            'created_at'     => $entry->created_at?->toIso8601String(),
            'updated_at'     => $entry->updated_at?->toIso8601String(),
        ];
    }
}
