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
        .dp-comments { margin-top: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
        .dp-comments .dp-card-title { font-size: 14px; font-weight: 700; margin: 0 0 4px; color: #0f172a; }
        .dp-comments-sub { font-size: 12px; color: #94a3b8; margin: 0 0 12px; }
        .dp-flash { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #dcfce7; color: #16a34a; font-size: 13px; }
        .dp-flash-err { background: #fee2e2; color: #dc2626; }
        .dp-thread { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
        .dp-msg { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; background: #f8fafc; }
        .dp-msg-team { background: #eff6ff; }
        .dp-msg-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .dp-msg-author { font-size: 12px; font-weight: 600; color: #0f172a; }
        .dp-msg-team .dp-msg-author { color: #3d6bff; }
        .dp-msg-time { font-size: 11px; color: #94a3b8; }
        .dp-msg-body { font-size: 13px; color: #334155; white-space: pre-line; }
        .dp-empty { color: #94a3b8; font-size: 13px; }
        .dp-form { display: flex; flex-direction: column; gap: 8px; }
        .dp-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; color: #0f172a; font-family: inherit; }
        .dp-btn { align-self: flex-start; border: 0; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; color: #fff; background: #3d6bff; cursor: pointer; }
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

        {{-- Ask a question / comment thread --}}
        <div class="dp-card dp-comments">
            <h3 class="dp-card-title">Questions &amp; comments</h3>
            <p class="dp-comments-sub">Have a question about your order? Send the team a message.</p>

            @if(session('success'))
                <div class="dp-flash">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="dp-flash dp-flash-err">{{ $errors->first() }}</div>
            @endif

            <div class="dp-thread">
                @forelse($project->comments as $comment)
                    <div class="dp-msg {{ $comment->isTeam() ? 'dp-msg-team' : 'dp-msg-client' }}">
                        <div class="dp-msg-head">
                            <span class="dp-msg-author">{{ $comment->displayName() }} · {{ $comment->isTeam() ? 'Team' : 'Customer' }}</span>
                            <span class="dp-msg-time">{{ optional($comment->created_at)->diffForHumans() }}</span>
                        </div>
                        <div class="dp-msg-body">{{ $comment->body }}</div>
                    </div>
                @empty
                    <p class="dp-empty">No messages yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('delivery-project.share.comment', $project->share_token) }}" class="dp-form">
                @csrf
                <input name="author_name" maxlength="120" placeholder="Your name (optional)" class="dp-input" value="{{ old('author_name') }}">
                <textarea name="body" required maxlength="2000" rows="3" placeholder="Type your question…" class="dp-input">{{ old('body') }}</textarea>
                <button type="submit" class="dp-btn">Send message</button>
            </form>
        </div>

        <div class="dp-foot">This is a live project status page. You can follow progress and send messages to the team here.</div>
    </div>
</body>
</html>
