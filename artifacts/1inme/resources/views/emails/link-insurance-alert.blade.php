<!doctype html>
<html><body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; max-width: 560px; margin: 0 auto; padding: 24px;">

@if ($type === 'link_restored')
    <h2 style="margin: 0 0 16px;">Your primary destination is back</h2>
    <p>Good news — the primary destination for <strong>{{ $shortUrl }}</strong> is responding again, so Link Insurance has automatically restored it.</p>
    <p style="color: #555;">Restored URL: {{ $payload['restored_url'] ?? $link->long_url }}</p>
@else
    <h2 style="margin: 0 0 16px;">Link Insurance triggered for {{ $shortUrl }}</h2>
    @if (($payload['reason'] ?? null) === 'all_destinations_down')
        <p>Heads up — both your primary destination <em>and</em> every backup URL on <strong>{{ $shortUrl }}</strong> failed our health checks.</p>
        <p style="color: #b91c1c;">Visitors are still being sent to your last-known destination, but you should add a working backup as soon as possible.</p>
    @else
        <p>Your primary destination for <strong>{{ $shortUrl }}</strong> failed our health checks, so Link Insurance promoted backup #{{ $payload['position'] ?? 1 }} to keep your traffic flowing.</p>
        <p style="color: #555;">
            New destination: {{ $payload['new_url'] ?? '' }}<br>
            @if (!empty($payload['previous_url']))
                Previous destination: {{ $payload['previous_url'] }}<br>
            @endif
        </p>
    @endif

    {{-- Diagnosis block — surface the probe result verbatim so the
         user can see WHY we cut over (HTTP status / connection error)
         without having to dig into the dashboard. --}}
    @if (!empty($payload['http_code']) || !empty($payload['error_class']) || !empty($payload['error_detail']))
        <p style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 12px; color: #78350f; font-size: 13px; margin: 16px 0;">
            <strong>Diagnosis:</strong>
            @if (!empty($payload['http_code']))
                primary returned HTTP {{ $payload['http_code'] }}@if (!empty($payload['error_class'])) ({{ $payload['error_class'] }})@endif.
            @elseif (!empty($payload['error_class']))
                {{ $payload['error_class'] }}@if (!empty($payload['error_detail'])) — {{ $payload['error_detail'] }}@endif.
            @else
                {{ $payload['error_detail'] }}
            @endif
        </p>
    @endif
@endif

<p style="margin-top: 24px;">
    @if ($type !== 'link_restored')
        {{-- Both action URLs are signed (30-day expiry) so a
             logged-in recipient can't be CSRF'd into mutating state
             by simply opening a malicious page. --}}
        <a href="{{ \URL::temporarySignedRoute('user.links.insurance.restore-action', now()->addDays(30), ['link' => $link->id]) }}"
           style="display:inline-block; padding: 10px 16px; background: #16a34a; color: #fff; text-decoration: none; border-radius: 6px; margin-right: 8px; margin-bottom: 8px;">
            Restore primary now
        </a>
        <a href="{{ \URL::temporarySignedRoute('user.links.insurance.promote-next', now()->addDays(30), ['link' => $link->id]) }}"
           style="display:inline-block; padding: 10px 16px; background: #f59e0b; color: #fff; text-decoration: none; border-radius: 6px; margin-right: 8px; margin-bottom: 8px;">
            Promote next backup
        </a>
    @endif
    <a href="{{ route('user.links.insurance.settings', $link->id) }}"
       style="display:inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px;">
        Manage Link Insurance
    </a>
</p>

<p style="margin-top: 32px; color: #999; font-size: 12px;">
    You're receiving this because Link Insurance email alerts are on. You can mute this category from your notification preferences.
</p>
</body></html>
