<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;">

    <div style="padding:20px 24px;background:linear-gradient(135deg,#3d6bff,#db2777);color:#fff;">
        <div style="font-size:11px;letter-spacing:.12em;opacity:.85;text-transform:uppercase;">{{ $isSample ? 'Sample preview' : 'Your week on Sayzio' }}</div>
        <div style="font-size:22px;font-weight:800;margin-top:4px;">Hi {{ $creator->name }},</div>
        <div style="font-size:13px;opacity:.9;margin-top:2px;">{{ $periodStart }} – {{ $periodEnd }}</div>
    </div>

    <div style="padding:24px;">
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:16px;">
            <tr>
                <td align="center" style="padding:14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <div style="font-size:24px;font-weight:800;color:#3d6bff;">+{{ number_format($newFollowers) }}</div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">New followers</div>
                </td>
                <td width="8"></td>
                <td align="center" style="padding:14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <div style="font-size:24px;font-weight:800;color:#db2777;">+{{ number_format($newSubscribers) }}</div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">New subscribers</div>
                </td>
                <td width="8"></td>
                <td align="center" style="padding:14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <div style="font-size:24px;font-weight:800;color:#0ea5e9;">${{ number_format($unlockRevenueCents/100, 2) }}</div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">Unlock revenue</div>
                </td>
            </tr>
        </table>

        @if($newPosts->count())
            <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin:18px 0 8px;">Your posts this week</h3>
            <ul style="padding-left:18px;margin:0;">
                @foreach($newPosts as $p)
                    <li style="margin-bottom:6px;font-size:14px;color:#0f172a;">
                        <strong>{{ $p->title ?: \Illuminate\Support\Str::limit($p->body, 60) }}</strong>
                        <span style="color:#64748b;font-size:12px;"> — {{ optional($p->published_at)->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div style="margin-top:24px;text-align:center;">
            <a href="{{ $statsUrl }}" style="display:inline-block;padding:10px 18px;background:#3d6bff;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:13px;">Open your Stats home</a>
        </div>
        <div style="margin-top:8px;text-align:center;">
            <a href="{{ $profileUrl }}" style="font-size:12px;color:#64748b;text-decoration:underline;">View your profile</a>
        </div>
    </div>
</div>
</body></html>
