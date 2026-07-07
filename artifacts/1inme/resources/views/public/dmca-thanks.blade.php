<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Takedown received — {{ config('app.name') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl"><i class="fas fa-check"></i></div>
    <h1 class="mt-4 text-2xl font-extrabold text-slate-900">Thanks — your takedown is in our queue.</h1>
    <p class="mt-2 text-sm text-slate-600">A moderator will review it within a few business days. We'll email you when there's an update.</p>
    <a href="{{ url('/') }}" class="mt-6 inline-block px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Back to home</a>
</div>
</body></html>
