@extends('user.layouts.app')
@section('title', 'AI Inbox Agent')

@section('content')
@php
    $categoryLabels = \App\Modules\User\Models\InboxThread::CATEGORY_LABELS;
@endphp
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'AI Inbox Agent',
        'subtitle' => 'Let AI triage, draft, and auto-reply across every channel',
        'icon' => 'fa-robot',
        'actions' => [
            ['label' => 'Back to inbox', 'url' => route('user.inbox.unified.index'), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
        </div>
    @endif

    @unless($engineOn)
        <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
            <i class="fas fa-triangle-exclamation mr-1.5"></i>AI features are currently turned off platform-wide. Triage falls back to rules and AI drafting/autopilot are paused until an admin re-enables the AI engine.
        </div>
    @endunless

    @unless($planAllows)
        <div class="mb-4 px-4 py-3 rounded-xl text-sm flex items-center justify-between gap-3" style="background: rgba(92,131,255,0.1); border: 1px solid rgba(92,131,255,0.25); color: #bccfff;">
            <span><i class="fas fa-lock mr-1.5"></i>The AI Inbox Agent isn't included in your current plan@if($upgradePlan), upgrade to <strong>{{ $upgradePlan->name }}</strong> to unlock AI triage, drafting, and autopilot@endif.</span>
            <a href="{{ route('user.upgrade') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">Upgrade</a>
        </div>
    @endunless

    <form method="POST" action="{{ route('user.inbox.unified.agent.update') }}"
          x-data="{ autopilot: {{ $settings['autopilot_enabled'] ? 'true' : 'false' }}, threshold: {{ (float) $settings['confidence_threshold'] }} }"
          class="space-y-5 {{ $planAllows ? '' : 'opacity-60 pointer-events-none' }}">
        @csrf

        {{-- Triage --}}
        <div class="card-premium p-5">
            <div class="text-sm font-bold mb-1" style="color: var(--text-primary);"><i class="fas fa-wand-magic-sparkles mr-2" style="color:#5c83ff;"></i>Smart triage</div>
            <div class="text-xs mb-4" style="color: var(--text-muted);">Use AI to categorize, prioritize, and summarize new threads as they arrive. When off, fast rule-based triage is used instead.</div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="ai_triage" value="1" {{ $settings['ai_triage'] ? 'checked' : '' }} class="w-4 h-4">
                <span class="text-sm" style="color: var(--text-secondary);">Enable AI triage for incoming threads</span>
            </label>
        </div>

        {{-- Voice --}}
        <div class="card-premium p-5 space-y-4">
            <div>
                <div class="text-sm font-bold mb-1" style="color: var(--text-primary);"><i class="fas fa-comment-dots mr-2" style="color:#5c83ff;"></i>Reply voice</div>
                <div class="text-xs" style="color: var(--text-muted);">Shapes both manual AI drafts and autopilot replies.</div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Tone</label>
                <select name="tone" class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    @foreach($tones as $key => $label)
                        <option value="{{ $key }}" {{ $settings['tone'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Persona / brand voice</label>
                <textarea name="persona" rows="3" maxlength="2000" placeholder="e.g. You are Mia, a friendly indie musician. Keep replies short, warm, and never make promises about tour dates."
                          class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">{{ $settings['persona'] }}</textarea>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Signature</label>
                <textarea name="signature" rows="2" maxlength="500" placeholder="Appended to AI replies, e.g., Mia"
                          class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">{{ $settings['signature'] }}</textarea>
            </div>
        </div>

        {{-- Autopilot --}}
        <div class="card-premium p-5 space-y-4">
            <div>
                <div class="text-sm font-bold mb-1" style="color: var(--text-primary);"><i class="fas fa-plane mr-2" style="color:#5c83ff;"></i>Autopilot</div>
                <div class="text-xs" style="color: var(--text-muted);">When confident enough, the agent replies automatically and labels the thread <strong>Sent by AI</strong>. Low-confidence or sensitive messages are queued for your review instead. Spam is never auto-replied.</div>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="autopilot_enabled" value="1" x-model="autopilot" class="w-4 h-4">
                <span class="text-sm" style="color: var(--text-secondary);">Enable autopilot replies</span>
            </label>

            <div x-show="autopilot" x-cloak class="space-y-4 pt-1">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Auto-reply these categories</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($categories as $cat)
                            @php $lbl = $categoryLabels[$cat][0] ?? ucfirst($cat); $clr = $categoryLabels[$cat][1] ?? '#5c83ff'; @endphp
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <input type="checkbox" name="autopilot_categories[]" value="{{ $cat }}" {{ in_array($cat, $settings['autopilot_categories'], true) ? 'checked' : '' }} class="w-4 h-4">
                                <span class="text-xs font-semibold" style="color: {{ $clr }};">{{ $lbl }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">
                        Confidence threshold, <span x-text="Math.round(threshold * 100) + '%'"></span>
                    </label>
                    <input type="range" name="confidence_threshold" min="0.5" max="0.99" step="0.01" x-model="threshold" class="w-full">
                    <div class="text-[11px] mt-1" style="color: var(--text-muted);">Only send automatically when triage confidence is at or above this. Anything lower goes to the review queue.</div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">
                <i class="fas fa-save mr-1.5"></i>Save agent settings
            </button>
        </div>
    </form>
</div>
@endsection
