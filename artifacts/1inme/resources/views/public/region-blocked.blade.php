<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Not available in your region — {{ config('app.name') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-md mx-auto px-4 py-20 text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-2xl"><i class="fas fa-globe"></i></div>
    <h1 class="mt-4 text-2xl font-extrabold text-slate-900">Not available in your region</h1>
    <p class="mt-2 text-sm text-slate-600">{{ $reason ?? 'The creator has restricted this content in your region.' }}</p>
    <a href="{{ route('creators.index') }}" class="mt-6 inline-block px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Browse other creators</a>
</div>
</body></html>
