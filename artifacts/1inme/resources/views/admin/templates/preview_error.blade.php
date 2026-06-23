<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview unavailable — {{ $tpl->name }}</title>
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0d0818; color: #e9e4f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .box { max-width: 30rem; padding: 2rem; text-align: center; }
        .icon { font-size: 2.25rem; color: #f87171; margin-bottom: 1rem; }
        h1 { font-size: 1.05rem; margin: 0 0 .5rem; }
        p { font-size: .85rem; color: rgba(233,228,245,.6); line-height: 1.5; }
        .block { margin-top: 1rem; padding: .75rem; border-radius: .5rem; background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); color: #fca5a5; font-size: .8rem; text-align: left; }
        .block strong { color: #fecaca; }
        code { display: block; margin-top: 1rem; padding: .75rem; border-radius: .5rem; background: rgba(255,255,255,.06); color: #fca5a5; font-size: .75rem; word-break: break-word; text-align: left; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">&#9888;</div>
        <h1>This template can&rsquo;t be previewed</h1>

        @if (($reason ?? null) === 'unknown_block')
            <p>The snapshot for &ldquo;{{ $tpl->name }}&rdquo; uses a block type the renderer doesn&rsquo;t recognise. Open the design-fix tools to repair it (re-capture from a source page), then preview again.</p>
            <div class="block">
                Offending block: <strong>{{ $position }}</strong> &mdash;
                @if ($blockType === '')
                    its <strong>type is missing</strong>.
                @else
                    unknown type <strong>&ldquo;{{ $blockType }}&rdquo;</strong>.
                @endif
            </div>
        @else
            <p>The snapshot for &ldquo;{{ $tpl->name }}&rdquo; failed to render. This isn&rsquo;t an unknown block type &mdash; a block&rsquo;s settings or content broke the renderer. Open the design-fix tools to inspect and repair it, then preview again.</p>
            <code>{{ $message }}</code>
        @endif
    </div>
</body>
</html>
