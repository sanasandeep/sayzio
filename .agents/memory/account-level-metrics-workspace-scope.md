---
name: Account-level metrics in workspace-scoped aggregations
description: Policy for tables without a workspace_id column inside workspace-scoped analytics or feature signals.
---

# Account-level metrics vs workspace scope

**Rule:** in any workspace-scoped aggregation, data from tables without a `workspace_id` column (storefront orders, wallet spend, AI-companion/dialer activity) belongs ONLY to the personal/account scope — workspace id null or the user's own personal workspace. Team workspaces must exclude it entirely, including boolean "feature in use" signals, and the data subject inside a workspace is the workspace OWNER, not the viewing member.

**Why:** otherwise one context's financial or usage data is misattributed to (and disclosed inside) a team workspace.

**How to apply:** compute an `includesPersonal(user, wsId)` decision once and gate all account-level reads on it; personal scope matches `workspace_id IS NULL OR = personal id` for workspace-column tables (legacy rows are NULL). Cover with two-workspace and member-vs-owner tests. Guard clauses around such reads should swallow only undefined table/column (SQLSTATE 42P01/42703) and report everything else.
