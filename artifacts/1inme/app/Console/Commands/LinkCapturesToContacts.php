<?php

namespace App\Console\Commands;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactIdentityResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Backfill for unified contact linking (Task #6501): walks every capture
 * table and links historical rows (contact_id IS NULL) to the owning
 * creator's Contact by email/phone, mirroring the model `created` hooks.
 *
 * Chunked and idempotent — rows already linked are skipped, re-runs are
 * safe, and per-row failures never abort the sweep. Contact creation
 * respects the owner's plan cap (cap reached ⇒ that row is skipped, the
 * sweep continues).
 */
class LinkCapturesToContacts extends Command
{
    protected $signature = 'contacts:link-captures
        {--user= : Only backfill captures owned by this user id}
        {--source=* : Limit to specific sources (subscriber, form, restaurant_order, store_order, booking, rsvp, event_ticket, product_order, review, inbox)}
        {--chunk=500 : Rows per chunk}
        {--dry-run : Report what would be linked without writing}';

    protected $description = 'Link historical capture records (subscribers, orders, bookings, RSVPs, tickets, reviews, form submissions, inbox threads) to Contacts';

    private int $linked = 0;
    private int $skipped = 0;

    public function handle(ContactIdentityResolver $resolver): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $only = (array) $this->option('source');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $sources = $this->sources();
        foreach ($sources as $source => $cfg) {
            if ($only && !in_array($source, $only, true)) {
                continue;
            }
            $this->info("Backfilling {$source}…");
            $before = $this->linked;

            $query = $cfg['query']();
            $query->whereNull('contact_id')->orderBy('id');

            $query->chunkById($chunk, function ($rows) use ($cfg, $resolver, $userId, $source) {
                foreach ($rows as $row) {
                    try {
                        [$ownerId, $email, $phone, $name] = $cfg['identity']($row);
                        if (!$ownerId || (blank($email) && blank($phone))) {
                            $this->skipped++;
                            continue;
                        }
                        if ($userId && (int) $ownerId !== $userId) {
                            $this->skipped++;
                            continue;
                        }
                        if ($this->option('dry-run')) {
                            $this->linked++;
                            continue;
                        }
                        $contact = $resolver->resolve((int) $ownerId, $email, $phone, $name, $source);
                        if (!$contact) {
                            $this->skipped++;
                            continue;
                        }
                        $row->contact_id = $contact->id;
                        $row->saveQuietly();
                        $this->linked++;
                    } catch (\Throwable $e) {
                        $this->skipped++;
                        \Log::warning('contacts:link-captures row failed', [
                            'source' => $source,
                            'id'     => $row->getKey(),
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }
            });

            $this->line('  linked ' . ($this->linked - $before) . ' rows');
        }

        $verb = $this->option('dry-run') ? 'would link' : 'linked';
        $this->info("Done: {$verb} {$this->linked}, skipped {$this->skipped}.");
        \Log::info('contacts:link-captures summary', [
            'dry_run' => (bool) $this->option('dry-run'),
            'linked'  => $this->linked,
            'skipped' => $this->skipped,
        ]);

        return self::SUCCESS;
    }

    /**
     * Per-source config: base query + identity extraction mirroring the
     * model `created` hooks (keep both in lockstep).
     *
     * @return array<string, array{query: \Closure, identity: \Closure}>
     */
    private function sources(): array
    {
        $ownerFromLink = fn ($linkId) => $linkId
            ? Link::withoutGlobalScope('workspace')->whereKey($linkId)->value('user_id')
            : null;

        return [
            'subscriber' => [
                'query'    => fn () => Subscriber::withoutGlobalScope('workspace'),
                'identity' => fn ($s) => [(int) $s->user_id ?: null, $s->email, $s->phone, $s->name],
            ],
            'form' => [
                'query'    => fn () => FormSubmission::withoutGlobalScope('workspace')->where('is_spam', false),
                'identity' => function ($sub) {
                    $ownerId = Form::withoutGlobalScope('workspace')->whereKey($sub->form_id)->value('user_id');
                    $identity = ContactIdentityResolver::identityFromFormData((array) $sub->data);

                    return [$ownerId, $identity['email'], $identity['phone'], $identity['name']];
                },
            ],
            'restaurant_order' => [
                'query'    => fn () => RestaurantOrder::query(),
                'identity' => function ($o) use ($ownerFromLink) {
                    $meta = (array) $o->meta;

                    return [
                        $ownerFromLink($o->link_id),
                        isset($meta['customer_email']) ? (string) $meta['customer_email'] : null,
                        isset($meta['customer_phone']) ? (string) $meta['customer_phone'] : null,
                        $o->customer_name,
                    ];
                },
            ],
            'store_order' => [
                'query'    => fn () => StoreOrder::query(),
                'identity' => function ($o) use ($ownerFromLink) {
                    $raw = trim((string) $o->customer_contact);
                    $isEmail = $raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL);

                    return [
                        $ownerFromLink($o->link_id),
                        $isEmail ? $raw : null,
                        (!$isEmail && $raw !== '') ? $raw : null,
                        $o->customer_name,
                    ];
                },
            ],
            'booking' => [
                'query'    => fn () => ServiceBookingRequest::query(),
                'identity' => fn ($b) => [$ownerFromLink($b->link_id), $b->customer_email, $b->customer_phone, $b->customer_name],
            ],
            'rsvp' => [
                'query'    => fn () => Rsvp::query(),
                'identity' => fn ($r) => [$ownerFromLink($r->link_id), $r->email, $r->phone, $r->name],
            ],
            'event_ticket' => [
                'query'    => fn () => EventTicket::query(),
                'identity' => fn ($t) => [$ownerFromLink($t->link_id), $t->attendee_email, $t->attendee_phone, $t->attendee_name],
            ],
            'product_order' => [
                'query'    => fn () => ProductOrder::query(),
                'identity' => function ($o) {
                    if (!$o->creator_user_id || !$o->buyer_user_id) {
                        return [null, null, null, null];
                    }
                    $buyer = User::find($o->buyer_user_id);
                    if (!$buyer) {
                        return [null, null, null, null];
                    }

                    return [(int) $o->creator_user_id, $buyer->email, $buyer->mobile, $buyer->name];
                },
            ],
            'review' => [
                'query'    => fn () => Review::withoutGlobalScope('workspace')->where('is_spam', false),
                'identity' => fn ($r) => [$r->user_id ? (int) $r->user_id : null, $r->author_email, null, $r->author_name],
            ],
            'inbox' => [
                'query'    => fn () => InboxThread::withoutGlobalScope('workspace'),
                'identity' => fn ($t) => [$t->user_id ? (int) $t->user_id : null, $t->sender_email, null, $t->sender_name],
            ],
        ];
    }
}
