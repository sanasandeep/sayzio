@extends('user.layouts.app')
@section('title', $profile->exists ? 'Edit Project' : 'New Project')

@section('content')
@php
    $toText = function ($bag) {
        if (!is_array($bag)) return trim((string) $bag);
        return implode("\n", array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $bag));
    };
    $isEdit   = $profile->exists;
    $action   = $isEdit
        ? route('user.ai.marketing-strategist.projects.update', $profile->id)
        : route('user.ai.marketing-strategist.projects.store');
    $inputCls = 'w-full rounded-xl bg-white/[0.04] border border-white/10 text-sm text-white placeholder-white/30 px-3 py-2.5 focus:outline-none focus:border-blue-500/50';
@endphp
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => $isEdit ? 'Edit project' : 'New project',
        'subtitle' => 'Everything here pre-fills new strategies you build for this project.',
        'balance'  => $balance,
    ])

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('user.ai.marketing-strategist.projects.index') }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-arrow-left mr-1"></i> All projects
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-500/25 bg-red-500/[0.08] text-red-200 text-sm px-4 py-3 mb-4">
            <i class="fas fa-triangle-exclamation mr-1.5"></i>{{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-5">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div>
            <label for="name" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-folder text-indigo-300 mr-1"></i> Project name
            </label>
            <input type="text" id="name" name="name" maxlength="120" required
                   value="{{ old('name', $profile->name) }}"
                   placeholder="e.g. Summer launch, Coaching business"
                   class="{{ $inputCls }}">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="business_name" class="block text-sm font-medium text-white mb-1">Business name</label>
                <input type="text" id="business_name" name="business_name" maxlength="160"
                       value="{{ old('business_name', $profile->business_name) }}"
                       placeholder="e.g. Acme Studio" class="{{ $inputCls }}">
            </div>
            <div>
                <label for="industry" class="block text-sm font-medium text-white mb-1">Industry</label>
                <input type="text" id="industry" name="industry" maxlength="160"
                       value="{{ old('industry', $profile->industry) }}"
                       placeholder="e.g. Fitness, SaaS, D2C" class="{{ $inputCls }}">
            </div>
        </div>

        <div>
            <label for="main_offer" class="block text-sm font-medium text-white mb-1">Main offer / product</label>
            <input type="text" id="main_offer" name="main_offer" maxlength="300"
                   value="{{ old('main_offer', $profile->main_offer) }}"
                   placeholder="e.g. Paid newsletter, $9/mo coaching" class="{{ $inputCls }}">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="budget" class="block text-sm font-medium text-white mb-1">Budget</label>
                <input type="text" id="budget" name="budget" maxlength="120"
                       value="{{ old('budget', $profile->budget) }}"
                       placeholder="e.g. 200 / month" class="{{ $inputCls }}">
            </div>
            <div>
                <label for="currency" class="block text-sm font-medium text-white mb-1">Currency</label>
                <input type="text" id="currency" name="currency" maxlength="40"
                       value="{{ old('currency', $profile->currency) }}"
                       placeholder="e.g. USD, EUR, INR" class="{{ $inputCls }}">
            </div>
        </div>

        @if(!empty($brandKits))
            <div>
                <label for="brand_kit_id" class="block text-sm font-medium text-white mb-1">
                    <i class="fas fa-palette text-pink-300 mr-1"></i> Brand kit
                </label>
                <p class="text-xs text-white/45 mb-2">Its logo and colors brand this project's reports and shared pages.</p>
                <select id="brand_kit_id" name="brand_kit_id" class="{{ $inputCls }}">
                    <option value="" class="bg-slate-800">Use my active brand kit</option>
                    @foreach($brandKits as $id => $label)
                        <option value="{{ $id }}" @selected((int) old('brand_kit_id', $profile->brand_kit_id) === (int) $id) class="bg-slate-800">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="target_audience" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-users text-sky-300 mr-1"></i> Who are you trying to reach?
            </label>
            <p class="text-xs text-white/45 mb-2">Your audience, niche, or ideal customer. One per line.</p>
            <textarea id="target_audience" name="target_audience" rows="4" maxlength="4000"
                      placeholder="e.g. Indie fitness coaches&#10;Women 25–40 in metro cities"
                      class="{{ $inputCls }}">{{ old('target_audience', $toText($profile->target_audience)) }}</textarea>
        </div>

        <div>
            <label for="expectations" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-bullseye text-emerald-300 mr-1"></i> What do you want to achieve?
            </label>
            <p class="text-xs text-white/45 mb-2">Goals and outcomes you care about. One per line.</p>
            <textarea id="expectations" name="expectations" rows="4" maxlength="4000"
                      placeholder="e.g. Grow email subscribers to 5,000&#10;More bookings from my biolink"
                      class="{{ $inputCls }}">{{ old('expectations', $toText($profile->expectations)) }}</textarea>
        </div>

        <div>
            <label for="constraints" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-hand text-amber-300 mr-1"></i> Any constraints?
            </label>
            <p class="text-xs text-white/45 mb-2">Budget limits, platforms to avoid, brand rules. One per line.</p>
            <textarea id="constraints" name="constraints" rows="4" maxlength="4000"
                      placeholder="e.g. No paid ads for now&#10;Keep it Instagram + WhatsApp only"
                      class="{{ $inputCls }}">{{ old('constraints', $toText($profile->constraints)) }}</textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <a href="{{ route('user.ai.marketing-strategist.projects.index') }}"
               class="px-4 py-2 rounded-xl bg-white/5 text-white/70 text-sm hover:bg-white/10">Cancel</a>
            <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                <i class="fas fa-floppy-disk mr-1"></i> {{ $isEdit ? 'Save changes' : 'Create project' }}
            </button>
        </div>
    </form>
</div>
@endsection
