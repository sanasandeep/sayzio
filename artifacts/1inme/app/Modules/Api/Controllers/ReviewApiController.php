<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Controllers\ReviewSubmissionController;
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
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public REST API for the reviews system, mounted at /api/v1. Mirrors the
 * web reviews surface:
 *   - GET  /reviews/{alias}          → paginated unified review feed
 *   - GET  /reviews/{alias}/summary  → rating summary
 *   - POST /reviews/{alias}          → submit a review (no-login, throttled)
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
                'created_at'    => $i['created_at']?->toIso8601String(),
                'media'         => $i['media'],
                'answers'       => $i['answers'],
            ];
        }, $items);

        return $this->ok([
            'reviews' => $items,
            'summary' => app(ReviewSummaryService::class)->summary((int) $link->user_id, (int) $link->id, $source, (array) ($settings['providers'] ?? [])),
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

        $validated = $request->validate([
            'author_name'  => 'nullable|string|max:120',
            'author_email' => 'nullable|email|max:255',
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
        $status = $spam['is_spam']
            ? Review::STATUS_HIDDEN
            : ($requireApproval ? Review::STATUS_PENDING : Review::STATUS_APPROVED);

        $review = Review::create([
            'user_id'      => $link->user_id,
            'link_id'      => $link->id,
            'author_name'  => $validated['author_name'] ?? null,
            'author_email' => ($settings['collect_email'] ?? true) ? ($validated['author_email'] ?? null) : null,
            'rating'       => $validated['rating'] ?? null,
            'body'         => $validated['body'] ?? null,
            'status'       => $status,
            'is_spam'      => (bool) $spam['is_spam'],
            'spam_reason'  => $spam['reason'] ?? null,
            'ip_hash'      => hash('sha256', $request->ip() . '|' . config('app.key')),
            'fingerprint'  => substr(hash('sha256', (string) $request->userAgent()), 0, 64),
        ]);

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

        if (($settings['collect_media'] ?? true) && $request->hasFile('media')) {
            $owner = $link->user;
            $sort = 0;
            foreach ($request->file('media') as $file) {
                try {
                    $userFile = UserFile::createFromUpload($file, $owner, [
                        'upload_key' => 'review.media', 'enforce_allowlist' => true,
                    ]);
                } catch (\Throwable $e) {
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

        return $this->created([
            'status'  => $status,
            'pending' => $status === Review::STATUS_PENDING,
            'message' => $status === Review::STATUS_APPROVED
                ? 'Your review is now live.'
                : 'Your review was submitted and is awaiting approval.',
        ]);
    }
}
