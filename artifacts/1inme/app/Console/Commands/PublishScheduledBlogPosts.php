<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\NotificationBroadcast;
use Illuminate\Console\Command;

class PublishScheduledBlogPosts extends Command
{
    protected $signature = 'blogs:publish-scheduled';
    protected $description = 'Publish blog posts whose scheduled_at has elapsed.';

    public function handle(): int
    {
        $now = now();
        $due = BlogPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($due as $post) {
            $post->update([
                'status'       => 'published',
                'published_at' => $post->published_at ?: $post->scheduled_at ?: $now,
            ]);
            $this->notifyPublished($post);
            $count++;
        }
        $this->info("Published {$count} scheduled post(s).");
        return self::SUCCESS;
    }

    private function notifyPublished(BlogPost $post): void
    {
        try {
            $recipients = 0;
            foreach (Admin::with('role.permissions')->get() as $admin) {
                if ($admin->hasPermission('blogs.view')) $recipients++;
            }
            NotificationBroadcast::create([
                'admin_id'         => null,
                'target_kind'      => 'permission',
                'target_value'     => 'blogs.view',
                'type'             => 'blog_post_published',
                'subject'          => 'Blog post published: ' . $post->title,
                'body'             => 'The scheduled post "' . $post->title . '" is now live.',
                'target_url'       => route('site.blogs.show', $post->slug),
                'recipients_count' => $recipients,
            ]);
        } catch (\Throwable $e) {
            // Notification is best-effort; never block publishing.
        }
    }
}
