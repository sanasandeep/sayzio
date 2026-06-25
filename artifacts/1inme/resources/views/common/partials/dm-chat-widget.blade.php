{{--
    Reusable Direct Message chat widget. Mounted on:
      - The biolink page's "Direct Message" block (default)
      - The Creators directory "Message" overlay (variant='overlay',
        inheritDmContext=true so the caller owns the dmBlock x-data with
        a dynamic linkId)

    Required vars (when inheritDmContext = false):
      $dmLinkId  – biolink id the conversation is attached to
      $dmLimit   – initial outreach cap (ViewerDmConversation::VIEWER_INITIAL_LIMIT)
      $loggedIn  – bool, viewer signed in?
    Optional vars:
      $dmTitle, $dmDesc, $dmPh, $dmBtn
      $fontColor          – textarea text color (defaults to inherit)
      $variant            – 'block' (default) or 'overlay'
      $inheritDmContext   – when true, skip the dmBlock x-data wrapper and
                            rely on the caller to provide one (so a parent
                            Alpine scope can drive linkId dynamically).
--}}
@php
    $dmTitle          = $dmTitle          ?? 'Send a direct message';
    $dmDesc           = $dmDesc           ?? 'Reach out — replies arrive in your inbox.';
    $dmPh             = $dmPh             ?? 'Write your message…';
    $dmBtn            = $dmBtn            ?? 'Send message';
    $fontColor        = $fontColor        ?? 'inherit';
    $variant          = $variant          ?? 'block';
    $isOverlay        = $variant === 'overlay';
    $inheritDmContext = $inheritDmContext ?? false;
@endphp
<div class="{{ $isOverlay ? '' : 'mb-4 glass-block rounded-2xl p-5' }}"
     @if(!$inheritDmContext)
         x-data='dmBlock({{ json_encode([
            "linkId"   => (int) $dmLinkId,
            "limit"    => (int) $dmLimit,
            "loggedIn" => (bool) $loggedIn,
            "csrf"     => csrf_token(),
         ]) }})'
         x-init="init()"
         {{-- After OTP login the modal fires viewer-message-ready. If this widget
              is mounted (e.g. on the biolink page) and the event targets our
              biolink (or omits the id, meaning "any"), flip to logged-in and
              refresh the thread so the composer appears immediately. --}}
         @viewer-message-ready.window="
             if (!loggedIn && (!$event.detail || !$event.detail.biolinkId || Number($event.detail.biolinkId) === Number(linkId))) {
                 loggedIn = true;
                 refresh();
             }
         "
     @endif>
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
             style="background: rgba(99,102,241,.18); color:#a5b4fc">
            <i class="fas fa-comments"></i>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold leading-tight">{{ $dmTitle }}</p>
            <p class="text-xs opacity-60 leading-tight">{{ $dmDesc }}</p>
        </div>
    </div>

    {{-- Anti-spam note (always shown so the rule is transparent). --}}
    <p class="text-[11px] mb-3 px-3 py-2 rounded-lg"
       style="background: rgba(250,204,21,.08); color:#fde68a; border:1px solid rgba(250,204,21,.2);">
        <i class="fas fa-info-circle mr-1"></i>
        New conversations are limited to {{ $dmLimit }} messages until the creator replies.
    </p>

    {{-- Logged-out: prompt to login first. --}}
    <template x-if="!loggedIn">
        <button type="button"
                @click="$dispatch('open-viewer-login', { action: 'message', biolinkId: linkId })"
                class="w-full {{ $isOverlay ? 'px-4 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-500' : 'bio-btn py-2.5' }} text-sm font-medium">
            <i class="fas fa-sign-in-alt mr-1"></i> Login to send a message
        </button>
    </template>

    {{-- Logged-in: thread + composer. --}}
    <template x-if="loggedIn">
        <div>
            {{-- Status banners --}}
            <template x-if="state.unavailable">
                <p class="text-xs mb-3 px-3 py-2 rounded-lg" style="background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.25)">
                    <i class="fas fa-triangle-exclamation mr-1"></i> This creator can no longer be messaged.
                </p>
            </template>
            <template x-if="state.blocked">
                <p class="text-xs mb-3 px-3 py-2 rounded-lg" style="background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.25)">
                    <i class="fas fa-ban mr-1"></i> The creator has blocked further messages in this conversation.
                </p>
            </template>
            <template x-if="!state.blocked && state.throttled">
                <p class="text-xs mb-3 px-3 py-2 rounded-lg" style="background:rgba(250,204,21,.1);color:#fde68a;border:1px solid rgba(250,204,21,.25)">
                    <i class="fas fa-hourglass-half mr-1"></i>
                    You've used your <span x-text="limit"></span> intro messages. Wait for a reply to continue.
                </p>
            </template>

            {{-- Thread --}}
            <div class="space-y-2 mb-3 max-h-64 overflow-y-auto pr-1" x-show="messages.length > 0">
                <template x-for="m in messages" :key="m.id">
                    <div :class="m.side === 'viewer' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="rounded-2xl px-3 py-2 text-xs max-w-[80%] whitespace-pre-wrap break-words"
                             :class="m.side === 'viewer'
                                ? 'bg-white/10 text-white'
                                : 'bg-indigo-500/30 text-indigo-50'"
                             x-text="m.body"></div>
                    </div>
                </template>
            </div>

            {{-- Composer --}}
            <form @submit.prevent="send()"
                  x-show="!state.blocked && !state.throttled && !state.unavailable"
                  class="flex gap-2 items-end">
                <textarea x-model="body" required rows="2" maxlength="2000"
                          :placeholder="'{{ addslashes($dmPh) }}'"
                          class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm outline-none focus:border-white/20 resize-none"
                          style="color: {{ $fontColor }}"></textarea>
                <button type="submit"
                        :disabled="loading || !body.trim()"
                        class="{{ $isOverlay ? 'px-4 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-50' : 'bio-btn px-4 py-2.5' }} text-sm font-medium whitespace-nowrap">
                    <span x-show="!loading">{{ $dmBtn }}</span>
                    <span x-show="loading"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
            </form>
            <p class="text-[11px] mt-2 opacity-60" x-show="!state.blocked && !state.owner_replied && !state.throttled && !state.unavailable">
                <span x-text="(limit - state.sent)"></span> intro messages left.
            </p>
            <p class="text-[11px] mt-2 text-red-300" x-show="error" x-text="error"></p>
        </div>
    </template>
</div>

@include('common.partials.dm-chat-widget-script')
