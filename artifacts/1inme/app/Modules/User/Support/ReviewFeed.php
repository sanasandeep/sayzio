<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\ExternalReview;
use App\Modules\User\Models\Review;

/**
 * Builds a unified, normalized, sorted list of reviews for public display,
 * merging native (approved) reviews and imported 3rd-party reviews into a
 * single shape the views and API can render without caring about source.
 */
class ReviewFeed
{
    /** Friendly labels for known providers. */
    public const PROVIDER_LABELS = [
        'native'     => 'Sayzio',
        'google'     => 'Google',
        'trustpilot' => 'Trustpilot',
    ];

    /**
     * @param array<int,string> $providers When non-empty, imported reviews are
     *        restricted to these provider slugs (e.g. ['google']). Empty = all.
     * @return array<int,array<string,mixed>>
     */
    public static function build(int $userId, ?int $linkId, string $source, string $sort, int $limit, array $providers = []): array
    {
        $items = [];

        if ($source === 'native' || $source === 'both') {
            $q = Review::query()->public()->forUser($userId)->with(['media', 'answers']);
            if ($linkId !== null) {
                $q->where('link_id', $linkId);
            }
            foreach ($q->get() as $r) {
                $items[] = [
                    'id'            => 'n' . $r->id,
                    'source'        => 'native',
                    'source_label'  => self::PROVIDER_LABELS['native'],
                    'author_name'   => $r->author_name ?: 'Anonymous',
                    'author_avatar' => $r->author_avatar,
                    'rating'        => $r->rating,
                    'body'          => $r->body,
                    'reply'         => $r->reply,
                    'replied_at'    => $r->replied_at,
                    'is_pinned'     => (bool) $r->is_pinned,
                    'verified'      => $r->verified_at !== null,
                    'created_at'    => $r->created_at,
                    'source_url'    => null,
                    'media'         => $r->media->map(fn ($m) => [
                        'type' => $m->type, 'url' => $m->url, 'meta' => $m->meta,
                    ])->all(),
                    'answers'       => $r->answers->map(fn ($a) => [
                        'prompt' => $a->prompt, 'answer' => $a->answer,
                    ])->all(),
                ];
            }
        }

        if ($source === 'external' || $source === 'both') {
            $eq = ExternalReview::query()->forUser($userId);
            if (!empty($providers)) {
                $eq->whereIn('provider', $providers);
            }
            foreach ($eq->get() as $r) {
                $items[] = [
                    'id'            => 'e' . $r->id,
                    'source'        => $r->provider,
                    'source_label'  => self::PROVIDER_LABELS[$r->provider] ?? ucfirst($r->provider),
                    'author_name'   => $r->author_name ?: 'Anonymous',
                    'author_avatar' => $r->author_avatar,
                    'rating'        => $r->rating,
                    'body'          => $r->body,
                    'reply'         => null,
                    'replied_at'    => null,
                    'is_pinned'     => false,
                    'verified'      => false,
                    'created_at'    => $r->reviewed_at ?: $r->created_at,
                    'source_url'    => $r->source_url,
                    'media'         => [],
                    'answers'       => [],
                ];
            }
        }

        usort($items, function ($a, $b) use ($sort) {
            // Pinned native reviews always float to the top.
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $a['is_pinned'] ? -1 : 1;
            }
            if ($sort === 'rating') {
                $cmp = ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
                if ($cmp !== 0) return $cmp;
            }
            $at = $a['created_at'] ? $a['created_at']->getTimestamp() : 0;
            $bt = $b['created_at'] ? $b['created_at']->getTimestamp() : 0;
            return $bt <=> $at;
        });

        return $limit > 0 ? array_slice($items, 0, $limit) : $items;
    }
}
