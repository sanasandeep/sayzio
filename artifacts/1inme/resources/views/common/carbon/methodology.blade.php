<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Carbon-Neutral Biolinks · Methodology · {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800">
    <div class="max-w-3xl mx-auto px-5 py-10 prose prose-sm">
        @include('common.carbon._methodology_body')
    </div>
</body>
</html>
