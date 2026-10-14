<?php

namespace App\Modules\User\Services;

use App\Modules\User\Middleware\EnforceWorkspaceFeature;
use Illuminate\Database\Eloquent\Builder;

class WorkspaceRecordAccess
{
    /** Only records with a persisted actor can offer own/others controls. */
    public static function models(): array
    {
        return [
            'Link' => ['links', 'created_by_user_id'], 'Form' => ['forms', 'created_by_user_id'],
            'Project' => ['projects', 'created_by_user_id'], 'CreatorPost' => ['posts', 'created_by_user_id'],
            'QrCode' => ['qr', 'created_by_user_id'], 'Pixel' => ['pixels', 'created_by_user_id'],
            'SplashPage' => ['splash-pages', 'created_by_user_id'], 'UserFile' => ['files', 'created_by_user_id'],
            'CloudFile' => ['files', 'added_by_user_id'], 'CloudConnection' => ['files', 'user_id'],
            'DeliveryProject' => ['delivery-projects', 'created_by_user_id'], 'ClientPortal' => ['client-portals', 'created_by_user_id'],
            'Contact' => ['contacts', 'created_by_user_id'], 'SocialProof' => ['social-proofs', 'created_by_user_id'],
            'IntegrationConfig' => ['integrations', 'created_by_user_id'], 'CalendarAccount' => ['calendar', 'created_by_user_id'],
            'TaskBoard' => ['tasks', 'created_by_user_id'], 'TaskCard' => ['tasks', 'created_by_user_id'],
            'VaultClient' => ['vault', 'created_by_user_id'], 'VaultCredential' => ['vault', 'created_by_user_id'],
            'PlannerNote' => ['planner', 'user_id'], 'NoteActionPlan' => ['planner', 'user_id'],
        ];
    }

    public static function parents(): array
    {
        return ['FormSubmission' => 'form', 'TaskColumn' => 'board', 'TaskSubtask' => 'card', 'TaskComment' => 'card', 'TaskAttachment' => 'card'];
    }

    public static function apply(Builder $query): void
    {
        if (! app()->bound('current_workspace') || ! auth()->check() || WorkspacePermissions::userIsOwner()) {
            return;
        }
        $request = request();
        if (! WorkspacePermissionCatalog::routeFeature($request->route()?->uri())) {
            return;
        }
        $parent = self::parents()[class_basename($query->getModel())] ?? null;
        if ($parent) {
            $query->whereHas($parent);

            return;
        }
        $entry = self::models()[class_basename($query->getModel())] ?? null;
        if (! $entry) {
            return;
        }
        [$feature, $column] = $entry;
        $action = WorkspacePermissionCatalog::baseAction(EnforceWorkspaceFeature::action($request));
        if (! in_array($action, ['edit', 'delete']) || ! WorkspacePermissions::userCan($feature.'.'.$action)) {
            $action = 'view';
        }
        if (! WorkspacePermissions::userCan($feature.'.view') || ! WorkspacePermissions::userCan($feature.'.'.$action)) {
            $query->whereRaw('1 = 0');
        } elseif (! WorkspacePermissions::userCan($feature.'.view_others') || ! WorkspacePermissions::userCan($feature.'.'.$action.'_others')) {
            // NULL attribution is deliberately not treated as the current member's record.
            $query->where($query->getModel()->getTable().'.'.$column, auth()->id());
        }
    }
}
