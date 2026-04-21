<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ContactController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $q = Contact::where('user_id', $request->user()->id);
        if ($s = $request->string('q')->toString()) {
            $q->where(function ($w) use ($s) {
                $w->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%");
            });
        }
        $page = $q->orderBy('name')->paginate(min(200, max(1, (int) $request->input('per_page', 50))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($c) => $this->transform($c))->all(),
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
            'name'   => ['required', 'string', 'max:120'],
            'email'  => ['nullable', 'email', 'max:190'],
            'phone'  => ['nullable', 'string', 'max:40'],
            'notes'  => ['nullable', 'string', 'max:2000'],
            'tags'   => ['nullable', 'array'],
        ]);
        $c = Contact::create(array_merge($data, ['user_id' => $request->user()->id]));
        return $this->created(['contact' => $this->transform($c)]);
    }

    public function update(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tags'  => ['sometimes', 'nullable', 'array'],
        ]);
        $c->fill($data)->save();
        return $this->ok(['contact' => $this->transform($c->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');
        $c->delete();
        return $this->noContent();
    }

    protected function transform(Contact $c): array
    {
        return [
            'id'         => $c->id,
            'name'       => $c->name,
            'email'      => $c->email,
            'phone'      => $c->phone,
            'notes'      => $c->notes,
            'tags'       => $c->tags ?? [],
            'created_at' => optional($c->created_at)->toIso8601String(),
        ];
    }
}
