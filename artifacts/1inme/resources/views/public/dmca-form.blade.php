<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>DMCA Takedown — {{ config('app.name') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <h1 class="text-2xl font-extrabold text-slate-900">DMCA / IP Takedown</h1>
        <p class="text-sm text-slate-600 mt-1">Use this form to report content you believe infringes your copyright. Filing a false claim has legal consequences — please review your work and the linked content carefully.</p>

        @if(session('error'))
            <div class="mt-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <ul class="mt-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm list-disc pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('legal.dmca.store') }}" class="mt-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-slate-700">Your name *</label>
                    <input name="reporter_name" required maxlength="200" value="{{ old('reporter_name') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">Email *</label>
                    <input type="email" name="reporter_email" required maxlength="200" value="{{ old('reporter_email') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-700">Mailing address (optional)</label>
                <input name="reporter_address" maxlength="500" value="{{ old('reporter_address') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-700">Rights holder (if not you)</label>
                <input name="rights_holder" maxlength="200" value="{{ old('rights_holder') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-700">URL of original work *</label>
                <input type="url" name="original_work_url" required value="{{ old('original_work_url') }}" placeholder="https://…" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-700">Infringing URL on this site *</label>
                <input type="url" name="infringing_url" required value="{{ old('infringing_url') }}" placeholder="{{ url('/@handle/p/123') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
            </div>

            <label class="flex items-start gap-2 text-xs text-slate-700">
                <input type="checkbox" name="good_faith_acknowledged" value="1" required class="mt-0.5"/>
                <span>I have a good-faith belief that the use described above is not authorised by the copyright owner, its agent, or the law.</span>
            </label>

            <label class="flex items-start gap-2 text-xs text-slate-700">
                <input type="checkbox" name="penalty_of_perjury_acknowledged" value="1" required class="mt-0.5"/>
                <span>I swear, under penalty of perjury, that the information in this notice is accurate and that I am the copyright owner or authorised to act on the owner's behalf.</span>
            </label>

            <div>
                <label class="text-xs font-semibold text-slate-700">Electronic signature *</label>
                <input name="signature" required maxlength="200" value="{{ old('signature') }}" placeholder="Type your full legal name" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"/>
            </div>

            <button class="w-full px-4 py-3 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-black">
                Submit takedown request
            </button>
            <p class="text-[11px] text-slate-500 text-center">We'll email you when a moderator reviews your request.</p>
        </form>
    </div>
</div>
</body></html>
