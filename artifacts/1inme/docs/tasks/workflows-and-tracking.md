# Task workflows and tracking

Task completion uses the source column's first matching completion route. Rules can match priority, category, or every task, and can target multiple different columns. A non-Done destination starts the next stage at 0%; a Done destination records completion at 100%. Without completion rules, completion moves to a Done column (created if needed). Manual routes restrict drag destinations only when configured. Each action moves one stage, avoiding route loops. Column work limits remain advisory.

The task editor separates details, checklist, people/categories, connections/files, time/billing, and discussion/history. Categories are reusable labels for filtering and workflow conditions. The related-item picker searches accessible workspace resources; normal destination permissions still apply.

Time tracking works independently of billing. A user can run one task timer across workspaces. Follow attendance selects a task for clock-in/resume and pauses its timer on break/clock-out. Manual timers stay independent; manual logs can be entered separately. Completion stops running timers and clears attendance selections for the task. Internal platform staff attendance is isolated from workspace task tracking.

Owners can configure `tasks.workflow`, `tasks.track`, and `tasks.export` independently in workspace roles. Existing default editors inherit these from edit access until overridden. Board visibility is required for every operation. Time-entry deletion requires ownership of the entry or task-edit permission.

Board and Gantt exports support PNG in the browser and paginated PDF. Exports include the full board, up to 500 tasks and 20 columns; Gantt exports use the current date window and zoom. Search filters are for the interactive view. Large image exports are scaled to browser canvas limits; PDF preserves pagination. Remote assets are disabled in PDF generation.

Migration: `2029_11_16_000001_add_task_workflow_and_attendance_tracking.php` adds column transitions, timer participant links, and per-user attendance task selections.
