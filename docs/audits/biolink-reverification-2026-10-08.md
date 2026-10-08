# Deployed block re-verification, 8 October 2026

Revisited all 114 canonical admin preview URLs after PR 270 deployment. Waited for the iframe data-block-type wrapper before collecting rendered DOM; discarded an initial scan that read iframe bodies too early.

Confirmed: no fallback renderer errors or leaked Blade expressions across the 114 loaded previews. Card, grid, grid_auto, insider, fan_leaderboard and roadmap now render. Provider handles render @yourname. Missing poll runtime ReferenceErrors were not observed in collected console logs.

New findings: header_video, video, audio, pdf_document, spotify, apple_music, youtube, vimeo, twitch, map, yandex_maps, typeform, calendly, discord_server and iframe_embed overflowed the narrow admin preview. YouTube iframe retained intrinsic width 300 pixels in a 296-pixel body. Add explicit native-media sizing to the standalone admin preview so correctness does not depend on generated utility CSS. Console warned twice per accordion preview that x-collapse plugin was missing; include the already-vendored collapse plugin before Alpine, matching the public page.

RSS Feed still displays only its feed URL. Resume renders nothing without an owner-published public resume by design; this is not evidence of a failed Blade compile. Empty innerText for images, spacers, social icons and embeds is not a failure signal. Community blocks need persisted page data to exercise loading and mutation behavior; their admin skeletons do not prove successful API interactions.

Full CI remains red and one shard terminated prematurely. No all-block end-to-end pass is claimed. Actual external provider interactions, submissions, payments, and every setting combination require additional configured fixtures. No production content was saved or forms submitted during this check.

## After PR 271 deployment

Revisited all 114 loaded admin previews again. None had horizontal overflow, fallback renderer errors or leaked Blade expressions. The deployed YouTube iframe measured 240px within a 296px body. The collapse plugin is now present and its prior warning was not observed. Third-party YouTube scripts still report sandbox cache-storage/writeEmbed errors; fitting the iframe does not verify playback. No sandbox protections were changed.

Downloaded the complete CI log archive for run 37737735725. BlockTextColorRenderingTest failed because its standalone renderer fixture omitted the required link variable. The style-picker distinction test compared only inline_style, so heading/text animation designs were counted as duplicates despite distinct text_design keys. Correct these fixture/assertion defects, also supply link context to the two new standalone renderer regressions, A dedicated CI step with a JUnit artifact was prepared, but GitHub rejected the workflow update because the credential lacks workflow scope. The proposed change is saved locally at /private/tmp/biolink-focused-workflow.patch and is not part of the PR. The full suite remains red and its crashed shard prevents a blanket verification claim.
