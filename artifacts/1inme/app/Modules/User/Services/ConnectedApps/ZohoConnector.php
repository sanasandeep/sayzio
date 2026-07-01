<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;
use Illuminate\Support\Facades\Http;

/**
 * Zoho CRM connector. Pushes leads into the Leads module and pulls Contacts
 * back using the incremental modified-since header. Zoho uses a bespoke
 * "Zoho-oauthtoken" Authorization scheme and returns the API domain
 * (api_domain) on token exchange, stored as the connection instance_url.
 */
class ZohoConnector extends AbstractOAuthConnector
{
    protected function providerKey(): string
    {
        return 'zoho';
    }

    protected function extraAuthParams(): array
    {
        return ['access_type' => 'offline'];
    }

    protected function afterExchange(ConnectedApp $conn, array $tok): void
    {
        if (empty($conn->instance_url)) {
            $conn->instance_url = 'https://www.zohoapis.com';
        }
        try {
            $resp = Http::withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $conn->access_token])
                ->get($conn->instance_url . '/crm/v3/users?type=CurrentUser');
            if ($resp->successful()) {
                $user = $resp->json('users.0') ?? [];
                $conn->account_email       = $user['email'] ?? null;
                $conn->account_label       = $user['full_name'] ?? null;
                $conn->external_account_id = isset($user['id']) ? (string) $user['id'] : null;
            }
        } catch (\Throwable $e) {
            // best-effort identity
        }
    }

    public function pushContact(ConnectedApp $conn, array $lead): array
    {
        $fields = $this->mapLead($conn, $lead);
        if (empty($fields['Last_Name'])) {
            $fields['Last_Name'] = $lead['display_name'] ?: ($lead['last_name'] ?: ($lead['email'] ?: 'Lead'));
        }
        if (empty($fields['Company'])) {
            $fields['Company'] = $lead['company'] ?: 'Sayzio Lead';
        }
        $fields['Lead_Source'] = 'Sayzio';

        $resp = $this->zoho($conn)->post($conn->instance_url . '/crm/v3/Leads', ['data' => [$fields]]);
        if (!$resp->successful()) {
            throw new ConnectedAppException('Zoho lead push failed: ' . $resp->body());
        }
        return ['id' => $resp->json('data.0.details.id')];
    }

    public function pullContacts(ConnectedApp $conn, ?callable $onCursor = null): iterable
    {
        $this->refreshIfNeeded($conn);
        $cursor   = $conn->pull_cursor;
        $newSince = now()->toIso8601String();
        $page     = 1;

        do {
            $req = $this->zoho($conn)
                ->withQueryParameters([
                    'fields'   => 'First_Name,Last_Name,Email,Phone,Title,Account_Name',
                    'per_page' => 200,
                    'page'     => $page,
                ]);
            if ($cursor) {
                $req = $req->withHeaders(['If-Modified-Since' => $cursor]);
            }
            $resp = $req->get($conn->instance_url . '/crm/v3/Contacts');
            if ($resp->status() === 304 || $resp->status() === 204) {
                break; // nothing modified
            }
            if (!$resp->successful()) {
                throw new ConnectedAppException('Zoho contacts pull failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['data'] ?? []) as $rec) {
                yield $this->normalize($rec);
            }
            $more = $data['info']['more_records'] ?? false;
            $page++;
        } while ($more && $page < 10);

        if ($onCursor) {
            $onCursor($newSince);
        }
    }

    private function zoho(ConnectedApp $conn)
    {
        $this->refreshIfNeeded($conn);
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $conn->access_token])->acceptJson();
    }

    private function normalize(array $rec): array
    {
        $email = $rec['Email'] ?? null;
        $phone = $rec['Phone'] ?? null;
        $acct  = $rec['Account_Name']['name'] ?? ($rec['Account_Name'] ?? null);
        return [
            'external_id'  => isset($rec['id']) ? (string) $rec['id'] : null,
            'display_name' => trim((($rec['First_Name'] ?? '') . ' ' . ($rec['Last_Name'] ?? ''))) ?: ($email ?: $phone),
            'given_name'   => $rec['First_Name'] ?? null,
            'family_name'  => $rec['Last_Name'] ?? null,
            'organization' => is_string($acct) ? $acct : null,
            'job_title'    => $rec['Title'] ?? null,
            'notes'        => null,
            'photo_url'    => null,
            'phones'       => $phone ? [['label' => 'work', 'value' => $phone, 'primary' => true]] : [],
            'emails'       => $email ? [['label' => 'work', 'value' => $email, 'primary' => true]] : [],
        ];
    }
}
