<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NfcWrite;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class NfcWriteController extends Controller
{
    use ApiResponses;

    public function index(Request $request, int $linkId)
    {
        $link = Link::where('user_id', $request->user()->id)->find($linkId);
        if (!$link) return $this->notFound('Link not found');

        $page = NfcWrite::where('link_id', $link->id)
            ->orderByDesc('written_at')
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($w) => $this->transform($w))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, int $linkId)
    {
        $link = Link::where('user_id', $request->user()->id)->find($linkId);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'written_url'        => ['required', 'url', 'max:2048'],
            'written_at'         => ['nullable', 'date'],
            'tag_uid'            => ['nullable', 'string', 'max:80'],
            'tag_type'           => ['nullable', 'string', 'max:40'],
            'tag_capacity_bytes' => ['nullable', 'integer', 'min:0'],
            'locked'             => ['nullable', 'boolean'],
            'device'             => ['nullable', 'string', 'max:120'],
            'device_label'       => ['nullable', 'string', 'max:120'],
            'platform'           => ['nullable', Rule::in(['ios', 'android'])],
            'source'             => ['nullable', Rule::in(['mobile', 'web', 'import', 'api'])],
            'lat'                => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                => ['nullable', 'numeric', 'between:-180,180'],
            'label'              => ['nullable', 'string', 'max:120'],
            'meta'               => ['nullable', 'array'],
        ]);

        $write = NfcWrite::create(array_merge($data, [
            'user_id'    => $request->user()->id,
            'link_id'    => $link->id,
            'locked'     => (bool) ($data['locked'] ?? false),
            'source'     => $data['source'] ?? 'mobile',
            'written_at' => $data['written_at'] ?? now(),
        ]));

        return $this->created(['nfc_write' => $this->transform($write)]);
    }

    public function destroy(Request $request, int $linkId, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($linkId);
        if (!$link) return $this->notFound('Link not found');

        $write = NfcWrite::where('link_id', $link->id)->find($id);
        if (!$write) return $this->notFound('NFC write not found');

        $write->delete();
        return $this->noContent();
    }

    public function summary(Request $request)
    {
        $userId = $request->user()->id;
        $byLink = NfcWrite::selectRaw('link_id, count(*) as writes_count, max(coalesce(written_at, created_at)) as last_written_at')
            ->where('user_id', $userId)
            ->groupBy('link_id')
            ->get();
        return $this->ok([
            'total'   => (int) $byLink->sum('writes_count'),
            'by_link' => $byLink->map(fn ($r) => [
                'link_id'         => (int) $r->link_id,
                'writes_count'    => (int) $r->writes_count,
                'last_written_at' => $r->last_written_at ? \Carbon\Carbon::parse($r->last_written_at)->toIso8601String() : null,
            ])->all(),
        ]);
    }

    protected function transform(NfcWrite $w): array
    {
        return [
            'id'                 => $w->id,
            'link_id'            => $w->link_id,
            'written_url'        => $w->written_url,
            'written_at'         => optional($w->written_at ?: $w->created_at)->toIso8601String(),
            'tag_uid'            => $w->tag_uid,
            'tag_type'           => $w->tag_type,
            'tag_capacity_bytes' => $w->tag_capacity_bytes,
            'locked'             => (bool) $w->locked,
            'device'             => $w->device,
            'device_label'       => $w->device_label,
            'platform'           => $w->platform,
            'source'             => $w->source,
            'lat'                => $w->lat !== null ? (float) $w->lat : null,
            'lng'                => $w->lng !== null ? (float) $w->lng : null,
            'label'              => $w->label,
            'meta'               => $w->meta,
            'created_at'         => optional($w->created_at)->toIso8601String(),
        ];
    }
}
