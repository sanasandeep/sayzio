---
name: Repeatable-group old() repopulation
description: How public forms restore repeatable-section data after a 422, and the two lockstep surfaces it needs.
---

# Repeatable-group old() repopulation (public forms)

Public form repeatable sections restore visitor input after a validation
failure across TWO lockstep Blade surfaces — change both or data silently
disappears:

1. `common/form-field-rep.blade.php` — per-field value seeding via
   `old($errKey)` where `$errKey = "rep_{sectionId}.{idx}.{fieldId}"` (this
   key matches the controller's collect/rules/error keys). Every field type
   must be handled (value= for text/date/time/email/phone/number/url;
   textarea body; select `@selected`; radio/scale/consent `@checked`;
   checkbox via an `$oldChecks` array; rating by seeding the Alpine `x-data`
   `value`).
2. `common/form.blade.php` — the repeatable section's Alpine `rCount` must be
   seeded from `old('rep_'.$secId)` count (clamped to repeat_min..cap).
   **Why:** without this, per-field values survive but copies beyond index 0
   stay hidden (rCount reset to min), so the data looks lost. The counter and
   the per-field seed are independent; both are required.

**How to apply:** when touching repeatable-form rendering or adding a new
field type, update the per-field branch AND confirm the copy-counter seed.

## File fields in repeatable copies
File children of repeatable sections are enabled end-to-end (render/collect/
validate). Uploads can't be flashed by `old()` (the browser never resubmits a
file value), so a 422 shows a per-copy "please re-attach" notice keyed by
`_rep_file_pending[sectionId][copyIdx][fieldId]`, flashed just before validate.

**Dropzone `form=` attribute detach trap:** the dropzone partial renders a
`form="{{ $form }}"` attribute when `$form` is truthy. Blade `@include` inherits
parent scope, so including it inside a view whose scope already has `$form` (e.g.
the public form view, where `$form` is the Form model) makes the file `<input>`
render a bogus `form="<model>"` attribute — which **detaches it from its real
`<form>` for submission**. **Symptom that misleads:** the field name never
appears in the multipart body at all, yet `input.closest("form")` still finds the
ancestor form (closest ignores the `form` attribute), so it looks attached.
**Rule:** any dropzone include inside a `$form`-scoped view MUST pass
`'form' => null` explicitly, or the upload is silently dropped with no error.

## Gotchas
- `Form::user_id` is NOT in `$fillable` — `Form::create([...,'user_id'=>x])`
  drops it (NOT NULL violation). Set `$form->user_id = ...; $form->save()`.
- e2e file attach must create the `File` **in-page** via `page.evaluate` (not
  `setInputFiles`): the dropzone `@change` handler rebuilds the FileList through
  a fresh `DataTransfer`, and Playwright's CDP-injected File can't be re-added
  (`dt.items.add` drops it), silently clearing the input before submit.
- To trigger a server 422 in an e2e while leaving rep copies valid, submit via
  `form.submit()` in `page.evaluate` (bypasses HTML5 `required`), not the
  submit button / `requestSubmit`.
- Guarded by `tests/Browser/form-repeatable-old-input.spec.ts` (validation
  gate `e2e-form-rep-old`).
