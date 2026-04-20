<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\InboxAggregator;
use Illuminate\Http\Request;

class InboxController
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $aggregator = new InboxAggregator($userId);

        $filters = [
            'source'    => $request->get('source'),
            'form_id'   => $request->get('form_id'),
            'link_id'   => $request->get('link_id'),
            'unread'    => $request->boolean('unread'),
            'starred'   => $request->boolean('starred'),
            'spam'      => $request->boolean('spam'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'q'         => $request->get('q'),
        ];

        $page = (int) $request->get('page', 1) ?: 1;
        $items = $aggregator->paginate($filters, 25, $page);
        $items->withPath($request->url())->appends($request->except('page'));

        $forms = Form::where('user_id', $userId)->orderBy('title')->get(['id', 'title']);
        $links = Link::where('user_id', $userId)->where('type', 'biolink')->orderBy('alias')->get(['id', 'alias']);
        $sourceLabels = InboxAggregator::sourceLabels();
        $unread = $aggregator->unreadCount();

        return view('user.inbox.index', compact('items', 'forms', 'links', 'sourceLabels', 'unread', 'filters'));
    }

    public function show(Request $request, string $type, int $id)
    {
        $userId = $request->user()->id;

        if ($type === InboxAggregator::SOURCE_FORM) {
            $sub = FormSubmission::with('form')->findOrFail($id);
            abort_unless($sub->form && $sub->form->user_id === $userId, 403);
            if (!$sub->is_read) {
                $sub->update(['is_read' => true]);
                InboxAggregator::bustCache($userId);
            }
            return redirect()->route('user.forms.submissions.show', [$sub->form_id, $sub->id]);
        }

        $subscriber = Subscriber::with(['link:id,alias', 'block:id,link_id,type,settings'])->findOrFail($id);
        abort_unless($subscriber->user_id === $userId, 403);
        if (!$subscriber->is_read) {
            $subscriber->update(['is_read' => true, 'read_at' => now()]);
            InboxAggregator::bustCache($userId);
        }
        return view('user.inbox.show-subscriber', compact('subscriber'));
    }

    public function update(Request $request, string $type, int $id)
    {
        $userId = $request->user()->id;
        $action = $request->input('action');
        $valid = ['read', 'unread', 'star', 'unstar', 'spam', 'not_spam', 'delete'];
        abort_unless(in_array($action, $valid, true), 422);

        $model = $this->locate($type, $id, $userId);
        $this->applyAction($model, $action);
        InboxAggregator::bustCache($userId);

        if ($action === 'delete') {
            return redirect()->route('user.inbox.index')->with('success', 'Item deleted.');
        }
        return back()->with('success', 'Updated.');
    }

    public function bulk(Request $request)
    {
        $userId = $request->user()->id;
        $action = $request->input('action');
        $items = (array) $request->input('items', []);
        $valid = ['read', 'unread', 'star', 'unstar', 'spam', 'not_spam', 'delete', 'export'];
        abort_unless(in_array($action, $valid, true), 422);

        if ($action === 'export') {
            return $this->exportItems($userId, $items);
        }

        $skipped = 0;
        foreach ($items as $token) {
            [$type, $id] = array_pad(explode(':', $token, 2), 2, null);
            if (!$type || !$id || !in_array($type, [InboxAggregator::SOURCE_FORM, 'subscriber'], true)) {
                $skipped++;
                continue;
            }
            $model = $this->tryLocate($type, (int)$id, $userId);
            if (!$model) { $skipped++; continue; }
            $this->applyAction($model, $action);
        }
        InboxAggregator::bustCache($userId);
        $msg = 'Bulk action applied.';
        if ($skipped > 0) $msg .= " ({$skipped} skipped — not found or no longer accessible.)";
        return back()->with('success', $msg);
    }

    /**
     * Locate a model owned by the given user, returning null if missing.
     * Used in bulk paths where individual missing items shouldn't abort the whole batch.
     */
    protected function tryLocate(string $type, int $id, int $userId)
    {
        if ($type === InboxAggregator::SOURCE_FORM) {
            $row = FormSubmission::with('form')->find($id);
            if (!$row || !$row->form || $row->form->user_id !== $userId) return null;
            return $row;
        }
        $row = Subscriber::find($id);
        if (!$row || $row->user_id !== $userId) return null;
        return $row;
    }

    protected function csvSafe($v): string
    {
        $v = (string) $v;
        if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $v = "'" . $v;
        }
        return $v;
    }

    protected function fputcsvSafe($h, array $row): void
    {
        fputcsv($h, array_map(fn($v) => $this->csvSafe($v), $row));
    }

    public function exportFiltered(Request $request)
    {
        $userId = $request->user()->id;
        $aggregator = new InboxAggregator($userId);
        $filters = [
            'source'    => $request->get('source'),
            'form_id'   => $request->get('form_id'),
            'link_id'   => $request->get('link_id'),
            'unread'    => $request->boolean('unread'),
            'starred'   => $request->boolean('starred'),
            'spam'      => $request->boolean('spam'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'q'         => $request->get('q'),
        ];

        // Stream all matching items
        $filename = 'inbox-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($aggregator, $filters) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['source', 'source_label', 'name', 'preview', 'submitted_at', 'is_read', 'is_starred', 'is_spam']);
            $page = 1;
            do {
                $p = $aggregator->paginate($filters, 200, $page);
                foreach ($p->items() as $row) {
                    $this->fputcsvSafe($h, [
                        $row->source_type,
                        $row->source_label,
                        $row->name,
                        $row->preview,
                        optional($row->created_at)->toIso8601String(),
                        $row->is_read ? '1' : '0',
                        $row->is_starred ? '1' : '0',
                        $row->is_spam ? '1' : '0',
                    ]);
                }
                $hasMore = $p->hasMorePages();
                $page++;
            } while ($hasMore);
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportItems(int $userId, array $tokens)
    {
        $filename = 'inbox-selection-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($tokens, $userId) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['source', 'name', 'email', 'phone', 'preview', 'submitted_at']);
            foreach ($tokens as $token) {
                [$type, $id] = array_pad(explode(':', $token, 2), 2, null);
                if (!$type || !$id) continue;
                $model = $this->tryLocate($type, (int)$id, $userId);
                if (!$model) continue;
                if ($model instanceof FormSubmission) {
                    $name = $model->data['name'] ?? '';
                    $email = $model->data['email'] ?? '';
                    $phone = $model->data['phone'] ?? '';
                    $preview = collect($model->data ?? [])->reject(fn($v) => is_array($v))->take(5)
                        ->map(fn($v, $k) => "$k=$v")->implode(' | ');
                    $this->fputcsvSafe($h, ['form_submission', $name, $email, $phone, $preview, $model->created_at?->toIso8601String()]);
                } else {
                    $this->fputcsvSafe($h, [
                        'subscriber:' . $model->type,
                        $model->name ?? '',
                        $model->email ?? '',
                        $model->phone ?? '',
                        $model->channel_url ?? '',
                        ($model->subscribed_at ?? $model->created_at)?->toIso8601String(),
                    ]);
                }
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return FormSubmission|Subscriber */
    protected function locate(string $type, int $id, int $userId)
    {
        if ($type === InboxAggregator::SOURCE_FORM) {
            $row = FormSubmission::with('form')->findOrFail($id);
            abort_unless($row->form && $row->form->user_id === $userId, 403);
            return $row;
        }
        $row = Subscriber::findOrFail($id);
        abort_unless($row->user_id === $userId, 403);
        return $row;
    }

    protected function applyAction($model, string $action): void
    {
        $isSub = $model instanceof Subscriber;
        switch ($action) {
            case 'read':     $model->update($isSub ? ['is_read' => true, 'read_at' => now()] : ['is_read' => true]); break;
            case 'unread':   $model->update($isSub ? ['is_read' => false, 'read_at' => null] : ['is_read' => false]); break;
            case 'star':     $model->update(['is_starred' => true]); break;
            case 'unstar':   $model->update(['is_starred' => false]); break;
            case 'spam':     $model->update(['is_spam' => true, 'is_read' => true]); break;
            case 'not_spam': $model->update(['is_spam' => false]); break;
            case 'delete':   $model->delete(); break;
        }
    }
}
