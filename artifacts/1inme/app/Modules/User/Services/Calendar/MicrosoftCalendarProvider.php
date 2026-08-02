<?php

namespace App\Modules\User\Services\Calendar;

use App\Modules\User\Models\CalendarAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Outlook / Microsoft 365 calendar provider backed by the Microsoft
 * Graph API. Mirrors {@see GoogleCalendarProvider} one-for-one: OAuth2 connect
 * (login.microsoftonline.com, "common" tenant), token refresh, and event CRUD
 * against https://graph.microsoft.com/v1.0/me/events.
 *
 * Credentials read from config('services.microsoft_calendar.*'), which
 * PlatformServiceSettings hydrates from either an admin-saved value or the
 * MICROSOFT_CALENDAR_* env fallback. Absent credentials => isConfigured()
 * returns false and the connect flow refuses gracefully (never 500).
 */
class MicrosoftCalendarProvider implements CalendarProvider
{
    private const API_BASE = 'https://graph.microsoft.com/v1.0';

    private const SCOPES = [
        'offline_access',
        'Calendars.ReadWrite',
        'User.Read',
        'openid',
        'email',
        'profile',
    ];

    public function key(): string { return 'microsoft'; }

    public function tenant(): string
    {
        $t = (string) config('services.microsoft_calendar.tenant', env('MICROSOFT_CALENDAR_TENANT', 'common'));
        return $t !== '' ? $t : 'common';
    }

    public function authBase(): string
    {
        return 'https://login.microsoftonline.com/' . $this->tenant() . '/oauth2/v2.0';
    }

    public function clientId(): string
    {
        return (string) config('services.microsoft_calendar.client_id', env('MICROSOFT_CALENDAR_CLIENT_ID', ''));
    }

