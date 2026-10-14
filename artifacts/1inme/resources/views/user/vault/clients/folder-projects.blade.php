@if(\App\Modules\User\Services\WorkspacePermissions::userCan('delivery-projects.view') && \App\Modules\User\Services\WorkspacePermissions::userCan('tasks.view'))
@php $deliveryWork=\App\Modules\User\Models\DeliveryProject::where('folder_id',$project->id)->get(); @endphp
@if($deliveryWork->isNotEmpty())
<div class="notes-panel"><h3>Client projects in this Folder</h3>@foreach($deliveryWork as $work)<a class="folder-resource-row" href="{{ route('user.delivery-projects.show',$work) }}">{{ $work->title }} · {{ ucfirst($work->status) }} →</a>@endforeach</div>
@endif
@endif
