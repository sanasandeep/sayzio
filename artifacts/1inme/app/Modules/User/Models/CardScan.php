<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CardScan extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'actor_user_id', 'source_file_id',
        'source_file_ids', 'derived_file_ids',
        'status', 'error', 'raw_response', 'extracted', 'credits_spent',
        'contact_id', 'wizard_draft_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'raw_response'     => 'array',
            'extracted'        => 'array',
            'source_file_ids'  => 'array',
            'derived_file_ids' => 'array',
            'credits_spent'    => 'integer',
        ];
    }

    /** All originals the user uploaded for this scan, in upload order. */
    public function sourceFiles()
    {
        $ids = array_values(array_map('intval', (array) ($this->source_file_ids ?? [])));
        if (!$ids) return collect();
        // Preserve the upload order regardless of DB ordering. Works on
        // both Postgres (this app) and MySQL — no DB-specific ORDER BY.
        $byId = UserFile::whereIn('id', $ids)->get()->keyBy('id');
        return collect($ids)->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    public const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    public function user()       { return $this->belongsTo(User::class); }
    public function actor()      { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function sourceFile() { return $this->belongsTo(UserFile::class, 'source_file_id'); }
    public function contact()    { return $this->belongsTo(Contact::class); }
    public function wizardDraft(){ return $this->belongsTo(BiolinkWizardDraft::class, 'wizard_draft_id'); }
}
