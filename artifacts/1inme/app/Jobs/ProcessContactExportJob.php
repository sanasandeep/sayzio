<?php

namespace App\Jobs;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactExport;
use App\Modules\User\Services\Contacts\ContactExportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generate a bulk contact export in the background for large address books.
 * Progress is tracked on the ContactExport model so the UI can poll it.
 */
class ProcessContactExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10-minute ceiling; exports are reads only
    public int $tries   = 1;

    public function __construct(public int $exportId) {}

    public function handle(ContactExportBuilder $builder): void
    {
        $export = ContactExport::find($this->exportId);
        if (!$export || $export->status === 'completed') return;

        $export->forceFill([
            'status'     => 'processing',
            'started_at' => $export->started_at ?? now(),
        ])->save();

        try {
            $content = $this->generate($export, $builder);

            $path = "exports/{$export->user_id}/{$export->id}.{$export->format}";
            Storage::disk('local')->put($path, $content);

            $export->forceFill([
                'status'       => 'completed',
                'file_path'    => $path,
                'expires_at'   => now()->addDay(),
                'completed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $export->forceFill([
                'status'       => 'failed',
                'error'        => Str::limit($e->getMessage(), 500),
                'completed_at' => now(),
            ])->save();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $export = ContactExport::find($this->exportId);
        if ($export && $export->status !== 'completed') {
            $export->forceFill([
                'status'       => 'failed',
                'error'        => Str::limit($e->getMessage(), 500),
                'completed_at' => now(),
            ])->save();
        }
    }

    private function generate(ContactExport $export, ContactExportBuilder $builder): string
    {
        $query = Contact::withoutGlobalScope('workspace')
            ->with(['phones', 'emails'])
            ->where('user_id', $export->user_id);

        $scope = (array) ($export->scope ?? []);
        if (($scope['tab'] ?? '') === 'biolink') {
            $query->whereNotNull('biolink_user_id');
        }
        if (!empty($scope['q'])) {
            $needle = '%' . $scope['q'] . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name',  'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization','ilike', $needle)
                  ->orWhereHas('emails',  fn ($e) => $e->where('value', 'ilike', $needle))
                  ->orWhereHas('phones',  fn ($p) => $p->where('value', 'ilike', $needle));
            });
        }

        $contacts = $query->orderBy('display_name')->get();

        $export->contact_count = $contacts->count();
        $export->save();

        return $export->format === 'vcf'
            ? $builder->buildVcf($contacts)
            : $builder->buildCsv($contacts);
    }
}
