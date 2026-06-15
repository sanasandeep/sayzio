<?php

namespace App\Modules\User\Support;

use App\Mail\ReviewVerificationMail;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Subscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Decides how a freshly submitted native review should be verified when the
 * page requires customer verification. A review is auto-verified when the
 * reviewer's email matches one the creator already trusts (an active
 * subscriber, or a saved contact); otherwise it is held in the `unverified`
 * status and a one-time email confirmation link is sent.
 */
class ReviewVerifier
{
    /**
     * Try to match the email against the creator's known customers.
     *
     * @return string|null Review::METHOD_SUBSCRIBER / METHOD_CONTACT, or null.
     */
    public function matchKnownCustomer(int $userId, ?string $email): ?string
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return null;
        }

        $isSubscriber = Subscriber::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->exists();
        if ($isSubscriber) {
            return Review::METHOD_SUBSCRIBER;
        }

        $isContact = ContactEmail::query()
            ->whereRaw('LOWER(value) = ?', [$email])
            ->whereHas('contact', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $userId))
            ->exists();
        if ($isContact) {
            return Review::METHOD_CONTACT;
        }

        return null;
    }

    /**
     * Send the one-time email confirmation link for an unverified review.
     * Best-effort: a mail failure must not lose the review.
     */
    public function sendVerificationEmail(Link $link, Review $review): void
    {
        if (!$review->author_email || !$review->verification_token) {
            return;
        }
        try {
            Mail::to($review->author_email)->send(new ReviewVerificationMail($link, $review));
        } catch (\Throwable $e) {
            Log::warning('Review verification email failed', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function freshToken(): string
    {
        return Str::random(64);
    }

    private function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }
}
