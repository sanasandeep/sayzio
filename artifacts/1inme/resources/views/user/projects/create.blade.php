@extends('user.layouts.app')
@section('title', 'Create Folder')

@section('content')
@include('user.planner.styles')
<div class="notes-studio folder-workspace">
@include('user.partials.page-hero',['title'=>'Create folder','subtitle'=>'Bring content, knowledge and work into one project.','icon'=>'fa-folder-plus','favicon'=>null,'url'=>null,'back'=>route('user.dashboard').'#folders','actions'=>[],'chips'=>[]])
    <form method="POST" action="{{ route('user.projects.store') }}">
        @csrf
        @include('user.projects._form', ['project' => null])

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.projects.index') }}" class="px-4 py-2.5 text-sm text-secondary hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-medium">Create Folder</button>
        </div>
    </form>
</div>
@endsection
