<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CloudFileAttachment;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\InboxReply;
use App\Modules\User\Models\TaskCard;
use Illuminate\Http\Request;

/**
 * Lets composers (posts, task cards, inbox replies) attach files from the
 * shared workspace cloud library. The link is just a reference to the
 * existing cloud_file row — bytes stay in the provider.
 */
class CloudFileAttachmentController extends Controller
{
    /** Polymorphic targets we accept; map UI keys to fully-qualified models. */
    protected const TARGETS = [
        'post'         => CreatorPost::class,
        'task_card'    => TaskCard::class,
        'inbox_reply'  => InboxReply::class,
    ];

    /** JSON list of all cloud-library files in the active workspace. */
    public function library(Request $request)
    {
        $q = CloudFile::query()->with('addedBy:id,name');
        if ($needle = trim((string) $request->query('q', ''))) {
            $q->where('name', 'like', '%' . $needle . '%');
        }
        $files = $q->orderByDesc('added_at')->limit(200)->get();

        return response()->json([
            'files' => $files->map(fn (CloudFile $f) => [
                'id'            => $f->id,
                'name'          => $f->name,
                'link'          => $f->link,
                'provider'      => $f->provider,
                'provider_icon' => $f->providerIcon(),
                'provider_label'=> $f->providerLabel(),
                'human_size'    => $f->humanSize(),
                'added_by'      => $f->addedBy?->name,
            ])->all(),
        ]);
    }

    public function attach(Request $request)
    {
        $data = $request->validate([
            'target_type'      => 'required|string',
            'target_id'        => 'required|integer',
            'cloud_file_ids'   => 'required|array|min:1|max:50',
            'cloud_file_ids.*' => 'integer',
        ]);

        $cls = self::TARGETS[$data['target_type']] ?? null;
        abort_unless($cls, 422, 'Unknown attach target.');

        // Workspace global scope on each target model ensures we can only
        // attach to records the active workspace owns.
        $target = $cls::query()->findOrFail($data['target_id']);

        $created = [];
        foreach (array_unique($data['cloud_file_ids']) as $cfId) {
            $file = CloudFile::query()->find($cfId);
            if (!$file) continue;
            $att = CloudFileAttachment::firstOrCreate(
                [
                    'cloud_file_id'   => $file->id,
                    'attachable_type' => $cls,
                    'attachable_id'   => $target->id,
                ],
                [
                    'attached_by_user_id' => $request->user()->id,
                ],
            );
            $created[] = $this->serialize($att, $file);
        }

        return response()->json(['attachments' => $created]);
    }

    public function destroy(CloudFileAttachment $attachment)
    {
        // workspace global scope on the model already filtered to this workspace
        $attachment->delete();
        return response()->json(['ok' => true]);
    }

    public static function serialize(CloudFileAttachment $att, ?CloudFile $file = null): array
    {
        $file = $file ?? $att->cloudFile;
        return [
            'id'             => $att->id,
            'cloud_file_id'  => $att->cloud_file_id,
            'name'           => $file?->name,
            'link'           => $file?->link,
            'provider'       => $file?->provider,
            'provider_icon'  => $file?->providerIcon(),
            'provider_label' => $file?->providerLabel(),
            'human_size'     => $file?->humanSize(),
        ];
    }
}