    public function clientSecret(): string
    {
        return (string) config('services.microsoft_calendar.client_secret', env('MICROSOFT_CALENDAR_CLIENT_SECRET', ''));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authorizationUrl(string $stateToken, string $redirectUri): string
    {
        if (!$this->isConfigured()) {
            throw new CalendarSyncException('Microsoft Calendar OAuth is not configured. Please set MICROSOFT_CALENDAR_CLIENT_ID and MICROSOFT_CALENDAR_CLIENT_SECRET.');
        }
        return $this->authBase() . '/authorize?' . http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope'         => implode(' ', self::SCOPES),
            'prompt'        => 'consent',
            'state'         => $stateToken,
        ]);
    }

    public function exchangeCode(int $userId, string $code, string $redirectUri): CalendarAccount
    {
        $resp = Http::asForm()->post($this->authBase() . '/token', [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(' ', self::SCOPES),
        ]);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Microsoft token exchange failed: ' . $resp->body());
        }
        $tok = $resp->json();

        // Resolve the signed-in user's email + object id from Graph /me.
        $email = null; $extId = null; $displayName = null;
        $info = Http::withToken($tok['access_token'])->get(self::API_BASE . '/me');
        if ($info->successful()) {
            $email       = $info->json('mail') ?: $info->json('userPrincipalName');
            $extId       = $info->json('id');
            $displayName = $info->json('displayName');
        }

        $account = CalendarAccount::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'microsoft', 'account_email' => $email],
            [
                'display_name'        => $displayName ?: ($email ?: 'Microsoft Outlook'),
                'external_account_id' => $extId,
                'access_token'        => $tok['access_token'] ?? null,
                'refresh_token'       => $tok['refresh_token'] ?? null,
                'token_expires_at'    => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
                'scope'               => $tok['scope'] ?? implode(' ', self::SCOPES),
                'default_calendar_id' => null, // null => the user's default calendar (/me/events)
                'last_sync_status'    => null,
            ]
        );

        return $account->fresh();
    }

    public function refreshIfNeeded(CalendarAccount $account): void
    {
        if (!$account->token_expires_at || $account->token_expires_at->isFuture()) {
            return;
        }
        if (!$account->refresh_token) {
            throw new CalendarSyncException('Microsoft account has no refresh token — please reconnect.');
        }
        $resp = Http::asForm()->post($this->authBase() . '/token', [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $account->refresh_token,
            'grant_type'    => 'refresh_token',
            'scope'         => implode(' ', self::SCOPES),
        ]);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Microsoft refresh failed: ' . $resp->body());
        }
        $tok = $resp->json();
        $account->update([
            'access_token'     => $tok['access_token'] ?? $account->access_token,
            'refresh_token'    => $tok['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
        ]);
    }

    /** Base path for the events collection (default calendar or a named calendar). */
    private function eventsPath(CalendarAccount $account): string
    {
        $calId = $account->default_calendar_id;
        return $calId
            ? self::API_BASE . '/me/calendars/' . urlencode($calId) . '/events'
            : self::API_BASE . '/me/events';
    }

    public function listEvents(CalendarAccount $account, CarbonInterface $from, CarbonInterface $to): iterable
    {
        $this->refreshIfNeeded($account);
        $calId = $account->default_calendar_id;

        // calendarView expands recurrences into single instances within the window,
        // matching Google's singleEvents=true behaviour.
        $viewPath = $calId
            ? self::API_BASE . '/me/calendars/' . urlencode($calId) . '/calendarView'
            : self::API_BASE . '/me/calendarView';

        $url = $viewPath . '?' . http_build_query([
            'startDateTime' => $from->copy()->setTimezone('UTC')->toIso8601String(),
            'endDateTime'   => $to->copy()->setTimezone('UTC')->toIso8601String(),
            '$top'          => 250,
            '$orderby'      => 'start/dateTime',
        ]);

        do {
            $resp = Http::withToken($account->access_token)
                ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
                ->get($url);
            if (!$resp->successful()) {
                throw new CalendarSyncException('Microsoft list failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['value'] ?? []) as $ev) {
                $payload = $this->normalize($ev, (string) ($calId ?: 'default'));
                if ($payload) yield $payload;
            }
            $url = $data['@odata.nextLink'] ?? null;
        } while ($url);
    }

    public function createEvent(CalendarAccount $account, array $event): array
    {
        $this->refreshIfNeeded($account);
        $body = $this->denormalize($event);
        $resp = Http::withToken($account->access_token)
            ->post($this->eventsPath($account), $body);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Microsoft create failed: ' . $resp->body());
        }
        return [
            'external_event_id'    => $resp->json('id'),
            'external_calendar_id' => $account->default_calendar_id ?: 'default',
            'etag'                 => $this->cleanEtag($resp->json('@odata.etag')),
            'ical_uid'             => $resp->json('iCalUId'),
        ];
    }

    public function updateEvent(CalendarAccount $account, string $externalEventId, array $event): array
    {
        $this->refreshIfNeeded($account);
        $body = $this->denormalize($event);
        $resp = Http::withToken($account->access_token)
            ->patch(self::API_BASE . '/me/events/' . urlencode($externalEventId), $body);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Microsoft update failed: ' . $resp->body());
        }
        return [
            'external_event_id'    => $resp->json('id') ?: $externalEventId,
            'external_calendar_id' => $account->default_calendar_id ?: 'default',
            'etag'                 => $this->cleanEtag($resp->json('@odata.etag')),
            'ical_uid'             => $resp->json('iCalUId'),
        ];
    }

    public function deleteEvent(CalendarAccount $account, string $externalEventId): void
    {
        $this->refreshIfNeeded($account);
        $resp = Http::withToken($account->access_token)
            ->delete(self::API_BASE . '/me/events/' . urlencode($externalEventId));
        if (!$resp->successful() && $resp->status() !== 410 && $resp->status() !== 404) {
            throw new CalendarSyncException('Microsoft delete failed: ' . $resp->body());
        }
    }

    /** Convert a Graph event resource into our normalised payload. */
    private function normalize(array $ev, string $calId): ?array
    {
        if (($ev['isCancelled'] ?? false) === true || empty($ev['start'])) return null;

        $allDay = (bool) ($ev['isAllDay'] ?? false);
        // Graph returns start/end as {dateTime, timeZone}. With the Prefer header
        // above the timeZone is UTC unless the event carries its own.
        $tz = $ev['start']['timeZone'] ?? ($ev['end']['timeZone'] ?? 'UTC');
        // Graph uses "UTC" and Windows tz ids; Carbon understands IANA + UTC.
        if (strcasecmp($tz, 'UTC') === 0) $tz = 'UTC';

        try {
            $startRaw = $ev['start']['dateTime'] ?? null;
            $endRaw   = $ev['end']['dateTime'] ?? $startRaw;
            if (!$startRaw) return null;

            if ($allDay) {
                $start = Carbon::parse($startRaw, $tz)->startOfDay();
                $end   = Carbon::parse($endRaw, $tz)->startOfDay();
            } else {
                $start = Carbon::parse($startRaw, $tz);
                $end   = Carbon::parse($endRaw, $tz);
            }
        } catch (\Throwable $e) {
            Log::warning('MicrosoftCalendar normalize date parse failed', ['ev' => $ev['id'] ?? null]);
            return null;
        }

        $organizer = null;
        if (!empty($ev['organizer']['emailAddress'])) {
            $organizer = [
                'name'  => $ev['organizer']['emailAddress']['name']    ?? ($ev['organizer']['emailAddress']['address'] ?? null),
                'email' => $ev['organizer']['emailAddress']['address'] ?? null,
            ];
        }

        $description = null;
        if (!empty($ev['body']['content'])) {
            $description = ($ev['body']['contentType'] ?? 'text') === 'html'
                ? trim(html_entity_decode(strip_tags($ev['body']['content'])))
                : $ev['body']['content'];
        } elseif (!empty($ev['bodyPreview'])) {
            $description = $ev['bodyPreview'];
        }

        return [
            'external_event_id'    => $ev['id'],
            'external_calendar_id' => $calId,
            'ical_uid'             => $ev['iCalUId'] ?? null,
            'etag'                 => $this->cleanEtag($ev['@odata.etag'] ?? ''),
            'summary'              => (string) ($ev['subject'] ?? '(no title)'),
            'description'          => $description,
            'location'             => $ev['location']['displayName'] ?? null,
            'start'                => $start,
            'end'                  => $end,
            'timezone'             => $tz,
            'all_day'              => $allDay,
            'url'                  => $ev['onlineMeeting']['joinUrl'] ?? ($ev['webLink'] ?? null),
            'organizer'            => $organizer,
            'recurrence'           => null, // calendarView expands recurrences
            'updated_at'           => isset($ev['lastModifiedDateTime']) ? Carbon::parse($ev['lastModifiedDateTime']) : null,
        ];
    }

    /** Convert our normalised payload back into a Graph event body. */
    private function denormalize(array $event): array
    {
        $allDay = !empty($event['all_day']);
        $tz     = $event['timezone'] ?? 'UTC';
        $start  = Carbon::parse($event['start'])->setTimezone($tz);
        $end    = Carbon::parse($event['end'])->setTimezone($tz);

        $body = [
            'subject' => $event['summary'] ?? '(no title)',
            'isAllDay' => $allDay,
        ];
        if (!empty($event['description'])) {
            $body['body'] = ['contentType' => 'text', 'content' => (string) $event['description']];
        }
        if (!empty($event['location'])) {
            $body['location'] = ['displayName' => (string) $event['location']];
        }
        if ($allDay) {
            // Graph all-day events must start/end at midnight; end is exclusive.
            $s = $start->copy()->startOfDay();
            $e = $end->copy()->startOfDay();
            if ($e <= $s) $e = $s->copy()->addDay();
            $body['start'] = ['dateTime' => $s->toDateString() . 'T00:00:00', 'timeZone' => $tz];
            $body['end']   = ['dateTime' => $e->toDateString() . 'T00:00:00', 'timeZone' => $tz];
        } else {
            $body['start'] = ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $tz];
            $body['end']   = ['dateTime' => $end->format('Y-m-d\TH:i:s'),   'timeZone' => $tz];
        }
        return $body;
    }

    private function cleanEtag(?string $etag): ?string
    {
        if ($etag === null || $etag === '') return null;
        return trim($etag, '"');
    }
}
