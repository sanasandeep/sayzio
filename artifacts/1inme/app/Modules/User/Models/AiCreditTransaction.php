<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'ai_credit_transactions';

    protected $fillable = [
        'balance_id', 'user_id', 'type', 'delta_credits', 'balance_after',
        'idempotency_key', 'feature', 'related_id', 'model',
        'tokens_in', 'tokens_out', 'wallet_transaction_id',
        'admin_id', 'reason', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'delta_credits' => 'integer',
            'balance_after' => 'integer',
            'tokens_in'     => 'integer',
            'tokens_out'    => 'integer',
            'meta'          => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public const TYPES = ['purchase', 'spend', 'refund', 'grant', 'admin_adjustment'];

    /** Known AI features for filtering / reporting. */
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach', 'voice_stt', 'voice_llm', 'voice_tts', 'card_scan', 'resume_import', 'resume_tailor'];

    /** Friendly labels for ledger surfaces. */
    public const FEATURE_LABELS = [
        'mind'          => 'AI Mind',
        'persona'       => 'AI Persona',
        'companion'     => 'AI Companion',
        'coach'         => 'AI Coach',
        'ask_coach'     => 'Ask Coach',
        'voice_stt'     => 'Voice — Transcription',
        'voice_llm'     => 'Voice — Reasoning',
        'voice_tts'     => 'Voice — Speech',
        'card_scan'     => 'Card / Brochure Scan',
        'resume_import' => 'Resume — Import',
        'resume_tailor' => 'Resume — Tailor to Job',
    ];

    public static function featureLabel(?string $feature): string
    {
        if (!$feature) return '—';
        return self::FEATURE_LABELS[$feature] ?? ucwords(str_replace('_', ' ', $feature));
    }

    public function balance() { return $this->belongsTo(AiCreditBalance::class, 'balance_id'); }
    public function user()    { return $this->belongsTo(User::class); }
    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
