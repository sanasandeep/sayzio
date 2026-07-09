<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Services\Inbox\MailboxAiReplyDrafter;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * POST /api/v1/mailbox/draft-reply
 *
 * Accepts an email thread extracted by the Sayzio browser extension and
 * returns an AI-drafted reply. Charges the user's coin wallet.
 *
 * Request body:
 *   thread.subject        string          Email subject line.
 *   thread.participants   string[]        Visible participant names/addresses.
 *   thread.messages       object[]        Ordered messages:
 *       role    "inbound"|"outbound"
 *       sender  string (name or address)
 *       body    string
 *   knowledge_base_ids    int[]  optional User-owned AiMind IDs to ground on.
 *   include_links         bool   optional Inject the user's Sayzio links.
 *   instruction           string optional Regenerate hint ("shorter", …).
 *
 * Response 200:
 *   draft          string   Ready-to-insert reply text.
 *   citations      object[] [{id, name}] knowledge bases cited (may be empty).
 *   credits_spent  int      Coins charged.
 *   model          string   Model used.
 *
 * Error codes:
 *   402 insufficient_coins   Wallet balance too low.
 *   403 ai_disabled          AI Engine not configured.
 *   422                      Validation failure.
 */
class MailboxReplyDraftController extends Controller
{
    use ApiResponses;

    public function __construct(protected MailboxAiReplyDrafter $drafter) {}

    public function draft(Request $request): JsonResponse
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI Engine is not enabled on this instance.', 403, 'ai_disabled');
        }

        $data = $request->validate([
            'thread'                    => 'required|array',
            'thread.subject'            => 'nullable|string|max:500',
            'thread.participants'       => 'nullable|array',
            'thread.participants.*'     => 'nullable|string|max:200',
            'thread.messages'           => 'required|array|min:1|max:50',
            'thread.messages.*.role'    => 'required|string|in:inbound,outbound',
            'thread.messages.*.sender'  => 'nullable|string|max:200',
            'thread.messages.*.body'    => 'required|string|max:8000',
            'knowledge_base_ids'        => 'nullable|array|max:5',
            'knowledge_base_ids.*'      => 'integer|min:1',
            'include_links'             => 'nullable|boolean',
            'instruction'               => 'nullable|string|max:300',
        ]);

        $user = $request->user();

        try {
            $result = $this->drafter->draft(
                thread:       $data['thread'],
                user:         $user,
                mindIds:      array_map('intval', $data['knowledge_base_ids'] ?? []),
                includeLinks: (bool) ($data['include_links'] ?? true),
                instruction:  (string) ($data['instruction'] ?? ''),
            );
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail(
                sprintf(
                    'Not enough coins to generate this draft. You need %d coins but have %d.',
                    $e->required,
                    $e->balance,
                ),
                402,
                'insufficient_coins',
                [
                    'required' => $e->required,
                    'balance'  => $e->balance,
                    'topup_url' => route('user.upgrade'),
                ],
            );
        } catch (\Exception $e) {
            // Surface a clean message for unexpected AI / network errors.
            return $this->fail(
                'Could not generate a draft: ' . $e->getMessage(),
                503,
                'ai_error',
            );
        }

        return $this->ok([
            'draft'         => $result['draft'],
            'citations'     => $result['citations'],
            'credits_spent' => $result['credits_spent'],
            'model'         => $result['model'],
        ]);
    }
}
