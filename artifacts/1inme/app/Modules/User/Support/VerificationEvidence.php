<?php

namespace App\Modules\User\Support;

final class VerificationEvidence
{
    public static function rules(): array
    {
        return [
            'additional_info' => 'nullable|string|max:4000',
            'supporting_urls' => 'nullable|array|max:5',
            'supporting_urls.*' => 'nullable|url:http,https|max:2048',
        ];
    }

    public static function fields(array $data): array
    {
        return [
            'additional_info' => trim((string) ($data['additional_info'] ?? '')) ?: null,
            'supporting_urls' => array_values(array_unique(array_filter($data['supporting_urls'] ?? [], fn ($url) => is_string($url) && trim($url) !== ''))),
        ];
    }
}
