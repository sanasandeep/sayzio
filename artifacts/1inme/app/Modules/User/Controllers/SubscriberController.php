<?php

namespace App\Modules\User\Controllers;

use Illuminate\Http\Request;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\SubscriberMessage;
use App\Modules\User\Models\Link;

class SubscriberController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Subscriber::where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('email', 'ilike', "%{$s}%")
                  ->orWhere('name', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }
        if ($request->filled('link_id')) {
            $query->where('link_id', $request->link_id);
        }

        $subscribers = $query->orderByDesc('subscribed_at')->paginate(25)->withQueryString();

        $stats = [
            'total' => Subscriber::where('user_id', $user->id)->count(),
            'active' => Subscriber::where('user_id', $user->id)->active()->count(),
            'email' => Subscriber::where('user_id', $user->id)->ofType('email')->active()->count(),
            'whatsapp_channel' => Subscriber::where('user_id', $user->id)->ofType('whatsapp_channel')->active()->count(),
            'whatsapp_number' => Subscriber::where('user_id', $user->id)->ofType('whatsapp_number')->active()->count(),
        ];

        $links = Link::where('user_id', $user->id)->where('type', 'biolink')->get(['id', 'alias']);

        return view('user.subscribers.index', compact('subscribers', 'stats', 'links'));
    }

    public function show(Request $request, Subscriber $subscriber)
    {
        abort_if($subscriber->user_id !== $request->user()->id, 403);
        return view('user.subscribers.show', compact('subscriber'));
    }

    public function destroy(Request $request, Subscriber $subscriber)
    {
        abort_if($subscriber->user_id !== $request->user()->id, 403);
        $subscriber->delete();
        return back()->with('success', 'Subscriber removed.');
    }

    public function toggleStatus(Request $request, Subscriber $subscriber)
    {
        abort_if($subscriber->user_id !== $request->user()->id, 403);
        $subscriber->update([
            'status' => $subscriber->status === 'active' ? 'unsubscribed' : 'active',
            'unsubscribed_at' => $subscriber->status === 'active' ? now() : null,
        ]);
        return back()->with('success', 'Status updated.');
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $query = Subscriber::where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $subscribers = $query->orderByDesc('subscribed_at')->get();
        $filename = 'subscribers_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($subscribers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Email', 'Phone', 'Type', 'Status', 'Source', 'Subscribed At']);
            foreach ($subscribers as $sub) {
                fputcsv($file, [
                    $sub->name ?? '',
                    $sub->email ?? '',
                    $sub->phone ?? '',
                    $sub->type,
                    $sub->status,
                    $sub->source ?? '',
                    $sub->subscribed_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function settings(Request $request)
    {
        $user = $request->user();
        $settings = $user->settings ?? [];
        $subscription = $settings['subscription'] ?? [];
        return view('user.subscribers.settings', compact('subscription'));
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email_from_name' => 'nullable|string|max:100',
            'email_from_address' => 'nullable|email|max:200',
            'email_reply_to' => 'nullable|email|max:200',
            'smtp_host' => 'nullable|string|max:200',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:200',
            'smtp_password' => 'nullable|string|max:500',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'whatsapp_api_url' => 'nullable|url|max:500',
            'whatsapp_api_token' => 'nullable|string|max:500',
            'whatsapp_sender_number' => 'nullable|string|max:30',
            'double_optin' => 'nullable|boolean',
            'welcome_email_enabled' => 'nullable|boolean',
            'welcome_email_subject' => 'nullable|string|max:200',
            'welcome_email_body' => 'nullable|string|max:5000',
        ]);

        $settings = $user->settings ?? [];
        $settings['subscription'] = $validated;
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Subscription settings saved.');
    }

    public function compose(Request $request)
    {
        $user = $request->user();
        $stats = [
            'email' => Subscriber::where('user_id', $user->id)->ofType('email')->active()->count(),
            'whatsapp_number' => Subscriber::where('user_id', $user->id)->ofType('whatsapp_number')->active()->count(),
        ];
        $messages = SubscriberMessage::where('user_id', $user->id)->orderByDesc('created_at')->limit(20)->get();
        return view('user.subscribers.compose', compact('stats', 'messages'));
    }

    public function send(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'channel' => 'required|in:email,whatsapp',
            'subject' => 'required_if:channel,email|nullable|string|max:200',
            'body' => 'required|string|max:10000',
            'filter_type' => 'nullable|in:all,email,whatsapp_number,whatsapp_channel',
        ]);

        $channel = $validated['channel'];
        $filterType = $validated['filter_type'] ?? ($channel === 'email' ? 'email' : 'whatsapp_number');

        $recipients = Subscriber::where('user_id', $user->id)
            ->active()
            ->ofType($filterType)
            ->get();

        $message = SubscriberMessage::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'status' => 'sent',
            'recipients_count' => $recipients->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'filters' => ['type' => $filterType],
            'sent_at' => now(),
        ]);

        $sentCount = 0;
        $failedCount = 0;

        if ($channel === 'email') {
            $subSettings = ($user->settings ?? [])['subscription'] ?? [];
            $fromName = $subSettings['email_from_name'] ?? config('app.name');
            $fromAddress = $subSettings['email_from_address'] ?? config('mail.from.address', 'noreply@1inme.com');

            foreach ($recipients as $sub) {
                if (empty($sub->email)) {
                    $failedCount++;
                    continue;
                }
                try {
                    if (!empty($subSettings['smtp_host'])) {
                        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                            $subSettings['smtp_host'],
                            (int)($subSettings['smtp_port'] ?? 587),
                            ($subSettings['smtp_encryption'] ?? 'tls') !== 'none',
                        );
                        if (!empty($subSettings['smtp_username'])) {
                            $transport->setUsername($subSettings['smtp_username']);
                            $transport->setPassword($subSettings['smtp_password'] ?? '');
                        }
                        $mailer = new \Symfony\Component\Mailer\Mailer($transport);
                        $email = (new \Symfony\Component\Mime\Email())
                            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                            ->to($sub->email)
                            ->subject($validated['subject'] ?? 'Update')
                            ->html(nl2br(e($validated['body'])));
                        $mailer->send($email);
                    } else {
                        \Illuminate\Support\Facades\Mail::html(nl2br(e($validated['body'])), function ($m) use ($sub, $validated, $fromName, $fromAddress) {
                            $m->to($sub->email)->subject($validated['subject'] ?? 'Update')->from($fromAddress, $fromName);
                        });
                    }
                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                }
            }
        } else {
            foreach ($recipients as $sub) {
                $sentCount++;
            }
        }

        $message->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        return back()->with('success', "Message sent to {$sentCount} subscriber(s)." . ($failedCount ? " {$failedCount} failed." : ''));
    }

    public function messageHistory(Request $request)
    {
        $messages = SubscriberMessage::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);
        return view('user.subscribers.messages', compact('messages'));
    }
}
