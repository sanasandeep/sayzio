<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\DialerNoteShare;
use App\Modules\User\Models\LinkedIdentifier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dialer notes & reminders with server sync.
 *
 * A note belongs to its creator; it can be shared with phone numbers — when a
 * phone maps to a Sayzio account (via linked_identifiers) the note also shows
 * up in that user's list (read-only).
 */
class DialerNoteController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user = $request->user();

        $own = DialerNote::query()
            ->where('user_id', $user->id)
            ->with('shares')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $shared = DialerNote::query()
            ->whereHas('shares', fn ($q) => $q->where('shared_with_user_id', $user->id))
            ->where('user_id', '!=', $user->id)
            ->with('user:id,name')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => [
            'notes' => $own->map(fn ($n) => $this->payload($n, true))->values(),
            'shared' => $shared->map(fn ($n) => $this->payload($n, false))->values(),
        ]]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        $note = DialerNote::create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'number_e164' => isset($data['number']) && $data['number'] !== null && $data['number'] !== ''
                ? ContactPhone::normalize($data['number'])
                : null,
            'remind_at' => $data['remind_at'] ?? null,
            'done' => (bool) ($data['done'] ?? false),
            'color' => $data['color'] ?? null,
            'kind' => $data['kind'] ?? 'note',
            'checklist' => array_key_exists('checklist', $data)
                ? DialerNote::normalizeChecklist($data['checklist'])
                : null,
        ]);

        if (array_key_exists('share_phones', $data)) {
            $this->syncShares($note, $data['share_phones'] ?? []);
        }

        return response()->json(['data' => $this->payload($note->fresh('shares'), true)], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $note = DialerNote::where('user_id', $user->id)->find($id);
        if (!$note) {
            return response()->json(['error' => ['message' => 'Note not found.', 'code' => 'not_found']], 404);
        }

        $data = $this->validated($request);

        $updates = [];
        foreach (['title', 'body', 'remind_at', 'color', 'kind'] as $k) {
            if (array_key_exists($k, $data)) $updates[$k] = $data[$k];
        }
        if (array_key_exists('done', $data)) $updates['done'] = (bool) $data['done'];
        if (array_key_exists('checklist', $data)) {
            $updates['checklist'] = $data['checklist'] === null
                ? null
                : DialerNote::normalizeChecklist($data['checklist']);
        }
        // A changed reminder time re-arms the due alert.
        if (array_key_exists('remind_at', $data)
            && (string) $data['remind_at'] !== (string) $note->remind_at?->toIso8601String()) {
            $updates['reminder_sent_at'] = null;
        }
        if (array_key_exists('number', $data)) {
            $updates['number_e164'] = $data['number'] !== null && $data['number'] !== ''
                ? ContactPhone::normalize($data['number'])
                : null;
        }
        if ($updates !== []) $note->update($updates);

        if (array_key_exists('share_phones', $data)) {
            $this->syncShares($note, $data['share_phones'] ?? []);
        }

        return response()->json(['data' => $this->payload($note->fresh('shares'), true)]);
    }

    public function destroy(Request $request, int $id)
    {
        $note = DialerNote::where('user_id', $request->user()->id)->find($id);
        if (!$note) {
            return response()->json(['error' => ['message' => 'Note not found.', 'code' => 'not_found']], 404);
        }
        $note->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'number' => ['nullable', 'string', 'max:32'],
            'remind_at' => ['nullable', 'date'],
            'done' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:16'],
            'kind' => ['nullable', 'string', 'in:note,checklist'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*' => ['array'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'share_phones' => ['nullable', 'array', 'max:20'],
            'share_phones.*' => ['string', 'max:32'],
        ]);
    }

    /** Replace the share list, resolving each phone to a user when possible. */
    private function syncShares(DialerNote $note, array $phones): void
    {
        $normalized = collect($phones)
            ->map(fn ($p) => ContactPhone::normalize((string) $p))
            ->filter()
            ->unique()
            ->values();

        $note->shares()->whereNotIn('phone_e164', $normalized)->delete();

        foreach ($normalized as $phone) {
            $userId = LinkedIdentifier::resolveUser('phone', $phone)?->id;

            DialerNoteShare::updateOrCreate(
                ['dialer_note_id' => $note->id, 'phone_e164' => $phone],
                ['shared_with_user_id' => $userId !== $note->user_id ? $userId : null],
            );
        }
    }

    private function payload(DialerNote $note, bool $own): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'number' => $note->number_e164,
            'remind_at' => $note->remind_at?->toIso8601String(),
            'done' => (bool) $note->done,
            'color' => $note->color,
            'kind' => $note->kind ?: 'note',
            'checklist' => is_array($note->checklist) ? array_values($note->checklist) : [],
            'source_type' => $note->source_type,
            'source_id' => $note->source_id !== null ? (int) $note->source_id : null,
            'own' => $own,
            'owner_name' => $own ? null : ($note->user?->name),
            'share_phones' => $own
                ? $note->shares->pluck('phone_e164')->values()->all()
                : [],
            'updated_at' => $note->updated_at?->toIso8601String(),
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }
}
