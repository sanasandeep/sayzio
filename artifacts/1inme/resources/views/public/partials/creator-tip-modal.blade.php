{{-- Tip modal (Task #1209). Shared by the /@handle creator profile and the
     Paid Page link type. Submits to the handle-based tip routes; the post id
     is filled in by [data-cp-open-tip] handlers in creator-feed-scripts. --}}
@if(!$isOwner)
<div id="cp-tip-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] hidden items-center justify-center" style="display: none;">
    <style>#cp-tip-modal:not(.hidden){display:flex !important;}</style>
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-[95%] p-6">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Send a tip to {{ $creator->name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">100% goes to the creator. 1INME takes 0%.</p>
            </div>
            <button id="cp-tip-close" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <form id="cp-tip-form" method="POST" action="{{ route('creator-profile.tip', ['handle' => $creator->handle]) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="post_id" id="cp-tip-post-id" value="">
            <div class="grid grid-cols-4 gap-1.5">
                @foreach([3, 5, 10, 20, 50, 100] as $amt)
                    <button type="button" data-cp-tip-amount="{{ $amt }}"
                            class="px-2 py-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 hover:border-rose-400 hover:bg-rose-50">
                        ${{ $amt }}
                    </button>
                @endforeach
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-slate-500">Amount ($)</label>
                <input type="number" name="amount" min="1" max="500" step="0.5" required value="5"
                       class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-rose-400 focus:outline-none">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-slate-500">Message (optional)</label>
                <textarea name="note" rows="2" maxlength="280" placeholder="Say something nice…"
                          class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-rose-400 focus:outline-none text-sm"></textarea>
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-bold text-white bg-gradient-to-r from-rose-500 to-pink-600">
                <i class="fas fa-heart mr-1"></i> Send tip
            </button>
        </form>
    </div>
</div>
@endif
