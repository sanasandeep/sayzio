<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Models\Subscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class InboxForwarder
{
    /** Maximum delivery attempts before a delivery is parked as 'dead'. */
    public const MAX_ATTEMPTS = 5;

    /** Backoff (in minutes) per attempt index. */
    protected const BACKOFF_MIN = [1, 5, 15, 60, 240];

    /**
     * Send a synthetic, but realistic, form_submission payload to a single
     * destination so the creator can verify their inbox/webhook setup
     * without waiting for a real submission. The attempt is logged like a
     * normal delivery, flagged with is_test=true.
     */
    public function sendTest(InboxForwardDestination $dest): InboxForwardDelivery
    {
        $payload = $this->buildTestPayload();

        $delivery = InboxForwardDelivery::create([
            'destination_id'   => $dest->id,
            'user_id'          => $dest->user_id,
            'source_type'      => InboxAggregator::SOURCE_FORM,
            'source_id'        => 0,
            'is_test'          => true,
            'status'           => 'pending',
            'payload_snapshot' => $payload,
        ]);

        try {
            $this->deliver($delivery);
        } catch (\Throwable $e) {
            logger()->warning('Inbox forwarder test dispatch error: ' . $e->getMessage());
        }

        return $delivery->fresh();
    }

    protected function buildTestPayload(): array
    {
        return [
            'event'       => 'form_submission',
            'test'        => true,
            'occurred_at' => now()->toIso8601String(),
            'form'        => [
                'id'    => 0,
                'slug'  => 'test-form',
                'title' => 'Test form',
            ],
            'submission'  => [
                'id' => 0,
                'ip' => '127.0.0.1',
            ],
            'fields'      => [
                'name'    => 'Test Sender',
                'email'   => 'test@example.com',
                'message' => 'This is a test forward from your 1INME inbox forwarding rule.',
            ],
            'files'       => [],
        ];
    }

    public function dispatchForFormSubmission(int $userId, FormSubmission $submission): void
    {
        $payload = $this->buildFormPayload($submission);
        $this->dispatchAll($userId, InboxAggregator::SOURCE_FORM, $submission->id, $payload);
    }

    public function dispatchForSubscriber(int $userId, Subscriber $subscriber): void
    {
        $blockType = $subscriber->block?->type;
        $sourceType = (new InboxAggregator($userId))->mapSubscriberSource($subscriber->type, $blockType);
        $payload = $this->buildSubscriberPayload($subscriber, $sourceType);
        $this->dispatchAll($userId, $sourceType, $subscriber->id, $payload);
    }

    /** Walk all destinations matching $sourceType for $userId and try to deliver. */
    protected function dispatchAll(int $userId, string $sourceType, int $sourceId, array $payload): void
    {
        $destinations = InboxForwardDestination::where('user_id', $userId)
            ->where('is_active', true)->get();

        foreach ($destinations as $dest) {
            if (!$dest->matchesSource($sourceType)) continue;

            $delivery = InboxForwardDelivery::create([
                'destination_id'   => $dest->id,
                'user_id'          => $userId,
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'status'           => 'pending',
                'payload_snapshot' => $payload,
            ]);

            try {
                $this->deliver($delivery);
            } catch (\Throwable $e) {
                logger()->warning('Inbox forwarder dispatch error: ' . $e->getMessage());
            }
        }
    }

    /** Attempt one delivery and update the row. Safe to call from retry workers. */
    public function deliver(InboxForwardDelivery $delivery): void
    {
        $dest = $delivery->destination;
        if (!$dest || !$dest->is_active) {
            $delivery->update(['status' => 'dead', 'last_error' => 'Destination missing or disabled.']);
            return;
        }

        $delivery->attempts = $delivery->attempts + 1;
        $delivery->last_attempt_at = now();

        try {
            if ($dest->type === 'email') {
                $this->deliverEmail($dest, $delivery);
            } else {
                $this->deliverWebhook($dest, $delivery);
            }

            $delivery->status = 'success';
            $delivery->delivered_at = now();
            $delivery->next_retry_at = null;
            $delivery->last_error = null;
            $delivery->save();

            $dest->update([
                'last_delivered_at' => now(),
                'last_status'       => 'success',
            ]);
        } catch (\Throwable $e) {
            $delivery->last_error = mb_substr($e->getMessage(), 0, 1000);
            if ($delivery->attempts >= self::MAX_ATTEMPTS) {
                $delivery->status = 'dead';
                $delivery->next_retry_at = null;
            } else {
                $delivery->status = 'failed';
                $minutes = self::BACKOFF_MIN[min($delivery->attempts - 1, count(self::BACKOFF_MIN) - 1)];
                $delivery->next_retry_at = now()->addMinutes($minutes);
            }
            $delivery->save();
            $dest->update(['last_status' => $delivery->status]);
        }
    }

    /* ----------------------- transports ----------------------- */

    protected function deliverEmail(InboxForwardDestination $dest, InboxForwardDelivery $delivery): void
    {
        $address = trim($dest->target);
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email address.');
        }
        $payload = $delivery->payload_snapshot ?? [];
        $sourceLabel = InboxAggregator::sourceLabels()[$delivery->source_type] ?? $delivery->source_type;
        $subject = "[1INME Inbox] New {$sourceLabel}";
        $lines = ["New {$sourceLabel} received.", ''];
        foreach ((array) ($payload['fields'] ?? []) as $k => $v) {
            $lines[] = ucfirst(str_replace('_', ' ', (string) $k)) . ': ' . (is_scalar($v) ? (string) $v : json_encode($v));
        }
        $lines[] = '';
        $lines[] = 'View in your dashboard inbox.';

        Mail::raw(implode("\n", $lines), function ($m) use ($address, $subject) {
            $m->to($address)->subject($subject);
        });
    }

    protected function deliverWebhook(InboxForwardDestination $dest, InboxForwardDelivery $delivery): void
    {
        if (!self::isSafeWebhookUrl($dest->target)) {
            throw new \RuntimeException('Webhook URL is not allowed.');
        }
        $headers = ['Content-Type' => 'application/json'];
        if ($dest->header_key && $dest->header_value) {
            $hk = preg_replace('/[\r\n\t:\s]+/', '-', trim($dest->header_key));
            $hv = preg_replace('/[\r\n]+/', ' ', trim($dest->header_value));
            if ($hk !== '') $headers[$hk] = $hv;
        }

        $body = json_encode($delivery->payload_snapshot ?? [], JSON_UNESCAPED_SLASHES);
        if ($dest->secret) {
            $headers['X-1INME-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $dest->secret);
        }
        $headers['X-1INME-Event']    = $delivery->source_type;
        $headers['X-1INME-Delivery'] = (string) $delivery->id;

        $method = strtolower($dest->method ?: 'POST');
        if (!in_array($method, ['post', 'put', 'get'], true)) $method = 'post';

        $response = Http::withHeaders($headers)
            ->timeout(8)
            ->withBody($body, 'application/json')
            ->{$method}($dest->target);

        $delivery->last_response_code = $response->status();
        if ($response->failed()) {
            throw new \RuntimeException('Remote responded with HTTP ' . $response->status());
        }
    }

    /* ----------------------- payload builders ----------------------- */

    protected function buildFormPayload(FormSubmission $submission): array
    {
        $form = $submission->form;
        return [
            'event'      => 'form_submission',
            'occurred_at' => optional($submission->created_at)->toIso8601String(),
            'form'       => $form ? ['id' => $form->id, 'slug' => $form->slug, 'title' => $form->title] : null,
            'submission' => [
                'id' => $submission->id,
                'ip' => $submission->ip,
            ],
            'fields'     => (array) $submission->data,
            'files'      => (array) ($submission->files ?? []),
        ];
    }

    protected function buildSubscriberPayload(Subscriber $sub, string $sourceType): array
    {
        return [
            'event'       => 'subscriber',
            'source_type' => $sourceType,
            'occurred_at' => optional($sub->subscribed_at ?? $sub->created_at)->toIso8601String(),
            'link'        => $sub->link ? ['id' => $sub->link->id, 'alias' => $sub->link->alias] : null,
            'subscriber'  => [
                'id'    => $sub->id,
                'type'  => $sub->type,
            ],
            'fields'      => array_filter([
                'name'        => $sub->name,
                'email'       => $sub->email,
                'phone'       => $sub->phone,
                'channel_url' => $sub->channel_url,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /* ----------------------- url safety ----------------------- */

    /** SSRF guard — reject URLs targeting private/loopback/link-local IPs. */
    public static function isSafeWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) return false;
        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', '0.0.0.0', 'broadcasthost', 'metadata.google.internal'], true)) return false;
        $ips = @gethostbynamel($host) ?: [];
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return !empty($ips);
    }
}
