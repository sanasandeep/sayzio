<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI-generated cover letter for a resume.
 *
 * Letters are persisted on every successful generation so the
 * creator's History panel always shows the actual text they were
 * shown, not just a credit-ledger excerpt. Per-section regenerates
 * mutate `content` and bump `credits_spent` in place.
 */
class ResumeCoverLetter extends Model
{
    protected $table = 'resume_cover_letters';

    protected $fillable = [
        'user_id', 'resume_id', 'resume_revision',
        'title', 'tone', 'jd_text', 'jd_excerpt',
        'language', 'ai_persona_id', 'content', 'model', 'credits_spent',
    ];

    protected function casts(): array
    {
        return [
            'content'         => 'array',
            'resume_revision' => 'integer',
            'credits_spent'   => 'integer',
            'ai_persona_id'   => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function aiPersona(): BelongsTo
    {
        return $this->belongsTo(AiPersona::class, 'ai_persona_id');
    }

    /**
     * Render the letter as a single plain-text block (greeting +
     * blank line + body paragraphs + blank line + sign-off). Used
     * for the "Copy to clipboard" affordance and as the source for
     * PDF export.
     */
    public function toPlainText(): string
    {
        $c = is_array($this->content) ? $this->content : [];
        $greeting = trim((string) ($c['greeting'] ?? ''));
        $signOff  = trim((string) ($c['sign_off'] ?? ''));
        $body     = array_values(array_filter(array_map(
            fn($p) => trim((string) $p),
            (array) ($c['body'] ?? []),
        ), fn($p) => $p !== ''));

        $parts = [];
        if ($greeting !== '') $parts[] = $greeting;
        if ($body)            $parts[] = implode("\n\n", $body);
        if ($signOff !== '')  $parts[] = $signOff;

        return implode("\n\n", $parts);
    }
}
