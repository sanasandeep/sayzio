<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\NotificationBroadcast;
use App\Modules\Common\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $broadcasts = NotificationBroadcast::orderByDesc('id')->paginate(20);
        $plans      = Plan::orderBy('sort_order')->get(['id', 'slug', 'name']);
        $catalog    = NotificationService::catalog();

        return view('admin.notifications.index', compact('broadcasts', 'plans', 'catalog'));
    }

    public function send(Request $request, NotificationService $svc)
    {
        $data = $request->validate([
            'subject'      => ['required', 'string', 'max:200'],
            'body'         => ['required', 'string', 'max:4000'],
            'target_url'   => ['nullable', 'url', 'max:500'],
            'target_kind'  => ['required', Rule::in(['all', 'plan', 'role', 'country', 'user'])],
            'target_value' => ['nullable', 'string', 'max:120'],
        ]);

        if ($data['target_kind'] !== 'all' && empty($data['target_value'])) {
            return back()->withErrors([
                'target_value' => 'A target is required when not broadcasting to everyone.',
            ])->withInput();
        }

        $broadcast = $svc->broadcast(
            adminId:     Auth::guard('admin')->id(),
            targetKind:  $data['target_kind'],
            targetValue: $data['target_value'] ?? null,
            subject:     $data['subject'],
            body:        $data['body'],
            targetUrl:   $data['target_url'] ?? null,
        );

        return redirect()->route('admin.notifications.index')->with(
            'success',
            'Broadcast sent to ' . $broadcast->recipients_count . ' user' . ($broadcast->recipients_count === 1 ? '' : 's') . '.'
        );
    }
}
