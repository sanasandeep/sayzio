<?php

namespace App\Services\AI;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class ZioTranscriptRecorder
{
    public static function record(User $actor, ?string $token, array $messages): void
    {
        if (! $token || strlen($token) > 64) {
            return;
        }
        $conversation = SiteAssistantConversation::where('visitor_token', $token)->where('bound_user_id', $actor->id)->first();
        if (! $conversation) {
            return;
        }
        DB::transaction(function () use ($conversation, $messages) {
            foreach ($messages as $message) {
                SiteAssistantMessage::create(['conversation_id' => $conversation->id] + $message);
            }
            $conversation->turns_count += count(array_filter($messages, fn ($message) => ($message['role'] ?? '') === 'user'));
            $conversation->last_message_at = now();
            $conversation->credits_spent += (int) array_sum(array_column($messages, 'credits_spent'));
            $conversation->save();
        });
    }
}
