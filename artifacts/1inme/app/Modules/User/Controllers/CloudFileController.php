<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudFileController extends Controller
{
    public function index(Request $request)
    {
        $q = CloudFile::query();
        if ($p = $request->query('provider')) {
            if (CloudProviderApp::isKnownProvider($p)) $q->where('provider', $p);
        }
        if ($owner = $request->query('owner')) {
            $q->where('added_by_user_id', (int) $owner);
        }
        if ($needle = trim((string) $request->query('q'))) {
            $q->where('name', 'like', '%' . $needle . '%');
        }

        $files = $q->with('addedBy')->orderByDesc('added_at')->paginate(40)->withQueryString();

        $myConnections = CloudConnection::where('user_id', $request->user()->id)->get();
        $apps = CloudProviderApp::query()->get()->keyBy('provider');

        return view('user.cloud-files.index', compact('files', 'myConnections', 'apps'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'connection_id'      => ['required', 'integer'],
            'items'              => ['required', 'array', 'min:1', 'max:50'],
            'items.*.remote_id'  => ['required', 'string', 'max:255'],
            'items.*.name'       => ['required', 'string', 'max:255'],
            'items.*.mime'       => ['nullable', 'string', 'max:191'],
            'items.*.size'       => ['nullable', 'integer', 'min:0'],
            'items.*.link'       => ['required', 'url', 'max:1024'],
            'items.*.thumbnail_url' => ['nullable', 'url', 'max:1024'],
            'parent_folder_path' => ['nullable', 'string', 'max:255'],
        ]);

        $conn = CloudConnection::query()->where('id', $data['connection_id'])->firstOrFail();
        abort_unless($conn->user_id === $request->user()->id, 403);

        $added = 0;
        foreach ($data['items'] as $it) {
            $exists = CloudFile::query()
                ->where('provider', $conn->provider)
                ->where('remote_id', $it['remote_id'])
                ->exists();
            if ($exists) continue;

            CloudFile::create([
                'added_by_user_id'   => Auth::id(),
                'connection_id'      => $conn->id,
                'provider'           => $conn->provider,
                'remote_id'          => $it['remote_id'],
                'name'               => $it['name'],
                'mime'               => $it['mime'] ?? null,
                'size'               => (int) ($it['size'] ?? 0),
                'link'               => $it['link'],
                'thumbnail_url'      => $it['thumbnail_url'] ?? null,
                'parent_folder_path' => $data['parent_folder_path'] ?? null,
                'added_at'           => now(),
            ]);
            $added++;
        }

        if ($request->expectsJson()) {
            return response()->json(['added' => $added]);
        }
        return redirect()->route('user.cloud-files.index')
            ->with('success', $added . ' file' . ($added === 1 ? '' : 's') . ' added to the workspace library.');
    }

    public function destroy(Request $request, CloudFile $cloudFile)
    {
        $cloudFile->delete();
        return back()->with('success', 'Removed from library.');
    }
}
