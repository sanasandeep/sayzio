<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QrCodeController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = QrCode::where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return $this->ok(['items' => $items->map(fn ($q) => $this->transform($q))->all()]);
    }

    public function show(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        return $this->ok(['qr_code' => $this->transform($q)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'type'       => ['required', 'string', 'max:24'],
            'link_id'    => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'payload'    => ['nullable', 'array'],
            'design'     => ['nullable', 'array'],
        ]);
        $userId = $request->user()->id;
        if (!empty($data['link_id'])) {
            $owns = Link::where('user_id', $userId)->whereKey($data['link_id'])->exists();
            if (!$owns) return $this->forbidden('You do not own that link');
        }
        if (!empty($data['project_id'])) {
            $owns = Project::where('user_id', $userId)->whereKey($data['project_id'])->exists();
            if (!$owns) return $this->forbidden('You do not own that project');
        }
        $q = QrCode::create(array_merge([
            'payload' => [],
            'design'  => [],
        ], $data, ['user_id' => $userId]));
        return $this->created(['qr_code' => $this->transform($q)]);
    }

    public function destroy(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        $q->delete();
        return $this->noContent();
    }

    protected function transform(QrCode $q): array
    {
        return [
            'id'          => $q->id,
            'name'        => $q->name,
            'type'        => $q->type,
            'link_id'     => $q->link_id,
            'project_id'  => $q->project_id,
            'payload'     => $q->payload,
            'design'      => $q->design,
            'preview_url' => $q->preview_url,
            'created_at'  => optional($q->created_at)->toIso8601String(),
        ];
    }
}
