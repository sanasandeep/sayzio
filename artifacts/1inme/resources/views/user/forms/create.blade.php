@extends('user.layouts.app')
@section('title', 'Create Form')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Create a New Form',
        'subtitle' => 'Pick a starting template — you can fully customize fields and design afterwards.',
        'icon' => 'fa-wpforms',
        'back' => route('user.forms.index'),
    ])

    <form method="POST" action="{{ route('user.forms.store') }}" class="space-y-6" x-data="{ template: 'contact' }">
        @csrf

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Basics</h3>
            <p class="text-[11px] mb-5" style="color: var(--text-faint);">Give your form a name and an optional description. You can change all of this later.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Form title <span class="text-red-400">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Contact Us" class="theme-input w-full" required maxlength="160">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description <span class="text-[10px]" style="color: var(--text-faint);">— shown below the title on the public form</span></label>
                    <textarea name="description" rows="2" maxlength="1000" placeholder="Optional — a short message to set expectations" class="theme-input w-full">{{ old('description') }}</textarea>
                </div>
                @if(auth()->user()->projects()->exists())
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Project <span class="text-[10px]" style="color: var(--text-faint);">— optional</span></label>
                    <select name="project_id" class="theme-input w-full">
                        <option value="">— No project —</option>
                        @foreach(auth()->user()->projects()->orderBy('name')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Choose a template</h3>
            <p class="text-[11px] mb-5" style="color: var(--text-faint);">Start with a ready-made set of fields — or pick Blank and design from scratch.</p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach([
                    'contact' => ['Contact', 'fa-envelope', 'Name + email + message'],
                    'lead' => ['Lead Capture', 'fa-bullseye', 'Name, email, phone, budget'],
                    'survey' => ['Survey', 'fa-poll', 'Ratings & open feedback'],
                    'registration' => ['Registration', 'fa-user-plus', 'Sign-up with consent'],
                    'feedback' => ['Feedback', 'fa-comment-dots', 'Rating + category'],
                    'blank' => ['Blank', 'fa-file', 'Start from scratch'],
                ] as $key => [$label, $icon, $desc])
                    <label class="cursor-pointer">
                        <input type="radio" name="template" value="{{ $key }}" x-model="template" class="sr-only" {{ $key === 'contact' ? 'checked' : '' }}>
                        <div class="p-4 rounded-xl text-center transition-all"
                             :class="template === '{{ $key }}'
                                ? 'ring-2 ring-violet-500'
                                : 'hover:border-violet-400'"
                             style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <i class="fas {{ $icon }} text-lg mb-2" :class="template === '{{ $key }}' ? 'text-violet-400' : ''" style="color: var(--text-muted);"></i>
                            <div class="text-xs font-bold" style="color: var(--text-primary);">{{ $label }}</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--text-faint);">{{ $desc }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-6 py-3 text-sm font-semibold inline-flex items-center gap-2">
                <i class="fas fa-arrow-right text-xs"></i> Create Form & Open Builder
            </button>
            <a href="{{ route('user.forms.index') }}" class="text-xs px-4 py-2" style="color: var(--text-faint);">Cancel</a>
        </div>
    </form>
</div>
@endsection
