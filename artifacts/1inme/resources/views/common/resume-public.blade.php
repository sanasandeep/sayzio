@php
    /** @var \App\Modules\User\Models\Resume $resume */
    /** @var \App\Modules\User\Models\User $user */
    $h        = $resume->getMergedSections()['header'] ?? [];
    $summary  = (string) ($resume->getMergedSections()['summary'] ?? '');
    $name     = trim($h['name'] ?? '') ?: $user->name;
    $headline = trim($h['headline'] ?? '');
    $title    = $name . ($headline ? ' — '.$headline : "'s resume");
    $description = trim($resume->meta_description ?? '') ?: ($headline ?: \Illuminate\Support\Str::limit($summary, 200));
    $publicUrl = url('/' . $user->publicHandle() . '/resume');
    $avatar    = $user->avatar ?? null;
    $allowIndex = (bool) ($resume->allow_indexing ?? true) && (($resume->visibility ?? 'public') === 'public');
    $locked = ($resume->visibility ?? 'public') === 'password'
        && !empty($resume->password)
        && !session("resume_unlocked_{$resume->id}")
        && empty($isOwner);

    // JSON-LD Person object so Google + LinkedIn surface a rich card.
    $person = [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => $name,
        'url'      => $publicUrl,
    ];
    if ($headline)             $person['jobTitle']    = $headline;
    if (!empty($h['email']))   $person['email']       = $h['email'];
    if (!empty($h['phone']))   $person['telephone']   = $h['phone'];
    if (!empty($h['website'])) $person['sameAs']      = [$h['website']];
    if ($avatar)               $person['image']       = $avatar;
    if (!empty($h['location'])) $person['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $h['location']];
    if ($summary)              $person['description'] = \Illuminate\Support\Str::limit($summary, 500);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if (!$allowIndex)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="canonical" href="{{ $publicUrl }}">

    {{-- Open Graph + Twitter --}}
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:url" content="{{ $publicUrl }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    @if ($avatar)<meta property="og:image" content="{{ $avatar }}">@endif
    <meta name="twitter:card" content="{{ $avatar ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if ($avatar)<meta name="twitter:image" content="{{ $avatar }}">@endif

    <script type="application/ld+json">{!! json_encode($person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: #f3f4f6;
            font-family: 'Inter','Helvetica Neue',Arial,sans-serif;
            min-height: 100vh;
            color: #111827;
        }
        .resume-page-wrap { max-width: 880px; margin: 0 auto; padding: 32px 16px 64px; }
        .resume-paper {
            background: #fff; border-radius: 16px; overflow: hidden;
            box-shadow: 0 12px 40px rgba(15,23,42,0.10);
        }
        .resume-render { padding: 36px 40px; min-height: 600px; line-height: 1.55; }
        .resume-render.tight { font-size: 12px; line-height: 1.4; padding: 28px 32px; }
        .resume-render.spacious { font-size: 14px; line-height: 1.65; padding: 40px 44px; }
        .resume-render.serif { font-family: Georgia,'Times New Roman',serif; }
        .resume-render.display { font-family: 'Plus Jakarta Sans','Inter',sans-serif; }
        .pv-name { font-size: 26px; font-weight: 800; margin: 0 0 4px; line-height: 1.1; }
        .pv-headline { font-size: 13px; margin: 0 0 6px; }
        .pv-contact { font-size: 11px; opacity: 0.85; display: flex; flex-wrap: wrap; gap: 4px 10px; margin: 6px 0 16px; }
        .pv-contact span+span::before { content: '·'; margin-right: 10px; opacity: 0.5; }
        .pv-section { margin-top: 18px; }
        .pv-section h2 { font-size: 11px; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1.5px solid currentColor; }
        .pv-item { margin-bottom: 12px; }
        .pv-item-row { display:flex; align-items:baseline; justify-content:space-between; gap: 10px; }
        .pv-item-title { font-size: 13px; font-weight: 700; }
        .pv-item-sub { font-size: 12px; }
        .pv-item-meta { font-size: 11px; opacity: 0.75; white-space: nowrap; }
        .pv-item-desc { font-size: 12px; margin-top: 4px; white-space: pre-wrap; }
        .pv-summary { font-size: 12.5px; line-height: 1.6; white-space: pre-wrap; }
        .pv-skill-row { display:flex; flex-wrap:wrap; gap: 6px; }
        .pv-skill-pill { padding: 3px 9px; border-radius: 999px; font-size: 11px; border: 1px solid currentColor; }
        .pv-link { font-size: 12px; text-decoration: underline; word-break: break-all; }
        .pv-sidebar { display: grid; grid-template-columns: 200px 1fr; gap: 22px; }
        .pv-sidebar > .pv-side-col { border-right: 1px solid rgba(0,0,0,0.08); padding-right: 18px; }
        .pv-portfolio-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pv-portfolio-card { border: 1px solid currentColor; border-radius: 8px; padding: 10px; }
        .resume-render.compact { padding: 18px 20px; }
        @media (max-width: 640px) {
            .resume-render, .resume-render.tight, .resume-render.spacious { padding: 22px 18px; }
            .pv-sidebar { grid-template-columns: 1fr; }
            .pv-sidebar > .pv-side-col { border-right: none; border-bottom: 1px solid rgba(0,0,0,0.08); padding: 0 0 14px; }
            .pv-portfolio-grid { grid-template-columns: 1fr; }
        }
        .resume-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; flex-wrap: wrap;
            background: rgba(15,23,42,0.04); border-radius: 12px;
            padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #475569;
        }
        .resume-toolbar a, .resume-toolbar button {
            display: inline-flex; align-items: center; gap: 6px;
            color: #4338ca; text-decoration: none; font-weight: 600;
            background: none; border: none; cursor: pointer; font-size: 12px;
        }
        .resume-toolbar a:hover, .resume-toolbar button:hover { text-decoration: underline; }
        .resume-locked-card {
            max-width: 420px; margin: 80px auto; background: #fff;
            border-radius: 16px; padding: 28px; text-align: center;
            box-shadow: 0 12px 40px rgba(15,23,42,0.10);
        }
        .resume-locked-card input {
            width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0;
            border-radius: 10px; font-size: 14px; margin: 12px 0;
        }
        .resume-locked-card button {
            width: 100%; padding: 11px; border: none; border-radius: 10px;
            background: #4f46e5; color: #fff; font-weight: 600; cursor: pointer;
        }
        .resume-owner-banner {
            background: rgba(124,58,237,0.10); color: #5b21b6; border-radius: 12px;
            padding: 10px 14px; margin-bottom: 14px; font-size: 12px;
            display: flex; align-items: center; gap: 8px;
        }
    </style>
    @include('common.partials.resume-styles')
</head>
<body>
<main class="resume-page-wrap">
    @if (!empty($isOwner))
        <div class="resume-owner-banner">
            <i class="fas fa-eye"></i>
            <span>You're viewing your own resume page.
                @if (!$resume->is_public)
                    It's currently <strong>not public</strong> — only you can see it.
                @endif
                <a href="{{ route('user.resume.editor') }}" style="color:#5b21b6; font-weight:700; margin-left:4px;">Manage</a>
            </span>
        </div>
    @endif

    @if ($locked)
        <div class="resume-locked-card">
            <i class="fas fa-lock" style="font-size: 28px; color:#6366f1;"></i>
            <h1 style="margin: 12px 0 4px; font-size: 18px;">{{ $name }}'s resume is password-protected</h1>
            <p style="font-size: 12px; color: #64748b; margin: 0 0 8px;">Enter the password the owner shared with you.</p>
            <form method="POST" action="{{ url('/' . $user->publicHandle() . '/resume') }}">
                @csrf
                <input type="password" name="password" placeholder="Password" autofocus required>
                @if (!empty($lockedError))
                    <p style="color:#dc2626; font-size:12px; margin: -4px 0 8px;">{{ $lockedError }}</p>
                @endif
                <button type="submit"><i class="fas fa-unlock-alt"></i> Unlock</button>
            </form>
        </div>
    @else
        <div class="resume-toolbar">
            <span><i class="fas fa-file-lines"></i> {{ $name }}'s resume</span>
            <span style="display:inline-flex; gap:14px;">
                <button type="button" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                @php $primaryBio = $user->primaryBiolink(); @endphp
                @if ($primaryBio)
                    <a href="{{ url('/' . $primaryBio->alias) }}"><i class="fas fa-link"></i> Bio link</a>
                @endif
            </span>
        </div>

        <div class="resume-paper">
            @include('common.partials.resume-render', ['resume' => $resume])
        </div>
    @endif

    <p style="text-align:center; font-size: 11px; color:#94a3b8; margin-top: 28px;">
        Powered by <a href="{{ url('/') }}" style="color:#94a3b8;">1INME</a>
    </p>
</main>
</body>
</html>
