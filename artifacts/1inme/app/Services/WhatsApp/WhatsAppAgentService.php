<?php

namespace App\Services\WhatsApp;

use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WhatsAppAgentConversation;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Integrations\IntegrationKeySettings;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one inbound WhatsApp message into a reply (Task #2759).
 *
 * Flow: resolve the sender to a Sayzio user by their verified WhatsApp
 * number → gate (agent on, registered, verified, paid plan, AI engine
 * on) → run a bounded OpenAI function-calling loop where the model
 * drives WhatsAppAgentTools to actually create/edit links → reply with
 * the result. The chat calls meter against the coin wallet under the
 * `whatsapp_agent` feature; if the turn throws after we've charged, the
 * accumulated credits are auto-refunded so a failed turn never nets a
 * charge.
 *
 * Inbound media (handled before this service runs) arrives as pending
 * items on the conversation: images/files as vault references, voice
 * notes already transcribed into the message text.
 */
class WhatsAppAgentService
{
    public const FEATURE = 'whatsapp_agent';

    private const MAX_TOOL_ITERATIONS = 5;
    private const MAX_OUTPUT_TOKENS   = 700;

    public function __construct(
        private OpenAiService $openai,
        private AiUsageCharger $credits,
        private WhatsAppCloudApi $cloud,
    ) {}

    /**
     * Handle one inbound text turn from $waPhone. $pendingMedia are media
     * items collected from this (and recent) messages — see
     * WhatsAppAgentTools for the shape.
     *
     * @param array<int,array<string,mixed>> $pendingMedia
     */
    public function handle(string $waPhone, string $text, array $pendingMedia = []): void
    {
        // Master switch — silently ignore when the agent is off so we don't
        // reply to numbers that happen to message a configured business line.
        if (!IntegrationKeySettings::whatsappAgentEnabled()) {
            return;
        }

        $user = LinkedIdentifier::resolveUser('phone', $waPhone);

        $conversation = $user
            ? $this->conversationFor($user, $waPhone)
            : null;

        // Stash any media the user sent even if the current message is empty,
        // so "here's a photo" followed by "make a bio page with it" works.
        if ($conversation && $pendingMedia) {
            foreach ($pendingMedia as $m) $conversation->pushPending($m);
            $conversation->save();
        }

        $text = trim($text);
        if ($text === '') {
            // Media-only message with nothing to act on yet — acknowledge.
            if ($pendingMedia && $user) {
                $this->reply($waPhone, "Got it — I've saved that. Tell me what you'd like me to make with it (a bio page, a download link, a QR code…).");
            }
            return;
        }

        // ── Gating ────────────────────────────────────────────────
        if (!$user) {
            $this->reply($waPhone, "I don't recognise this number. Add and verify your WhatsApp number in your Sayzio account settings first, then message me again.");
            return;
        }

        if (!AiEngineSettings::isEnabled()) {
            $this->reply($waPhone, "The AI assistant is temporarily unavailable. Please try again later.");
            return;
        }

        if (!AiPlanAccess::featureAllowed($user, self::FEATURE)) {
            $plan = AiPlanAccess::featureUpgradePlan($user, self::FEATURE);
            $msg = $plan
                ? "The WhatsApp assistant is a paid feature. Upgrade to {$plan->name} to use it."
                : "The WhatsApp assistant isn't included in your current plan. Upgrade to use it.";
            $this->reply($waPhone, $msg);
            return;
        }

        $conversation = $conversation ?: $this->conversationFor($user, $waPhone);

        try {
            $reply = $this->runLoop($user, $conversation, $text);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp agent loop failed: ' . $e->getMessage());
            $reply = 'Sorry — something went wrong handling that. Please try again.';
        }

        $this->reply($waPhone, $reply);
    }

    /**
     * Bounded function-calling loop. Returns the assistant's final text.
     * Charges `whatsapp_agent` on each model call; auto-refunds the full
     * turn if anything throws after a charge.
     */
    private function runLoop(User $user, WhatsAppAgentConversation $conversation, string $text): string
    {
        $pending = $conversation->takePending();
        $tools   = new WhatsAppAgentTools($user, $pending);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($user)]],
            is_array($conversation->history) ? $conversation->history : [],
            [['role' => 'user', 'content' => $text]],
        );

        $model = AiEngineSettings::featureModel(self::FEATURE, $user);
        $toolDefs = $tools->functionDefinitions();

        $totalCredits = 0;
        $finalText = '';

        try {
            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $out = $this->openai->chat($user, $model, $messages, [
                    'feature'     => self::FEATURE,
                    'temperature' => 0.3,
                    'max_tokens'  => self::MAX_OUTPUT_TOKENS,
                    'reason'      => 'WhatsApp agent turn',
                    'tools'       => $toolDefs,
                ]);
                $totalCredits += (int) ($out['credits_spent'] ?? 0);

                $toolCalls = $out['tool_calls'] ?? [];
                if (!$toolCalls) {
                    $finalText = (string) ($out['content'] ?? '');
                    break;
                }

                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $out['content'] !== '' ? $out['content'] : null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $call) {
                    $name = (string) ($call['function']['name'] ?? '');
                    $callId = (string) ($call['id'] ?? '');
                    $args = $this->decodeArgs($call['function']['arguments'] ?? '');

                    $result = $tools->run($name, $args);

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $callId,
                        'content'      => (string) ($result['summary'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            if ($totalCredits > 0) {
                $this->credits->refund($user, $totalCredits, [
                    'feature' => self::FEATURE,
                    'reason'  => 'WhatsApp agent turn failed — auto refund',
                ]);
            }
            throw $e;
        }

        // Fall back to a deterministic summary if the model produced tool
        // results but no closing text.
        if (trim($finalText) === '') {
            if (!empty($tools->touched)) {
                $finalText = "Done! " . $tools->touched[count($tools->touched) - 1]->getShortUrl();
            } else {
                $finalText = "I'm not sure what to make. Try: \"shorten https://example.com\", \"make a bio page for my coffee shop\", or \"create a QR code for my menu\".";
            }
        }

        // Persist the trimmed conversation window for the next turn.
        $conversation->pushHistory('user', $text);
        $conversation->pushHistory('assistant', $finalText);
        $conversation->last_message_at = now();
        $conversation->save();

        return $finalText;
    }

    private function systemPrompt(User $user): string
    {
        $name = $user->name ? " The user's name is {$user->name}." : '';
        return <<<PROMPT
You are Sayzio's WhatsApp assistant. You help the user create and manage links by chatting on WhatsApp.{$name}

You can create these link types via your tools: link-in-bio pages, short links, QR codes, file download links, calendar events, and contact cards (vCards). You can also scan business cards.

Rules:
- Use a tool whenever the user wants something built. Do not claim you created something unless a tool returned success.
- Keep replies short and friendly — this is WhatsApp. Always include the resulting short URL when a link is created.
- If the user sent an image or file, you can use it (set use_images for bio pages, use create_file_link for downloads, or use scan_card if it looks like a business card).
- If the user sends a photo of a business card (or says "scan this card", "read this card", "save this contact"), use the scan_card tool. If they also say "save as contact" or "add to contacts", set save_as_contact to true.
- If the user sends a second card photo right after the first, both will be included in a single scan automatically.
- If a request is ambiguous or missing required info, ask one short clarifying question instead of guessing.
- You can only manage links and contacts; politely decline anything else.
PROMPT;
    }

    private function decodeArgs(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function conversationFor(User $user, string $waPhone): WhatsAppAgentConversation
    {
        return WhatsAppAgentConversation::firstOrCreate(
            ['user_id' => $user->id, 'wa_phone' => preg_replace('/\D+/', '', $waPhone) ?? $waPhone],
            ['history' => [], 'pending' => []],
        );
    }

    private function reply(string $waPhone, string $body): void
    {
        $this->cloud->sendText($waPhone, $body);
    }
}
