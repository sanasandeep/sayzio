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
 * to opt out of the filter for admin / cross-workspace queries.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (!app()->bound('current_workspace')) return;
            $ws = app('current_workspace');
            if (!$ws) return;
            $builder->where($builder->getModel()->getTable() . '.workspace_id', $ws->id);
        });

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
