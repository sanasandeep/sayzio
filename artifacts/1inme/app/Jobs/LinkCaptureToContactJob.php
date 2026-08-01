<?php

namespace App\Jobs;

use App\Modules\User\Services\Contacts\ContactIdentityResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Links one freshly-captured customer record (subscriber, order, booking,
 * RSVP, ticket, review, form submission, inbox thread, …) to the owning
 * creator's Contact — matching by email/phone, auto-creating one when
 * needed (Task #6501).
 *
 * Runs off the request hot path (queued, after commit) and is fully
 * defensive: any failure is swallowed by the resolver, and a record whose
 * contact_id was already set (e.g. re-delivered job) is left untouched.
 */
class LinkCaptureToContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        private string $modelClass,
        private int $modelId,
        private int $ownerUserId,
        private ?string $email,
        private ?string $phone,
        private ?string $name,
        private string $source,
    ) {
        // Capture rows are written inside transactions on several flows —
        // only link once the row is actually committed.
        $this->afterCommit();
    }

    /**
     * Dispatch for a capture record. No-op unless there is an addressable
     * identity (email or phone) and a resolvable owner. Never throws — this
     * is called from model `created` hooks on public customer paths.
     */
    public static function forRecord(Model $record, ?int $ownerUserId, ?string $email, ?string $phone, ?string $name, string $source): void
    {
        try {
            if (!$ownerUserId || (blank($email) && blank($phone))) {
                return;
            }
            self::dispatch(
                get_class($record),
                (int) $record->getKey(),
                (int) $ownerUserId,
                $email !== null ? trim($email) : null,
                $phone !== null ? trim($phone) : null,
                $name !== null ? trim($name) : null,
                $source
            );
        } catch (\Throwable $e) {
            \Log::warning('LinkCaptureToContactJob: dispatch failed', ['source' => $source, 'error' => $e->getMessage()]);
        }
    }

    public function handle(ContactIdentityResolver $resolver): void
    {
        $class = $this->modelClass;
        if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
            return;
        }

        $query = $class::query();
        if (method_exists($class, 'bootBelongsToWorkspace') || in_array(\App\Modules\User\Concerns\BelongsToWorkspace::class, class_uses_recursive($class), true)) {
            $query->withoutGlobalScope('workspace');
        }
        $record = $query->find($this->modelId);
        if (!$record || $record->contact_id) {
            return; // gone, or already linked (idempotent)
        }

        $contact = $resolver->resolve($this->ownerUserId, $this->email, $this->phone, $this->name, $this->source);
        if (!$contact) {
            return;
        }

        $record->contact_id = $contact->id;
        $record->saveQuietly();
    }
}
