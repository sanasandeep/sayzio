<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Str;

/**
 * Drafts an AI reply for an email thread extracted from a browser mailbox
 * (Gmail or Outlook web). Works with raw thread data — no InboxThread model —
 * so it is callable from the browser extension without any prior import step.
 *
 * Key properties that differ from InboxAiReplyDrafter:
 *  - Input is a plain PHP array (subject / participants / messages[]) rather
 *    than an Eloquent InboxThread.
 *  - Supports optional AiMind knowledge-base grounding with citations.
 *  - Resolves {{link:ID}} tokens to their short URLs in the returned draft so
 *    the extension can insert plain text into the mailbox compose box.
 *  - Does NOT append a workspace signature (the user writes email in their own
 *    mail client, not through Sayzio outbox).
 *
 * Exceptions (disabled engine, InsufficientCoinsForAiException, network)
 * bubble up so the controller can map them to the correct HTTP status.
 */
class MailboxAiReplyDrafter
{
    public const FEATURE = 'inbox_agent';

    protected const MAX_MESSAGES = 20;
    protected const MAX_BODY_CHARS = 3000;

    public function __construct(
        protected OpenAiService $openai,
        protected AiMindQueryService $mindQuery,
    ) {}

    /**
     * @param array{
     *   subject: string,
     *   participants: string[],
     *   messages: array<array{role: string, sender: string, body: string}>,
     * } $thread Extracted from the browser extension content script.
     * @param int[]  $mindIds          User-owned AiMind IDs to ground on.
     * @param bool   $includeLinks     Inject the user's own links catalogue.
     * @param string $instruction      Optional user note ("shorter", "formal").
     *
     * @return array{
     *   draft: string,
     *   citations: array<array{id: int, name: string}>,
     *   credits_spent: int,
     *   model: string,
     * }
     */
    public function draft(
        array   $thread,
        User    $user,
        array   $mindIds     = [],
        bool    $includeLinks = true,
        string  $instruction  = '',
    ): array {
        $model = AiEngineSettings::featureModel(self::FEATURE);

        [$groundingBlock, $citations] = $this->groundingContext($user, $mindIds, $thread);
        $linksBlock = $includeLinks ? $this->linksContextBlock($user->id) : '';
        $messages   = $this->buildMessages($thread, $groundingBlock, $linksBlock, $instruction);

        $res = $this->openai->chat(
            $user,
            $model,
            $messages,
            [
                'feature'     => self::FEATURE,
                'temperature' => 0.7,
                'max_tokens'  => 600,
                'reason'      => 'Mailbox AI reply draft',
            ],
        );

        $draft = trim((string) ($res['content'] ?? ''));

        if ($includeLinks) {
            $draft = $this->resolveTokensToUrls($draft, $user->id);
        }

        return [
            'draft'         => $draft,
            'citations'     => $citations,
            'credits_spent' => (int) ($res['credits_spent'] ?? 0),
            'model'         => (string) ($res['model'] ?? $model),
        ];
    }

    protected function buildMessages(
        array  $thread,
        string $groundingBlock,
        string $linksBlock,
        string $instruction,
    ): array {
        $subject      = Str::limit((string) ($thread['subject'] ?? '(no subject)'), 200, '');
        $participants = implode(', ', array_slice((array) ($thread['participants'] ?? []), 0, 10));

        $system = <<<PROMPT
You are an email reply assistant helping the user write a reply to the email
thread below. Write a single, ready-to-send reply on behalf of the user (first
person). The reply should be concise, warm and genuinely address what was asked.

Rules:
- Never invent facts, dates, prices, links or commitments you cannot verify
  from the thread or the context below.
- If (and only if) it is genuinely relevant, reference one of the user's Sayzio
  links listed below by inserting its exact token {{link:ID}} into the text.
  Never invent link IDs or write out raw URLs yourself.
- Do not add a subject line, greeting placeholder like "[Name]", or a sign-off
  block. Output only the reply body text.{$groundingBlock}{$linksBlock}
PROMPT;

        $messages = [['role' => 'system', 'content' => $system]];

        $header = "Thread subject: {$subject}";
        if ($participants !== '') {
            $header .= "\nParticipants: {$participants}";
        }
        $messages[] = ['role' => 'system', 'content' => $header];

        $history = array_slice((array) ($thread['messages'] ?? []), -self::MAX_MESSAGES);
        foreach ($history as $msg) {
            $role   = ($msg['role'] ?? 'user') === 'outbound' ? 'assistant' : 'user';
            $sender = isset($msg['sender']) && $msg['sender'] !== ''
                ? "[{$msg['sender']}] "
                : '';
            $body = Str::limit((string) ($msg['body'] ?? ''), self::MAX_BODY_CHARS, '');
            $messages[] = ['role' => $role, 'content' => $sender . $body];
        }

        if ($instruction !== '') {
            $messages[] = [
                'role'    => 'user',
                'content' => 'Please adjust the reply: ' . Str::limit($instruction, 300, ''),
            ];
        }

        return $messages;
    }

