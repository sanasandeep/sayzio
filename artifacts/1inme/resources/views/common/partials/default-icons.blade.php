@php $__iconV = 'v=' . config('app.icon_version'); @endphp
<link rel="icon" type="image/png" sizes="96x96" href="{{ url('/favicon-96x96.png') }}?{{ $__iconV }}">
<link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}?{{ $__iconV }}">
<link rel="shortcut icon" href="{{ url('/favicon.ico') }}?{{ $__iconV }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ url('/apple-touch-icon.png') }}?{{ $__iconV }}">
<meta name="apple-mobile-web-app-title" content="SAYZIO">
<link rel="manifest" href="{{ url('/site.webmanifest') }}?{{ $__iconV }}">
