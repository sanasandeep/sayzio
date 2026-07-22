@extends('admin.layouts.app')
@section('title', 'Site Assistant: Response Templates')
@section('page-title', 'Site Assistant, Response Templates')

@section('content')
<div class="max-w-6xl space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="text-sm text-white/60 ak-muted"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white ak-strong">New response template</h3>
        <p class="text-xs text-white/40 ak-note">Reusable rich blocks the model can refer to by key (the system prompt is updated to mention available templates). Payload is the JSON envelope (e.g. <code>{"options":[…]}</code> for a buttons block).</p>
        <form method="POST" action="{{ route('admin.site-assistant.templates.store') }}" class="grid md:grid-cols-2 gap-3">
            @csrf
            <input name="key" required maxlength="64" placeholder="key (e.g. pricing_plans)" pattern="[a-z0-9_]+" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
            <input name="label" required maxlength="120" placeholder="Label" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
            <select name="kind" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <option value="buttons">Buttons</option><option value="list">List</option><option value="form">Form</option><option value="image">Image</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-white ak-strong"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <textarea name="payload_json" rows="6" required class="md:col-span-2 bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong">{"options":[{"label":"Tell me more","value":"more"}]}</textarea>
            <div><button class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold">Add template</button></div>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase ak-muted">
                <tr><th class="text-left p-3">Key</th><th class="text-left p-3">Label</th><th class="text-left p-3">Kind</th><th class="text-left p-3">Active</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
            @forelse($templates as $t)
                <tr class="border-t border-white/5">
                    <td class="p-3 text-white font-mono text-xs ak-strong">{{ $t->key }}</td>
                    <td class="p-3 text-white ak-strong">{{ $t->label }}</td>
                    <td class="p-3 text-white/70 ak-strong">{{ $t->kind }}</td>
                    <td class="p-3">{!! $t->is_active ? '<span class="text-emerald-300 text-xs ak-green">●</span>' : '<span class="text-white/30 text-xs ak-note">○</span>' !!}</td>
                    <td class="p-3 text-right space-x-1">
                        <details class="inline-block text-left">
                            <summary class="cursor-pointer text-indigo-300 text-xs ak-blue">Edit</summary>
                            <form method="POST" action="{{ route('admin.site-assistant.templates.update', $t) }}" class="mt-2 space-y-2 p-3 bg-black/40 rounded-lg w-96">
                                @csrf @method('PUT')
                                <input name="key" value="{{ $t->key }}" pattern="[a-z0-9_]+" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white ak-strong">
                                <input name="label" value="{{ $t->label }}" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white ak-strong">
                                <select name="kind" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white ak-strong">
                                    @foreach(['buttons','list','form','image'] as $k)<option value="{{ $k }}" @selected($t->kind===$k)>{{ $k }}</option>@endforeach
                                </select>
                                <textarea name="payload_json" rows="6" class="w-full bg-black/30 border border-white/10 rounded px-2 py-1 text-xs text-white font-mono ak-strong">{{ json_encode($t->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea>
                                <label class="flex items-center gap-2 text-xs text-white ak-strong"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ $t->is_active?'checked':'' }}> Active</label>
                                <button class="px-3 py-1 rounded bg-indigo-500 text-white text-xs">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.site-assistant.templates.destroy', $t) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this template?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button class="text-red-300 text-xs ak-red">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-white/40 text-sm ak-note">No templates yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $templates->links() }}
</div>
@endsection
