<?php

namespace App\Mail;

use App\Modules\User\Models\CommunityPost;
use App\Modules\User\Models\Link;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies an Insider community member that the creator just published a
 * new post in the gated feed. Plain-text only so we don't depend on a
 * dedicated marketing template.
 */
class InsiderPostMail extends Mailable
{
    use Queueable, SerializesModels;

    public Link $link;
    public CommunityPost $post;
    public string $creatorName;

    public function __construct(Link $link, CommunityPost $post, string $creatorName)
    {
        $this->link        = $link;
        $this->post        = $post;
        $this->creatorName = $creatorName;
    }

    public function build(): self
    {
        $title = $this->post->title ?: 'New Insider post';
        $url   = url('/' . $this->link->alias);
        return $this
            ->subject("{$this->creatorName} posted: {$title}")
            ->text('emails.insider-post-text', [
                'creator' => $this->creatorName,
                'title'   => $title,
                'body'    => $this->post->body,
                'url'     => $url,
            ]);
    }
}
