<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Http\Request;

class InboxForwardController
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $destinations = InboxForwardDestination::where('user_id', $userId)
            ->orderBy('created_at')->get();
        $deliveries = InboxForwardDelivery::where('user_id', $userId)
            ->with('destination:id,label,type')
            ->latest()->limit(50)->get();
        $sourceLabels = InboxAggregator::sourceLabels();

        return view('user.inbox.forwards.index', compact('destinations', 'deliveries', 'sourceLabels'));
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->validateData($request);
        $data['user_id'] = $userId;
        InboxForwardDestination::create($data);
        return redirect()->route('user.inbox.forwards.index')->with('success', 'Forwarding rule added.');
    }

    public function update(Request $request, InboxForwardDestination $forward)
    {
        $this->authorize($request, $forward);
        $data = $this->validateData($request);
        $forward->update($data);
        return redirect()->route('user.inbox.forwards.index')->with('success', 'Forwarding rule updated.');
    }

    public function toggle(Request $request, InboxForwardDestination $forward)
    {
        $this->authorize($request, $forward);
        $forward->update(['is_active' => !$forward->is_active]);
        return back()->with('success', $forward->is_active ? 'Rule enabled.' : 'Rule paused.');
    }

    public function destroy(Request $request, InboxForwardDestination $forward)
    {
        $this->authorize($request, $forward);
        $forward->delete();
        return redirect()->route('user.inbox.forwards.index')->with('success', 'Forwarding rule deleted.');
    }

    public function test(Request $request, InboxForwardDestination $forward)
    {
        $this->authorize($request, $forward);
        if (!$forward->is_active) {
            return back()->with('success', 'Enable the rule before sending a test.');
        }
        $delivery = app(InboxForwarder::class)->sendTest($forward);
        $msg = $delivery && $delivery->status === 'success'
            ? 'Test sent — check your destination.'
            : 'Test attempted — see the deliveries log for details.';
        return back()->with('success', $msg);
    }

    public function retry(Request $request, InboxForwardDelivery $delivery)
    {
        abort_unless($delivery->user_id === $request->user()->id, 403);
        abort_unless(in_array($delivery->status, ['failed', 'dead'], true), 422);
        // Reset retry window so worker (or this request) can run it now.
        $delivery->update(['next_retry_at' => now()->subSecond()]);
        app(InboxForwarder::class)->deliver($delivery);
        return back()->with('success', 'Delivery retried.');
    }

    protected function authorize(Request $request, InboxForwardDestination $forward): void
    {
        abort_unless($forward->user_id === $request->user()->id, 403);
    }

    protected function validateData(Request $request): array
    {
        $allowedSources = array_keys(InboxAggregator::sourceLabels());
        $rules = [
            'label'        => ['required', 'string', 'max:120'],
            'type'         => ['required', 'in:email,webhook'],
            'target'       => ['required', 'string', 'max:500'],
            'method'       => ['nullable', 'in:POST,PUT,GET'],
            'sources'      => ['nullable', 'array'],
            'sources.*'    => ['string', 'in:' . implode(',', $allowedSources)],
            'header_key'   => ['nullable', 'string', 'max:120'],
            'header_value' => ['nullable', 'string', 'max:500'],
            'secret'       => ['nullable', 'string', 'max:120'],
            'is_active'    => ['nullable', 'boolean'],
        ];
        $data = $request->validate($rules);

        if ($data['type'] === 'email') {
            if (!filter_var($data['target'], FILTER_VALIDATE_EMAIL)) {
                abort(redirect()->back()->withErrors(['target' => 'Enter a valid email address.'])->withInput());
            }
            $data['method'] = 'POST';
            $data['secret'] = null;
        } else {
            if (!InboxForwarder::isSafeWebhookUrl($data['target'])) {
                abort(redirect()->back()->withErrors(['target' => 'URL must be a public http(s) endpoint.'])->withInput());
            }
            $data['method'] = $data['method'] ?? 'POST';
        }

        $data['sources'] = !empty($data['sources']) ? array_values(array_unique($data['sources'])) : null;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
