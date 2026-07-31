<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Controllers\SlideDeckController as DeckEditor;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile/REST editor for slide decks (biolink-family links running in
 * "slides" mode). Reaches parity with the web SlideDeckController editor:
 * deck settings (auto-play/transition/loop), per-slide backgrounds
 * (including copy-from-previous / apply-to-all done client-side then
 * saved wholesale), and slide↔block attachment.
 *
 * Validation + persistence are delegated to the shared static helpers on
 * the web SlideDeckController (saveRules / sanitizeSaveData / persistDeck /
 * ensureDeckFor) so web and mobile never drift. New blocks are created via
 * the existing POST /links/{id}/blocks endpoint (Api\BiolinkBlockController)
 * which owns the plan gating.
 */
class SlideDeckApiController extends Controller
{
    use ApiResponses;

    /**
     * The same curated block-type subset the web slides editor offers for
     * in-slide creation. Filtered per user by the plan allowlist
     * (userCanUseBlockType) — identical to the web editor's list.
     */
    public const CREATABLE_TYPES = [
        'heading', 'paragraph', 'paragraph_rich', 'image', 'image_grid',
        'link', 'link_big', 'list', 'divider', 'spacer', 'alert', 'badge',
        'socials', 'video', 'audio',
    ];

    /** Load the deck (settings + slides) + editor metadata. */
    public function show(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('Slide deck not found');

        $deck = DeckEditor::ensureDeckFor($link);
        $slides = $deck->slides()->get();

        $blockOptions = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'type', 'settings'])
            ->map(function ($b) {
                $s = is_array($b->settings) ? $b->settings : [];
                $label = $s['title'] ?? $s['text'] ?? $s['label'] ?? $s['heading'] ?? $s['question'] ?? null;
                return [
                    'id'    => (int) $b->id,
                    'type'  => $b->type,
                    'label' => is_string($label) ? mb_substr($label, 0, 60) : null,
                ];
            })
            ->values();

        $user = $request->user();
        $creatableTypes = collect(self::CREATABLE_TYPES)
            ->filter(fn ($t) => $user->userCanUseBlockType($t))
            ->map(fn ($t) => [
                'type'  => $t,
                'label' => BiolinkBlock::TYPES[$t]['label'] ?? $t,
            ])
            ->values();

        return $this->ok([
            'deck' => [
                'id'           => (int) $deck->id,
                'is_published' => (bool) $deck->is_published,
                'version'      => (int) $deck->version,
                'settings'     => $deck->settings ?? [],
                'slides'       => $slides->map(fn ($s) => [
                    'id'             => (int) $s->id,
                    'sort_order'     => (int) $s->sort_order,
                    'title'          => $s->title,
                    'block_ids'      => array_values($s->block_ids ?? []),
                    'block_settings' => is_array(($s->settings['block_settings'] ?? null)) ? $s->settings['block_settings'] : (object) [],
                    'background'     => $s->background ?? ['type' => 'color', 'color' => '#0f172a'],
                    'animation'      => $s->animation ?? ['enter' => 'fade', 'duration_ms' => 400],
                    'transition'     => $s->transition ?? 'slide',
                    'settings'       => $s->settings ?? [],
                ])->values(),
            ],
            'meta' => [
                'link_id'         => (int) $link->id,
                'alias'           => $link->alias,
                'public_url'      => url('/' . $link->alias),
                'mode'            => data_get($link->settings, 'biolink.mode', 'list'),
                'blocks'          => $blockOptions,
                'creatable_types' => $creatableTypes,
            ],
        ]);
    }

    /** Replace deck settings + ordered slides wholesale (mirrors web save). */
    public function save(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('Slide deck not found');

        $deck = DeckEditor::ensureDeckFor($link);

        $data = $request->validate(DeckEditor::saveRules());
        $data = DeckEditor::sanitizeSaveData($data, $link);

        DeckEditor::persistDeck($deck, $link, $data);

        return $this->show($request, $id);
    }

    /** Resolve a biolink-family link owned by the signed-in user, or null. */
    protected function ownedBiolinkFamilyLink(Request $request, int $id): ?Link
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link || !$link->isBiolinkFamily()) {
            return null;
        }
        return $link;
    }
}
