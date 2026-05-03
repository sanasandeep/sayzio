<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\User\Models\InboxThread;

/**
 * Generates 3 reply drafts per thread.
 *
 * v1 is template-based, parameterised by category and lightly tuned to the
 * creator's prior outgoing tone (formal vs casual based on punctuation /
 * emoji density in their last 50 sent messages). The shape mirrors what an
 * AI backend would return so the call-site doesn't change when we plug in
 * a model later.
 */
class InboxReplySuggester
{
    /**
     * @param string[] $priorOutgoingMessages
     * @return string[]   Up to 3 draft reply bodies.
     */
    public function suggest(InboxThread $thread, array $priorOutgoingMessages = []): array
    {
        $tone = $this->detectTone($priorOutgoingMessages);
        $name = trim((string) $thread->sender_name) ?: 'there';
        $first = explode(' ', $name)[0] ?? $name;

        return match ($thread->category) {
            'sponsorship' => $this->sponsorship($first, $tone),
            'support'     => $this->support($first, $tone),
            'fan'         => $this->fan($first, $tone),
            'spam'        => [],
            default       => $this->lead($first, $tone),
        };
    }

    /** @param string[] $samples */
    protected function detectTone(array $samples): string
    {
        if (empty($samples)) return 'casual';
        $blob = mb_strtolower(implode(' ', array_slice($samples, -50)));
        $emoji = preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $blob);
        $exclam = substr_count($blob, '!');
        $words  = max(1, str_word_count($blob));
        $score  = ($emoji + $exclam) / $words;
        return $score > 0.02 ? 'casual' : 'formal';
    }

    protected function lead(string $first, string $tone): array
    {
        if ($tone === 'casual') {
            return [
                "Hey {$first}! Thanks for reaching out — happy to chat. What does your timeline look like?",
                "Hi {$first}, appreciate you getting in touch. Could you share a bit more about what you have in mind so I can quote properly?",
                "Hi {$first} — sounds great. I'll send over a short intro and rate card. What's the best email for you?",
            ];
        }
        return [
            "Hi {$first}, thanks for getting in touch. Could you share more detail about scope and timeline so I can put together a quote?",
            "Hi {$first}, happy to discuss this further. I've attached a short intro — let me know which times work this week.",
            "Hi {$first}, appreciate the enquiry. To give you accurate pricing I'll need a few more details — could you reply with project scope and dates?",
        ];
    }

    protected function sponsorship(string $first, string $tone): array
    {
        return [
            "Hi {$first}, thanks for the partnership note. I've attached my media kit and current rates — happy to jump on a quick call to align on scope.",
            "Hi {$first}, this sounds like a great fit. Could you share campaign goals, deliverables and timeline so I can put together a tailored proposal?",
            "Hi {$first}, appreciate you reaching out. Here's a quick yes in principle — I'll need brand guidelines, a rough budget and target dates to confirm.",
        ];
    }

    protected function support(string $first, string $tone): array
    {
        return [
            "Hi {$first}, sorry you're hitting this. Could you share a screenshot and the link/page where it happens? I'll dig in straight away.",
            "Hi {$first}, thanks for flagging — I want to fix this for you fast. What device and browser are you on?",
            "Hi {$first}, I've taken a look. Could you try " . ($tone === 'casual' ? "logging out and back in?" : "logging out and back in and let me know if the issue persists?"),
        ];
    }

    protected function fan(string $first, string $tone): array
    {
        if ($tone === 'casual') {
            return [
                "{$first}!! 🥹 thank you so much, this honestly made my day.",
                "Aww {$first}, thank you — comments like this keep me going ❤️",
                "Thank you {$first}!! Really means a lot.",
            ];
        }
        return [
            "Thank you so much, {$first} — it really means a lot to hear that.",
            "Thank you {$first}, I really appreciate you taking the time to write.",
            "Thank you {$first} — kind notes like this are the best part of doing this.",
        ];
    }
}
