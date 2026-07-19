<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Inbox forwarding API: mobile parity for the web forwarding rules page
 * (User\InboxForwardController). Lets creators fan inbox events out to an
 * email address or a webhook, with a delivery log + manual retry.
 *
 * Ownership is scoped to the active workspace owner (workspace_owner_id())
 * so team members manage the owner's rules, mirroring the web.
 */
class InboxForwardController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $userId = workspace_owner_id();
        $destinations = InboxForwardDestination::where('user_id', $userId)
            ->orderBy('created_at')->get();
        $deliveries = InboxForwardDelivery::where('user_id', $userId)
            ->with('destination:id,label,type')
            ->latest()->limit(50)->get();

        return $this->ok([
            'destinations'  => $destinations->map(fn ($d) => $this->transformDestination($d))->all(),
            'deliveries'    => $deliveries->map(fn ($d) => $this->transformDelivery($d))->all(),
            'source_labels' => InboxAggregator::sourceLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['user_id'] = workspace_owner_id();
        $forward = InboxForwardDestination::create($data);

        return $this->created(['destination' => $this->transformDestination($forward)]);
    }

    public function update(Request $request, int $id)
    {
        $forward = $this->findOwned($id);
        if (!$forward) return $this->notFound('Forwarding rule not found.');

        $data = $this->validateData($request);
        $forward->update($data);

        return $this->ok(['destination' => $this->transformDestination($forward->fresh())]);
    }

    public function toggle(Request $request, int $id)
    {
        $forward = $this->findOwned($id);
        if (!$forward) return $this->notFound('Forwarding rule not found.');

        $forward->update(['is_active' => !$forward->is_active]);

        return $this->ok(['destination' => $this->transformDestination($forward->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $forward = $this->findOwned($id);
        if (!$forward) return $this->notFound('Forwarding rule not found.');

        $forward->delete();

        return $this->noContent();
    }

    public function test(Request $request, int $id)
    {
        $forward = $this->findOwned($id);
        if (!$forward) return $this->notFound('Forwarding rule not found.');
        if (!$forward->is_active) {
            return $this->fail('Enable the rule before sending a test.', 422, 'inactive');
        }

        $delivery = app(InboxForwarder::class)->sendTest($forward);
        $ok = $delivery && $delivery->status === 'success';

        return $this->ok([
            'sent'     => $ok,
            'message'  => $ok
                ? 'Test sent — check your destination.'
                : 'Test attempted — see the deliveries log for details.',
            'delivery' => $delivery ? $this->transformDelivery($delivery) : null,
        ]);
    }

    public function retry(Request $request, int $id)
    {
        $delivery = InboxForwardDelivery::where('user_id', workspace_owner_id())->find($id);
        if (!$delivery) return $this->notFound('Delivery not found.');
        if (!in_array($delivery->status, ['failed', 'dead'], true)) {
            return $this->fail('Only failed deliveries can be retried.', 422, 'not_retryable');
        }

        $delivery->update(['next_retry_at' => now()->subSecond()]);
        app(InboxForwarder::class)->deliver($delivery);

        return $this->ok(['delivery' => $this->transformDelivery($delivery->fresh())]);
    }

    // ---- helpers --------------------------------------------------------

    private function findOwned(int $id): ?InboxForwardDestination
    {
        return InboxForwardDestination::where('user_id', workspace_owner_id())->find($id);
    }

    private function validateData(Request $request): array
    {
        $allowedSources = array_keys(InboxAggregator::sourceLabels());
        $data = $request->validate([
            'label'             => ['required', 'string', 'max:120'],
            'type'              => ['required', 'in:email,webhook'],
            'target'            => ['required', 'string', 'max:500'],
            'method'            => ['nullable', 'in:POST,PUT,GET'],
            'sources'           => ['nullable', 'array'],
            'sources.*'         => ['string', 'in:' . implode(',', $allowedSources)],
            'click_milestones'  => ['nullable', 'array', 'max:20'],
            'click_milestones.*' => ['integer', 'min:1', 'max:1000000000'],
            'header_key'        => ['nullable', 'string', 'max:120'],
            'header_value'      => ['nullable', 'string', 'max:500'],
            'secret'            => ['nullable', 'string', 'max:120'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === 'email') {
            if (!filter_var($data['target'], FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['target' => 'Enter a valid email address.']);
            }
            $data['method'] = 'POST';
            $data['secret'] = null;
        } else {
            if (!InboxForwarder::isSafeWebhookUrl($data['target'])) {
                throw ValidationException::withMessages(['target' => 'URL must be a public http(s) endpoint.']);
            }
            $data['method'] = $data['method'] ?? 'POST';
        }

        $data['sources'] = !empty($data['sources']) ? array_values(array_unique($data['sources'])) : null;

        // Normalise milestones: unique, positive ints, sorted, capped at 20.
        if (!empty($data['click_milestones'])) {
            $ms = array_values(array_unique(array_filter(array_map('intval', $data['click_milestones']), fn ($v) => $v > 0)));
            sort($ms);
            $data['click_milestones'] = array_slice($ms, 0, 20) ?: null;
        } else {
            $data['click_milestones'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function transformDestination(InboxForwardDestination $d): array
    {
        return [
            'id'                => $d->id,
            'label'             => $d->label,
            'type'              => $d->type,
            'target'            => $d->target,
            'method'            => $d->method,
            'sources'           => $d->sources ?: [],
            'click_milestones'  => $d->clickMilestoneThresholds(),
            'header_key'        => $d->header_key,
            'has_secret'        => !empty($d->secret),
            'is_active'         => (bool) $d->is_active,
            'last_status'       => $d->last_status,
            'last_delivered_at' => optional($d->last_delivered_at)->toIso8601String(),
            'created_at'        => optional($d->created_at)->toIso8601String(),
        ];
    }

    private function transformDelivery(InboxForwardDelivery $d): array
    {
        return [
            'id'                 => $d->id,
            'destination_id'     => $d->destination_id,
            'destination_label'  => $d->destination?->label,
            'destination_type'   => $d->destination?->type,
            'source_type'        => $d->source_type,
            'is_test'            => (bool) $d->is_test,
            'status'             => $d->status,
            'attempts'           => (int) $d->attempts,
            'last_error'         => $d->last_error,
            'last_response_code' => $d->last_response_code,
            'last_attempt_at'    => optional($d->last_attempt_at)->toIso8601String(),
            'delivered_at'       => optional($d->delivered_at)->toIso8601String(),
            'created_at'         => optional($d->created_at)->toIso8601String(),
        ];
    }
}
