<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Share your experience — {{ config('app.name') }}</title>
    @include('common.partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('common.partials.fontawesome')
    <style>
        body {
            background: #0f172a;
            min-height: 100vh;
        }
        html.light-mode body {
            background: #f1f5f9;
        }
        .ts-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.10);
        }
        html.light-mode .ts-card {
            background: #fff;
            border: 1px solid #e2e8f0;
        }
        .ts-label {
            color: rgba(255,255,255,0.65);
        }
        html.light-mode .ts-label {
            color: #374151;
        }
        .ts-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.9);
        }
        html.light-mode .ts-input {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #111827;
        }
        .ts-input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        html.light-mode .ts-input::placeholder {
            color: #94a3b8;
        }
        .ts-input:focus {
            outline: none;
            border-color: #3d6bff;
        }
        html.light-mode .ts-input:focus {
            border-color: #3d6bff;
        }
        .ts-heading {
            color: rgba(255,255,255,0.95);
        }
        html.light-mode .ts-heading {
            color: #0f172a;
        }
        .ts-body {
            color: rgba(255,255,255,0.55);
        }
        html.light-mode .ts-body {
            color: #475569;
        }
        .ts-error {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }
        html.light-mode .ts-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .ts-star-btn {
            color: rgba(255,255,255,0.25);
            cursor: pointer;
            font-size: 1.375rem;
            transition: color .15s;
        }
        html.light-mode .ts-star-btn {
            color: #cbd5e1;
        }
        .ts-star-btn.active {
            color: #fbbf24;
        }
        html.light-mode .ts-star-btn.active {
            color: #d97706;
        }
        .ts-hint {
            color: rgba(255,255,255,0.35);
        }
        html.light-mode .ts-hint {
            color: #94a3b8;
        }
        /* Honeypot: visually hidden, not display:none so it renders */
        .hp-field {
            position: absolute;
            left: -9999px;
            height: 0;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            tab-index: -1;
        }
    </style>
</head>
<body>
<div class="max-w-xl mx-auto px-4 py-12 sm:py-20">

    {{-- Logo / back link --}}
    <div class="mb-8 flex items-center gap-3">
        <a href="{{ url('/') }}" class="text-blue-400 hover:text-blue-300 text-sm flex items-center gap-1.5">
            <i class="fas fa-arrow-left text-xs"></i>
            Back to {{ config('app.name') }}
        </a>
    </div>

    @if(session('success'))
        {{-- Thank-you state --}}
        <div class="ts-card rounded-2xl p-8 text-center">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: rgba(61,107,255,.15);">
                <i class="fas fa-check text-2xl text-blue-400"></i>
            </div>
            <h1 class="text-2xl font-bold ts-heading mb-2">Thank you!</h1>
            <p class="ts-body text-sm leading-relaxed">
                Your testimonial has been received. Our team will review it shortly — if approved, it'll appear on the homepage.
            </p>
            <a href="{{ url('/') }}"
               class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                Visit {{ config('app.name') }}
            </a>
        </div>
    @else
        {{-- Form --}}
        <div class="ts-card rounded-2xl p-6 sm:p-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold ts-heading">Share your experience</h1>
                <p class="ts-body text-sm mt-1.5 leading-relaxed">
                    We'd love to hear what you think of {{ config('app.name') }}. Submissions are reviewed before they appear publicly.
                </p>
            </div>

            @if($errors->any())
                <div class="ts-error rounded-xl px-4 py-3 mb-5 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('testimonials.submit.store') }}" novalidate>
                @csrf

                {{-- Honeypot --}}
                <div class="hp-field" aria-hidden="true">
                    <label for="hp_website">Leave blank</label>
                    <input type="text" name="website" id="hp_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="space-y-5">
                    {{-- Quote --}}
                    <div>
                        <label for="ts_quote" class="block text-xs font-semibold ts-label mb-1.5">
                            Your testimonial <span class="text-rose-400">*</span>
                        </label>
                        <textarea id="ts_quote" name="quote" rows="4" required maxlength="600"
                                  class="ts-input w-full rounded-xl px-3 py-2.5 text-sm resize-none"
                                  placeholder="What do you love about Sayzio? What has it helped you achieve?">{{ old('quote') }}</textarea>
                        <p class="ts-hint text-[11px] mt-1">Up to 600 characters. Please don't include quote marks — they're added automatically.</p>
                    </div>

                    {{-- Name + Role --}}
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="ts_author_name" class="block text-xs font-semibold ts-label mb-1.5">
                                Your name <span class="text-rose-400">*</span>
                            </label>
                            <input id="ts_author_name" type="text" name="author_name" required maxlength="120"
                                   value="{{ old('author_name') }}"
                                   class="ts-input w-full rounded-xl px-3 py-2.5 text-sm"
                                   placeholder="e.g. Jane Doe">
                        </div>
                        <div>
                            <label for="ts_author_role" class="block text-xs font-semibold ts-label mb-1.5">
                                Role / company <span class="ts-hint font-normal">(optional)</span>
                            </label>
                            <input id="ts_author_role" type="text" name="author_role" maxlength="160"
                                   value="{{ old('author_role') }}"
                                   class="ts-input w-full rounded-xl px-3 py-2.5 text-sm"
                                   placeholder="e.g. Founder, Acme Co.">
                        </div>
                    </div>

                    {{-- Star rating --}}
                    <div x-data="{ rating: {{ (int) old('rating', 5) }} }">
                        <p class="block text-xs font-semibold ts-label mb-1.5">Star rating</p>
                        <div class="flex items-center gap-1">
                            @for ($r = 1; $r <= 5; $r++)
                                <button type="button"
                                        class="ts-star-btn"
                                        :class="{ 'active': rating >= {{ $r }} }"
                                        @click="rating = {{ $r }}"
                                        aria-label="{{ $r }} star{{ $r > 1 ? 's' : '' }}">
                                    <i class="fas fa-star"></i>
                                </button>
                            @endfor
                        </div>
                        <input type="hidden" name="rating" :value="rating">
                    </div>

                    {{-- Email (optional) --}}
                    <div>
                        <label for="ts_email" class="block text-xs font-semibold ts-label mb-1.5">
                            Email <span class="ts-hint font-normal">(optional — only used to contact you if needed)</span>
                        </label>
                        <input id="ts_email" type="email" name="submitter_email" maxlength="200"
                               value="{{ old('submitter_email') }}"
                               class="ts-input w-full rounded-xl px-3 py-2.5 text-sm"
                               placeholder="you@example.com">
                    </div>

                    <button type="submit"
                            class="w-full px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm transition-colors">
                        Submit testimonial
                    </button>
                    <p class="ts-hint text-[11px] text-center">
                        By submitting you agree that we may display your name, role, and quote on our marketing pages.
                    </p>
                </div>
            </form>
        </div>
    @endif
</div>
</body>
</html>
