<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\ContactMessageReply;
use App\Modules\Common\Services\Emailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ContactInboxController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $sort = $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $q = ContactMessage::query();
        if (in_array($status, ['new', 'read', 'replied', 'archived', 'spam'])) {
            $q->where('status', $status);
        } else {
            // The default "All" view excludes the spam review queue so a
            // distributed flood that's been quarantined can't bury real
            // leads. Quarantined items are reviewed under the Spam tab.
            $q->where('status', '!=', 'spam');
        }
        $messages = $q->with('replies.admin')->orderBy('created_at', $sort)->paginate(20)->withQueryString();
        $spamCount = ContactMessage::where('status', 'spam')->count();
        return view('admin.contact-inbox.index', compact('messages', 'status', 'sort', 'spamCount'));
    }

    public function markRead(ContactMessage $message)
    {
        $message->update(['status' => 'read']);
        return back()->with('success', 'Marked as read.');
    }

    public function archive(ContactMessage $message)
    {
        $message->update(['status' => 'archived']);
        return back()->with('success', 'Archived.');
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();
        return back()->with('success', 'Deleted.');
    }

    public function reply(Request $request, ContactMessage $message)
    {
        if (empty($message->email)) {
            return back()->withErrors(['reply' => 'This message has no email address to reply to.']);
        }

        $validated = $request->validate([
            'reply_subject' => ['required', 'string', 'max:500'],
            'reply_body'    => ['required', 'string', 'max:20000'],
        ]);

        $admin = $request->user('admin');

        // Send via the centralized email pipeline. Throw on transport failure
        // so a failed send doesn't silently mark the thread as replied — the
        // admin gets the compose box back pre-filled and can retry.
        try {
            Emailer::send(
                'contact.inbox_reply',
                $message->email,
                [
                    'recipient_name'   => $message->name,
                    'reply_subject'    => $validated['reply_subject'],
                    'reply_body'       => $validated['reply_body'],
                    'original_message' => $message->message,
                    'app_name'         => config('app.name'),
                ],
                [
                    'subject'          => $validated['reply_subject'],
                    'body_type'        => 'view',
                    'view_data'        => [
                        'reply_body'       => $validated['reply_body'],
                        'original_message' => $message->message,
                    ],
                    'related'          => ['type' => 'contact_message', 'id' => $message->id],
                    'to_name'          => $message->name,
                    'throw_on_failure' => true,
                ]
            );
        } catch (\App\Modules\Common\Exceptions\EmailDeliveryException $e) {
            return back()
                ->withInput()
                ->withErrors(['reply' => 'Sending failed — the email could not be delivered. Your reply has been kept below so you can retry.']);
        }

        // Persist the reply thread.
        ContactMessageReply::create([
            'contact_message_id' => $message->id,
            'admin_id'           => $admin ? $admin->id : 0,
            'subject'            => $validated['reply_subject'],
            'body'               => $validated['reply_body'],
        ]);

        // Mark the message as replied.
        $message->update([
            'status'     => 'replied',
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Reply sent.');
    }
}
