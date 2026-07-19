<!DOCTYPE html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="UTF-8">
    <title>Domain not connected</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
               background:linear-gradient(135deg,#0a0612,#1a0533); color:#fff; padding:20px; }
        .card { max-width:480px; text-align:center; padding:40px; border-radius:16px;
                background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
        h1 { font-size:20px; margin:0 0 12px; }
        p  { font-size:14px; color:rgba(255,255,255,0.6); line-height:1.5; }
        code { background:rgba(61,107,255,0.15); padding:2px 8px; border-radius:6px; color:#bccfff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Domain not connected</h1>
        <p>The host <code>{{ $host }}</code> isn't pointed at this short-link service yet, or hasn't been verified.</p>
        <p>If you own this domain, log in and complete CNAME verification under <strong>Domains</strong>.</p>
    </div>
</body>
</html>
