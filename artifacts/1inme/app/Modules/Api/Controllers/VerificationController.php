<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\VerificationRequest;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = VerificationRequest::where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return $this->ok(['items' => $items->map(fn ($r) => $this->transform($r))->all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'link_id'       => ['required', 'integer'],
            'category'      => ['required', Rule::in(['individual', 'business', 'org', 'creator'])],
            'business_name' => ['nullable', 'string', 'max:160'],
            'display_name'  => ['nullable', 'string', 'max:160'],
            'purpose'       => ['nullable', 'string', 'max:2000'],
        ]);
        $owns = Link::where('user_id', $request->user()->id)->whereKey($data['link_id'])->exists();
        if (!$owns) return $this->forbidden('You do not own that link');

        $r = VerificationRequest::create(array_merge($data, [
            'user_id' => $request->user()->id,
            'status'  => 'pending',
        ]));
        return $this->created(['verification_request' => $this->transform($r)]);
    }

    protected function transform(VerificationRequest $r): array
    {
        return [
            'id'            => $r->id,
            'link_id'       => $r->link_id,
            'category'      => $r->category,
            'business_name' => $r->business_name,
            'display_name'  => $r->display_name,
            'status'        => $r->status,
            'reviewed_at'   => optional($r->reviewed_at)->toIso8601String(),
            'created_at'    => optional($r->created_at)->toIso8601String(),
        ];
    }
}
