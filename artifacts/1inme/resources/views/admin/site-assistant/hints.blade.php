@extends('admin.layouts.app')
@section('title', 'Site Assistant — Page Hints')
@section('page-title', 'Site Assistant — Page Hints')

@section('content')
<div class="max-w-6xl space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="text-sm text-white/60"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white">New page hint</h3>
        <p class="text-xs text-white/40">Match a route name pattern (e.g. <code>billing.*</code>) or path glob (e.g. <code>/pricing*</code>) — these get injected as additional context when a visitor chats from a matching page.</p>
        <form method="POST" action="{{ route('admin.site-assistant.hints.store') }}" class="grid md:grid-cols-2 gap-3">
            @csrf
            <input name="label" required maxlength="120" placeholder="Label (e.g. Pricing page)" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
            <input name="route_pattern" required maxlength="200" placeholder="Route pattern (e.g. billing.* or /pricing*)" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
            <select name="surface" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                <option value="any">Any surface</option>
                <option value="marketing">Marketing</option>
                <option value="app">App</option>
            </select>
            <input name="priority" type="number" value="100" min="0" max="1000" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="Priority (lower wins)">
            <textarea name="description" rows="2" maxlength="2000" placeholder="What this page is about (becomes context for the bot)" class="md:col-span-2 bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"></textarea>
            <textarea name="suggested_actions_text" rows="3" maxlength="2000" placeholder="Suggested actions (one per line)" class="md:col-span-2 bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"></textarea>
            <label class="flex items-center gap-2 text-sm text-white"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <label class="flex items-center gap-2 text-sm text-white"><input type="hidden" name="disable_widget" value="0"><input type="checkbox" name="disable_widget" value="1"> Hide widget on these routes</label>
            <div><button class="px-4 py-2 rounded-xl bg-purple-500 hover:bg-purple-400 text-white text-sm font-semibold">Add hint</button></div>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase">
                <tr><th class="text-left p-3">Label</th><th class="text-left p-3">Pattern</th><th class="text-left p-3">Surface</th><th class="text-left p-3">Pri</th><th class="text-left p-3">Active</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
            @forelse($hints as $h)
                <tr class="border-t border-white/5">
                    <td class="p-3 text-white">{{ $h->label }}</td>
                    <td class="p-3 text-white/70 font-mono text-xs">{{ $h->route_pattern }}</td>
                    <td class="p-3 text-white/70">{{ $h->surface }}</td>
                    <td class="p-3 text-white/70">{{ $h->priority }}</td>
                    <td class="p-3">{!! $h->is_active ? '<span class="text-emerald-300 text-xs">●</span>' : '<span class="text-white/30 text-xs">○</span>' !!}</td>
                    <td class="p-3 text-right space-x-1">
                        <details class="inline-block text-left">
                            <summary class="cursor-pointer text-purple-300 text-xs">Edit</summary>
                            <form method="POST" action="{{ route('admin.site-assistant.hints.update', $h) }}" class="mt-2 space-y-2 p-3 bg-black/40 rounded-lg w-80">
                                @csrf @method('PUT')
                                <input name="label" value="{{ $h->label }}" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white">
                                <input name="route_pattern" value="{{ $h->route_pattern }}" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white">
                                <select name="surface" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white">
                                    <option value="any" @selected($h->surface==='any')>Any</option>
                                    <option value="marketing" @selected($h->surface==='marketing')>Marketing</option>
                                    <option value="app" @selected($h->surface==='app')>App</option>
                                </select>
                                <input name="priority" type="number" value="{{ $h->priority }}" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white">
                                <textarea name="description" rows="2" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white">{{ $h->description }}</textarea>
                                <textarea name="suggested_actions_text" rows="3" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white" placeholder="One action per line">{{ collect((array) $h->suggested_actions)->map(fn($a)=>is_array($a)?($a['label']??''):$a)->filter()->implode("\n") }}</textarea>
                                <label class="flex items-center gap-2 text-xs text-white"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ $h->is_active?'checked':'' }}> Active</label>
                                <label class="flex items-center gap-2 text-xs text-white"><input type="hidden" name="disable_widget" value="0"><input type="checkbox" name="disable_widget" value="1" {{ $h->disable_widget?'checked':'' }}> Hide widget</label>
                                <button class="px-3 py-1 rounded bg-purple-500 text-white text-xs">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.site-assistant.hints.destroy', $h) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this hint?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button class="text-red-300 text-xs">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-white/40 text-sm">No page hints yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $hints->links() }}
</div>
@endsection
