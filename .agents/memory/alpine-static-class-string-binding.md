---
name: Alpine string :class never removes static classes
description: Server-rendered "active" classes get stuck forever when the Alpine :class binding uses string syntax
---
Rule: when Blade server-renders an initial active class (e.g. `class="pane {{ $i===0 ? 'pane-on' : '' }}"`) for first paint AND Alpine toggles the same class, the `:class` binding MUST use OBJECT syntax (`:class="{'pane-on': active===i}"`).

**Why:** Alpine's string syntax (`:class="cond ? 'pane-on' : ''"`) only removes classes it previously added — it never strips a class present in the static `class` attribute. Slide 0 stayed permanently visible under every other slide in the home link-types showcase, which read as a "crossfade overlap" bug but was actually a stuck static class. Object syntax does toggle static classes off.

**How to apply:** any Blade+Alpine carousel/tab pattern that seeds an initial "-on" class server-side for pre-hydration paint. Also useful there: put the CSS `transition` only on the active-state class with `transition:none` on the base — CSS uses the NEW state's transition on class change, so deactivate is instant and activate fades in (true out-then-in, zero overlap).
