<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriberController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $q = Subscriber::where('user_id', $request->user()->id);

        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('email', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $page = $q->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        $items = collect($page->items())->map(fn (Subscriber $s) => [
            'id'             => $s->id,
            'type'           => $s->type,
            'email'          => $s->email,
            'phone'          => $s->phone,
            'name'           => $s->name,
            'status'         => $s->status,
            'source'         => $s->source,
            'is_read'        => (bool) $s->is_read,
            'is_starred'     => (bool) $s->is_starred,
            'subscribed_at'  => optional($s->subscribed_at)->toIso8601String(),
            'unsubscribed_at'=> optional($s->unsubscribed_at)->toIso8601String(),
            'created_at'     => optional($s->created_at)->toIso8601String(),
        ])->all();

        return $this->ok([
            'items' => $items,
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $sub = Subscriber::where('user_id', $request->user()->id)->find($id);
        if (!$sub) return $this->notFound('Subscriber not found');
        $sub->forceFill(['status' => 'unsubscribed', 'unsubscribed_at' => now()])->save();
        return $this->ok(['unsubscribed' => true]);
    }
}