    /**
     * Fetch grounding context from the selected AiMinds. Returns the context
     * block string and the citations array for the API response.
     *
     * @return array{0: string, 1: array<array{id:int,name:string}>}
     */
    protected function groundingContext(User $user, array $mindIds, array $thread): array
    {
        if (empty($mindIds)) {
            return ['', []];
        }

        $mindIds = array_values(array_unique(array_map('intval', $mindIds)));
        $minds   = AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->whereIn('id', $mindIds)
            ->get();

        if ($minds->isEmpty()) {
            return ['', []];
        }

        $subject  = (string) ($thread['subject'] ?? '');
        $lastBody = '';
        $history  = (array) ($thread['messages'] ?? []);
        if (!empty($history)) {
            $last     = end($history);
            $lastBody = (string) ($last['body'] ?? '');
        }
        $query = trim($subject . ' ' . Str::limit($lastBody, 500, ''));
        if ($query === '') {
            return ['', []];
        }

        try {
            $ctx = $this->mindQuery->retrieveContext($user, $minds->all(), $query);
        } catch (\Throwable) {
            return ['', []];
        }

        $contextText = (string) ($ctx['context'] ?? '');
        if ($contextText === '') {
            return ['', []];
        }

        $citations = collect($ctx['citations'] ?? [])->map(fn ($c) => [
            'id'   => (int) ($c['mind_id'] ?? 0),
            'name' => (string) ($c['mind_name'] ?? 'AI Mind'),
        ])->filter(fn ($c) => $c['id'] > 0)->values()->all();

        $block = "\n\nKnowledge Base context (use this to inform your reply where relevant):\n{$contextText}";

        return [$block, $citations];
    }

    /**
     * A short catalogue of the user's own active links so the model can
     * reference them via {{link:ID}} tokens.
     */
    protected function linksContextBlock(int $userId): string
    {
        $links = Link::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get(['id', 'title', 'alias', 'type']);

        if ($links->isEmpty()) {
            return '';
        }

        $lines = $links->map(function (Link $l) {
            $title = $l->title ?: ($l->alias ?: 'Untitled');
            return "- {{link:{$l->id}}} — \"{$title}\" ({$l->type_label})";
        })->implode("\n");

        return "\n\nYour Sayzio links you may reference (token — title — type):\n{$lines}";
    }

    /**
     * Replace {{link:ID}} tokens with their resolved short URLs. Foreign /
     * unresolvable IDs are silently dropped so the draft reads cleanly.
     */
    protected function resolveTokensToUrls(string $draft, int $userId): string
    {
        if (!preg_match_all('/\{\{\s*link:(\d+)\s*\}\}/i', $draft, $m)) {
            return $draft;
        }

        $ids   = array_values(array_unique(array_map('intval', $m[1])));
        $links = Link::where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get(['id', 'alias', 'title'])
            ->keyBy('id');

        return preg_replace_callback(
            '/\{\{\s*link:(\d+)\s*\}\}/i',
            function (array $match) use ($links) {
                $id   = (int) $match[1];
                $link = $links->get($id);
                if (!$link) {
                    return '';
                }
                return $link->getShortUrl();
            },
            $draft,
        );
    }
}
