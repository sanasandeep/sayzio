<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\IngestAiMindSourceJob;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiMindSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD for the polymorphic sources inside an AI Mind.
 *
 *   POST   minds/{mind}/sources             create one of: text/document/faq/link/feature.
 *   POST   minds/{mind}/sources/{src}/refresh  re-queue ingestion.
 *   DELETE minds/{mind}/sources/{src}       remove the source + its chunks.
 */
class MindSourceController extends Controller
{
    /**
     * Read-only view of a single source's body so creators can verify
     * what an AI citation actually pulled from. Accessible to the mind
     * owner (or anyone for the platform-managed mind), even when the
     * mind is disabled — read-only by design.
     */
    public function show(Request $request, AiMind $mind, AiMindSource $source)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        if (!$mind->isPlatform() && (int) $mind->user_id !== (int) $request->user()->id) {
            abort(403);
        }
        if ((int) $source->mind_id !== (int) $mind->id) abort(404);

        // If a citation linked here with ?chunk=ID, look up that chunk so
        // the view can scroll to and highlight the exact passage the AI
        // pulled. Silently ignored if it doesn't belong to this source.
        $highlightChunk = null;
        $chunkId = (int) $request->query('chunk');
        if ($chunkId > 0) {
            $highlightChunk = AiMindChunk::where('id', $chunkId)
                ->where('source_id', $source->id)
                ->first();
        }

