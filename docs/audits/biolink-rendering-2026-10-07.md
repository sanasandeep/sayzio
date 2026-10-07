# Link in Bio rendering audit

Scope: editor fields in `block-settings-form.blade.php`, dedicated renderer dispatch, inline renderer branches, shared profile layouts, live text listener and storefront controls.

## Confirmed fixes

- Profile layouts: configured location, website, CTA, social links and verification omitted by Cover and other layouts. Fixed by the preceding profile-details change.
- Video: Loop and Muted were editor options but the renderer only considered autoplay. Render both flags, retaining muted autoplay for browser compatibility.
- Product: badge and native checkout settings were saved but the partial only exposed an external Buy URL. Render badge, numeric price fallback and the existing storefront buy/add actions. Include visible container children when initializing the storefront so nested products have a working cart.
- Animated text: replacing live preview text removed animated spans but retained the initialization marker. Clear that marker before replacement so the observer rebuilds the animation.

## Investigated fields that are not missing display output

- Verified heading/avatar lock flags are enforced by save logic rather than printed.
- Tip amount CSV is normalized to an amounts array before rendering.
- Product digital files, product type and thank-you copy belong to the checkout/fulfillment flow rather than the product card.
- Event range/count are consumed by the calendar source helper.
- WhatsApp item name belongs to its item renderer, not the widget button.

## Verification and limits

All dedicated partial dispatch paths exist. Blade attribute/Alpine guards and whitespace checks pass. Added renderer tests for video flags and native product actions. PHP and vendor dependencies are unavailable locally; tests must run in CI. This is a source audit, not a claim that all interactive blocks passed live end-to-end tests. Third-party embeds, checkout transactions, uploads and provider APIs require authenticated runtime verification.

## Second pass

Additional confirmed fixes: link labels now have explicit preview targets so edits cannot overwrite arrows, list numbers or descriptions. Alignment patches the actual tilted text wrapper. Phone collector previously only changed the submit button to “Done!” without a request; it now submits to the subscriber endpoint, validates the number, persists a phone capture and confirms only after success. WhatsApp item buttons now use their configured label.

An isolated Chromium check passed for label/description updates, preserved arrow/number and alignment. Added browser regression and phone persistence/validation tests; PHP execution remains pending.

Remaining incomplete features: RSS feed and YouTube feed renderers print the configured URL/channel instead of entries and ignore their count controls. These require a feed implementation; the presence of a renderer branch does not mean the feature works. Poll results visibility is enforced in the API, so its hidden-results setting is not a missing renderer field.
