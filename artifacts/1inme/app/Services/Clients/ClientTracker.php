<?php

namespace App\Services\Clients;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use App\Modules\User\Models\WorkspaceActivityEvent;
use App\Services\Planner\PlannerResources;
use Illuminate\Support\Facades\DB;

class ClientTracker
{
    public const LABELS = ['invoice' => 'Invoices', 'task' => 'Tasks', 'submission' => 'Form responses', 'lead' => 'Leads', 'conversation' => 'Conversations', 'subscription' => 'Subscriptions', 'account' => 'Vault accounts'];

    public function clients()
    {
        if (! auth()->user()->canInWorkspace(app('current_workspace'), 'vault.view')) {
            return collect();
        }

return VaultClient::orderBy('name')->get()->filter(fn ($c) => $c->visibleTo(auth()->user(), app('current_workspace')));
    }

    public function profile($id)
    {
        return $id ? $this->clients()->firstWhere('id', $id) : null;
    }

    public function query(string $kind)
    {
        $ws = app('current_workspace');
        $user = auth()->user();
        $can = fn ($permission) => $user->canInWorkspace($ws, $permission);

        return match ($kind) {
            'invoice' => $can('invoices.view') && $can('tasks.view') ? Invoice::where('workspace_id', $ws->id)->where('kind', 'client') : null,
            'task' => $can('tasks.view') ? app(PlannerResources::class)->query('task', $user) : null,
            'submission' => $can('forms.view') && $can('inbox.view') ? FormSubmission::whereHas('form', fn ($q) => $q->where('user_id', workspace_owner_id())) : null,
            'lead' => $can('contacts.view') && $can('settings.view') ? Contact::where('user_id', $user->id) : null,
            'conversation' => $can('inbox.view') ? InboxThread::where(fn ($q) => $q->where('is_private', false)->orWhere('assignee_user_id', $user->id)) : null,
            'subscription' => $can('posts.view') && (int) $ws->owner_user_id === (int) $user->id ? CreatorSubscription::where('creator_user_id', $user->id) : null,
            'account' => $can('vault.view') ? VaultCredential::query() : null,
            default => null
        };
    }

    public function get(string $kind, int $id)
    {
        $row = $this->query($kind)?->find($id);
        if ($kind === 'account' && $row && ! $row->visibleTo(auth()->user(), app('current_workspace'))) {
            return null;
        }

return $row;
    }

    public function item(string $kind, $row): array
    {
        $label = match ($kind) {
            'invoice' => $row->number ?: 'Invoice #'.$row->id,'task' => $row->title,'lead' => $row->display_name,'conversation' => $row->subject ?: 'Conversation #'.$row->id,'account' => $row->label,default => self::LABELS[$kind].' #'.$row->id
        };
        $url = match ($kind) {
            'invoice' => route('user.client-invoices.edit', $row),'task' => route('user.tasks.show', $row->board_id),'submission' => route('user.forms.submissions.show', [$row->form_id, $row->id]),'lead' => route('user.contacts.show', $row),'conversation' => route('user.inbox.unified.index', ['thread' => $row->id]),'subscription' => route('user.monetization.subscribers'),'account' => route('user.vault.credentials.show', $row)
        };

        return ['kind' => $kind, 'id' => $row->id, 'label' => $label, 'url' => $url, 'status' => $row->status ?? ($row->payment_status ?? ($row->completed_at ? 'Completed' : 'Recorded')), 'updated' => $row->updated_at ?? $row->created_at];
    }

    public function choices(): array
    {
        $items = [];
        foreach (self::LABELS as $kind => $label) {
            foreach ($this->query($kind)?->latest('id')->limit(50)->get() ?? [] as $row) {
                if ($kind === 'account' && ! $row->visibleTo(auth()->user(), app('current_workspace'))) {
                    continue;
                }$items[] = $this->item($kind, $row);
            }
        }

return $items;
    }

    public function data($project): array
    {
        $links = DB::table('client_tracking_items')->where('workspace_id', $project->workspace_id)->where('delivery_project_id', $project->id)->get();
        $items = [];
        $invoices = collect();
        foreach ($links as $link) {
            $row = $this->get($link->kind, $link->resource_id);
            if (! $row) {
                continue;
            }$items[] = $this->item($link->kind, $row);
            if ($link->kind === 'invoice') {
                $invoices->push($row);
            }
        }
        if ($project->vault_client_id && $this->query('invoice')) {
            foreach ($this->query('invoice')->where('vault_client_id', $project->vault_client_id)->get() as $invoice) {
                if (! $invoices->contains('id', $invoice->id)) {
                    $invoices->push($invoice);
                    $items[] = $this->item('invoice', $invoice);
                }
            }
        }
        if ($project->sourceable_type === Invoice::class && ($source = $this->get('invoice', (int) $project->sourceable_id)) && ! $invoices->contains('id', $source->id)) {
            $invoices->push($source);
            $items[] = $this->item('invoice', $source);
        }
        if ($project->sourceable_type === FormSubmission::class && ($source = $this->get('submission', (int) $project->sourceable_id)) && ! collect($items)->contains(fn ($i) => $i['kind'] === 'submission' && $i['id'] === $source->id)) {
            $items[] = $this->item('submission', $source);
        }
        $receipts = Receipt::whereIn('invoice_id', $invoices->pluck('id'))->get();
        $payments = PaymentAttempt::whereIn('invoice_id', $invoices->pluck('id'))->get();
        $ledger = $invoices->groupBy('currency')->map(fn ($rows, $currency) => ['currency' => $currency, 'invoiced_minor' => $rows->sum('grand_total_minor'), 'paid_minor' => $receipts->whereIn('invoice_id', $rows->pluck('id'))->sum('amount_minor')])->values();
        $events = WorkspaceActivityEvent::where('workspace_id', $project->workspace_id)->where('object_type', 'delivery-project')->where('object_id', $project->id);
        $member = auth()->user()->membershipFor(app('current_workspace'));
        if ((int) app('current_workspace')->owner_user_id !== (int) auth()->id() && $member?->role !== 'admin') {
            $events->where('actor_user_id', auth()->id());
        }$activity = $events->with('actor')->latest()->limit(30)->get();

        return ['activity' => $activity, 'items' => $items, 'counts' => collect($items)->countBy('kind'), 'ledger' => $ledger, 'receipts' => $receipts, 'payments' => $payments, 'profile' => $this->profile($project->vault_client_id), 'choices' => $this->choices()];
    }
}
