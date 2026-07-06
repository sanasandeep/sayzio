<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Controllers\ReviewSubmissionController;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Controllers\ReviewsController;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\ReviewMedia;
use App\Modules\User\Models\ReviewQuestion;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\SpamChecker;
use App\Modules\User\Support\ReviewFeed;
use App\Modules\User\Support\ReviewSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public REST API for the reviews system, mounted at /api/v1. Mirrors the
 * web reviews surface:
 *   - GET  /reviews/{alias}          → paginated unified review feed
 *   - GET  /reviews/{alias}/summary  → rating summary
 *   - POST /reviews/{alias}          → submit a review (no-login, throttled)
 *
 * Owner-side moderation (auth:sanctum) mirrors the web
 * ReviewsController moderation actions so creators can triage reviews
 * from the mobile app:
 *   - GET    /me/reviews                  → list own reviews (all statuses)
 *   - POST   /me/reviews/{review}/approve → publish a review
 *   - POST   /me/reviews/{review}/hide    → hide a review
 *   - POST   /me/reviews/{review}/pin     → toggle pinned
 *   - POST   /me/reviews/{review}/reply   → set / clear the owner reply
 *   - DELETE /me/reviews/{review}         → delete a review
 *
 * All responses use the unified {data}/{error} envelope.
 */
class ReviewApiController extends Controller
{
    use ApiResponses;

