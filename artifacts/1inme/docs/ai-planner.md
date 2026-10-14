# AI Planner and folder workspaces

AI Planner extends Notes → Action Plan. Saved plans have Overview, Tasks, Activities, Resources, Remember and History tabs. Folders now open a workspace with plans, tasks, resources, reminders, AI activity and reports; Links and detailed reports render inside the same folder header and navigation. Both canonical folder tabs and older filtered link URLs retain this shared workspace interface. The overview combines top content performance, task progress, plans and notes.

## Working from a plan

Generate a plan using the existing Upload, URL, My Files or pasted-note workflow. Review its tasks, checklists, milestones and questions. Create selected real tasks in a visible board, choose an assignee and optionally a due date. Repeating creation reopens the same linked tasks. Existing task calendar synchronization handles due dates. Forms and pages remain inactive drafts; calendar events require an explicit calendar/date.

Resources can be associated with folders and moved in bulk. Supported resources include links, forms, QR codes, files, calendars, boards, tasks, plans, Minds, generated personas, AI Agents, Companions, Brand Kits, Brand Studio kits and Staff. Visibility and workspace permissions still apply. Creating planner tasks, boards and direct form/page drafts uses the plan folder. Artifacts created later inside native AI tools can be added through the folder resource picker; a handoff does not move existing resources automatically.

AI handoffs carry a reviewed task or plan brief to the selected native tool. Use the brief, follow that tool's normal estimates and permissions, then capture its result back to the plan. Matching synchronous AI charges are attributed to the handoff; asynchronous native jobs do not automatically retain browser session attribution. Disconnect the brief to stop attribution. Saving decisions to a Mind creates an unlearned source; normal explicit learning and coin charges still apply.

## Background work

The author may enable daily/weekly reviews in a chosen timezone, or reviews triggered by selected form submissions. Form-triggered reviews receive response counts, not private response answers. A selected enabled AI Staff member supplies its identity and capabilities. Otherwise the existing mind model configuration performs the review, billed as `planner_review`.

Runs require explicit per-run, daily and monthly coin budgets. Quotes include provider fallbacks. The worker checks permissions, wallet balance and budgets again before spending. Scheduled slot and submission keys prevent duplicate dispatch; failures pause automatic work. Invalid/unsaved results refund charged coins. New AI results wait for review and Apply; Revert restores the previous plan without deleting created resources. Optional automatic task creation uses the already approved plan, never unreviewed proposals.

The planner does not autonomously publish pages, send campaigns or execute every AI tool. Native tool handoffs remain reviewed actions. Linked files provide identifying context; uploaded notes are interpreted during initial generation. Minds supply bounded source excerpts. Resource metrics are limited to current counts/status and task progress.

## Privacy and reminders

Plans and notes are private by default. Sharing a plan exposes its approved results to workspace members; private handoff briefs stay private. Shared notes are explicit. Reminders appear on related authenticated dashboard editors and folder pages, with pin, complete and snooze controls. They do not appear on public pages or unrelated external websites. Resource access is checked before rendering contextual notes.

## Deployment

Run normal migrations, including `2029_11_02_000001_add_ai_planner_workspaces.php`, and deploy PHP/views together. The server must run Laravel's scheduler and queue worker. `planner:dispatch` is registered every minute and publishes a heartbeat through the database cache. The UI warns when the dispatcher heartbeat is missing. Background work continues without an open browser only while this server infrastructure runs. No production scheduler or deployment is configured by this change.

## Project hub interface

Folder Overview includes Hub and Flow views. Hub counts come from visible assigned resources. Flow is a suggested process, not conversion attribution. AI plan suggestions come from saved plans and require review; no connection is applied implicitly. Explicit item-to-item relationships can be saved or disconnected under Resources. Relationships never grant access to private resources.

AI team lists assigned Minds, personas, Agents, Companions and Staff. Marketing shows assigned strategies, budgets, Buzz campaigns and brand resources, with lifetime Buzz counts. These tools retain native permissions and their own reviewed actions; folder assignment does not activate an assistant or campaign. Reports distinguish current project status, lifetime counts and recent AI runs from date-filtered link charts. My Links no longer exposes a Reports tab; legacy report/export URLs remain compatible.

Tasks support direct creation (including a project board when requested), assignment, checklists, deadlines, list/board/date views and completion/reopening. Notes support private/shared/pinned context and active, completed and snoozed filters. The activity timeline includes visible resource changes, note updates, task events and AI reviews. Mobile navigation uses one scrolling row and a section selector.

Folder settings can move all contents to another folder, retaining schedules. Deleting a folder keeps and unassigns links, forms, QR codes, plans, notes and generic resources, removes folder relationships, and pauses scheduled AI plans before clearing their folder. A queued nonmanual review checks paused state under lock before claiming work. Already-running provider calls cannot be retroactively cancelled by folder deletion.

Deploy with the new `2029_11_03_000001_create_folder_relationships.php` migration. No AI calls are made by migration or these presentation changes.

The notebook offers recent unassigned notes so notes kept after deleting a folder can be moved into another folder by their author. Private notes remain private.
