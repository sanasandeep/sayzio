<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Required - 1INME</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Password Required</h1>
            <p class="text-gray-500 text-sm mb-6">This link is password protected. Enter the password to continue.</p>

            @if(isset($error))
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg mb-4">{{ $error }}</div>
            @endif

            <form method="POST" action="{{ route('redirect.handle', $link->alias) }}">
                @csrf
                <input type="password" name="password" placeholder="Enter password" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm mb-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autofocus>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg text-sm font-medium">Continue</button>
            </form>
        </div>
        <p class="text-center text-xs text-gray-400 mt-4">Powered by 1INME</p>
    </div>
</body>
</html>
