# Notes → Action Plan

The former `/user/ai/mind` note summarizer now estimates, generates and saves a structured plan. Existing route and AI billing feature keys (`mind`) are retained.

## Creator workflow

1. Choose Meeting notes, Campaign planning, Customer feedback, Project ideas, or Organise my notes.
2. Paste notes (up to 40,000 characters), attach an image/PDF/text source through Upload, URL or My Files, or enter a public webpage URL. Add industry, audience and goal to the notes for better context. Optionally assign a project/folder.
3. Estimate coins for free. The estimate includes configured provider fallbacks, expires after ten minutes, and is bound to the author and active workspace. Changing inputs invalidates the displayed estimate.
4. Generate and save the plan. Review the summary, decisions, suggested tasks/priorities, unresolved questions and uncertainties.
5. Review the suggested form and page copy before creating an inactive form draft or private, inactive Link in Bio draft. These actions reuse the saved result without another AI charge. Repeated clicks reopen the same draft.
6. Add individual tasks to an owned calendar by choosing title, date/time and timezone. Calendar visibility determines who can see the event. Existing calendar validation and capacity checks apply. Repeated clicks do not create a second event for the same task.

Past plans are private to their author within the active workspace unless explicitly shared through AI Planner. Search titles, filter by project, reopen for free, export JSON, or delete a record. Deleting a plan does not delete already-created forms/pages/events. Saved plans remain readable while AI is disabled. Previous flash-session summaries cannot be backfilled; durable history starts with this feature.

## Configuration and source limitations

The administrator enables AI and selects the `mind` feature model in AI Engine settings. Images, handwritten notes and rendered PDFs require a vision-capable chat model. PDF processing reuses the form builder's bounded document reader: up to six pages, 10 MB, with Poppler (`pdfinfo`/`pdftoppm`) and PHP GD required. Without these dependencies, the UI reports the limitation and asks for page images. Uncertain handwriting is returned as a warning for user review.

Source picker URLs use the existing Vault import controls. The separate webpage reader accepts public HTTP(S) HTML/text, with standard ports only, a 2 MB response limit and bounded timeouts. Resolved public IPs are pinned, private/reserved networks and redirects are rejected, and scripts/styles are removed. Login-protected sources should be pasted or uploaded instead. Source attachments are not duplicated in plan history; structured results and pasted notes are saved.

Generation uses the existing coin ledger. Invalid output or failure to save a generated result refunds the charged coins. Estimate tokens are single-use and protected against concurrent submissions. The feature is blocked for readonly demo accounts because it now saves records.

For tasks, background reviews, AI handoffs and folder workspaces, see [AI Planner](ai-planner.md).

## Deployment

Run the normal Laravel migration step to create `note_action_plans`. Deploy the PHP controllers/services and Blade views together. No AI calls are made by migration or deployment.
