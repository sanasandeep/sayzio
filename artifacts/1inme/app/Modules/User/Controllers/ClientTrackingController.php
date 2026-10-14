<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\WorkspaceActivityEvent;
use App\Services\Clients\ClientTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientTrackingController extends Controller
{
    public function audit(DeliveryProject $client, string $action, string $label): void
    {
        WorkspaceActivityEvent::create(['workspace_id' => $client->workspace_id, 'actor_user_id' => auth()->id(), 'action' => $action, 'object_type' => 'delivery-project', 'object_id' => $client->id, 'object_label' => $label, 'object_url' => route('user.delivery-projects.show', $client), 'ip' => request()->ip(), 'created_at' => now()]);
    }

    public function event(Request $request, DeliveryProject $deliveryProject)
    {
        $data = $request->validate(['action' => 'required|in:copy,open']);
        $this->audit($deliveryProject, 'client.'.$data['action'], 'Client '.$data['action']);

        return response()->noContent();
    }

    public function store(Request $request, DeliveryProject $deliveryProject, ClientTracker $tracker)
    {
        $data = $request->validate(['items' => 'required|array|max:50', 'items.*' => 'required|string|regex:/^[a-z]+:[1-9][0-9]*$/']);
        $resolved = [];
        foreach ($data['items'] as $value) {
            [$kind,$id] = explode(':', $value);
            abort_unless($tracker->get($kind, (int) $id), 404);
            $resolved[] = [$kind, (int) $id];
        }
        DB::transaction(function () use ($resolved, $deliveryProject) {
            foreach ($resolved as [$kind,$id]) {
                DB::table('client_tracking_items')->updateOrInsert(['workspace_id' => $deliveryProject->workspace_id, 'delivery_project_id' => $deliveryProject->id, 'kind' => $kind, 'resource_id' => $id], ['created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($deliveryProject, 'client.connect', 'Client tracking records connected');
        });

        return back()->with('success', 'Selected records connected.');
    }

    public function open(DeliveryProject $deliveryProject, string $kind, int $id, ClientTracker $tracker)
    {
        $data = $tracker->data($deliveryProject);
        $item = collect($data['items'])->first(fn ($i) => $i['kind'] === $kind && $i['id'] === $id);
        abort_unless($item, 404);
        $this->audit($deliveryProject, 'client.open', 'Opened linked '.$kind.' #'.$id);

        return redirect($item['url'])->header('Cache-Control','no-store');
    }
}
