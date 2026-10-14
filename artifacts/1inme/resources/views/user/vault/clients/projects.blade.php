@php
 $connected=[];
 foreach($clientProjects as $clientProject){$connected[$clientProject->id]=app(\App\Services\Clients\ClientTracker::class)->data($clientProject);}
@endphp
<div class="client-create-page">
 <div class="client-toolbar">@if(\App\Modules\User\Services\WorkspacePermissions::userCan('links.view'))<a class="client-secondary" href="{{ route('user.calendars.mine',['client'=>$item->id]) }}">Client schedule →</a>@endif<p class="client-muted">One client, with projects and connected work.</p>@if(\App\Modules\User\Services\WorkspacePermissions::userCan('delivery-projects.create') && \App\Modules\User\Services\WorkspacePermissions::userCan('tasks.edit'))<a class="client-primary" href="{{ route('user.delivery-projects.create',['client'=>$item->id]) }}">+ Add project</a>@endif</div>
 <nav class="client-tabs" aria-label="Client sections">@foreach(['overview'=>'Overview','projects'=>'Projects','billing'=>'Billing','conversations'=>'Conversations','files'=>'Files & Vault','activity'=>'Activity'] as $key=>$label)<button type="button" @click="tab=@js($key)" :aria-pressed="tab===@js($key)">{{ $label }}</button>@endforeach</nav>
 <div x-show="tab==='overview'" class="client-create-intro"><strong>{{ $clientProjects->count() }} projects · {{ $clientInvoices->count() }} invoices</strong><p>Contact details below. Use Projects to manage delivery and Billing to review recorded invoices and payments.</p></div>
 <div x-show="tab==='projects'">
 @forelse($clientProjects as $clientProject)
  <div class="client-create-intro"><a href="{{ route('user.delivery-projects.show',$clientProject) }}"><strong>{{ $clientProject->title }}</strong></a><p>{{ ucfirst($clientProject->status) }} · {{ $clientProject->tasks_count }} delivery tasks · Updated {{ $clientProject->updated_at->format('d M Y, H:i') }}</p>
  @if($clientProject->folder_id && \App\Modules\User\Services\WorkspacePermissions::userCan('projects.view') && ($clientFolder=\App\Modules\User\Models\Project::find($clientProject->folder_id)))<a href="{{ route('user.projects.show',$clientFolder) }}">Folder: {{ $clientFolder->name }} →</a>@endif
  </div>
 @empty<div class="client-create-intro"><strong>Ready for your first project</strong><p>Add a project when work starts. Your client contact record is already saved.</p></div>@endforelse
 </div>
 <div x-show="tab==='billing'" x-cloak>
 @if($clientInvoices->isNotEmpty())<div class="client-create-intro"><strong>Client invoices</strong>@foreach($clientInvoices as $invoice)<a style="display:block" href="{{ route('user.client-invoices.edit',$invoice) }}">{{ $invoice->number }} · {{ $invoice->currency }} {{ number_format($invoice->grand_total_minor/100,2) }} · {{ ucfirst($invoice->status) }} →</a>@endforeach</div>@endif
 @forelse($clientProjects as $clientProject)
 <div class="client-create-intro"><strong>{{ $clientProject->title }}</strong><p>Invoices, receipts, payments, tasks, leads, conversations, subscriptions and accounts connected to this project.</p>
 @foreach(collect($connected[$clientProject->id]['items'])->where('kind','invoice') as $record)<a style="display:block" href="{{ route('user.delivery-projects.tracking.open',[$clientProject,$record['kind'],$record['id']]) }}">{{ \App\Services\Clients\ClientTracker::LABELS[$record['kind']] }} · {{ $record['label'] }} →</a>@endforeach
 @foreach($connected[$clientProject->id]['ledger'] as $total)<p>{{ $total['currency'] }} {{ number_format($total['invoiced_minor']/100,2) }} invoiced · {{ number_format($total['paid_minor']/100,2) }} receipts recorded</p>@endforeach
 <a href="{{ route('user.delivery-projects.show',$clientProject) }}">Manage connections and activity →</a></div>
 @empty<div class="client-create-intro"><p>Add a project to connect and track its work records.</p></div>@endforelse
 </div>
 <div x-show="tab==='conversations'" x-cloak>
 @php $conversations=collect(); foreach($clientProjects as $p){foreach(collect($connected[$p->id]['items'])->where('kind','conversation') as $r){$conversations->put($r['id'],['record'=>$r,'project'=>$p]);}} @endphp
 @forelse($conversations as $entry)<div class="client-create-intro"><a href="{{ route('user.delivery-projects.tracking.open',[$entry['project'],'conversation',$entry['record']['id']]) }}">{{ $entry['record']['label'] }} →</a></div>@empty<div class="client-create-intro"><p>No conversations connected yet. Connect an existing conversation from a project.</p></div>@endforelse
 </div>
 <div x-show="tab==='activity'" x-cloak>
 @php $events=\App\Modules\User\Models\VaultAudit::where('target_type','client')->where('target_id',$item->id);if(!\App\Modules\User\Services\WorkspacePermissions::userIsOwner() && !\App\Modules\User\Services\WorkspacePermissions::userCan('vault.delete'))$events->where('actor_user_id',auth()->id());$events=$events->with('actor')->latest('occurred_at')->limit(30)->get(); @endphp
 <div class="client-create-intro"><strong>Client activity</strong>@foreach($events as $event)<p>{{ ucfirst($event->action) }} · {{ $event->actor?->name ?: 'Team member' }} · {{ $event->occurred_at->format('d M Y, H:i') }}</p>@endforeach</div>
 @foreach($clientProjects as $p)<div class="client-create-intro"><strong>{{ $p->title }}</strong>@forelse($connected[$p->id]['activity'] as $event)<p>{{ $event->object_label }} · {{ $event->actor?->name ?: 'Team member' }} · {{ $event->created_at->format('d M Y, H:i') }}</p>@empty<p>No project activity recorded yet.</p>@endforelse</div>@endforeach
 </div>
</div>
