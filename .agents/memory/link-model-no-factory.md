---
name: Link model has no factory; tests using it never ran
description: 1inme Link has no Eloquent factory; Link::factory() tests fail at boot, so their coverage is illusory. Also links SEO column is seo_title, not meta_title.
---

# Link model has no factory

- `App\Modules\User\Models\Link` has NO factory (`database/factories/` only holds `UserDatabaseFactory`). A feature test calling `Link::factory()` throws `BadMethodCallException` on every test — the class fails wholesale, meaning it was never run green and its coverage is illusory.
- **Why it matters:** two shipped feature tests "covering" the link-picker endpoint used `Link::factory()`, so a real bug (querying nonexistent `links.meta_title`; the column is `seo_title` — `meta_title` exists only on blog_posts) reached the live editor as an HTTP 500 and was only caught by e2e.
- **How to apply:** create links in tests with `Link::create([...])` plus workspace binding (`app(WorkspaceContext::class)->resolve($user)` → `app()->instance('current_workspace', $ws)`), or the workspace global scope hides them from list endpoints. When a "passing" feature test coexists with a live 500, suspect the test never actually ran.
- Bonus: `Http::fake(['example.com/*' ...])` did not intercept a fetch of the bare root URL in one of these tests (real network hit); use `'*'` when the exact URL shape is uncertain.
