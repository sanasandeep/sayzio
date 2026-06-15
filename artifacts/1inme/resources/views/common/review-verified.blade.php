@php
    $title = $link->title ?: $link->alias;
    $already = $already ?? false;
    $pending = $pending ?? false;
    $backUrl = url('/' . $link->alias);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review verified — {{ $title }}</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0b0b13; --card:#15151f; --line:rgba(255,255,255,.08); --ink:#f4f4f8; --muted:#9aa0ad; --accent:#8b5cf6; --ok:#34d399; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Space Grotesk',system-ui,sans-serif; background:radial-gradient(1200px 600px at 50% -10%, #1c1430 0%, var(--bg) 60%); color:var(--ink); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:22px; padding:36px 30px; max-width:460px; text-align:center; }
        .badge { width:64px; height:64px; border-radius:50%; background:rgba(52,211,153,.14); color:var(--ok); display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 18px; }
        h1 { font-size:22px; margin:0 0 10px; }
        p { color:var(--muted); margin:0 0 22px; line-height:1.55; font-size:15px; }
        .btn { display:inline-block; background:var(--accent); color:#fff; border-radius:12px; padding:11px 20px; font-weight:600; font-size:14px; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">&#10003;</div>
        @if($already)
            <h1>You're all set</h1>
            <p>This review has already been confirmed. Thanks for verifying — there's nothing more to do.</p>
        @elseif($pending)
            <h1>Email confirmed</h1>
            <p>Thanks! Your review is verified and has been sent to {{ $title }} for approval. It'll appear with a “Verified customer” badge once approved.</p>
        @else
            <h1>Review published</h1>
            <p>Thanks! Your review is verified and now live on {{ $title }} with a “Verified customer” badge.</p>
        @endif
        <a class="btn" href="{{ $backUrl }}">Back to {{ $title }}</a>
    </div>
</body>
</html>
