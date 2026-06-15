<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Controllers\ReviewsController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\ReviewMedia;
use App\Modules\User\Models\ReviewQuestion;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;

/**
 * Public, no-login review submission for a standalone Reviews page (and the
 * reviews_wall biolink block). Visitors POST a star rating + optional written
 * review, media (image/audio/video) and answers to custom questions.
 *
 * Spam protection: a per-IP throttle (route middleware), a honeypot field,
 * and SpamChecker heuristics. Media is stored against the page owner's
 * UserFile storage since the visitor has no account.
 */
class ReviewSubmissionController extends Controller
{
    public function submit(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) {
            abort(404);
        }

        // Submissions arrive either from a standalone Reviews page, or from a
        // reviews_wall block embedded in a biolink. For the latter the
        // effective settings live on the block content; we still stamp the
        // originating link_id so the review is scoped to the page it came from.
        if ($link->type === Link::TYPE_REVIEWS) {
            $settings = array_merge(ReviewsController::DEFAULT_SETTINGS, $link->settings['reviews'] ?? []);
        } else {
            $block = $link->biolinkBlocks()
                ->where('type', 'reviews_wall')
                ->where('is_active', true)
                ->first();
            if (!$block) {
                abort(404);
            }
            $settings = array_merge(ReviewsController::DEFAULT_SETTINGS, $block->settings ?? []);
        }

        if (!($settings['allow_submissions'] ?? true)) {
            return $this->fail($request, 'Submissions are closed for this page.', 403);
        }

        $validated = $request->validate([
            'author_name'  => 'nullable|string|max:120',
            'author_email' => 'nullable|email|max:255',
            'rating'       => 'nullable|integer|min:1|max:5',
            'body'         => 'nullable|string|max:5000',
            'website'      => 'nullable|string|max:0', // honeypot — must stay empty
            'answers'      => 'nullable|array',
            'answers.*'    => 'nullable|string|max:2000',
            'media'        => 'nullable|array|max:6',
            'media.*'      => 'file|max:51200|mimes:jpg,jpeg,png,webp,gif,mp3,wav,ogg,m4a,mp4,webm,mov',
        ]);

        if (is_null($validated['rating'] ?? null) && empty($validated['body']) && empty($validated['answers'])) {
            return $this->fail($request, 'Please leave a rating, a written review, or answer a question.', 422);
        }

        // ── Spam protection: honeypot + heuristics ──
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

        // ── Custom question answers ──
        if (!empty($validated['answers'])) {
            $questions = ReviewQuestion::where('user_id', $link->user_id)->active()
                ->where(fn ($q) => $q->whereNull('link_id')->orWhere('link_id', $link->id))
                ->get()->keyBy('id');
            foreach ($validated['answers'] as $qid => $answer) {
                $answer = trim((string) $answer);
                if ($answer === '') continue;
                $question = $questions->get((int) $qid);
                if (!$question) continue;
                $review->answers()->create([
                    'question_id' => $question->id,
                    'prompt'      => $question->prompt,
                    'answer'      => $answer,
                ]);
            }
        }

        // ── Media uploads (stored against the page owner) ──
        if (($settings['collect_media'] ?? true) && $request->hasFile('media')) {
            $owner = $link->user;
            $sort = 0;
            foreach ($request->file('media') as $file) {
                try {
                    $userFile = UserFile::createFromUpload($file, $owner, [
                        'upload_key'        => 'review.media',
                        'enforce_allowlist' => true,
                    ]);
                } catch (\Throwable $e) {
                    continue; // skip the offending file, keep the review
                }
                $mime = $file->getMimeType() ?: '';
                $type = str_starts_with($mime, 'image/') ? 'image'
                    : (str_starts_with($mime, 'audio/') ? 'audio'
                    : (str_starts_with($mime, 'video/') ? 'video' : 'image'));
                ReviewMedia::create([
                    'review_id'  => $review->id,
                    'type'       => $type,
                    'url'        => $userFile->url,
                    'meta'       => ['mime' => $mime, 'size' => $file->getSize()],
                    'sort_order' => $sort++,
                ]);
            }
        }

        $message = $spam['is_spam']
            ? 'Thanks! Your review was received.'
            : ($status === Review::STATUS_APPROVED
                ? 'Thanks! Your review is now live.'
                : 'Thanks! Your review was submitted and is awaiting approval.');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => ['status' => $status, 'message' => $message]]);
        }

        return back()->with('success', $message);
    }

    private function fail(Request $request, string $message, int $status)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['error' => ['message' => $message]], $status);
        }
        return back()->with('error', $message);
    }
}
