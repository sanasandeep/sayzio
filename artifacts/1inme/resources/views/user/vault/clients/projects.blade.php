@php
 $connected=[];
 foreach($clientProjects as $clientProject){$connected[$clientProject->id]=app(\App\Services\Clients\ClientTracker::class)->data($clientProject);}
@endphp
<div class="client-create-page" x-data="{tab:'projects'}">
 <div class="client-create-intro flex flex-wrap items-center justify-between gap-3">
  <div><strong>Client workspace</strong><p>Manage projects here. Contact details and protected notes appear below.</p></div>
  @if(\App\Modules\User\Services\WorkspacePermissions::userCan('delivery-projects.create') && \App\Modules\User\Services\WorkspacePermissions::userCan('tasks.edit'))<a class="btn-primary" href="{{ route('user.delivery-projects.create',['client'=>$item->id]) }}">+ Add project</a>@endif
 </div>
 <div class="flex gap-2 mb-4"><button class="btn-ghost" @click="tab='projects'" :aria-pressed="tab==='projects'">Projects · {{ $clientProjects->count() }}</button><button class="btn-ghost" @click="tab='records'" :aria-pressed="tab==='records'">Connected records</button></div>
 <div x-show="tab==='projects'">
 @forelse($clientProjects as $clientProject)
  <div class="client-create-intro"><a href="{{ route('user.delivery-projects.show',$clientProject) }}"><strong>{{ $clientProject->title }}</strong></a><p>{{ ucfirst($clientProject->status) }} · {{ $clientProject->tasks_count }} delivery tasks · Updated {{ $clientProject->updated_at->format('d M Y, H:i') }}</p>
  @if($clientProject->folder_id && \App\Modules\User\Services\WorkspacePermissions::userCan('projects.view') && ($clientFolder=\App\Modules\User\Models\Project::find($clientProject->folder_id)))<a href="{{ route('user.projects.show',$clientFolder) }}">Folder: {{ $clientFolder->name }} →</a>@endif
  </div>
 @empty<div class="client-create-intro"><strong>Ready for your first project</strong><p>Add a project when work starts. Your client contact record is already saved.</p></div>@endforelse
 </div>
 <div x-show="tab==='records'" x-cloak>
 @if($clientInvoices->isNotEmpty())<div class="client-create-intro"><strong>Client invoices</strong>@foreach($clientInvoices as $invoice)<a style="display:block" href="{{ route('user.client-invoices.edit',$invoice) }}">{{ $invoice->number }} · {{ $invoice->currency }} {{ number_format($invoice->grand_total_minor/100,2) }} · {{ ucfirst($invoice->status) }} →</a>@endforeach</div>@endif
 @forelse($clientProjects as $clientProject)
 <div class="client-create-intro"><strong>{{ $clientProject->title }}</strong><p>Invoices, receipts, payments, tasks, leads, conversations, subscriptions and accounts connected to this project.</p>
 @foreach($connected[$clientProject->id]['items'] as $record)<a style="display:block" href="{{ route('user.delivery-projects.tracking.open',[$clientProject,$record['kind'],$record['id']]) }}">{{ \App\Services\Clients\ClientTracker::LABELS[$record['kind']] }} · {{ $record['label'] }} →</a>@endforeach
 @foreach($connected[$clientProject->id]['ledger'] as $total)<p>{{ $total['currency'] }} {{ number_format($total['invoiced_minor']/100,2) }} invoiced · {{ number_format($total['paid_minor']/100,2) }} receipts recorded</p>@endforeach
 <a href="{{ route('user.delivery-projects.show',$clientProject) }}">Manage connections and activity →</a></div>
 @empty<div class="client-create-intro"><p>Add a project to connect and track its work records.</p></div>@endforelse
 </div>
</div>
