<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\Common\Services\LinkReferenceRenderer;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Str;

/**
 * Drafts a reply for an inbox thread from its full message history plus the
 * workspace's configured tone / persona / signature. Used by both the
 * manual "Draft with AI" composer button and the autopilot agent.
 *
 * This service deliberately does NOT swallow OpenAiService exceptions
 * (disabled engine, missing key, InsufficientCoinsForAiException, network)
 * so the manual endpoint can surface precise 402/403 messaging. Background
 * callers (autopilot) wrap it in their own try/catch.
 */
class InboxAiReplyDrafter
{
    public const FEATURE = 'inbox_agent';

    /** Cap how much history we feed the model. */
    protected const MAX_HISTORY_MESSAGES = 24;

    public function __construct(protected OpenAiService $openai) {}

    /**
     * @return array{draft:string,credits_spent:int,model:string}
     */
    public function draft(InboxThread $thread, User $chargeUser, Workspace $ws): array
    {
        $cfg = InboxAgentSettings::for($ws);
        $model = AiEngineSettings::featureModel(self::FEATURE);

        $res = $this->openai->chat(
            $chargeUser,
            $model,
            $this->messages($thread, $cfg),
            [
                'feature'     => self::FEATURE . '.draft',
                'related_id'  => $thread->id,
                'temperature' => 0.7,
                'max_tokens'  => 600,
                'reason'      => 'Inbox Agent reply draft',
            ],
        );

        $draft = trim((string) ($res['content'] ?? ''));
        $draft = $this->withSignature($draft, (string) ($cfg['signature'] ?? ''));

        return [
            'draft'         => $draft,
            'credits_spent' => (int) ($res['credits_spent'] ?? 0),
            'model'         => (string) ($res['model'] ?? $model),
        ];
    }

    protected function messages(InboxThread $thread, array $cfg): array
    {
        $tone = (string) ($cfg['tone'] ?? 'auto');
        $persona = trim((string) ($cfg['persona'] ?? ''));

        $toneLine = $tone === 'auto'
            ? 'Match the tone and formality of the sender.'
            : 'Write in a ' . InboxAgentSettings::toneLabel($tone) . ' tone.';

        $personaBlock = $persona !== ''
            ? "\n\nAbout the creator you are writing as (voice & context):\n{$persona}"
            : '';

        $linksBlock = $this->linksContextBlock($thread->user_id);

        $system = <<<PROMPT
You are the reply assistant for a creator's unified message inbox. Write a
single, ready-to-send reply to the most recent inbound message in the
conversation below, on behalf of the creator (first person, as the creator).

Rules:
- {$toneLine}
- Be concise, warm and genuinely helpful. Address what the sender actually asked.
- Never invent facts, prices, dates, links or commitments you cannot support.
  If something needs the creator's input, ask a brief clarifying question.
- If (and only if) it is genuinely relevant to point the sender to one of the
  creator's own links/pages listed below, reference it by writing its exact
  token, e.g. {{link:123}}, inline in the reply text. Never write out a URL
  yourself and never invent a link, id, or token that is not in the list.
- Do not add a subject line, greeting placeholders like "[Name]", or a sign-off
  signature block — a signature is appended separately.
- Output only the reply body text, no quotes or commentary.{$personaBlock}{$linksBlock}
PROMPT;

        $messages = [['role' => 'system', 'content' => $system]];

        $header = trim(implode(' · ', array_filter([
            $thread->channelLabel(),
            $thread->categoryLabel(),
            $thread->sender_name ? 'From: ' . $thread->sender_name : null,
            $thread->subject ?: null,
        ])));
        if ($header !== '') {
            $messages[] = ['role' => 'system', 'content' => 'Thread: ' . $header];
        }

        $history = $thread->messages()
            ->orderByDesc('sent_at')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        if ($history->isEmpty() && $thread->preview) {
            $messages[] = ['role' => 'user', 'content' => Str::limit((string) $thread->preview, 4000, '')];
            return $messages;
        }

        foreach ($history as $m) {
            $messages[] = [
                'role'    => $m->direction === 'out' ? 'assistant' : 'user',
                'content' => Str::limit((string) $m->body, 4000, ''),
            ];
        }

        return $messages;
    }

    /**
     * A short catalogue of the creator's own links (id/title/type/short URL)
     * so the model can reference a real destination via a {{link:ID}} token
     * instead of fabricating one. Empty when the creator has no links yet.
     */
    protected function linksContextBlock(int $ownerUserId): string
    {
        $links = Link::where('user_id', $ownerUserId)
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

        return "\n\nThe creator's own links/pages you may reference (token — title — type):\n{$lines}";
    }

    protected function withSignature(string $draft, string $signature): string
    {
        $signature = trim($signature);
        if ($signature === '' || $draft === '') {
            return $draft;
        }
        // Avoid double-appending if the model already echoed it.
        if (str_contains($draft, $signature)) {
            return $draft;
        }
        return rtrim($draft) . "\n\n" . $signature;
    }
}
