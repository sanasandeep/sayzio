<?php

namespace App\Services\AI\Voice;

use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\ElevenLabsService;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use App\Services\AI\WhisperService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator for one Voice Assistant turn:
 *
 *   audio in  → Whisper STT  (feature: voice_stt)
 *             → GPT tool-calling loop (feature: voice_llm)
 *             → ElevenLabs TTS (feature: voice_tts)
 *             → audio + transcript + tool results back to the client.
 *
 * Each stage charges credits independently against {@see AiCreditService}
 * with its own `feature` tag and model so the ledger shows three line
 * items per turn, exactly as required.
 *
 * Destructive tool calls returned by GPT are NOT executed automatically:
 * the orchestrator emits a `confirm_required` payload so the UI can
 * collect a spoken or tapped confirmation, then re-call /turn with
 * `confirmed_tools[name] = true` to actually run them.
 */
class VoiceAssistantService
{
    public const MAX_TOOL_ITERATIONS = 4;

    public function __construct(
        protected WhisperService $whisper,
        protected OpenAiService $openai,
        protected ElevenLabsService $eleven,
        protected VoiceToolRegistry $tools,
        protected AiCreditService $credits,
    ) {}

    /**
     * Run a single turn. `$audio` is the user's recorded blob.
     * `$context` may include prior `messages[]` for multi-turn flow,
     * and `confirmed_tools` (a map of tool_name => true) when the
     * client is following up on a destructive prompt.
     *
     * @return array{
     *   transcript:string,
     *   reply:string,
     *   audio_base64:?string,
     *   tool_results:list<array>,
     *   pending_confirmations:list<array>,
     *   credits:array{stt:int,llm:int,tts:int,total:int},
     *   balance:int,
     *   messages:list<array>,
     * }
     */
    public function runTurn(User $user, bool $isAdmin, $audio, array $context = []): array
    {
        if (!AiEngineSettings::voiceEnabled()) {
            throw new \RuntimeException('Voice Assistant is disabled.');
        }
        if (!AiEngineSettings::voiceAllowedFor($user)) {
            throw new \RuntimeException('Voice Assistant is not enabled for your plan.');
        }
        if ($this->credits->getBalance($user) <= 0) {
            throw new InsufficientAiCreditsException(1, 0);
        }

        $confirmedTools = (array) ($context['confirmed_tools'] ?? []);
        $priorMessages  = $this->sanitizeHistory($context['messages'] ?? []);

        // 1. STT
        $stt = $this->whisper->transcribe($user, $audio);
        $userText = trim($stt['text']);
        if ($userText === '') {
            return $this->emptyResult($user, 'I couldn\'t hear anything — could you try again?', $stt['credits_spent']);
        }

        // 2. Build LLM prompt with tools
        $systemPrompt = $this->systemPrompt();
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $priorMessages,
            [['role' => 'user', 'content' => $userText]],
        );

        $toolDefs = $this->tools->functionDefinitionsFor($user, $isAdmin);
        $llmCredits     = 0;
        $toolResults    = [];
        $pendingConfirm = [];
        $finalContent   = '';

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            try {
                $out = $this->openai->chat(
                    $user,
                    AiEngineSettings::voiceGptModel(),
                    $messages,
                    [
                        'feature'     => 'voice_llm',
                        'temperature' => 0.4,
                        'max_tokens'  => 600,
                        'tools'       => $toolDefs,
                        'tool_choice' => 'auto',
                        'reason'      => 'Voice Assistant: reasoning turn',
                    ]
                );
            } catch (InsufficientAiCreditsException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Voice LLM call failed: ' . $e->getMessage());
                $finalContent = "Sorry, I couldn't think that through right now. Please try again.";
                break;
            }
            $llmCredits += (int) $out['credits_spent'];

            $toolCalls = (array) ($out['tool_calls'] ?? []);
            if (!$toolCalls) {
                $finalContent = (string) $out['content'];
                break;
            }

