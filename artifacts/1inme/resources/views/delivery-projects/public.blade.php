<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $project->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #0f172a; }
        .dp-wrap { max-width: 880px; margin: 0 auto; padding: 32px 16px 64px; }
        .dp-head { margin-bottom: 20px; }
        .dp-head h1 { font-size: 24px; margin: 0 0 4px; }
        .dp-head p { margin: 0; color: #64748b; font-size: 14px; }
        .dp-status { display: inline-block; margin-top: 8px; padding: 3px 10px; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 12px; font-weight: 600; }
        .dp-foot { margin-top: 24px; text-align: center; color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="dp-wrap">
        <div class="dp-head">
            <h1>{{ $project->title }}</h1>
            <p>Shared by {{ optional($project->creator)->name ?? config('app.name') }}</p>
            <span class="dp-status">{{ $project->statusLabel() }}</span>
        </div>

        @if($project->description)
            <p style="color:#475569;font-size:14px;margin-bottom:16px;">{{ $project->description }}</p>
        @endif

        @include('delivery-projects._readonly', ['project' => $project])

        <div class="dp-foot">This is a read-only project status page.</div>
    </div>
</body>
</html>
