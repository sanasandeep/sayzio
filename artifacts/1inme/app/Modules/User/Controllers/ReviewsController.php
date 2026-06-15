<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ExternalReview;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\ReviewProvider;
use App\Modules\User\Models\ReviewQuestion;
use App\Modules\User\Support\ReviewSummaryService;
use App\Services\ReviewProviders\ReviewProviderRegistry;
use Illuminate\Http\Request;

/**
 * Creator-side management for the reviews system: the standalone Reviews
 * page editor, moderation (approve / hide / reply / pin / delete), custom
 * review questions, page settings, and 3rd-party provider connections.
 *
 * Reviews are scoped to the creator (user_id); the editor is opened from a
 * particular Reviews-page link but moderates every review the creator owns.
 */
class ReviewsController extends Controller
{
    /** Defaults for a Reviews-page link's settings['reviews'] payload. */
    public const DEFAULT_SETTINGS = [
        'heading'           => 'Reviews',
        'subheading'        => 'See what people are saying — and leave your own.',
        'source'            => 'both',     // native | external | both
        'providers'         => [],         // imported-provider slugs to show; [] = all connected
        'sort'              => 'recent',   // recent | rating
        'layout'            => 'grid',     // grid | list
        'limit'             => 24,
        'show_summary'      => true,
        'allow_submissions' => true,
        'require_approval'  => true,
        'collect_media'     => true,
        'collect_email'     => true,
    ];

