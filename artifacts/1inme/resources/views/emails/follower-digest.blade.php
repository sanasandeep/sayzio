<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject ?? 'Your daily digest' }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                @include('common.partials.brand-logo-email')
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 8px 0;">
                @if(!empty($isExample))
                    Hi {{ $userName }}, here's an example of your daily digest
                @elseif(!empty($isSample))
                    Hi {{ $userName }}, here's a sample of your daily digest
                @else
                    Hi {{ $userName }}, here's your daily digest
                @endif
            </h1>
            @if(!empty($isExample))
                <p style="font-size:14px; color:#64748b; line-height:1.6; margin:0 0 24px 0;">
                    You don't have any pending creator updates yet, so this is an example using made-up creators to show what your digest will look like.
                </p>
            @elseif(!empty($isSample) && $totalUpdates === 0)
                <p style="font-size:14px; color:#64748b; line-height:1.6; margin:0 0 24px 0;">
                    You don't have any new creator updates waiting right now. When creators you follow post something, it'll show up here in your next digest.
                </p>
            @else
                <p style="font-size:14px; color:#64748b; line-height:1.6; margin:0 0 24px 0;">
                    {{ !empty($isSample) ? 'This is a preview using your ' : '' }}{{ $totalUpdates }} update{{ $totalUpdates === 1 ? '' : 's' }} from
                    {{ $creatorCount }} creator{{ $creatorCount === 1 ? '' : 's' }} {{ empty($isSample) ? 'you follow since your last digest' : 'currently waiting in your queue' }}.
                </p>
            @endif

            @foreach($creators as $c)
                <div style="border-top:1px solid #e2e8f0; padding:20px 0;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                        <tr>
                            <td style="width:56px; vertical-align:top; padding-right:12px;">
                                @if(!empty($c['avatar']))
                                    <img src="{{ $c['avatar'] }}" width="48" height="48" alt=""
                                         style="width:48px; height:48px; border-radius:9999px; object-fit:cover; display:block; background:#e2e8f0;">
                                @else
                                    <div style="width:48px; height:48px; border-radius:9999px; background:#dbeafe; color:#2563eb; font-weight:700; font-size:18px; line-height:48px; text-align:center;">
                                        {{ strtoupper(mb_substr($c['name'], 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td style="vertical-align:top;">
                                <div style="font-size:15px; font-weight:600; color:#1e293b; margin-bottom:6px;">
                                    @if(!empty($c['url']))
                                        <a href="{{ $c['url'] }}" style="color:#1e293b; text-decoration:none;">{{ $c['name'] }}</a>
                                    @else
                                        {{ $c['name'] }}
                                    @endif
                                </div>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 12px 0;">
                                    @foreach($c['messages'] as $m)
                                        @php
                                            $text = is_array($m) ? ($m['text'] ?? '') : $m;
                                            $img  = is_array($m) ? ($m['image'] ?? null) : null;
                                        @endphp
                                        <tr>
                                            @if(!empty($img))
                                                <td style="width:64px; vertical-align:top; padding:4px 10px 4px 0;">
                                                    @if(!empty($c['url']))
                                                        <a href="{{ $c['url'] }}" style="text-decoration:none;">
                                                            <img src="{{ $img }}" width="56" height="56" alt=""
                                                                 style="width:56px; height:56px; border-radius:6px; object-fit:cover; display:block; background:#e2e8f0;">
                                                        </a>
                                                    @else
                                                        <img src="{{ $img }}" width="56" height="56" alt=""
                                                             style="width:56px; height:56px; border-radius:6px; object-fit:cover; display:block; background:#e2e8f0;">
                                                    @endif
                                                </td>
                                            @endif
                                            <td style="vertical-align:top; padding:4px 0; color:#334155; font-size:14px; line-height:1.6;">
                                                @if(!empty($c['url']))
                                                    <a href="{{ $c['url'] }}" style="color:#2563eb; text-decoration:none;">{{ $text }}</a>
                                                @else
                                                    {{ $text }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if(!empty($c['extra']) && $c['extra'] > 0)
                                        <tr>
                                            <td colspan="2" style="padding:4px 0; color:#64748b; font-size:13px; font-style:italic;">
                                                …and {{ $c['extra'] }} more update{{ $c['extra'] === 1 ? '' : 's' }}
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                                @if(!empty($c['url']))
                                    <a href="{{ $c['url'] }}"
                                       style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:8px 18px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600;">
                                        Visit {{ $c['name'] }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving the daily digest from 1INME. To switch to instant emails or turn this off, visit your profile notification settings.
            </p>
        </div>
    </div>
</body>
</html>