    /**
     * Resolve a reviews surface from an alias. Accepts either a standalone
     * Reviews page (Link::TYPE_REVIEWS) or any link hosting an active
     * reviews_wall biolink block, mirroring the public web submission flow.
     */
    private function resolveReviewsLink(Request $request, string $alias): ?Link
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) {
            return null;
        }
        if ($link->type === Link::TYPE_REVIEWS) {
            return $link;
        }
        $hasWall = $link->biolinkBlocks()
            ->where('type', 'reviews_wall')
            ->where('is_active', true)
            ->exists();

        return $hasWall ? $link : null;
    }

    /**
     * Effective reviews settings for the surface: page settings for a
     * standalone Reviews page, block settings for a reviews_wall block.
     */
    private function resolveSettings(Link $link): array
    {
        if ($link->type === Link::TYPE_REVIEWS) {
            return array_merge(ReviewsController::DEFAULT_SETTINGS, $link->settings['reviews'] ?? []);
        }
        $block = $link->biolinkBlocks()
            ->where('type', 'reviews_wall')
            ->where('is_active', true)
            ->first();

        return array_merge(ReviewsController::DEFAULT_SETTINGS, $block->settings ?? []);
    }

    /**
     * Enforce the link's visibility tier on the reviews surface, mirroring
     * BiolinkController::show(). Returns an {error} JsonResponse when access is
     * denied, or null when the viewer may proceed. Owners always bypass.
     */
    private function gateVisibility(Request $request, Link $link)
    {
        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate === null) {
            return null;
        }

        return $this->fail($gate['message'], $gate['status'], $gate['code'], [
            'visibility' => $link->visibility,
            'owner'      => ['handle' => $owner?->handle, 'name' => $owner?->name],
        ]);
    }

    protected function checkVisibility(Link $link, $viewer, $owner): ?array
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return null;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return null;

        if (!$viewer) {
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view these reviews'];
        }
        if ($vis === 'registered') return null;

        if ($vis === 'followers') {
            $follows = Follow::where('follower_id', $viewer->id)->where('creator_id', $owner->id)->exists();
            return $follows ? null : ['status' => 403, 'code' => 'follow_required', 'message' => 'Follow this creator to view'];
        }

        if ($vis === 'subscribers') {
            $isSub = Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')
                ->exists();
            return $isSub ? null : ['status' => 403, 'code' => 'subscribe_required', 'message' => 'Subscribe to this creator to view'];
        }

        return ['status' => 403, 'code' => 'forbidden', 'message' => 'Not allowed'];
    }

    public function index(Request $request, string $alias)
    {
        $link = $this->resolveReviewsLink($request, $alias);
        if (!$link) return $this->notFound('Reviews page not found.');

        if ($gate = $this->gateVisibility($request, $link)) return $gate;

        $settings = $this->resolveSettings($link);
        $source = $request->query('source', $settings['source'] ?? 'both');
        $sort   = $request->query('sort', $settings['sort'] ?? 'recent');
        $limit  = min(100, max(1, (int) $request->query('limit', $settings['limit'] ?? 24)));

        if (!in_array($source, ['native', 'external', 'both'], true)) $source = 'both';
        if (!in_array($sort, ['recent', 'rating'], true)) $sort = 'recent';

        $items = ReviewFeed::build((int) $link->user_id, (int) $link->id, $source, $sort, $limit, (array) ($settings['providers'] ?? []));

        // Strip the internal pin flag from the public payload.
        $items = array_map(function ($i) {
            return [
                'id'            => $i['id'],
                'source'        => $i['source'],
                'source_label'  => $i['source_label'],
                'author_name'   => $i['author_name'],
                'author_avatar' => $i['author_avatar'],
                'rating'        => $i['rating'],
                'body'          => $i['body'],
                'reply'         => $i['reply'],
                'source_url'    => $i['source_url'],
                'pinned'        => $i['is_pinned'],
                'verified'      => $i['verified'] ?? false,
                'created_at'    => $i['created_at']?->toIso8601String(),
                'media'         => $i['media'],
                'answers'       => $i['answers'],
            ];
        }, $items);

        return $this->ok([
            'reviews' => $items,
            'summary' => app(ReviewSummaryService::class)->summary((int) $link->user_id, (int) $link->id, $source, (array) ($settings['providers'] ?? [])),
            'pairings' => SitePagesContent::linkTypePairingsFor('reviews'),
        ]);
    }

    public function summary(Request $request, string $alias)
    {
        $link = $this->resolveReviewsLink($request, $alias);
        if (!$link) return $this->notFound('Reviews page not found.');

        if ($gate = $this->gateVisibility($request, $link)) return $gate;

        $settings = $this->resolveSettings($link);
        $source = $request->query('source', $settings['source'] ?? 'both');
        if (!in_array($source, ['native', 'external', 'both'], true)) $source = 'both';

        return $this->ok(app(ReviewSummaryService::class)->summary((int) $link->user_id, (int) $link->id, $source, (array) ($settings['providers'] ?? [])));
    }

    public function submit(Request $request, string $alias)
    {
        $link = $this->resolveReviewsLink($request, $alias);
        if (!$link) return $this->notFound('Reviews page not found.');

        if ($gate = $this->gateVisibility($request, $link)) return $gate;

        $settings = $this->resolveSettings($link);
        if (!($settings['allow_submissions'] ?? true)) {
            return $this->fail('Submissions are closed for this page.', 403, 'submissions_closed');
        }

        $requireVerification = (bool) ($settings['require_verification'] ?? false);

        $validated = $request->validate([
            'author_name'  => 'nullable|string|max:120',
            'author_email' => ($requireVerification ? 'required' : 'nullable') . '|email|max:255',
            'rating'       => 'nullable|integer|min:1|max:5',
            'body'         => 'nullable|string|max:5000',
            'website'      => 'nullable|string|max:0',
            'answers'      => 'nullable|array',
            'answers.*'    => 'nullable|string|max:2000',
            'media'        => 'nullable|array|max:6',
            'media.*'      => 'file|max:51200|mimes:jpg,jpeg,png,webp,gif,mp3,wav,ogg,m4a,mp4,webm,mov',
        ]);

        if (is_null($validated['rating'] ?? null) && empty($validated['body']) && empty($validated['answers'])) {
            return $this->fail('Please leave a rating, a written review, or answer a question.', 422, 'empty_review');
        }

        $spam = app(SpamChecker::class)->check([
            'honeypot' => $request->input('website'),
            'ip'       => $request->ip(),
            'text'     => trim(($validated['body'] ?? '') . ' ' . ($validated['author_name'] ?? '')),
            'scope'    => 'review:' . $link->id,
            'user_id'  => $link->user_id,
            'email'    => $validated['author_email'] ?? null,
        ]);

        $requireApproval = $settings['require_approval'] ?? true;

        // ── Optional customer verification (mirrors the web flow) ──
        $email = $validated['author_email'] ?? null;
        $verifiedAt = null;
        $verificationMethod = null;
        $verificationToken = null;
        $needsEmailVerify = false;

        if ($requireVerification && !$spam['is_spam']) {
            $verifier = app(\App\Modules\User\Support\ReviewVerifier::class);
            if ($method = $verifier->matchKnownCustomer((int) $link->user_id, $email)) {
                $verifiedAt = now();
                $verificationMethod = $method;
            } else {
                $needsEmailVerify = true;
                $verificationToken = $verifier->freshToken();
            }
        }

        if ($spam['is_spam']) {
            $status = Review::STATUS_HIDDEN;
        } elseif ($needsEmailVerify) {
            $status = Review::STATUS_UNVERIFIED;
        } else {
            $status = $requireApproval ? Review::STATUS_PENDING : Review::STATUS_APPROVED;
        }

        $keepEmail = ($settings['collect_email'] ?? true) || $requireVerification;

        $review = Review::create([
            'user_id'      => $link->user_id,
            'link_id'      => $link->id,
            'author_name'  => $validated['author_name'] ?? null,
            'author_email' => $keepEmail ? $email : null,
            'rating'       => $validated['rating'] ?? null,
            'body'         => $validated['body'] ?? null,
            'status'       => $status,
            'is_spam'      => (bool) $spam['is_spam'],
            'spam_reason'  => $spam['reason'] ?? null,
            'verified_at'  => $verifiedAt,
            'verification_method' => $verificationMethod,
            'verification_token'  => $verificationToken,
            'ip_hash'      => hash('sha256', $request->ip() . '|' . config('app.key')),
            'fingerprint'  => substr(hash('sha256', (string) $request->userAgent()), 0, 64),
        ]);

        if ($needsEmailVerify) {
            app(\App\Modules\User\Support\ReviewVerifier::class)->sendVerificationEmail($link, $review);
        }

        if (!empty($validated['answers'])) {
            $questions = ReviewQuestion::where('user_id', $link->user_id)->active()
                ->where(fn ($q) => $q->whereNull('link_id')->orWhere('link_id', $link->id))
                ->get()->keyBy('id');
            foreach ($validated['answers'] as $qid => $answer) {
                $answer = trim((string) $answer);
                if ($answer === '') continue;
                if ($question = $questions->get((int) $qid)) {
                    $review->answers()->create([
                        'question_id' => $question->id,
                        'prompt'      => $question->prompt,
                        'answer'      => $answer,
                    ]);
                }
            }
        }

        $skipped = [];
        if (($settings['collect_media'] ?? true) && $request->hasFile('media')) {
            $owner = $link->user;
            $sort = 0;
            foreach ($request->file('media') as $file) {
                try {
                    $userFile = UserFile::createFromUpload($file, $owner, [
                        'upload_key' => 'review.media', 'enforce_allowlist' => true,
                    ]);
                } catch (\Throwable $e) {
                    // Don't silently lose the file — remember its name so the
                    // reviewer can be told it wasn't attached.
                    $skipped[] = $file->getClientOriginalName() ?: 'a file';
                    continue;
                }
                $mime = $file->getMimeType() ?: '';
                $type = str_starts_with($mime, 'audio/') ? 'audio'
                    : (str_starts_with($mime, 'video/') ? 'video' : 'image');
                ReviewMedia::create([
                    'review_id'  => $review->id,
                    'type'       => $type,
                    'url'        => $userFile->url,
                    'meta'       => ['mime' => $mime, 'size' => $file->getSize()],
                    'sort_order' => $sort++,
                ]);
            }
        }

        if ($needsEmailVerify) {
            $message = 'Check your email and tap the link to confirm and publish your review.';
        } elseif ($status === Review::STATUS_APPROVED) {
            $message = 'Your review is now live.';
        } else {
            $message = 'Your review was submitted and is awaiting approval.';
        }

        if (!empty($skipped)) {
            $count = count($skipped);
            $message .= $count === 1
                ? " However, 1 file couldn't be attached and was skipped."
                : " However, {$count} files couldn't be attached and were skipped.";
        }

        return $this->created([
            'status'             => $status,
            'pending'            => $status === Review::STATUS_PENDING,
            'needs_verification' => $needsEmailVerify,
            'message'            => $message,
            'media_skipped'      => count($skipped),
            'skipped_files'      => $skipped,
        ]);
    }

    // ── Owner moderation (auth:sanctum) ─────────────────────────────────

    /**
     * List the authenticated owner's native reviews for moderation. Unlike
     * the public feed this returns every status (pending / approved /
     * hidden / unverified), the spam flag, and the reviewer's email so the
     * creator can triage from the app. External (provider-imported) reviews
     * live in a separate table and are read-only, so they are excluded.
     *
     * Optional `status` query filters to one tier; `per_page` (1..100,
     * default 30) controls pagination.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $allowed = [
            Review::STATUS_PENDING,
            Review::STATUS_APPROVED,
            Review::STATUS_HIDDEN,
            Review::STATUS_UNVERIFIED,
        ];

        $query = Review::forUser((int) $user->id)
            ->with(['media', 'answers', 'link:id,title,alias'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, $allowed, true)) {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));
        $page = $query->paginate($perPage);

        return $this->ok([
            'reviews' => array_map(fn ($r) => $this->ownerReviewPayload($r), $page->items()),
            'counts'  => $this->ownerCounts((int) $user->id),
            'meta'    => [
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function approve(Request $request, int $review)
    {
        $r = $this->ownedReviewOr($request, $review);
        if ($r instanceof JsonResponse) return $r;
        $r->update(['status' => Review::STATUS_APPROVED, 'is_spam' => false]);
        return $this->ok($this->ownerReviewPayload($r));
    }

    public function hide(Request $request, int $review)
    {
        $r = $this->ownedReviewOr($request, $review);
        if ($r instanceof JsonResponse) return $r;
        $r->update(['status' => Review::STATUS_HIDDEN]);
        return $this->ok($this->ownerReviewPayload($r));
    }

    public function pin(Request $request, int $review)
    {
        $r = $this->ownedReviewOr($request, $review);
        if ($r instanceof JsonResponse) return $r;
        $r->update(['is_pinned' => !$r->is_pinned]);
        return $this->ok($this->ownerReviewPayload($r));
    }

    public function reply(Request $request, int $review)
    {
        $r = $this->ownedReviewOr($request, $review);
        if ($r instanceof JsonResponse) return $r;
        $data = $request->validate(['reply' => 'nullable|string|max:2000']);
        $r->update([
            'reply'      => $data['reply'] ?: null,
            'replied_at' => $data['reply'] ? now() : null,
        ]);
        return $this->ok($this->ownerReviewPayload($r));
    }

    public function destroy(Request $request, int $review)
    {
        $r = $this->ownedReviewOr($request, $review);
        if ($r instanceof JsonResponse) return $r;
        $r->delete();
        return $this->ok(['id' => (string) $review, 'deleted' => true]);
    }

    /**
     * Resolve a native review owned by the authenticated user, eager-loading
     * the relations the owner payload needs. Returns a 404 {error} when the
     * review does not exist and a 403 {error} when it belongs to another
     * creator, so moderation is always owner-scoped server-side.
     */
    private function ownedReviewOr(Request $request, int $id): Review|JsonResponse
    {
        $review = Review::with(['media', 'answers', 'link:id,title,alias'])->find($id);
        if (!$review) {
            return $this->notFound('Review not found.');
        }
        if ((int) $review->user_id !== (int) $request->user()->id) {
            return $this->forbidden('You can only moderate your own reviews.');
        }
        return $review;
    }

    /** Per-status review tallies for the owner (drives mobile tab badges). */
    private function ownerCounts(int $userId): array
    {
        $rows = Review::forUser($userId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'pending'    => (int) ($rows[Review::STATUS_PENDING] ?? 0),
            'approved'   => (int) ($rows[Review::STATUS_APPROVED] ?? 0),
            'hidden'     => (int) ($rows[Review::STATUS_HIDDEN] ?? 0),
            'unverified' => (int) ($rows[Review::STATUS_UNVERIFIED] ?? 0),
        ];
    }

    /** Owner-facing serialization (includes moderation-only fields). */
    private function ownerReviewPayload(Review $r): array
    {
        return [
            'id'            => (string) $r->id,
            'status'        => $r->status,
            'is_spam'       => (bool) $r->is_spam,
            'spam_reason'   => $r->spam_reason,
            'pinned'        => (bool) $r->is_pinned,
            'author_name'   => $r->author_name,
            'author_email'  => $r->author_email,
            'author_avatar' => $r->author_avatar,
            'rating'        => $r->rating,
            'body'          => $r->body,
            'reply'         => $r->reply,
            'replied_at'    => $r->replied_at?->toIso8601String(),
            'verified'      => $r->isVerified(),
            'created_at'    => $r->created_at?->toIso8601String(),
            'link'          => $r->link ? [
                'id'    => (string) $r->link->id,
                'title' => $r->link->title,
                'alias' => $r->link->alias,
            ] : null,
            'media' => $r->media->map(fn ($m) => [
                'type' => $m->type,
                'url'  => $m->url,
                'meta' => $m->meta,
            ])->all(),
            'answers' => $r->answers->map(fn ($a) => [
                'prompt' => $a->prompt,
                'answer' => $a->answer,
            ])->all(),
        ];
    }
}
