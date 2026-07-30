<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cached per-owner lists used by the biolink editor: the palette dropdowns
 * (Forms / Buzz / AI Companions) and the matching pickers inside the
 * block-settings form. Each list is small, changes rarely, and over a
 * distant DB is its own round-trip, so we cache per owner + active
 * workspace with a short backstop TTL. Lists are busted immediately on any
 * create/update/delete of the underlying model (see each model's booted()
 * editor-cache hook); the TTL only guards against rare bulk writes that
 * bypass model events.
 *
 * Single source for these cache reads: BiolinkBlockController::editor()
 * (page load) and the block-settings-form partial (AJAX edit form) both go
 * through here, so each list is queried at most once per request and never
 * per block.
 */
class EditorPaletteLists
{
    private const TTL = 120;

    private static function wsId(): string
    {
        return (app()->bound('current_workspace') && app('current_workspace'))
            ? (string) app('current_workspace')->id
            : 'none';
    }

    /** @return array<int, array{id:int,title:string,slug:string,is_active:bool}> */
    public static function forms(): array
    {
        $wsId = self::wsId();

        return Cache::remember(
            'editor:forms:' . auth()->id() . ":{$wsId}",
            self::TTL,
            fn () => auth()->user()->forms()
                ->orderByDesc('id')
                ->get(['id', 'title', 'slug', 'is_active'])
                ->map(fn ($f) => [
                    'id'        => $f->id,
                    'title'     => $f->title,
                    'slug'      => $f->slug,
                    'is_active' => (bool) $f->is_active,
                ])->values()->all()
        );
    }

    /** @return array<int, array{id:int,name:string,type:string,type_label:string,is_active:bool}> */
    public static function buzz(): array
    {
        $ownerId = workspace_owner_id();
        $wsId = self::wsId();

        return Cache::remember(
            "editor:buzz:{$ownerId}:{$wsId}",
            self::TTL,
            fn () => \App\Modules\User\Models\SocialProof::where('user_id', $ownerId)
                ->orderByDesc('id')
                ->get(['id', 'name', 'type', 'is_active'])
                ->map(fn ($b) => [
                    'id'         => $b->id,
                    'name'       => $b->name,
                    'type'       => $b->type,
                    'type_label' => $b->typeLabel(),
                    'is_active'  => (bool) $b->is_active,
                ])->values()->all()
        );
    }

    /** @return array<int, array{id:int,public_id:string,name:string,is_disabled:bool}> */
    public static function companions(): array
    {
        $ownerId = workspace_owner_id();

        return Cache::remember(
            "editor:companions:{$ownerId}",
            self::TTL,
            // AI Companions the owner can drop into a biolink block. We
            // restrict to the `biolink` placement so users don't accidentally
            // pick an embed-only or inbox-only companion.
            fn () => \App\Modules\User\Models\AiCompanion::where('user_id', $ownerId)
                ->where('placement', 'biolink')
                ->orderByDesc('id')
                ->get(['id', 'public_id', 'name', 'is_disabled'])
                ->map(fn ($c) => [
                    'id'          => $c->id,
                    'public_id'   => $c->public_id,
                    'name'        => $c->name,
                    'is_disabled' => (bool) $c->is_disabled,
                ])->values()->all()
        );
    }
}
