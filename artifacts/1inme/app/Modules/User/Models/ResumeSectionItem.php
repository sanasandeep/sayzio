<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeSectionItem extends Model
{
    /** Section types that store ordered list items. */
    public const TYPES = [
        'experience', 'education', 'skills', 'projects',
        'certifications', 'awards', 'languages', 'links', 'custom',
    ];

    protected $fillable = [
        'resume_id', 'section_type', 'position', 'data',
    ];

    protected function casts(): array
    {
        return [
            'data'     => 'array',
            'position' => 'integer',
        ];
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    public static function isValidType(?string $type): bool
    {
        return $type !== null && in_array($type, self::TYPES, true);
    }
}
