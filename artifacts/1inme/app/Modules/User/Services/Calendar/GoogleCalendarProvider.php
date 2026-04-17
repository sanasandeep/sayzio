<?php

namespace App\Modules\User\Services\Calendar;

use App\Modules\User\Models\CalendarAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarProvider implements CalendarProvider
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE  = 'https://www.googleapis.com/calendar/v3';

    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    public function key(): string { return 'google'; }

    public function clientId(): string
    {
        return (string) config('services.google_calendar.client_id', env('GOOGLE_CALENDAR_CLIENT_ID', ''));
    }

    public function clientSecret(): string
    {
        return (string) config('services.google_calendar.client_secret', env('GOOGLE_CALENDAR_CLIENT_SECRET', ''));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authorizationUrl(string $stateToken, string $redirectUri): string
    {
        if (!$this->isConfigured()) {
            throw new CalendarSyncException('Google Calendar OAuth is not configured. Please set GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET.');
        }
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'              => $this->clientId(),
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'scope'                  => implode(' ', self::SCOPES),
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent',
            'state'                  => $stateToken,
        ]);
    }

    public function exchangeCode(int $userId, string $code, string $redirectUri): CalendarAccount
    {
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
        ]);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Google token exchange failed: ' . $resp->body());
        }
        $tok = $resp->json();

        // Get user email
        $email = null; $extId = null;
        $info = Http::withToken($tok['access_token'])->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if ($info->successful()) {
            $email = $info->json('email');
            $extId = $info->json('sub');
        }

        $account = CalendarAccount::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'google', 'account_email' => $email],
            [
                'display_name'        => $email ?: 'Google Calendar',
                'external_account_id' => $extId,
                'access_token'        => $tok['access_token'] ?? null,
                'refresh_token'       => $tok['refresh_token'] ?? null,
                'token_expires_at'    => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
                'scope'               => $tok['scope'] ?? implode(' ', self::SCOPES),
                'default_calendar_id' => 'primary',
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
            throw new CalendarSyncException('Google account has no refresh token — please reconnect.');
        }
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $account->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Google refresh failed: ' . $resp->body());
        }
        $tok = $resp->json();
        $account->update([
            'access_token'     => $tok['access_token'] ?? $account->access_token,
            'token_expires_at' => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
        ]);
    }

    public function listEvents(CalendarAccount $account, CarbonInterface $from, CarbonInterface $to): iterable
    {
        $this->refreshIfNeeded($account);
        $calId = $account->default_calendar_id ?: 'primary';
        $pageToken = null;
        do {
            $resp = Http::withToken($account->access_token)->get(self::API_BASE . '/calendars/' . urlencode($calId) . '/events', array_filter([
                'timeMin'      => $from->copy()->setTimezone('UTC')->toIso8601String(),
                'timeMax'      => $to->copy()->setTimezone('UTC')->toIso8601String(),
                'singleEvents' => 'true',
                'showDeleted'  => 'false',
                'maxResults'   => 250,
                'orderBy'      => 'startTime',
                'pageToken'    => $pageToken,
            ]));
            if (!$resp->successful()) {
                throw new CalendarSyncException('Google list failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['items'] ?? []) as $ev) {
                $payload = $this->normalize($ev, $calId);
                if ($payload) yield $payload;
            }
            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken);
    }

    public function createEvent(CalendarAccount $account, array $event): array
    {
        $this->refreshIfNeeded($account);
        $calId = $account->default_calendar_id ?: 'primary';
        $body  = $this->denormalize($event);
        $resp  = Http::withToken($account->access_token)
            ->post(self::API_BASE . '/calendars/' . urlencode($calId) . '/events', $body);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Google create failed: ' . $resp->body());
        }
        return [
            'external_event_id'    => $resp->json('id'),
            'external_calendar_id' => $calId,
            'etag'                 => trim((string) $resp->json('etag'), '"'),
            'ical_uid'             => $resp->json('iCalUID'),
        ];
    }

    public function updateEvent(CalendarAccount $account, string $externalEventId, array $event): array
    {
        $this->refreshIfNeeded($account);
        $calId = $account->default_calendar_id ?: 'primary';
        $body  = $this->denormalize($event);
        $resp  = Http::withToken($account->access_token)
            ->patch(self::API_BASE . '/calendars/' . urlencode($calId) . '/events/' . urlencode($externalEventId), $body);
        if (!$resp->successful()) {
            throw new CalendarSyncException('Google update failed: ' . $resp->body());
        }
        return [
            'external_event_id'    => $resp->json('id'),
            'external_calendar_id' => $calId,
            'etag'                 => trim((string) $resp->json('etag'), '"'),
            'ical_uid'             => $resp->json('iCalUID'),
        ];
    }

    public function deleteEvent(CalendarAccount $account, string $externalEventId): void
    {
        $this->refreshIfNeeded($account);
        $calId = $account->default_calendar_id ?: 'primary';
        $resp  = Http::withToken($account->access_token)
            ->delete(self::API_BASE . '/calendars/' . urlencode($calId) . '/events/' . urlencode($externalEventId));
        if (!$resp->successful() && $resp->status() !== 410 && $resp->status() !== 404) {
            throw new CalendarSyncException('Google delete failed: ' . $resp->body());
        }
    }

    /** Convert a Google event resource into our normalised payload. */
    private function normalize(array $ev, string $calId): ?array
    {
        if (($ev['status'] ?? '') === 'cancelled' || empty($ev['start'])) return null;

        $allDay = isset($ev['start']['date']);
        $tz     = $ev['start']['timeZone'] ?? ($ev['end']['timeZone'] ?? 'UTC');

        try {
            $start = $allDay
                ? Carbon::parse($ev['start']['date'], $tz)->startOfDay()
                : Carbon::parse($ev['start']['dateTime'])->setTimezone($tz);
            $end = $allDay
                ? Carbon::parse($ev['end']['date'] ?? $ev['start']['date'], $tz)->startOfDay()
                : Carbon::parse($ev['end']['dateTime'] ?? $ev['start']['dateTime'])->setTimezone($tz);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar normalize date parse failed', ['ev' => $ev['id'] ?? null]);
            return null;
        }

        $organizer = null;
        if (!empty($ev['organizer'])) {
            $organizer = [
                'name'  => $ev['organizer']['displayName'] ?? ($ev['organizer']['email'] ?? null),
                'email' => $ev['organizer']['email'] ?? null,
            ];
        }

        return [
            'external_event_id'    => $ev['id'],
            'external_calendar_id' => $calId,
            'ical_uid'             => $ev['iCalUID'] ?? null,
            'etag'                 => trim((string) ($ev['etag'] ?? ''), '"'),
            'summary'              => (string) ($ev['summary'] ?? '(no title)'),
            'description'          => $ev['description'] ?? null,
            'location'             => $ev['location'] ?? null,
            'start'                => $start,
            'end'                  => $end,
            'timezone'             => $tz,
            'all_day'              => $allDay,
            'url'                  => $ev['hangoutLink'] ?? ($ev['htmlLink'] ?? null),
            'organizer'            => $organizer,
            'recurrence'           => null, // singleEvents=true expands recurrences
            'updated_at'           => isset($ev['updated']) ? Carbon::parse($ev['updated']) : null,
        ];
    }

    /** Convert our normalised payload back into a Google event body. */
    private function denormalize(array $event): array
    {
        $allDay = !empty($event['all_day']);
        $tz     = $event['timezone'] ?? 'UTC';
        $start  = Carbon::parse($event['start'])->setTimezone($tz);
        $end    = Carbon::parse($event['end'])->setTimezone($tz);

        $body = [
            'summary'     => $event['summary'] ?? '(no title)',
            'description' => $event['description'] ?? null,
            'location'    => $event['location'] ?? null,
        ];
        if ($allDay) {
            $body['start'] = ['date' => $start->toDateString()];
            $body['end']   = ['date' => $end->toDateString()];
        } else {
            $body['start'] = ['dateTime' => $start->toRfc3339String(), 'timeZone' => $tz];
            $body['end']   = ['dateTime' => $end->toRfc3339String(),   'timeZone' => $tz];
        }
        if (!empty($event['url'])) {
            $body['source'] = ['title' => '1INME', 'url' => $event['url']];
        }
        return array_filter($body, fn($v) => $v !== null);
    }
}
