<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign-in revoked - 1INME</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow p-8">
        <div class="text-2xl font-bold text-blue-600 mb-6">1INME</div>
        <h1 class="text-xl font-semibold text-slate-900 mb-3">Sign-in revoked</h1>
        <p class="text-sm text-slate-600 leading-relaxed mb-4">
            We've signed that session out, logged every other device off your account, and cleared your password.
        </p>
        <p class="text-sm text-slate-600 leading-relaxed mb-6">
            To get back in, use <strong>Forgot password</strong> on the sign-in page to set a brand-new one.
        </p>
        <a href="{{ route('user.login') }}"
           class="inline-block bg-blue-600 text-white font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700">
            Go to sign-in
        </a>
    </div>
</body>
</html>
