<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;

/**
 * HubSpot CRM connector. Pushes leads as HubSpot contacts (upserting by
 * email) and pulls contacts back using the incremental search API keyed on
 * hs_lastmodifieddate.
 */
class HubspotConnector extends AbstractOAuthConnector
{
    private const API = 'https://api.hubapi.com';
    private const PROPS = ['email', 'firstname', 'lastname', 'phone', 'company', 'jobtitle'];

    protected function providerKey(): string
    {
        return 'hubspot';
    }

    protected function afterExchange(ConnectedApp $conn, array $tok): void
    {
        try {
            $info = \Illuminate\Support\Facades\Http::asJson()
                ->get(self::API . '/oauth/v1/access-tokens/' . ($tok['access_token'] ?? ''));
            if ($info->successful()) {
                $conn->account_email       = $info->json('user');
                $conn->account_label       = $info->json('hub_domain');
                $conn->external_account_id = (string) $info->json('hub_id');
            }
        } catch (\Throwable $e) {
            // best-effort identity
        }
    }

    public function pushContact(ConnectedApp $conn, array $lead): array
    {
        $props = $this->mapLead($conn, $lead);
        $email = $lead['email'] ?? ($props['email'] ?? null);

        // Prefer an idempotent upsert by email when we have one.
        if ($email) {
            $resp = $this->client($conn)->patch(
                self::API . '/crm/v3/objects/contacts/' . rawurlencode($email) . '?idProperty=email',
                ['properties' => $props]
            );
            if ($resp->successful()) {
                return ['id' => $resp->json('id')];
            }
            // 404 => not found, fall through to create.
            if ($resp->status() !== 404) {
                throw new ConnectedAppException('HubSpot upsert failed: ' . $resp->body());
            }
        }

        $resp = $this->client($conn)->post(self::API . '/crm/v3/objects/contacts', ['properties' => $props]);
        if (!$resp->successful()) {
            throw new ConnectedAppException('HubSpot create failed: ' . $resp->body());
        }
        return ['id' => $resp->json('id')];
    }

    public function pullContacts(ConnectedApp $conn, ?callable $onCursor = null): iterable
    {
        $cursor   = $conn->pull_cursor;
        $newSince = (string) now()->valueOf(); // ms epoch for HubSpot filters
        $after    = null;
        $pages    = 0;

        do {
            $body = [
                'properties' => self::PROPS,
                'limit'      => 100,
                'sorts'      => [['propertyName' => 'hs_lastmodifieddate', 'direction' => 'ASCENDING']],
            ];
            if ($cursor) {
                $body['filterGroups'] = [[
                    'filters' => [[
                        'propertyName' => 'hs_lastmodifieddate',
                        'operator'     => 'GT',
                        'value'        => $cursor,
                    ]],
                ]];
            }
            if ($after) {
                $body['after'] = $after;
            }

            $resp = $this->client($conn)->post(self::API . '/crm/v3/objects/contacts/search', $body);
            if (!$resp->successful()) {
                throw new ConnectedAppException('HubSpot search failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['results'] ?? []) as $rec) {
                yield $this->normalize($rec);
            }
            $after = $data['paging']['next']['after'] ?? null;
            $pages++;
        } while ($after && $pages < 10);

        if ($onCursor) {
            $onCursor($newSince);
        }
    }

    private function normalize(array $rec): array
    {
        $p     = $rec['properties'] ?? [];
        $email = $p['email'] ?? null;
        $phone = $p['phone'] ?? null;
        return [
            'external_id'  => $rec['id'] ?? null,
            'display_name' => trim((($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? ''))) ?: ($email ?: $phone),
            'given_name'   => $p['firstname'] ?? null,
            'family_name'  => $p['lastname'] ?? null,
            'organization' => $p['company'] ?? null,
            'job_title'    => $p['jobtitle'] ?? null,
            'notes'        => null,
            'photo_url'    => null,
            'phones'       => $phone ? [['label' => 'work', 'value' => $phone, 'primary' => true]] : [],
            'emails'       => $email ? [['label' => 'work', 'value' => $email, 'primary' => true]] : [],
        ];
    }
}
