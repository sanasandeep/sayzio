@extends('user.layouts.app')
@section('title', 'Edit Folder')

@section('content')
@include('user.planner.styles')
<div class="notes-studio folder-workspace">
@include('user.partials.page-hero',['title'=>'Folder settings','subtitle'=>$project->name,'icon'=>'fa-folder','favicon'=>null,'url'=>null,'back'=>route('user.projects.show',$project),'actions'=>[],'chips'=>[]])
    <form method="POST" action="{{ route('user.projects.update', $project) }}">
        @csrf @method('PUT')
        @include('user.projects._form', ['project' => $project])

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.projects.index') }}" class="px-4 py-2.5 text-sm text-secondary hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
    <section class="notes-card mt-6"><h2>Move the project contents</h2><p class="notes-hint mb-4">Move all assigned resources and notes to another folder. Both folders are kept. Scheduled plans continue in their new folder.</p>
    @if($destinations->isNotEmpty())<form method="post" action="{{ route('user.ai.folders.move',$project) }}" class="notes-actions" onsubmit="return window.themedConfirmSubmit(this,{title:'Move folder contents?',message:'Move all contents to the selected destination. Both folders remain; scheduled plans continue.',confirmText:'Move contents',confirmIcon:'fa-folder',iconClass:'fa-folder'})">@csrf<select class="theme-input" name="destination" style="flex:1">@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select><button class="form-chip">Move contents</button></form>@else<p class="notes-hint">Create another folder to move contents into it.</p>@endif
    </section>
    @canInWorkspace('delete')<section class="notes-card mt-6"><h2 class="text-red-500">Delete folder, keep contents</h2><p class="notes-hint mb-4">{{ count($resources) }} visible resources will become unassigned. Other members' private resources are also kept. Notes remain saved, recorded folder connections are removed, and every scheduled AI plan assigned to this folder is paused. Links and public pages stay available.</p><form method="post" action="{{ route('user.projects.destroy',$project) }}" onsubmit="return window.themedConfirmSubmit(this,{title:'Delete folder, keep contents?',message:'Contents and notes are kept and unassigned. Scheduled AI plans are paused; folder connections are removed.',confirmText:'Delete folder',confirmIcon:'fa-trash',iconClass:'fa-trash'})">@csrf @method('DELETE')<button class="form-chip text-red-500">Delete folder and keep contents</button></form></section>@endcanInWorkspace
</div>
@endsection
