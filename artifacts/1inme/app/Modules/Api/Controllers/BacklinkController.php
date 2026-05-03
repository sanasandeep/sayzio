<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Backlink;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backlink radar storage. Rows are written by the browser extension when
 * it spots an outbound link on the page the creator is browsing that
 * points to one of their own properties (short link, bio-link username
 * path, custom domain). Read endpoints back the popup's "Backlinks" tab.
 */
class BacklinkController extends Controller
{
    use ApiResponses;

    protected const PROPERTY_TYPES = ['short_link', 'biolink_username', 'custom_domain'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'days'          => ['nullable', 'integer', 'in:7,30,90'],
            'property_type' => ['nullable', Rule::in(self::PROPERTY_TYPES)],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Backlink::where('user_id', $request->user()->id);

        if (!empty($data['days'])) {
            $q->where('first_seen_at', '>=', now()->subDays((int) $data['days']));
        }
        if (!empty($data['property_type'])) {
            $q->where('matched_property_type', $data['property_type']);
        }

        $page = $q->orderByDesc('first_seen_at')
            ->paginate(min(200, max(1, (int) ($data['per_page'] ?? 50))));

        return $this->ok([
            'items' => collect($page->items())->map(fn (Backlink $b) => $this->transform($b))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'page_url'               => ['required', 'url', 'max:2048'],
            'page_title'             => ['nullable', 'string', 'max:500'],
            'anchor_text'            => ['nullable', 'string', 'max:500'],
            'matched_url'            => ['required', 'url', 'max:2048'],
            'matched_property_type'  => ['required', Rule::in(self::PROPERTY_TYPES)],
            'matched_property_value' => ['nullable', 'string', 'max:253'],
        ]);

        $host = parse_url($data['page_url'], PHP_URL_HOST) ?: '';
        $host = strtolower(preg_replace('/^www\./i', '', $host));

        // Dedupe: re-saving the same (page, match) is a no-op that just
        // bumps the updated_at timestamp so the popup reads as "still
        // there". first_seen_at is preserved.
        $row = Backlink::firstOrNew([
            'user_id'     => $request->user()->id,
            'page_url'    => $data['page_url'],
            'matched_url' => $data['matched_url'],
        ]);

        if (!$row->exists) {
            $row->first_seen_at = now();
        }
        $row->page_host              = $host;
        $row->page_title             = $data['page_title'] ?? $row->page_title;
        $row->anchor_text            = $data['anchor_text'] ?? $row->anchor_text;
        $row->matched_property_type  = $data['matched_property_type'];
        $row->matched_property_value = $data['matched_property_value'] ?? null;
        $row->save();

        return $this->created(['backlink' => $this->transform($row->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $row = Backlink::where('user_id', $request->user()->id)->find($id);
        if (!$row) return $this->notFound('Backlink not found');
        $row->delete();
        return $this->noContent();
    }

    /**
     * CSV export, same filters as index(). Streamed so we don't pull the
     * whole collection into memory for big creators.
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'days'          => ['nullable', 'integer', 'in:7,30,90'],
            'property_type' => ['nullable', Rule::in(self::PROPERTY_TYPES)],
        ]);

        $userId = $request->user()->id;
        $days   = $request->integer('days');
        $type   = $request->string('property_type')->toString() ?: null;

        $filename = 'backlinks-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($userId, $days, $type) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'first_seen_at', 'page_url', 'page_title', 'anchor_text',
                'matched_url', 'matched_property_type', 'matched_property_value',
            ]);
            $q = Backlink::where('user_id', $userId);
            if ($days) $q->where('first_seen_at', '>=', now()->subDays($days));
            if ($type) $q->where('matched_property_type', $type);
            $q->orderByDesc('first_seen_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        optional($r->first_seen_at)->toIso8601String(),
                        $r->page_url,
                        $r->page_title,
                        $r->anchor_text,
                        $r->matched_url,
                        $r->matched_property_type,
                        $r->matched_property_value,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function transform(Backlink $b): array
    {
        return [
            'id'                     => $b->id,
            'page_url'               => $b->page_url,
            'page_host'              => $b->page_host,
            'page_title'             => $b->page_title,
            'anchor_text'            => $b->anchor_text,
            'matched_url'            => $b->matched_url,
            'matched_property_type'  => $b->matched_property_type,
            'matched_property_value' => $b->matched_property_value,
            'first_seen_at'          => optional($b->first_seen_at)->toIso8601String(),
            'created_at'             => optional($b->created_at)->toIso8601String(),
        ];
    }
}
