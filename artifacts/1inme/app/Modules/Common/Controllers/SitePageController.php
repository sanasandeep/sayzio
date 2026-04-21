<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SitePage;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class SitePageController extends Controller
{
    public function show(string $slug, ?Request $request = null)
    {
        $request = $request ?? request();

        if ($slug === 'features') {
            $page = SitePage::firstOrCreate(
                ['slug' => 'features'],
                [
                    'title' => 'Features',
                    'meta_description' => 'A complete tour of every capability inside 1INME — biolinks, short links, QR codes, analytics, inboxes, teams, billing, and more.',
                    'sections' => [],
                ]
            );
            return view('public.features', ['page' => $page]);
        }

        $page = SitePage::where('slug', $slug)->firstOrFail();

        if ($slug === 'faqs') {
            return view('public.faqs', ['page' => $page, 'faqs' => $page->faqs()]);
        }
        if ($slug === 'contact') {
            return view('public.contact', ['page' => $page]);
        }
        if ($slug === 'discovery') {
            return $this->showDiscovery($page, $request);
        }
        if ($slug === 'creators-feed') {
            return $this->showCreatorsFeed($page, $request);
        }
        return view('public.page', ['page' => $page]);
    }

    private function showDiscovery(SitePage $page, Request $request)
    {
        $perPage = max(4, min(60, (int) AppSetting::get('discovery_per_page', 24)));
        $showSearch = (bool) AppSetting::get('discovery_show_search', true);

        $q = trim((string) $request->query('q', ''));

        // Public biolinks: active + owned by a discoverable user.
        $query = Link::query()
            ->where('type', 'biolink')
            ->where('is_active', true)
            ->whereHas('user', fn($u) => $u->where('discoverable', true))
            ->with(['user:id,name,handle,bio,avatar,followers_count']);

        if ($showSearch && $q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'ilike', $like)
                    ->orWhere('alias', 'ilike', $like)
                    ->orWhereHas('user', function ($uq) use ($like) {
                        $uq->where('name', 'ilike', $like)
                            ->orWhere('handle', 'ilike', $like)
                            ->orWhere('bio', 'ilike', $like);
                    });
            });
        }

        $biolinks = $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString();

        return view('public.discovery', [
            'page'       => $page,
            'biolinks'   => $biolinks,
            'q'          => $q,
            'showSearch' => $showSearch,
        ]);
    }

    private function showCreatorsFeed(SitePage $page, Request $request)
    {
        $perPage = max(4, min(60, (int) AppSetting::get('creators_feed_per_page', 12)));
        $showPinned = (bool) AppSetting::get('creators_feed_show_pinned', true);

        $posts = CreatorPost::query()
            ->published()
            ->whereHas('user', fn($u) => $u->where('discoverable', true))
            ->with(['user:id,name,handle,avatar'])
            ->when($showPinned, fn($q) => $q->orderByDesc('pinned_at'))
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('public.creators-feed', [
            'page'       => $page,
            'posts'      => $posts,
            'showPinned' => $showPinned,
        ]);
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:190',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
            'website' => 'nullable|max:0', // honeypot
        ]);

        // Honeypot tripped — silently succeed.
        if (!empty($request->input('website'))) {
            return back()->with('success', 'Thanks! We will get back to you shortly.');
        }

        $key = 'contact:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['message' => 'Too many submissions — please try again in a few minutes.'])->withInput();
        }
        RateLimiter::hit($key, 600);

        $msg = ContactMessage::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip'      => $request->ip(),
            'status'  => 'new',
        ]);

        $recipient = AppSetting::get('contact_recipient_email');
        if ($recipient) {
            try {
                Mail::raw(
                    "New contact message from {$msg->name} <{$msg->email}>\n\nSubject: {$msg->subject}\n\n{$msg->message}",
                    function ($m) use ($recipient, $msg) {
                        $m->to($recipient)->subject('[1INME Contact] ' . $msg->subject);
                    }
                );
            } catch (\Throwable $e) {
                \Log::warning('Contact email failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Thanks! Your message has been sent. We will reply within one business day.');
    }
}