            // Append the assistant's tool-call message verbatim, then
            // execute each call and feed results back as `role=tool`.
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $out['content'] ?? '',
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true) ?: [];
                $confirmed = (bool) ($confirmedTools[$name] ?? false);
                $result = $this->tools->execute($user, $isAdmin, $name, $args, $confirmed);

                if (!empty($result['confirm_required'])) {
                    $pendingConfirm[] = $result;
                }
                $toolResults[] = ['tool' => $name, 'arguments' => $args, 'result' => $result];

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => (string) ($call['id'] ?? ''),
                    'name'         => $name,
                    'content'      => json_encode($result),
                ];
            }

            // If anything needs confirmation, stop the loop and let the
            // user respond before we run more tools or speak a final reply.
            if ($pendingConfirm) {
                $finalContent = $this->synthConfirmPrompt($pendingConfirm);
                break;
            }
        }

        if ($finalContent === '') {
            $finalContent = 'Done.';
        }

        // 3. TTS
        $audioB64 = null;
        $ttsCredits = 0;
        try {
            $tts = $this->eleven->speak($user, $finalContent);
            $audioB64   = base64_encode($tts['audio']);
            $ttsCredits = (int) $tts['credits_spent'];
        } catch (InsufficientAiCreditsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::info('Voice TTS skipped: ' . $e->getMessage());
        }

        $newHistory = array_merge($priorMessages, [
            ['role' => 'user',      'content' => $userText],
            ['role' => 'assistant', 'content' => $finalContent],
        ]);

        return [
            'transcript'            => $userText,
            'reply'                 => $finalContent,
            'audio_base64'          => $audioB64,
            'tool_results'          => $toolResults,
            'pending_confirmations' => $pendingConfirm,
            'credits' => [
                'stt'   => (int) $stt['credits_spent'],
                'llm'   => $llmCredits,
                'tts'   => $ttsCredits,
                'total' => (int) $stt['credits_spent'] + $llmCredits + $ttsCredits,
            ],
            'balance'  => $this->credits->getBalance($user),
            'messages' => $this->sanitizeHistory($newHistory),
        ];
    }

    protected function systemPrompt(): string
    {
        return "You are 1INME Voice, a calm, concise voice assistant inside the 1INME app. "
            . "The user is signed in and speaking to you. Keep replies short (under 60 words) "
            . "since they will be spoken aloud. When the user asks for an action, prefer "
            . "calling the matching tool over describing it. For destructive actions (delete, "
            . "send, charge, switch plan, invite) always confirm out loud first and only run "
            . "after the user explicitly confirms. If a tool returns 'confirm_required', "
            . "summarise what you're about to do and ask the user to confirm.";
    }

    /** Keep only role/content/tool fields the API accepts. */
    protected function sanitizeHistory(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if (!is_array($m) || empty($m['role'])) continue;
            $row = ['role' => (string) $m['role'], 'content' => (string) ($m['content'] ?? '')];
            if (!empty($m['tool_calls']))   $row['tool_calls']   = $m['tool_calls'];
            if (!empty($m['tool_call_id'])) $row['tool_call_id'] = $m['tool_call_id'];
            if (!empty($m['name']))         $row['name']         = $m['name'];
            $out[] = $row;
        }
        // Keep history bounded so prompts don't blow out per turn.
        return array_slice($out, -16);
    }

    protected function synthConfirmPrompt(array $pending): string
    {
        $first = $pending[0];
        $tool  = $first['tool'] ?? 'this action';
        return "I'm about to run {$tool}. Should I go ahead?";
    }

    protected function emptyResult(User $user, string $msg, int $sttCredits): array
    {
        return [
            'transcript'            => '',
            'reply'                 => $msg,
            'audio_base64'          => null,
            'tool_results'          => [],
            'pending_confirmations' => [],
            'credits' => ['stt' => $sttCredits, 'llm' => 0, 'tts' => 0, 'total' => $sttCredits],
            'balance' => $this->credits->getBalance($user),
            'messages' => [],
        ];
    }
}