        return view('user.minds.sources.show', [
            'mind'           => $mind,
            'source'         => $source,
            'highlightChunk' => $highlightChunk,
        ]);
    }

    public function store(Request $request, AiMind $mind)
    {
        $this->guard($mind, $request->user());
        $caps = AiMindSettings::caps();

        $type = (string) $request->input('type');
        if (!in_array($type, AiMindSource::TYPES, true)) {
            return back()->with('error', 'Unsupported source type.');
        }
        if ($mind->sources()->count() >= $caps['max_sources_per_mind']) {
            return back()->with('error', "This Mind already has {$caps['max_sources_per_mind']} sources (the platform cap).");
        }

        $source = match ($type) {
            AiMindSource::TYPE_TEXT     => $this->createText($request, $mind, $caps),
            AiMindSource::TYPE_FAQ      => $this->createFaq($request, $mind),
            AiMindSource::TYPE_LINK     => $this->createLink($request, $mind, $caps),
            AiMindSource::TYPE_DOCUMENT => $this->createDocument($request, $mind, $caps),
            AiMindSource::TYPE_FEATURE  => $this->createFeature($request, $mind),
        };

        if (is_string($source)) {
            // Helper returned an error message string instead of a model.
            return back()->with('error', $source);
        }

        // Queue ingestion immediately. Feature sources finish synchronously
        // inside the ingestor (no embedding to do).
        IngestAiMindSourceJob::dispatch($source->id);
        return back()->with('status', 'Source added — ingestion queued.');
    }

    public function refresh(Request $request, AiMind $mind, AiMindSource $source)
    {
        $this->guard($mind, $request->user());
        if ((int) $source->mind_id !== (int) $mind->id) abort(404);
        $source->forceFill(['status' => AiMindSource::STATUS_QUEUED, 'status_message' => null])->save();
        IngestAiMindSourceJob::dispatch($source->id);
        return back()->with('status', 'Source refresh queued.');
    }

    public function destroy(Request $request, AiMind $mind, AiMindSource $source)
    {
        $this->guard($mind, $request->user());
        if ((int) $source->mind_id !== (int) $mind->id) abort(404);
        // Drop the uploaded document file so deleted sources don't keep
        // taking up storage quota.
        if ($source->type === AiMindSource::TYPE_DOCUMENT && $source->storage_path) {
            try {
                Storage::disk($source->storage_disk ?: 'local')->delete($source->storage_path);
            } catch (\Throwable $e) {
                // Storage already gone — proceed.
            }
        }
        $source->delete();
        $mind->recountStats();
        return back()->with('status', 'Source removed.');
    }

    protected function createText(Request $request, AiMind $mind, array $caps)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body'  => 'required|string|max:' . $caps['max_text_chars'],
        ]);
        return AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => $data['title'],
            'body'    => $data['body'],
            'status'  => AiMindSource::STATUS_QUEUED,
        ]);
    }

    protected function createFaq(Request $request, AiMind $mind)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:200',
            'qs'      => 'required|array|min:1|max:200',
            'qs.*.q'  => 'required|string|max:500',
            'qs.*.a' => 'required|string|max:5000',
        ]);
        $faqs = [];
        foreach ($data['qs'] as $row) {
            $faqs[] = ['q' => trim($row['q']), 'a' => trim($row['a'])];
        }
        return AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_FAQ,
            'title'   => $data['title'],
            'body'    => json_encode($faqs, JSON_UNESCAPED_UNICODE),
            'status'  => AiMindSource::STATUS_QUEUED,
        ]);
    }

    protected function createLink(Request $request, AiMind $mind, array $caps)
    {
        $linkCount = $mind->sources()->where('type', AiMindSource::TYPE_LINK)->count();
        if ($linkCount >= $caps['max_links_per_mind']) {
            return "This Mind already has the maximum {$caps['max_links_per_mind']} link source(s).";
        }
        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'url'             => 'required|url|max:2048',
            'refresh_minutes' => 'nullable|integer|min:15|max:43200',
        ]);
        $minMin = max(15, $caps['link_refresh_min_minutes']);
        $minutes = max($minMin, (int) ($data['refresh_minutes'] ?? (60 * 24)));
        return AiMindSource::create([
            'mind_id'         => $mind->id,
            'type'            => AiMindSource::TYPE_LINK,
            'title'           => $data['title'],
            'url'             => $data['url'],
            'refresh_minutes' => $minutes,
            'status'          => AiMindSource::STATUS_QUEUED,
        ]);
    }

    protected function createDocument(Request $request, AiMind $mind, array $caps)
    {
        $docCount = $mind->sources()->where('type', AiMindSource::TYPE_DOCUMENT)->count();
        if ($docCount >= $caps['max_docs_per_mind']) {
            return "This Mind already has the maximum {$caps['max_docs_per_mind']} document(s).";
        }
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'file'  => 'required|file|max:' . ($caps['max_doc_size_mb'] * 1024)
                . '|mimes:pdf,docx,doc,rtf,pptx,txt,md',
        ]);
        $file = $request->file('file');
        $disk = 'local';
        $path = $file->store('ai-minds/' . $mind->id, $disk);
        return AiMindSource::create([
            'mind_id'      => $mind->id,
            'type'         => AiMindSource::TYPE_DOCUMENT,
            'title'        => $data['title'],
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime'         => (string) $file->getMimeType(),
            'size_bytes'   => (int) $file->getSize(),
            'status'       => AiMindSource::STATUS_QUEUED,
        ]);
    }

    protected function createFeature(Request $request, AiMind $mind)
    {
        $data = $request->validate([
            'feature_key' => 'required|string|max:64',
        ]);
        $key = $data['feature_key'];
        if (!AiMindFeatureAdapter::isFeature($key)) {
            return 'Unknown 1INME feature.';
        }
        // Avoid duplicate feature attachments — same feature twice
        // would just recompute the same snapshot.
        if ($mind->sources()->where('type', AiMindSource::TYPE_FEATURE)->where('feature_key', $key)->exists()) {
            return 'This 1INME feature is already attached to the Mind.';
        }
        return AiMindSource::create([
            'mind_id'     => $mind->id,
            'type'        => AiMindSource::TYPE_FEATURE,
            'title'       => '1INME — ' . AiMindFeatureAdapter::label($key),
            'feature_key' => $key,
            'status'      => AiMindSource::STATUS_QUEUED,
        ]);
    }

    protected function guard(AiMind $mind, $user): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        if ($mind->isPlatform()) abort(403, 'The default Mind is platform-managed.');
        if ((int) $mind->user_id !== (int) $user->id) abort(403);
        if ($mind->is_disabled) abort(403, 'This Mind is disabled.');
    }
}
