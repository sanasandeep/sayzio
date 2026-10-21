<?php

namespace App\Modules\User\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Auto-scopes Eloquent queries on a model that has a `workspace_id` column
 * to the active workspace bound in the container as `current_workspace`.
 *
 * - Read queries: a global scope filters where workspace_id = current.
 * - Writes: when creating a row without a workspace_id, the active workspace
 *   is filled in automatically; created_by_user_id gets the signed-in user
 *   when blank.
 *
 * Skipped automatically when no `current_workspace` is bound (CLI, public
 * link viewers, webhook handlers, etc.) so non-app code paths are unaffected.
 *
 * Use `Model::query()->withoutGlobalScope('workspace')` (or `withoutWorkspaceScope()`)
 * to opt out of the filter for a single admin / cross-workspace query.
 *
 * A model whose records are ACCOUNT-WIDE rather than per-workspace opts
 * out for good instead, by overriding {@see readsAreWorkspaceScoped()}.
 * That is a property of the record, not of the query, and answering it
 * per call site is how Contact ended up letting you open a contact on
 * mobile and then refusing to let you rename, tag, note or delete it --
 * `index` and `show` had opted out by hand and the other forty sites
 * had not. The writing half of this trait still applies: those models
 * keep filling workspace_id in, and keep the record-access scope.
 */
trait BelongsToWorkspace
{
    /**
     * Whether READS of this model are confined to the active workspace.
     *
     * Override to false on a model that belongs to the account rather
     * than to the workspace somebody happened to be standing in when
     * the row was written.
     */
    public static function readsAreWorkspaceScoped(): bool
    {
        return true;
    }

    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (!$builder->getModel()::readsAreWorkspaceScoped()) return;
            if (!app()->bound('current_workspace')) return;
            $ws = app('current_workspace');
            if (!$ws) return;
            $builder->where($builder->getModel()->getTable() . '.workspace_id', $ws->id);
        });

        static::addGlobalScope('workspace_record_access', fn (Builder $builder) => \App\Modules\User\Services\WorkspaceRecordAccess::apply($builder));

        static::creating(function ($model) {
            if (empty($model->workspace_id) && app()->bound('current_workspace')) {
                $ws = app('current_workspace');
                if ($ws) $model->workspace_id = $ws->id;
            }
            // Public-origin fallback: when no current_workspace is bound (e.g.
            // visitor submitting a form, subscribing through a biolink), derive
            // the workspace from a parent record the model knows about. Without
            // this, public writes land with NULL workspace_id and are then
            // hidden by the global scope when the owner views their inbox.
            if (empty($model->workspace_id) && method_exists($model, 'parentForWorkspace')) {
                $parent = $model->parentForWorkspace();
                if ($parent && !empty($parent->workspace_id)) {
                    $model->workspace_id = $parent->workspace_id;
                }
            }
            $record = \App\Modules\User\Services\WorkspaceRecordAccess::models()[class_basename($model)] ?? null;
            if ($record && $record[1] === 'created_by_user_id' && auth()->check() && empty($model->created_by_user_id)) {
                $model->created_by_user_id = auth()->id();
            }
            if (property_exists($model, 'fillable')) {
                // created_by_user_id: signed-in person who took the action.
                if (in_array('created_by_user_id', (array) $model->getFillable(), true)
                    && empty($model->created_by_user_id) && auth()->check()) {
                    $model->created_by_user_id = auth()->id();
                }
            }
        });
    }

    public function scopeWithoutWorkspaceScope(Builder $q): Builder
    {
        return $q->withoutGlobalScope('workspace');
    }
}
