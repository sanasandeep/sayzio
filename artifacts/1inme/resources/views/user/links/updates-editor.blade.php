@extends('user.layout.app')

@section('title', ($link->title ?: 'Updates Page') . ': Editor')

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4"
     x-data="updatesEditor()"
     x-init="init()">

    {{-- Breadcrumbs --}}
    <nav class="flex items-center gap-2 text-sm text-white/50 mb-5">
        <a href="{{ route('user.links.index') }}" class="hover:text-white/80 transition-colors">My Links</a>
        <i class="fa fa-chevron-right text-xs"></i>
        <span class="text-white/80">{{ $link->title ?: 'Updates Page' }}</span>
    </nav>

    @if(session('success'))
    <div class="mb-5 bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 text-emerald-300 text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-5 bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-300 text-sm">
        {{ session('error') }}
    </div>
    @endif

    <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-sky-500/20 flex items-center justify-center shrink-0">
                <i class="fa fa-bullhorn text-sky-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">{{ $link->title ?: 'Updates Page' }}</h1>
                <a href="{{ $link->getShortUrl() }}" target="_blank"
                   class="text-xs text-white/40 hover:text-sky-400 transition-colors flex items-center gap-1">
                    {{ $link->getShortUrl() }}
                    <i class="fa fa-external-link-alt text-[10px]"></i>
                </a>
            </div>
        </div>
        <button type="button" @click="showEntryForm = true; editingEntry = null; resetForm()"
                class="btn-primary flex items-center gap-2 text-sm py-2 px-4">
            <i class="fa fa-plus"></i>
            New Entry
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Entries list --}}
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider">Entries</h2>

            @if($entries->isEmpty())
            <div class="glass-card rounded-2xl p-8 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-sky-500/10 flex items-center justify-center">
                    <i class="fa fa-bullhorn text-sky-400 text-xl"></i>
                </div>
                <p class="text-white/60 text-sm">No entries yet. Click <strong class="text-white">New Entry</strong> to post your first update.</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($entries as $entry)
                <div class="glass-card rounded-2xl p-5 group relative">
                    <div class="flex items-start gap-4">
                        @if($entry->image)
                        <img src="{{ \App\Support\PublicStorageUrl::resolve($entry->image) }}"
                             alt="" class="w-16 h-16 rounded-xl object-cover shrink-0">
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                @if($entry->tag)
                                @php $tagClasses = \App\Modules\User\Models\UpdateEntry::tagClasses()[$entry->tag] ?? 'bg-white/10 text-white/60 border-white/20'; @endphp
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full border {{ $tagClasses }}">{{ $entry->tag }}</span>
                                @endif
                                <span class="text-xs text-white/40">{{ $entry->published_date?->format('M j, Y') }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $entry->status === 'published' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400' }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                                @if($entry->notified_at)
                                <span class="text-xs text-white/30" title="Followers notified {{ $entry->notified_at->diffForHumans() }}">
                                    <i class="fa fa-bell text-[10px]"></i> Notified
                                </span>
                                @endif
                            </div>
                            <h3 class="font-semibold text-white text-sm truncate">{{ $entry->title }}</h3>
                            @if($entry->body)
                            <p class="text-xs text-white/50 mt-1 line-clamp-2">{{ strip_tags($entry->body) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            <button type="button"
                                    @click="editEntry(@js(['id' => $entry->id, 'title' => $entry->title, 'body' => $entry->body, 'tag' => $entry->tag, 'published_date' => $entry->published_date?->toDateString(), 'status' => $entry->status]))"
                                    class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/70 hover:text-white transition-colors">
                                <i class="fa fa-pencil text-xs"></i>
                            </button>
                            <form method="POST" action="{{ route('user.links.updates.entries.destroy', [$link, $entry]) }}"
                                  @submit.prevent="if(confirm('Delete this entry?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 flex items-center justify-center text-red-400 hover:text-red-300 transition-colors">
                                    <i class="fa fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Sidebar: settings --}}
        <div class="space-y-4">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider">Page Settings</h2>
            <div class="glass-card rounded-2xl p-5">
                <form method="POST" action="{{ route('user.links.updates.settings', $link) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-white/70 mb-1">Page heading</label>
                            <input type="text" name="heading" value="{{ $settings['heading'] }}"
                                   class="glass-input w-full text-sm" maxlength="120">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-white/70 mb-1">Subheading</label>
                            <input type="text" name="subheading" value="{{ $settings['subheading'] }}"
                                   class="glass-input w-full text-sm" maxlength="255">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-white/70 mb-1">Entries per page</label>
                            <input type="number" name="per_page" value="{{ $settings['per_page'] }}"
                                   class="glass-input w-full text-sm" min="1" max="100">
                        </div>
                        <button type="submit" class="btn-secondary w-full text-sm py-2">Save settings</button>
                    </div>
                </form>
            </div>

            {{-- Quick link --}}
            <div class="glass-card rounded-2xl p-4">
                <p class="text-xs text-white/50 mb-2">Public page</p>
                <a href="{{ $link->getShortUrl() }}" target="_blank"
                   class="text-sm text-sky-400 hover:text-sky-300 transition-colors flex items-center gap-1.5 break-all">
                    <i class="fa fa-external-link-alt text-xs shrink-0"></i>
                    {{ $link->getShortUrl() }}
                </a>
            </div>
        </div>
    </div>

    {{-- Entry form slide-over --}}
    <div x-show="showEntryForm" x-cloak
         class="fixed inset-0 z-50 flex"
         @keydown.escape.window="showEntryForm = false">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showEntryForm = false"></div>
        <div class="relative ml-auto w-full max-w-lg bg-[#13111c] border-l border-white/10 h-full overflow-y-auto p-6 flex flex-col gap-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white" x-text="editingEntry ? 'Edit Entry' : 'New Entry'"></h2>
                <button @click="showEntryForm = false" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/70">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <form method="POST"
                  :action="editingEntry ? `{{ url('user/links/' . $link->id . '/updates-entries') }}/${editingEntry.id}` : `{{ route('user.links.updates.entries.store', $link) }}`"
                  enctype="multipart/form-data"
                  class="flex flex-col gap-4 flex-1"
                  @submit.prevent="submitForm($el)">
                @csrf
                <input type="hidden" name="_method" x-bind:value="editingEntry ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">Title <span class="text-red-400">*</span></label>
                    <input type="text" name="title" x-model="form.title"
                           class="glass-input w-full text-sm" maxlength="255" required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">Body <span class="text-white/30">(optional)</span></label>
                    <textarea name="body" x-model="form.body"
                              class="glass-input w-full text-sm resize-none"
                              rows="6"
                              maxlength="50000"
                              placeholder="Describe the update…"></textarea>
                    <p class="text-xs text-white/30 mt-1">Basic HTML formatting is preserved.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-white/70 mb-1">Tag</label>
                        <select name="tag" x-model="form.tag" class="glass-input w-full text-sm">
                            <option value="">None</option>
                            @foreach($allowedTags as $tag)
                            <option value="{{ $tag }}">{{ $tag }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/70 mb-1">Date</label>
                        <input type="date" name="published_date" x-model="form.published_date"
                               class="glass-input w-full text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">Status</label>
                    <select name="status" x-model="form.status" class="glass-input w-full text-sm">
                        <option value="draft">Draft (hidden from public)</option>
                        <option value="published">Published</option>
                    </select>
                    <p class="text-xs text-white/40 mt-1">Publishing for the first time notifies your followers.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">Cover image <span class="text-white/30">(optional)</span></label>
                    <input type="file" name="image" accept="image/*" class="text-sm text-white/60 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-white/10 file:text-white/80 file:text-xs hover:file:bg-white/20 transition-colors">
                    <template x-if="editingEntry">
                        <label class="flex items-center gap-2 mt-2 text-xs text-white/50">
                            <input type="checkbox" name="remove_image" value="1" class="rounded"> Remove existing image
                        </label>
                    </template>
                </div>

                <div class="flex items-center gap-3 pt-2 mt-auto">
                    <button type="submit" :disabled="saving"
                            class="btn-primary flex-1 flex items-center justify-center gap-2 text-sm py-2.5">
                        <span x-show="!saving" x-text="editingEntry ? 'Save changes' : 'Create entry'"></span>
                        <span x-show="saving" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                    <button type="button" @click="showEntryForm = false"
                            class="btn-secondary text-sm py-2.5 px-4">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updatesEditor() {
    return {
        showEntryForm: false,
        editingEntry: null,
        saving: false,
        form: {
            title: '',
            body: '',
            tag: '',
            published_date: '{{ now()->toDateString() }}',
            status: 'draft',
        },

        init() {},

        resetForm() {
            this.form = {
                title: '',
                body: '',
                tag: '',
                published_date: '{{ now()->toDateString() }}',
                status: 'draft',
            };
        },

        editEntry(entry) {
            this.editingEntry = entry;
            this.form = {
                title: entry.title || '',
                body: entry.body || '',
                tag: entry.tag || '',
                published_date: entry.published_date || '{{ now()->toDateString() }}',
                status: entry.status || 'draft',
            };
            this.showEntryForm = true;
        },

        submitForm(formEl) {
            this.saving = true;
            formEl.submit();
        },
    };
}
</script>
@endpush
