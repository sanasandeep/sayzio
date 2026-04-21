<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Services\WorkspaceEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attachment uploads/downloads. File bytes are written to disk as
 * workspace-scoped ciphertext: even an attacker with raw disk access
 * cannot read a vault attachment without the application key + the
 * workspace's HKDF context. Decryption only happens on authorised
 * download and is audited.
 */
class VaultAttachmentController extends Controller
{
    public function storeForClient(Request $request, VaultClient $client)
    {
        $this->authorizeAction('vault.edit');
        if (!$client->visibleTo($request->user(), app('current_workspace'))) {
            abort(404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'local');
        $ws = app('current_workspace');

        $plaintext = file_get_contents($file->getRealPath());
        $cipher = app(WorkspaceEncryption::class)->encrypt((int) $ws->id, $plaintext);

        // Random storage name decouples on-disk filename from the original.
        $path = 'vault/' . $ws->id . '/clients/' . $client->id . '/' . Str::random(40) . '.enc';
        Storage::disk($disk)->put($path, $cipher);

        VaultAttachment::create([
            'uploaded_by_user_id' => $request->user()->id,
            'parent_type' => 'client',
            'parent_id'   => $client->id,
            'filename'    => $file->getClientOriginalName(),
            'disk'        => $disk,
            'path'        => $path,
            'size'        => $file->getSize(),
            'mime'        => $file->getMimeType(),
            'encrypted'   => true,
        ]);

        VaultAudit::record('update', 'client', $client->id, $client->name . ' (attachment)');

        return back()->with('status', 'Attachment uploaded.');
    }

    public function download(Request $request, VaultAttachment $attachment)
    {
        $this->ensureVisibleParent($request, $attachment);
        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        $bytes = Storage::disk($attachment->disk)->get($attachment->path);
        if ($attachment->encrypted) {
            $bytes = app(WorkspaceEncryption::class)->decrypt((int) $attachment->workspace_id, $bytes);
        }

        VaultAudit::record('reveal', $attachment->parent_type, $attachment->parent_id, $attachment->filename);

        return response($bytes, 200, [
            'Content-Type'        => $attachment->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($attachment->filename) . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function destroy(Request $request, VaultAttachment $attachment)
    {
        $this->authorizeAction('vault.delete');
        $this->ensureVisibleParent($request, $attachment);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $name = $attachment->filename;
        $parentType = $attachment->parent_type;
        $parentId = $attachment->parent_id;
        $attachment->delete();
        VaultAudit::record('delete', $parentType, $parentId, $name);
        return back()->with('status', 'Attachment removed.');
    }

    protected function authorizeAction(string $perm): void
    {
        $user = auth()->user();
        $ws = app('current_workspace');
        if (!$user || !$ws || !$user->canInWorkspace($ws, $perm)) {
            abort(403);
        }
    }

    protected function ensureVisibleParent(Request $request, VaultAttachment $attachment): void
    {
        if ($attachment->parent_type === 'client') {
            $parent = VaultClient::find($attachment->parent_id);
        } else {
            $parent = \App\Modules\User\Models\VaultCredential::find($attachment->parent_id);
        }
        if (!$parent || !$parent->visibleTo($request->user(), app('current_workspace'))) {
            abort(404);
        }
    }
}
