<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - 1INME</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">1INME</h1>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="text-5xl mb-4">📧</div>
            <h2 class="text-xl font-semibold text-gray-800 mb-2">Verify Your Email</h2>
            <p class="text-gray-500 text-sm mb-6">We've sent a verification link to your email. Please check your inbox and click the link to verify.</p>

            @if(session('status'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('user.verification.send') }}">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg text-sm font-medium">Resend Verification Email</button>
            </form>

            <div class="mt-4">
                <a href="{{ route('user.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">Skip for now</a>
            </div>
        </div>
    </div>
</body>
</html>
