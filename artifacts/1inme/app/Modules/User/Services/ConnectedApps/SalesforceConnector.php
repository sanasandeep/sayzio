<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;

/**
 * Salesforce REST connector. Pushes captured leads as Salesforce Lead
 * records and pulls Salesforce Contacts back into Sayzio using SOQL with an
 * incremental LastModifiedDate cursor.
 */
class SalesforceConnector extends AbstractOAuthConnector
{
    private const API_VERSION = 'v59.0';

    protected function providerKey(): string
    {
        return 'salesforce';
    }

    protected function afterExchange(ConnectedApp $conn, array $tok): void
    {
        // Salesforce returns an `id` identity URL we can hit for the username.
        if (empty($tok['id']) || empty($tok['access_token'])) {
            return;
        }
        try {
            $info = \Illuminate\Support\Facades\Http::withToken($tok['access_token'])->get($tok['id']);
            if ($info->successful()) {
                $conn->account_email      = $info->json('email');
                $conn->account_label      = $info->json('display_name') ?? $info->json('username');
                $conn->external_account_id = $info->json('user_id');
            }
        } catch (\Throwable $e) {
            // best-effort identity
        }
    }

    public function pushContact(ConnectedApp $conn, array $lead): array
    {
        $fields = $this->mapLead($conn, $lead);
        // Salesforce Lead requires LastName + Company.
        if (empty($fields['LastName'])) {
            $fields['LastName'] = $lead['display_name'] ?: ($lead['last_name'] ?: ($lead['email'] ?: 'Lead'));
        }
        if (empty($fields['Company'])) {
            $fields['Company'] = $lead['company'] ?: 'Sayzio Lead';
        }
        $fields['LeadSource'] = 'Sayzio';

        $resp = $this->client($conn)
            ->post($this->base($conn) . '/sobjects/Lead', $fields);
        if (!$resp->successful()) {
            throw new ConnectedAppException('Salesforce lead push failed: ' . $resp->body());
        }
        return ['id' => $resp->json('id')];
    }

    public function pullContacts(ConnectedApp $conn, ?callable $onCursor = null): iterable
    {
        $cursor  = $conn->pull_cursor;
        $newSince = now()->toIso8601String();

        $soql = 'SELECT Id,FirstName,LastName,Email,Phone,Title,Account.Name,LastModifiedDate FROM Contact';
        if ($cursor) {
            $soql .= ' WHERE LastModifiedDate > ' . $cursor;
        }
        $soql .= ' ORDER BY LastModifiedDate ASC LIMIT 500';

        $url = $this->base($conn) . '/query?q=' . urlencode($soql);
        $pages = 0;
        do {
            $resp = $this->client($conn)->get($url);
            if (!$resp->successful()) {
                throw new ConnectedAppException('Salesforce query failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['records'] ?? []) as $rec) {
                yield $this->normalize($rec);
            }
            $next = $data['nextRecordsUrl'] ?? null;
            $url  = $next ? (rtrim($conn->instance_url, '/') . $next) : null;
            $pages++;
        } while ($url && $pages < 10);

        if ($onCursor) {
            $onCursor($newSince);
        }
    }

    private function base(ConnectedApp $conn): string
    {
        return rtrim((string) $conn->instance_url, '/') . '/services/data/' . self::API_VERSION;
    }

    private function normalize(array $rec): array
    {
        $email = $rec['Email'] ?? null;
        $phone = $rec['Phone'] ?? null;
        return [
            'external_id'  => $rec['Id'] ?? null,
            'display_name' => trim((($rec['FirstName'] ?? '') . ' ' . ($rec['LastName'] ?? ''))) ?: ($email ?: $phone),
            'given_name'   => $rec['FirstName'] ?? null,
            'family_name'  => $rec['LastName'] ?? null,
            'organization' => $rec['Account']['Name'] ?? null,
            'job_title'    => $rec['Title'] ?? null,
            'notes'        => null,
            'photo_url'    => null,
            'phones'       => $phone ? [['label' => 'work', 'value' => $phone, 'primary' => true]] : [],
            'emails'       => $email ? [['label' => 'work', 'value' => $email, 'primary' => true]] : [],
        ];
    }
}
