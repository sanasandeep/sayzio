<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $q = Link::where('user_id', $request->user()->id);

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        $page = $q->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($l) => LinkResource::toArray($l))->all(),
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
            'type'       => ['required', Rule::in(['short', 'biolink', 'file', 'qr', 'event', 'vcard', 'social', 'sms', 'wifi', 'pdf'])],
            'alias'      => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')],
            'title'      => ['nullable', 'string', 'max:200'],
            'long_url'   => ['nullable', 'url', 'max:2048'],
            'visibility' => ['nullable', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['nullable', 'boolean'],
            'seo_title'  => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $link = Link::create([
            'user_id'    => $request->user()->id,
            'type'       => $data['type'],
            'alias'      => $alias,
            'title'      => $data['title'] ?? null,
            'long_url'   => $data['long_url'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'is_active'  => $data['is_active'] ?? true,
            'seo_title'  => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    public function show(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok(['link' => LinkResource::toArray($link)]);
    }

    public function update(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'title'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'long_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
            'alias'      => ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')->ignore($link->id)],
            'visibility' => ['sometimes', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['sometimes', 'boolean'],
            'seo_title'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $link->fill($data)->save();
        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $link->delete();
        return $this->noContent();
    }
}
