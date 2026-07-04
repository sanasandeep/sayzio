<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Task #3564 — Delivery Projects. Reads gated by tasks.view, mutations by
 * tasks.edit (the route layer applies the base tasks.view; per-action
 * escalation happens on the routes). Distinct from the link-folder
 * ProjectController.
 */
class DeliveryProjectController extends Controller
{
    /**
     * Sale sources a project can be spun up from. Keys are stable short
     * identifiers used in URLs/forms; values are the Eloquent model classes.
     *
     * @var array<string, class-string>
     */
    private const SOURCE_MAP = [
        'invoice'          => Invoice::class,
        'product_order'    => ProductOrder::class,
        'restaurant_order' => RestaurantOrder::class,
        'store_order'      => StoreOrder::class,
        'form_submission'  => FormSubmission::class,
    ];

    public function index()
    {
        $projects = DeliveryProject::query()
            ->with('creator:id,name')
            ->withCount('tasks')
            ->withCount(['tasks as done_tasks_count' => fn ($q) => $q->where('status', DeliveryProjectTask::STATUS_DONE)])
            ->orderByDesc('id')
            ->get();

        return view('user.delivery-projects.index', compact('projects'));
    }

    public function show(DeliveryProject $deliveryProject)
    {
        $deliveryProject->load(['tasks.assignee:id,name,avatar', 'creator:id,name', 'clientUser:id,name']);
        $members = $this->workspaceMembers();

        return view('user.delivery-projects.show', [
            'project'  => $deliveryProject,
            'members'  => $members,
            'statuses' => DeliveryProjectTask::STATUSES,
            'shareUrl' => route('delivery-project.share', $deliveryProject->share_token),
        ]);
    }

    /** GET form to create a project, optionally prefilled from a sale. */
    public function create(Request $request)
    {
        $prefill = $this->resolveSalePrefill(
            (string) $request->query('source_type', ''),
            (int) $request->query('source_id', 0)
        );

        return view('user.delivery-projects.create', [
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string|max:4000',
            'source_type'      => 'nullable|string|in:' . implode(',', array_keys(self::SOURCE_MAP)),
            'source_id'        => 'nullable|integer',
            'client_name'      => 'nullable|string|max:200',
            'client_email'     => 'nullable|email|max:200',
            'warranty_expires_at'    => 'nullable|date',
            'warranty_reminder_days' => 'nullable|integer|min:0|max:365',
            'seed_starter_tasks'     => 'nullable|boolean',
        ]);

        $attrs = [
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'client_name'            => $data['client_name'] ?? null,
            'client_email'           => $data['client_email'] ?? null,
            'warranty_expires_at'    => $data['warranty_expires_at'] ?? null,
            'warranty_reminder_days' => $data['warranty_reminder_days'] ?? null,
            'status'                 => DeliveryProject::STATUS_ACTIVE,
        ];

        // Bind the sale source (and best-effort buyer identity) when provided.
        if (!empty($data['source_type']) && !empty($data['source_id'])) {
            $source = $this->resolveSaleModel($data['source_type'], (int) $data['source_id']);
            if ($source) {
                $attrs['sourceable_type'] = get_class($source);
                $attrs['sourceable_id']   = $source->getKey();
                $buyer = $this->buyerFromSource($source);
                $attrs['client_name']  = $attrs['client_name']  ?: $buyer['name'];
                $attrs['client_email'] = $attrs['client_email'] ?: $buyer['email'];
                $attrs['client_user_id'] = $buyer['user_id'];
            }
        }

        $project = DeliveryProject::create($attrs);

        if (!empty($data['seed_starter_tasks'])) {
            $this->seedStarterTasks($project);
        }

        return redirect()
            ->route('user.delivery-projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function update(Request $request, DeliveryProject $deliveryProject)
    {
        $data = $request->validate([
            'title'                  => 'sometimes|string|max:200',
            'description'            => 'sometimes|nullable|string|max:4000',
            'status'                 => 'sometimes|in:active,completed,archived',
            'client_name'            => 'sometimes|nullable|string|max:200',
            'client_email'           => 'sometimes|nullable|email|max:200',
            'warranty_expires_at'    => 'sometimes|nullable|date',
            'warranty_reminder_days' => 'sometimes|nullable|integer|min:0|max:365',
        ]);

        // Track the completion timestamp when the project flips to completed.
        if (array_key_exists('status', $data)) {
            if ($data['status'] === DeliveryProject::STATUS_COMPLETED && !$deliveryProject->completed_at) {
                $deliveryProject->completed_at = now();
            } elseif ($data['status'] !== DeliveryProject::STATUS_COMPLETED) {
                $deliveryProject->completed_at = null;
            }
        }

        $deliveryProject->fill($data)->save();

        return back()->with('success', 'Project updated.');
    }

    public function destroy(DeliveryProject $deliveryProject)
    {
        DB::transaction(function () use ($deliveryProject) {
            $deliveryProject->tasks()->delete();
            $deliveryProject->delete();
        });

        return redirect()->route('user.delivery-projects.index')->with('success', 'Project deleted.');
    }

    /** Rotate the public share token, invalidating any previously shared link. */
    public function regenerateShareToken(DeliveryProject $deliveryProject)
    {
        $deliveryProject->update(['share_token' => DeliveryProject::newShareToken()]);
        return back()->with('success', 'Share link regenerated.');
    }

    /**
     * Public, read-only view for anonymous buyers reached via the unguessable
     * share_token. No auth/workspace context — the token is the authenticator.
     */
    public function share(string $token)
    {
        $project = DeliveryProject::query()
            ->withoutGlobalScope('workspace')
            ->where('share_token', $token)
            ->with(['tasks.assignee:id,name', 'creator:id,name'])
            ->first();

        abort_unless($project, 404);

        return view('delivery-projects.public', compact('project'));
    }

    // ----- Tasks ------------------------------------------------------------

    public function storeTask(Request $request, DeliveryProject $deliveryProject)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'assignee_user_id' => 'nullable|integer',
            'start_date'       => 'nullable|date',
            'due_date'         => 'nullable|date',
        ]);