    private function ownLinkOrFail(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== Link::TYPE_REVIEWS, 404);
    }

    private function ownReviewOrFail(Review $review): void
    {
        abort_if($review->user_id !== workspace_owner_id(), 403);
    }

    public function editor(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $settings = array_merge(self::DEFAULT_SETTINGS, $link->settings['reviews'] ?? []);

        $reviews = Review::forUser($link->user_id)
            ->with(['media', 'answers', 'link:id,title,alias'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $questions = ReviewQuestion::where('user_id', $link->user_id)
            ->orderBy('sort_order')->get();

        $summary = app(ReviewSummaryService::class)
            ->summary((int) $link->user_id, (int) $link->id, $settings['source']);

        $providers = ReviewProviderRegistry::all();
        $connections = ReviewProvider::where('user_id', $link->user_id)
            ->get()->keyBy('provider');

        return view('user.links.reviews-editor', compact(
            'link', 'settings', 'reviews', 'questions', 'summary', 'providers', 'connections'
        ));
    }

    public function updateSettings(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $data = $request->validate([
            'heading'           => 'nullable|string|max:120',
            'subheading'        => 'nullable|string|max:255',
            'source'            => 'required|in:native,external,both',
            'providers'         => 'nullable|array',
            'providers.*'       => 'string|max:50',
            'sort'              => 'required|in:recent,rating',
            'layout'            => 'required|in:grid,list',
            'limit'             => 'required|integer|min:1|max:200',
            'show_summary'      => 'boolean',
            'allow_submissions' => 'boolean',
            'require_approval'  => 'boolean',
            'collect_media'     => 'boolean',
            'collect_email'     => 'boolean',
        ]);

        foreach (['show_summary', 'allow_submissions', 'require_approval', 'collect_media', 'collect_email'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // Normalise selected providers (empty / absent = show all connected).
        $data['providers'] = array_values(array_filter(
            (array) ($data['providers'] ?? []),
            fn ($p) => is_string($p) && $p !== ''
        ));

        $settings = $link->settings ?? [];
        $settings['reviews'] = array_merge(self::DEFAULT_SETTINGS, $settings['reviews'] ?? [], $data);
        $link->settings = $settings;
        $link->save();

        return back()->with('success', 'Reviews page settings saved.');
    }

    // ── Moderation ──────────────────────────────────────────────────────
    public function approve(Request $request, Review $review)
    {
        $this->ownReviewOrFail($review);
        $review->update(['status' => Review::STATUS_APPROVED, 'is_spam' => false]);
        return $this->modResponse($request, $review, 'Review approved.');
    }

    public function hide(Request $request, Review $review)
    {
        $this->ownReviewOrFail($review);
        $review->update(['status' => Review::STATUS_HIDDEN]);
        return $this->modResponse($request, $review, 'Review hidden.');
    }

    public function pin(Request $request, Review $review)
    {
        $this->ownReviewOrFail($review);
        $review->update(['is_pinned' => !$review->is_pinned]);
        return $this->modResponse($request, $review, $review->is_pinned ? 'Review pinned.' : 'Review unpinned.');
    }

    public function reply(Request $request, Review $review)
    {
        $this->ownReviewOrFail($review);
        $data = $request->validate(['reply' => 'nullable|string|max:2000']);
        $review->update([
            'reply'      => $data['reply'] ?: null,
            'replied_at' => $data['reply'] ? now() : null,
        ]);
        return $this->modResponse($request, $review, 'Reply saved.');
    }

    public function destroy(Request $request, Review $review)
    {
        $this->ownReviewOrFail($review);
        $review->delete();
        return $this->modResponse($request, $review, 'Review deleted.');
    }

    private function modResponse(Request $request, Review $review, string $message)
    {
        if ($request->ajax()) {
            return response()->json(['data' => ['id' => $review->id, 'message' => $message]]);
        }
        return back()->with('success', $message);
    }

    // ── Custom questions ────────────────────────────────────────────────
    public function storeQuestion(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);
        $data = $request->validate([
            'prompt'      => 'required|string|max:255',
            'type'        => 'required|in:text,rating,choice',
            'options'     => 'nullable|array',
            'options.*'   => 'string|max:120',
            'is_required' => 'boolean',
        ]);

        ReviewQuestion::create([
            'user_id'     => $link->user_id,
            'link_id'     => null,
            'prompt'      => $data['prompt'],
            'type'        => $data['type'],
            'options'     => $data['type'] === 'choice' ? array_values(array_filter($data['options'] ?? [])) : null,
            'is_required' => $request->boolean('is_required'),
            'is_active'   => true,
            'sort_order'  => (int) ReviewQuestion::where('user_id', $link->user_id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Question added.');
    }

    public function destroyQuestion(Request $request, ReviewQuestion $question)
    {
        abort_if($question->user_id !== workspace_owner_id(), 403);
        $question->delete();
        return back()->with('success', 'Question removed.');
    }

    // ── 3rd-party providers ─────────────────────────────────────────────
    public function connectProvider(Request $request, string $provider)
    {
        abort_unless(ReviewProviderRegistry::exists($provider), 404);
        $data = $request->validate(['external_ref' => 'nullable|string|max:255']);

        $connection = ReviewProvider::updateOrCreate(
            ['user_id' => workspace_owner_id(), 'provider' => $provider],
            [
                'external_ref' => $data['external_ref'] ?? null,
                'status'       => ReviewProvider::STATUS_PREVIEW,
            ]
        );

        $result = ReviewProviderRegistry::adapter($provider)->sync($connection);

        $note = $result['preview']
            ? "Connected in preview mode (no API credentials). Imported {$result['imported']} sample review(s)."
            : "Connected. Imported {$result['imported']} review(s).";

        return back()->with('success', $note);
    }

    public function refreshProvider(Request $request, ReviewProvider $providerConn)
    {
        abort_if($providerConn->user_id !== workspace_owner_id(), 403);
        $result = ReviewProviderRegistry::adapter($providerConn->provider)->sync($providerConn);
        return back()->with('success', "Synced — imported {$result['imported']} new review(s).");
    }

    public function disconnectProvider(Request $request, ReviewProvider $providerConn)
    {
        abort_if($providerConn->user_id !== workspace_owner_id(), 403);
        ExternalReview::where('provider_id', $providerConn->id)->delete();
        $providerConn->delete();
        return back()->with('success', 'Provider disconnected and its imported reviews removed.');
    }
}
