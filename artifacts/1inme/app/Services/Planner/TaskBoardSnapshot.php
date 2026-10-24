<?php

namespace App\Services\Planner;

use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;

class TaskBoardSnapshot
{
    public static function cardRevision(TaskCard $card): string
    {
        $attributes = $card->getRawOriginal();
        ksort($attributes);

        return hash('sha256', json_encode([$attributes, ProjectItem::where('kind', 'task')->where('resource_id', $card->id)->value('project_id')]));
    }

    public static function revision(TaskBoard $board): string
    {
        $board->load(['columns' => fn ($query) => $query->orderBy('id'), 'cards' => fn ($query) => $query->orderBy('id'), 'cards.assignees:id', 'cards.labels:id', 'cards.subtasks','cards.timeEntries'=>fn($q)=>$q->select(['id','card_id','updated_at','minutes','ended_at'])]);

        $assignments = ProjectItem::where(fn ($query) => $query->where(fn ($q) => $q->where('kind', 'board')->where('resource_id', $board->id))->orWhere(fn ($q) => $q->where('kind', 'task')->whereIn('resource_id', $board->cards->pluck('id'))))->orderBy('kind')->orderBy('resource_id')->get(['kind', 'resource_id', 'project_id']);

        return hash('sha256', json_encode([$board->toArray(), $assignments, TaskActivity::whereIn('card_id', $board->cards->pluck('id'))->max('id')]));
    }
}