        $assignee = $this->validAssignee($deliveryProject, $data['assignee_user_id'] ?? null);
        $position = (int) ($deliveryProject->tasks()->max('position') ?? 0) + 1;

        $task = $deliveryProject->tasks()->create([
            'workspace_id'     => $deliveryProject->workspace_id,
            'title'            => $data['title'],
            'status'           => DeliveryProjectTask::STATUS_TODO,
            'assignee_user_id' => $assignee,
            'start_date'       => $data['start_date'] ?? null,
            'due_date'         => $data['due_date'] ?? null,
            'progress'         => 0,
            'position'         => $position,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'task' => $this->taskArray($task)]);
        }
        return back();
    }

    public function updateTask(Request $request, DeliveryProjectTask $task)
    {
        $data = $request->validate([
            'title'            => 'sometimes|string|max:200',
            'status'           => 'sometimes|in:todo,in_progress,done',
            'progress'         => 'sometimes|integer|min:0|max:100',
            'assignee_user_id' => 'sometimes|nullable|integer',
            'start_date'       => 'sometimes|nullable|date',
            'due_date'         => 'sometimes|nullable|date',
        ]);

        if (array_key_exists('title', $data))      $task->title = $data['title'];
        if (array_key_exists('start_date', $data)) $task->start_date = $data['start_date'];
        if (array_key_exists('due_date', $data))   $task->due_date = $data['due_date'];
        if (array_key_exists('assignee_user_id', $data)) {
            $task->assignee_user_id = $this->validAssignee($task->project, $data['assignee_user_id']);
        }

        // status/progress kept coherent by the model helper.
        if (array_key_exists('status', $data) || array_key_exists('progress', $data)) {
            $task->syncStatusProgress($data['status'] ?? null, $data['progress'] ?? null);
        }

        $task->save();

        return response()->json(['ok' => true, 'task' => $this->taskArray($task->fresh('assignee'))]);
    }

    public function destroyTask(DeliveryProjectTask $task)
    {
        $task->delete();
        return response()->json(['ok' => true]);
    }

    public function reorderTasks(Request $request, DeliveryProject $deliveryProject)
    {
        $ids = (array) $request->input('order', []);
        $owned = $deliveryProject->tasks()->pluck('id')->all();
        $clean = array_values(array_intersect(array_map('intval', $ids), $owned));

        DB::transaction(function () use ($clean, $deliveryProject) {
            foreach ($clean as $i => $id) {
                DeliveryProjectTask::where('id', $id)
                    ->where('project_id', $deliveryProject->id)
                    ->update(['position' => $i + 1]);
            }
        });

        return response()->json(['ok' => true, 'updated' => count($clean)]);
    }

    // ----- Helpers ----------------------------------------------------------

    /** @return array{id:int,user_id:int,name:?string,avatar:?string}[] */
    private function workspaceMembers(): array
    {
        $ws = app('current_workspace');
        if (!$ws) return [];

        return $ws->members()
            ->whereNull('suspended_at')
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn (WorkspaceMember $m) => [
                'id'      => $m->id,
                'user_id' => (int) $m->user_id,
                'name'    => $m->user?->name,
                'avatar'  => $m->user?->avatar,
            ])
            ->filter(fn ($m) => $m['name'] !== null)
            ->values()
            ->all();
    }

    /** Return the given user id only if they are an active member of the project's workspace. */
    private function validAssignee(DeliveryProject $project, $userId): ?int
    {
        $userId = $userId ? (int) $userId : null;
        if (!$userId) return null;

        $ws = app('current_workspace');
        $isMember = $ws && (
            (int) $ws->owner_user_id === $userId
            || $ws->members()->where('user_id', $userId)->whereNull('suspended_at')->exists()
        );

        return $isMember ? $userId : null;
    }

    private function seedStarterTasks(DeliveryProject $project): void
    {
        $starters = ['Kickoff', 'In production', 'Review with client', 'Deliver'];
        foreach ($starters as $i => $title) {
            $project->tasks()->create([
                'workspace_id' => $project->workspace_id,
                'title'        => $title,
                'status'       => DeliveryProjectTask::STATUS_TODO,
                'progress'     => 0,
                'position'     => $i + 1,
            ]);
        }
    }

    private function resolveSaleModel(string $type, int $id)
    {
        $class = self::SOURCE_MAP[$type] ?? null;
        if (!$class || $id <= 0) return null;

        // Honour the workspace scope where the model has it; otherwise fall
        // back to owner-scoped lookup so cross-workspace ids never resolve.
        $model = $class::query()->find($id);
        return $model;
    }

    /** @return array{name:?string,email:?string,user_id:?int} */
    private function buyerFromSource($source): array
    {
        $name = null; $email = null; $userId = null;

        if ($source instanceof Invoice) {
            $name  = $source->recipient_name ?: null;
            $email = $source->recipient_email ?: null;
        } elseif ($source instanceof ProductOrder) {
            $userId = $source->buyer_user_id ? (int) $source->buyer_user_id : null;
            $name   = optional($source->buyer)->name;
            $email  = optional($source->buyer)->email;
        } elseif ($source instanceof RestaurantOrder || $source instanceof StoreOrder) {
            $name  = $source->customer_name ?: null;
            $email = filter_var($source->customer_contact ?? '', FILTER_VALIDATE_EMAIL) ? $source->customer_contact : null;
        } elseif ($source instanceof FormSubmission) {
            $data  = (array) ($source->data ?? []);
            foreach (['email', 'e-mail', 'Email'] as $k) {
                if (!empty($data[$k]) && is_string($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                    $email = $data[$k];
                    break;
                }
            }
        }

        return ['name' => $name, 'email' => $email, 'user_id' => $userId];
    }

    /** @return array{source_type:?string,source_id:?int,title:string,client_name:?string,client_email:?string} */
    private function resolveSalePrefill(string $type, int $id): array
    {
        $out = ['source_type' => null, 'source_id' => null, 'title' => '', 'client_name' => null, 'client_email' => null];
        if (!isset(self::SOURCE_MAP[$type]) || $id <= 0) return $out;

        $source = $this->resolveSaleModel($type, $id);
        if (!$source) return $out;

        $buyer = $this->buyerFromSource($source);
        $label = match ($type) {
            'invoice'          => 'Invoice ' . ($source->number ?? '#' . $source->id),
            'product_order'    => 'Order #' . $source->id,
            'restaurant_order' => 'Restaurant order #' . $source->id,
            'store_order'      => 'Store order #' . $source->id,
            'form_submission'  => 'Form submission #' . $source->id,
            default            => 'Project',
        };

        $out['source_type']  = $type;
        $out['source_id']    = $id;
        $out['title']        = 'Delivery — ' . $label;
        $out['client_name']  = $buyer['name'];
        $out['client_email'] = $buyer['email'];
        return $out;
    }

    /** @return array<string,mixed> */
    private function taskArray(DeliveryProjectTask $task): array
    {
        return [
            'id'               => $task->id,
            'title'            => $task->title,
            'status'           => $task->status,
            'status_label'     => $task->statusLabel(),
            'progress'         => (int) $task->progress,
            'assignee_user_id' => $task->assignee_user_id ? (int) $task->assignee_user_id : null,
            'assignee_name'    => optional($task->assignee)->name,
            'start_date'       => optional($task->start_date)->toDateString(),
            'due_date'         => optional($task->due_date)->toDateString(),
            'position'         => (int) $task->position,
        ];
    }
}
