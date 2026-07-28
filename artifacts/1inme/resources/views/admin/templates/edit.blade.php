@extends('admin.layouts.app')
@section('title', 'Edit Template')
@section('page-title', 'Edit ' . ucfirst($kind) . ' Template, ' . $tpl->name)

@section('content')
<div class="max-w-4xl">
    <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="text-xs text-white/40 hover:text-white mb-4 inline-block ak-note"><i class="fas fa-arrow-left mr-1"></i>Back to templates</a>

    @if($kind === 'page')
        {{-- Visual design editor: opens the template in the real biolink editor
             (blocks, backgrounds, live preview) via a temporary draft page. --}}
        <div class="mb-5 rounded-2xl px-4 py-3.5 flex flex-wrap items-center gap-3"
             style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.3);">
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold ak-strong" style="color:#fff;">
                    <i class="fas fa-wand-magic-sparkles mr-1.5" style="color:#90acff;"></i>Design editor
                </div>
                <div class="text-[11px] text-white/50 ak-note mt-0.5">
                    Edit this template's background and blocks visually with a live preview — the same editor users get. Saving there updates the design users receive when they pick this template.
                </div>
            </div>
            <form method="POST" action="{{ route('admin.templates.design.session', ['id' => $tpl->id]) }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white transition-all"
                        style="background: linear-gradient(135deg, #3d6bff, #2f54d6);">
                    <i class="fas fa-pen-ruler mr-1.5"></i>Open design editor
                </button>
            </form>
        </div>
    @endif

    @include('admin.templates._form', ['tpl' => $tpl, 'categories' => $categories, 'plans' => $plans, 'kind' => $kind])
</div>
@endsection
