<?php

namespace App\Mail;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a reviewer when a page requires customer verification and the
 * reviewer isn't already a known subscriber/contact. Clicking the one-time
 * link confirms the review and publishes it (or sends it for approval).
 */
class ReviewVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link, public Review $review) {}

    public function build(): self
    {
        $title = $this->link->title ?: $this->link->alias;
        $url   = route('redirect.reviews.verify', [
            'alias' => $this->link->alias,
            'token' => $this->review->verification_token,
        ]);

        return $this
            ->subject("Confirm your review for {$title}")
            ->text('emails.review-verification-text', [
                'link'  => $this->link,
                'review' => $this->review,
                'title' => $title,
                'url'   => $url,
            ]);
    }
}
