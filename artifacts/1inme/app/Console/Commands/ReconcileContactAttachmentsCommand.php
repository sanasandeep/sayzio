<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Contact;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use Illuminate\Console\Command;

class ReconcileContactAttachmentsCommand extends Command
{
    protected $signature   = 'contacts:reconcile-attachments {--user= : Only reconcile contacts owned by this user id}';
    protected $description = 'Clear or re-evaluate caller-ID biolink attachments whose creator is no longer reachable to the contact owner (suspended/deactivated/deleted, or has blocked the owner).';

    public function handle(BiolinkAttachResolver $resolver): int
    {
        $q = Contact::query()->whereNotNull('biolink_user_id');
        if ($user = $this->option('user')) $q->where('user_id', $user);

        $cleared = 0;
        $checked = 0;

        $q->with('biolinkUser')->chunkById(500, function ($contacts) use ($resolver, &$cleared, &$checked): void {
            foreach ($contacts as $contact) {
                $checked++;
                $before = $contact->biolink_user_id;
                $resolver->reconcile($contact);
                if ($contact->biolink_user_id !== $before) $cleared++;
            }
        });

        $this->info("Reconciled {$checked} attached contact(s); cleared {$cleared}.");
        return self::SUCCESS;
    }
}
