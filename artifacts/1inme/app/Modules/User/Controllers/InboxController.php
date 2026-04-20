<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxReply;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\SpamChecker;
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
        $candidate = trim((string) $subscriber->email);
        $replyTo = ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) ? $candidate : null;
        $replies = InboxReply::where('user_id', $userId)
            ->where('item_type', 'subscriber')
            ->where('item_id', $subscriber->id)
            ->orderByDesc('created_at')
            ->get();
        return view('user.inbox.show-subscriber', compact('subscriber', 'replyTo', 'replies'));
    }

    public function reply(Request $request, string $type, int $id)
    {
        $userId = $request->user()->id;
        abort_unless(in_array($type, [InboxAggregator::SOURCE_FORM, 'subscriber'], true), 404);

        $validated = $request->validate([
            'subject' => 'required|string|max:300',
            'body' => 'required|string|max:20000',
        ]);

        $model = $this->locate($type, $id, $userId);
        $toEmail = trim((string) $this->extractEmail($model));
        abort_unless($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL), 422, 'No usable email address.');

        $user = $request->user();
        $subSettings = ($user->settings ?? [])['subscription'] ?? [];
        $fromName = $subSettings['email_from_name'] ?? config('app.name');
        $fromAddress = $subSettings['email_from_address'] ?? config('mail.from.address', 'noreply@1inme.com');
        $replyTo = $subSettings['email_reply_to'] ?? null;

        $status = 'sent';
        $error = null;

        try {
            if (!empty($subSettings['smtp_host'])) {
                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                    $subSettings['smtp_host'],
                    (int)($subSettings['smtp_port'] ?? 587),
                    ($subSettings['smtp_encryption'] ?? 'tls') !== 'none',
                );
                if (!empty($subSettings['smtp_username'])) {
                    $transport->setUsername($subSettings['smtp_username']);
                    $transport->setPassword($subSettings['smtp_password'] ?? '');
                }
                $mailer = new \Symfony\Component\Mailer\Mailer($transport);
                $email = (new \Symfony\Component\Mime\Email())
                    ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                    ->to($toEmail)
                    ->subject($validated['subject'])
                    ->html(nl2br(e($validated['body'])));
                if ($replyTo) {
                    $email->replyTo(new \Symfony\Component\Mime\Address($replyTo));
                }
                $mailer->send($email);
            } else {
                \Illuminate\Support\Facades\Mail::html(nl2br(e($validated['body'])), function ($m) use ($toEmail, $validated, $fromName, $fromAddress, $replyTo) {
                    $m->to($toEmail)->subject($validated['subject'])->from($fromAddress, $fromName);
                    if ($replyTo) $m->replyTo($replyTo);
                });
            }
        } catch (\Exception $e) {
            $status = 'failed';
            $error = $e->getMessage();
        }

        InboxReply::create([
            'user_id' => $userId,
            'item_type' => $type === InboxAggregator::SOURCE_FORM ? 'form_submission' : 'subscriber',
            'item_id' => $id,
            'to_email' => $toEmail,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        if ($status === 'failed') {
            return back()->withInput()->with('error', 'Reply failed: ' . $error);
        }
        return back()->with('success', 'Reply sent to ' . $toEmail . '.');
    }

    protected function extractEmail($model): ?string
    {
        if ($model instanceof Subscriber) {
            return $model->email;
        }
        if ($model instanceof FormSubmission) {
            $data = $model->data ?? [];
            foreach (['email', 'Email', 'e_mail', 'email_address'] as $k) {
                if (!empty($data[$k]) && is_string($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                    return $data[$k];
                }
            }
            foreach ($data as $v) {
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    return $v;
                }
            }
        }
        return null;
    }

    public function update(Request $request, string $type, int $id)
    {
        $user = $request->user();
        $userId = $user->id;
        $action = $request->input('action');
        $valid = ['read', 'unread', 'star', 'unstar', 'spam', 'not_spam', 'not_spam_trust', 'delete'];
        abort_unless(in_array($action, $valid, true), 422);

        $model = $this->locate($type, $id, $userId);
        $this->applyAction($model, $action);
        if ($action === 'not_spam_trust') {
            $this->trustSender($user, $model);
        }
        InboxAggregator::bustCache($userId);

        if ($action === 'delete') {
            return redirect()->route('user.inbox.index')->with('success', 'Item deleted.');
        }
        if ($action === 'not_spam_trust') {
            return back()->with('success', 'Marked not spam and added sender to trusted list.');
        }
        return back()->with('success', 'Updated.');
    }

    public function bulk(Request $request)
    {
        $userId = $request->user()->id;
        $action = $request->input('action');
        $items = (array) $request->input('items', []);
        $valid = ['read', 'unread', 'star', 'unstar', 'spam', 'not_spam', 'not_spam_trust', 'delete', 'export'];
        abort_unless(in_array($action, $valid, true), 422);

        if ($action === 'export') {
            return $this->exportItems($userId, $items);
        }

        $skipped = 0;
        $user = $request->user();
        foreach ($items as $token) {
            [$type, $id] = array_pad(explode(':', $token, 2), 2, null);
            if (!$type || !$id || !in_array($type, [InboxAggregator::SOURCE_FORM, 'subscriber'], true)) {
                $skipped++;
                continue;
            }
            $model = $this->tryLocate($type, (int)$id, $userId);
            if (!$model) { $skipped++; continue; }
            $this->applyAction($model, $action);
            if ($action === 'not_spam_trust') {
                $this->trustSender($user, $model);
            }
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
            case 'not_spam':
            case 'not_spam_trust':
                $model->update(['is_spam' => false]); break;
            case 'delete':   $model->delete(); break;
        }
    }

    /**
     * Add the sender's email/phone (whichever we can extract) to the user's
     * trusted-senders list so future submissions from them skip the spam
     * heuristics. Quietly no-ops when the sender has no usable identifier.
     */
    protected function trustSender($user, $model): void
    {
        $checker = app(SpamChecker::class);
        $email = $checker->normalizeEmail($this->extractEmail($model));
        $phone = $checker->normalizePhone($this->extractPhone($model));
        if ($email === null && $phone === null) return;

        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];
        $trustedEmails = array_values(array_filter((array)($spam['trusted_emails'] ?? []), 'is_string'));
        $trustedPhones = array_values(array_filter((array)($spam['trusted_phones'] ?? []), 'is_string'));

        if ($email !== null && !in_array($email, $trustedEmails, true)) {
            $trustedEmails[] = $email;
        }
        if ($phone !== null && !in_array($phone, $trustedPhones, true)) {
            $trustedPhones[] = $phone;
        }
        $spam['trusted_emails'] = $trustedEmails;
        $spam['trusted_phones'] = $trustedPhones;
        $settings['spam'] = $spam;
        $user->update(['settings' => $settings]);
    }

    protected function extractPhone($model): ?string
    {
        if ($model instanceof Subscriber) {
            return $model->phone;
        }
        if ($model instanceof FormSubmission) {
            $data = $model->data ?? [];
            foreach (['phone', 'Phone', 'tel', 'mobile', 'phone_number'] as $k) {
                if (!empty($data[$k]) && is_string($data[$k])) {
                    return $data[$k];
                }
            }
        }
        return null;
    }

    public function settings(Request $request)
    {
        $user = $request->user();
        $checker = app(SpamChecker::class);
        $spam = $checker->loadUserSpamSettings($user->id);
        $defaults = SpamChecker::BLOCKED_KEYWORDS;

        // Recent keyword-hit counts (last 30 days), broken down per keyword.
        // Helps creators see which custom keywords are actually firing — and
        // which defaults might be over-aggressive in their niche.
        $since = now()->subDays(30);
        $formIds = Form::where('user_id', $user->id)->pluck('id');
        $reasons = collect();
        if ($formIds->isNotEmpty()) {
            $reasons = $reasons->concat(
                FormSubmission::whereIn('form_id', $formIds)
                    ->where('is_spam', true)
                    ->whereNotNull('spam_reason')
                    ->where('created_at', '>=', $since)
                    ->pluck('spam_reason')
            );
        }
        $reasons = $reasons->concat(
            Subscriber::where('user_id', $user->id)
                ->where('is_spam', true)
                ->whereNotNull('spam_reason')
                ->where('created_at', '>=', $since)
                ->pluck('spam_reason')
        );

        $keywordHits = [];   // keyword (lowercased) => count
        $ruleHits = [        // structured-rule counts for the summary strip
            'blocked_keyword' => 0,
            'too_many_links'  => 0,
            'rate_limit'      => 0,
            'honeypot'        => 0,
        ];
        foreach ($reasons as $r) {
            $p = SpamChecker::parseReason($r);
            if (!$p) continue;
            $code = $p['code'];
            if (isset($ruleHits[$code])) $ruleHits[$code]++;
            if ($code === 'blocked_keyword' && $p['detail']) {
                $k = mb_strtolower($p['detail']);
                $keywordHits[$k] = ($keywordHits[$k] ?? 0) + 1;
            }
        }
        arsort($keywordHits);

        return view('user.inbox.spam-settings', compact('spam', 'defaults', 'keywordHits', 'ruleHits'));
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'blocked_keywords'          => 'nullable|string|max:5000',
            'disabled_default_keywords' => 'nullable|array',
            'disabled_default_keywords.*' => 'string|max:200',
            'trusted_emails'            => 'nullable|string|max:5000',
            'trusted_phones'            => 'nullable|string|max:5000',
        ]);

        $checker = app(SpamChecker::class);
        $defaultLowerSet = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);

        $blocked = $this->splitLines($validated['blocked_keywords'] ?? '');
        // Drop user-added keywords that duplicate (case-insensitively) a
        // default they didn't disable — defaults already cover them.
        $disabledRaw = array_map('mb_strtolower', (array)($validated['disabled_default_keywords'] ?? []));
        $disabled = array_values(array_intersect($disabledRaw, $defaultLowerSet));

        $blocked = array_values(array_filter(
            $blocked,
            fn($kw) => !in_array(mb_strtolower($kw), $defaultLowerSet, true)
                   || in_array(mb_strtolower($kw), $disabled, true)
        ));

        $emails = array_values(array_filter(array_map(
            fn($e) => $checker->normalizeEmail($e),
            $this->splitLines($validated['trusted_emails'] ?? '')
        ), fn($e) => $e !== null && filter_var($e, FILTER_VALIDATE_EMAIL)));

        $phones = array_values(array_filter(array_map(
            fn($p) => $checker->normalizePhone($p),
            $this->splitLines($validated['trusted_phones'] ?? '')
        )));

        $settings = $user->settings ?? [];
        $settings['spam'] = [
            'blocked_keywords'          => array_values(array_unique($blocked)),
            'disabled_default_keywords' => array_values(array_unique($disabled)),
            'trusted_emails'            => array_values(array_unique($emails)),
            'trusted_phones'            => array_values(array_unique($phones)),
        ];
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Spam settings saved.');
    }

    /**
     * One-click "stop blocking this keyword" action used from chips on the
     * Spam Settings page and from the spam-reason banner in the inbox.
     * If the keyword matches a default (case-insensitively) it's added to
     * `disabled_default_keywords`; if it matches a custom entry it's removed
     * from `blocked_keywords`. Unknown keywords are a no-op so stale links
     * don't 500.
     */
    public function disableKeyword(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:200',
        ]);
        $kwRaw = trim($validated['keyword']);
        if ($kwRaw === '') {
            return back()->with('error', 'No keyword provided.');
        }
        $kwLower = mb_strtolower($kwRaw);

        $user = $request->user();
        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];

        $defaultsLower = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);
        $blocked  = array_values(array_filter((array)($spam['blocked_keywords'] ?? []), 'is_string'));
        $disabled = array_values(array_filter((array)($spam['disabled_default_keywords'] ?? []), 'is_string'));

        $changed = false;
        $isDefault = in_array($kwLower, $defaultsLower, true);
        if ($isDefault) {
            $disabledLower = array_map('mb_strtolower', $disabled);
            if (!in_array($kwLower, $disabledLower, true)) {
                // Store with the canonical default casing so the settings
                // page checkbox matches by exact value.
                $idx = array_search($kwLower, $defaultsLower, true);
                $disabled[] = SpamChecker::BLOCKED_KEYWORDS[$idx];
                $changed = true;
            }
        }

        $newBlocked = array_values(array_filter(
            $blocked,
            fn($kw) => mb_strtolower($kw) !== $kwLower
        ));
        if (count($newBlocked) !== count($blocked)) {
            $blocked = $newBlocked;
            $changed = true;
        }

        if (!$changed) {
            return back()->with('error', '“' . $kwRaw . '” isn\'t in your blocked keyword list.');
        }

        $spam['blocked_keywords']          = array_values(array_unique($blocked));
        $spam['disabled_default_keywords'] = array_values(array_unique($disabled));
        $settings['spam'] = $spam;
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Stopped blocking “' . $kwRaw . '”. New submissions matching it won\'t be flagged.');
    }

    public function importTrustedCsv(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|max:5120|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel|mimes:csv,txt',
        ]);

        $user = $request->user();
        $checker = app(SpamChecker::class);

        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];
        // Re-normalize existing entries before building the dedupe maps so a
        // previously-stored "VIP@Example.com" still matches an incoming
        // "vip@example.com" instead of being added a second time.
        $trustedEmails = [];
        foreach ((array)($spam['trusted_emails'] ?? []) as $e) {
            if (!is_string($e)) continue;
            $n = $checker->normalizeEmail($e);
            if ($n !== null) $trustedEmails[] = $n;
        }
        $trustedPhones = [];
        foreach ((array)($spam['trusted_phones'] ?? []) as $p) {
            if (!is_string($p)) continue;
            $n = $checker->normalizePhone($p);
            if ($n !== null) $trustedPhones[] = $n;
        }
        $trustedEmails = array_values(array_unique($trustedEmails));
        $trustedPhones = array_values(array_unique($trustedPhones));
        $existingEmails = array_flip($trustedEmails);
        $existingPhones = array_flip($trustedPhones);

        $emailsAdded   = 0;
        $phonesAdded   = 0;
        $duplicates    = 0;
        $invalidValues = 0;
        $invalidRows   = 0;
        $rowsRead      = 0;

        $emailKeys = ['email', 'e_mail', 'email_address', 'mail'];
        $phoneKeys = ['phone', 'tel', 'telephone', 'mobile', 'phone_number', 'cell'];

        $h = fopen($request->file('csv')->getRealPath(), 'r');
        if ($h === false) {
            return back()->with('error', 'Could not read uploaded file.');
        }

        // Strip UTF-8 BOM from the first row, if present.
        $first = fgetcsv($h);
        if ($first === false) {
            fclose($h);
            return back()->with('error', 'CSV is empty.');
        }
        if (isset($first[0])) {
            $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$first[0]);
        }

        // Detect headers by checking if any cell matches a known column name.
        $emailIdx = null;
        $phoneIdx = null;
        $hasHeader = false;
        foreach ($first as $i => $cell) {
            $key = mb_strtolower(trim((string)$cell));
            if (in_array($key, $emailKeys, true)) { $emailIdx = $i; $hasHeader = true; }
            if (in_array($key, $phoneKeys, true)) { $phoneIdx = $i; $hasHeader = true; }
        }

        $process = function (array $row) use (
            &$emailsAdded, &$phonesAdded, &$duplicates, &$invalidValues, &$invalidRows, &$rowsRead,
            &$existingEmails, &$existingPhones, &$trustedEmails, &$trustedPhones,
            $emailIdx, $phoneIdx, $checker
        ) {
            $rowsRead++;
            $rowEmails = [];
            $rowPhones = [];

            if ($emailIdx !== null && isset($row[$emailIdx])) {
                $rowEmails[] = $row[$emailIdx];
            }
            if ($phoneIdx !== null && isset($row[$phoneIdx])) {
                $rowPhones[] = $row[$phoneIdx];
            }
            // No headers → guess: email-looking cells go to emails, phone-ish cells to phones.
            if ($emailIdx === null && $phoneIdx === null) {
                foreach ($row as $cell) {
                    $cell = trim((string)$cell);
                    if ($cell === '') continue;
                    if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                        $rowEmails[] = $cell;
                    } elseif (preg_match('/^\+?[\d().\-\s]{7,}$/', $cell)) {
                        $rowPhones[] = $cell;
                    }
                }
            }

            $usedAny = false;
            foreach ($rowEmails as $raw) {
                $raw = trim((string)$raw);
                if ($raw === '') continue;
                $usedAny = true;
                $norm = $checker->normalizeEmail($raw);
                if ($norm === null || !filter_var($norm, FILTER_VALIDATE_EMAIL)) {
                    $invalidValues++;
                    continue;
                }
                if (isset($existingEmails[$norm])) {
                    $duplicates++;
                    continue;
                }
                $existingEmails[$norm] = true;
                $trustedEmails[] = $norm;
                $emailsAdded++;
            }
            foreach ($rowPhones as $raw) {
                $raw = trim((string)$raw);
                if ($raw === '') continue;
                $usedAny = true;
                $norm = $checker->normalizePhone($raw);
                if ($norm === null || strlen($norm) < 7) {
                    $invalidValues++;
                    continue;
                }
                if (isset($existingPhones[$norm])) {
                    $duplicates++;
                    continue;
                }
                $existingPhones[$norm] = true;
                $trustedPhones[] = $norm;
                $phonesAdded++;
            }
            if (!$usedAny) {
                $invalidRows++;
            }
        };

        if (!$hasHeader) {
            $process($first);
        }
        while (($row = fgetcsv($h)) !== false) {
            // Skip blank rows (single empty cell).
            if (count($row) === 1 && trim((string)$row[0]) === '') continue;
            $process($row);
        }
        fclose($h);

        $settings['spam'] = array_merge($spam, [
            'trusted_emails' => array_values(array_unique($trustedEmails)),
            'trusted_phones' => array_values(array_unique($trustedPhones)),
        ]);
        $user->update(['settings' => $settings]);

        $msg = "Imported {$rowsRead} row(s): added {$emailsAdded} email(s), {$phonesAdded} phone(s)";
        if ($duplicates > 0)    $msg .= ", {$duplicates} duplicate(s) skipped";
        if ($invalidValues > 0) $msg .= ", {$invalidValues} invalid value(s) skipped";
        if ($invalidRows > 0)   $msg .= ", {$invalidRows} unusable row(s) skipped";
        $msg .= '.';
        return back()->with('success', $msg);
    }

    protected function splitLines(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }
}
