---
name: Pushing a large repo to GitHub from this workspace
description: Why the Git pane and background pushes fail for the ~600MB history, and the chunked fast-forward push that works.
---

# Large GitHub push: chunked fast-forward pushes, not one big push

**Symptoms:** Git pane push → "Git Error: TIMEOUT". Shell `git push` via nohup/setsid dies between tool calls (sandbox kills detached processes; log freezes mid "Counting objects"). One-shot foreground push exceeds the 120s bash cap (~580MB pack, ~2.8k commits).

**What works:** resumable milestone script (`/tmp/push_chunks.sh` pattern):
- `git rev-list --reverse --first-parent main | awk 'NR%100==0'` + HEAD → milestone list.
- Each invocation: `ls-remote` for the remote tip, skip milestones already ancestors, push `sha:refs/heads/main` (fast-forward) with a per-run time budget (~95s), exit `BUDGET_EXHAUSTED`; re-run the script across multiple tool calls until `ALL_DONE`.

**Gotchas:**
- The sandbox BLOCKS force-push (`+sha:ref`) as a destructive git op — plain fast-forward refspecs are fine and sufficient for sequential milestones.
- Shell has GIT_ASKPASS but it does NOT authenticate GitHub pushes; the Git pane "GitHub Active" connection is UI-only and there is no `github` OpenInt connection. Use a user-provided classic PAT via requestEnvVar, fed through an inline `credential.helper` echoing `username=x-access-token` / `password=$GITHUB_PERSONAL_ACCESS_TOKEN` (never on the command line).
- The PAT needs BOTH `repo` AND `workflow` scopes — history contains `.github/workflows/*`, and pushes touching those are remote-rejected without `workflow`.
- A `git+ssh://git@ssh.riker.replit.dev:...` value in the Git pane Remote field is Replit's own address, not the user's repo — pushes to it time out; set the real `https://github.com/<user>/<repo>.git`.
- Recurrent "may have crashed in this repository earlier: remove the file manually" warnings are stale remote-ref lock noise from earlier killed pushes; pushes still succeed (`OK`/rc=0).

**How to apply:** any future push of this repo's full history (new remote, mirror) should go straight to the chunked script; don't retry the Git pane or detached background pushes.
