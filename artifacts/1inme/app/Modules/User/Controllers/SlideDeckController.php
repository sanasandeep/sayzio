<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlide;
use App\Modules\User\Models\LinkSlideDeck;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class SlideDeckController extends Controller
{
    protected function authorizeLink(Link $link): void
    {
        abort_if(
            $link->user_id !== workspace_owner_id() || $link->type !== 'biolink',
            403,
        );
    }

    protected function ensureDeck(Link $link): LinkSlideDeck
    {
        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        if ($deck) return $deck;

        $deck = LinkSlideDeck::create([
            'link_id'      => $link->id,
            'workspace_id' => $link->workspace_id,
            'version'      => 1,
            'is_published' => false,
            'settings'     => [
                'theme'        => ['background' => '#0f172a', 'accent' => '#8b5cf6', 'text' => '#f8fafc'],
                'transition'   => 'slide',
                'auto_advance' => 0,
                'loop'         => false,
            ],
        ]);

        // Seed one welcome slide so the editor isn't empty on first open.
        LinkSlide::create([
            'deck_id'    => $deck->id,
            'sort_order' => 0,
            'title'      => 'Welcome',
            'block_ids'  => [],
            'background' => ['type' => 'color', 'color' => '#0f172a'],
            'animation'  => ['enter' => 'fade', 'duration_ms' => 400],
            'transition' => 'slide',
            'settings'   => [],
        ]);

        return $deck;
    }

    public function editor(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);
        $slides = $deck->slides()->get();

        // Same signed-URL preview pattern conversational mode uses.
        $previewUrl = URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHours(24),
            ['alias' => $link->alias, '_preview' => 1, '_slides_preview' => 1],
            false,
        );

        $blockOptions = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'type', 'settings'])
            ->map(function ($b) {
                $s = is_array($b->settings) ? $b->settings : [];
                $label = $s['title'] ?? $s['text'] ?? $s['label'] ?? $s['heading'] ?? $s['question'] ?? null;
                return [
                    'id'    => $b->id,
                    'type'  => $b->type,
                    'label' => is_string($label) ? mb_substr($label, 0, 60) : null,
                ];
            })
            ->values();

        $deckPayload = [
            'id'           => $deck->id,
            'is_published' => (bool) $deck->is_published,
            'version'      => (int) $deck->version,
            'settings'     => $deck->settings ?? [],
            'mode'         => data_get($link->settings, 'biolink.mode', 'list'),
            'slides'       => $slides->map(fn ($s) => [
                'id'         => $s->id,
                'sort_order' => (int) $s->sort_order,
                'title'      => $s->title,
                'block_ids'      => array_values($s->block_ids ?? []),
                'block_settings' => is_array(($s->settings['block_settings'] ?? null)) ? $s->settings['block_settings'] : (object) [],
                'background' => $s->background ?? ['type' => 'color', 'color' => '#0f172a'],
                'animation'  => $s->animation ?? ['enter' => 'fade', 'duration_ms' => 400],
                'transition' => $s->transition ?? 'slide',
                'settings'   => $s->settings ?? [],
            ])->values(),
        ];

        return view('user.links.slides.editor', [
            'link'         => $link,
            'deck'         => $deck,
            'deckPayload'  => $deckPayload,
            'blockOptions' => $blockOptions,
            'previewUrl'   => $previewUrl,
        ]);
    }

    public function toggleMode(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $on = (bool) $request->boolean('enabled');

        $settings = $link->settings ?? [];
        $settings['biolink'] = $settings['biolink'] ?? [];
        $settings['biolink']['mode'] = $on ? 'slides' : 'list';
        $link->update(['settings' => $settings]);

        if ($on) $this->ensureDeck($link);

        return response()->json(['ok' => true, 'mode' => $settings['biolink']['mode']]);
    }

    /**
     * Replace deck (settings + ordered slides) wholesale. Mirrors the
     * ConversationFlowController::save shape — a single endpoint takes
     * the full editor state and atomically swaps the slide rows.
     */
    public function save(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);

        $data = $request->validate([
            'settings'                  => 'nullable|array',
            'settings.theme'            => 'nullable|array',
            'settings.transition'       => 'nullable|string|in:slide,fade,zoom,flip,none',
            'settings.auto_advance'     => 'nullable|integer|min:0|max:60000',
            'settings.loop'             => 'nullable|boolean',
            'is_published'              => 'nullable|boolean',
            'slides'                    => 'required|array|min:1|max:50',
            'slides.*.title'            => 'nullable|string|max:160',
            'slides.*.block_ids'        => 'nullable|array|max:10',
            'slides.*.block_ids.*'      => 'integer',
            // Per-block animation/placement overrides keyed by block id:
            // { "<block_id>": { "enter":"fade", "delay_ms":120, "duration_ms":400, "align":"center" } }
            'slides.*.block_settings'           => 'nullable|array',
            'slides.*.block_settings.*'         => 'nullable|array',
            'slides.*.block_settings.*.enter'        => 'nullable|string|in:fade,slide_up,slide_down,slide_left,slide_right,zoom,flip,none',
            'slides.*.block_settings.*.delay_ms'     => 'nullable|integer|min:0|max:10000',
            'slides.*.block_settings.*.duration_ms'  => 'nullable|integer|min:0|max:5000',
            'slides.*.block_settings.*.align'        => 'nullable|string|in:left,center,right,stretch',
            'slides.*.background'       => 'nullable|array',
            'slides.*.background.type'  => 'nullable|string|in:color,gradient,image',
            'slides.*.background.color' => 'nullable|string|max:60',
            'slides.*.background.from_color' => 'nullable|string|max:60',
            'slides.*.background.to_color'   => 'nullable|string|max:60',
            'slides.*.background.image_url'  => 'nullable|string|max:1024',
            'slides.*.animation'        => 'nullable|array',
            'slides.*.animation.enter'  => 'nullable|string|in:fade,slide_up,slide_down,slide_left,slide_right,zoom,flip,none',
            'slides.*.animation.duration_ms' => 'nullable|integer|min:0|max:5000',
            'slides.*.transition'       => 'nullable|string|in:slide,fade,zoom,flip,none',
            'slides.*.settings'         => 'nullable|array',
        ]);

        // Restrict block_ids to ones actually owned by this link.
        $allowedBlockIds = BiolinkBlock::where('link_id', $link->id)->pluck('id')->all();
        foreach ($data['slides'] as $i => $row) {
            $ids = $row['block_ids'] ?? [];
            $data['slides'][$i]['block_ids'] = array_values(array_intersect(
                array_map('intval', $ids), $allowedBlockIds,
            ));
        }

        DB::transaction(function () use ($deck, $data, $link) {
            if (isset($data['settings'])) {
                $deck->settings = array_merge($deck->settings ?? [], $data['settings']);
            }

            // Swap slides atomically: nuke + reinsert (only ~50 rows max).
            $deck->slides()->delete();
            foreach ($data['slides'] as $i => $s) {
                $slideSettings = is_array($s['settings'] ?? null) ? $s['settings'] : [];
                // Persist per-block overrides inside the slide's settings JSON
                // so we don't have to introduce another table for what is
                // essentially a small map keyed by block id.
                if (!empty($s['block_settings']) && is_array($s['block_settings'])) {
                    $slideSettings['block_settings'] = $s['block_settings'];
                }
                LinkSlide::create([
                    'deck_id'    => $deck->id,
                    'sort_order' => $i,
                    'title'      => $s['title'] ?? null,
                    'block_ids'  => $s['block_ids'] ?? [],
                    'background' => $s['background'] ?? null,
                    'animation'  => $s['animation'] ?? null,
                    'transition' => $s['transition'] ?? ($deck->settings['transition'] ?? 'slide'),
                    'settings'   => $slideSettings,
                ]);
            }

            $publish = (bool) ($data['is_published'] ?? $deck->is_published);
            if ($publish) {
                $deck->is_published = true;
                $deck->version = (int) $deck->version + 1;
                $deck->save();
                $deck->load('slides');
                $deck->published_snapshot = $this->buildSnapshot($deck, $link);
            }
            $deck->save();
        });

        $deck->refresh();

        return response()->json([
            'ok'           => true,
            'is_published' => (bool) $deck->is_published,
            'version'      => (int) $deck->version,
        ]);
    }

    /**
     * Build a server-rendered snapshot of the deck so public viewers can
     * load a frozen copy of the slides + their hosted block HTML without
     * touching the live editor tables.
     */
    public static function buildSnapshot(LinkSlideDeck $deck, Link $link): array
    {
        $blockMap = BiolinkBlock::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $slides = $deck->slides->map(function ($slide) use ($blockMap, $link) {
            $slideSettings   = is_array($slide->settings) ? $slide->settings : [];
            $perBlockOverrides = is_array($slideSettings['block_settings'] ?? null)
                ? $slideSettings['block_settings'] : [];

            $blocks = collect($slide->block_ids ?? [])
                ->map(function ($id) use ($blockMap, $link, $perBlockOverrides) {
                    $b = $blockMap->get((int) $id);
                    if (!$b) return null;
                    $s = is_array($b->settings) ? $b->settings : [];
                    $html = '';
                    try {
                        $html = view('common.partials.biolink-block-render', [
                            'block'     => $b,
                            's'         => $s,
                            'fontColor' => '#ffffff',
                            'link'      => $link,
                        ])->render();
                    } catch (\Throwable $e) {
                        logger()->warning('Slide block render failed: ' . $e->getMessage(), [
                            'block_id' => $b->id, 'link_id' => $link->id,
                        ]);
                    }
                    $override = $perBlockOverrides[(string) $b->id]
                        ?? $perBlockOverrides[(int) $b->id]
                        ?? null;
                    return [
                        'id'        => (int) $b->id,
                        'type'      => (string) $b->type,
                        'settings'  => $s,
                        'html'      => $html,
                        'animation' => is_array($override) ? [
                            'enter'       => $override['enter']       ?? 'fade',
                            'delay_ms'    => (int) ($override['delay_ms']    ?? 0),
                            'duration_ms' => (int) ($override['duration_ms'] ?? 400),
                            'align'       => $override['align']       ?? 'center',
                        ] : null,
                    ];
                })
                ->filter()
                ->values();

            return [
                'id'         => $slide->id,
                'sort_order' => (int) $slide->sort_order,
                'title'      => $slide->title,
                'background' => $slide->background ?? ['type' => 'color', 'color' => '#0f172a'],
                'animation'  => $slide->animation ?? ['enter' => 'fade', 'duration_ms' => 400],
                'transition' => $slide->transition ?? 'slide',
                'settings'   => $slide->settings ?? [],
                'blocks'     => $blocks,
            ];
        })->values()->all();

        return [
            'version'    => (int) $deck->version,
            'settings'   => $deck->settings ?? [],
            'slides'     => $slides,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
