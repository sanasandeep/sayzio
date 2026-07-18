---
name: Shared password-field partial rollout
description: Gotchas when using/extending the reusable show-hide password Blade partial across the app
---

- `common/partials/password-field.blade.php` is the single source for password inputs with an eye toggle (Alpine `_pwShow`).
- **Don't put `@blur` (or any `@directive`-looking token) inside an `@include` argument string** — Blade compiles it inside the array literal and breaks the compile. The partial exposes an `onBlur` param rendered as `x-on:blur` instead.
- Pages without Tailwind/Alpine (e.g. the public resume unlock page) intentionally do NOT use the partial — they use a self-contained inline vanilla-JS toggle with inline styles, because page-scoped CSS (e.g. `.resume-locked-card button`) would restyle the toggle button.
- The public link-password page needed the vendored `js/vendor/alpine.min.js` + `common.partials.fontawesome` include added to its head before the partial could work there.
- **How to apply:** any new password input should use the partial; sweep with `grep -rn 'type="password"' resources/views` after adding forms.
