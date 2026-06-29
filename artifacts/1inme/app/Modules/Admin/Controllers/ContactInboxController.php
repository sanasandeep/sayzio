<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactInboxController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $sort = $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $q = ContactMessage::query();
        if (in_array($status, ['new', 'read', 'archived', 'spam'])) {
            $q->where('status', $status);
        } else {
            // The default "All" view excludes the spam review queue so a
            // distributed flood that's been quarantined can't bury real
            // leads. Quarantined items are reviewed under the Spam tab.
            $q->where('status', '!=', 'spam');
        }
        $messages = $q->orderBy('created_at', $sort)->paginate(20)->withQueryString();
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
}
