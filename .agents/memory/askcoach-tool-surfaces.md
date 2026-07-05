---
name: AskCoach tool-calling surfaces & parameterized tools
description: Which Ask Coach surfaces support native OpenAI tool-calling vs keyword fallback, and how to add a tool that needs an argument.
---

# Ask Coach tool surfaces

`AskCoachToolRegistry` tools historically were all **parameter-less** (each is
implicitly scoped to the asking user; the model only decides *whether* to pull a
snapshot, not *whose*). Two dispatch paths exist:

- **Native OpenAI tool-calling** — only the **web** `AskCoachController`
  (`app/Modules/User/Controllers/AI/AskCoachController.php`) runs the
  `functionDefinitions()` loop and can invoke a tool the model chose, with
  arguments. This is the ONLY place a parameterized tool actually gets its args.
- **Keyword fallback** — `pickToolsForQuestion()` pre-splices snapshots into the
  system prompt. Used by the web controller as a fallback AND is the *only* path
  the **API/mobile** AskCoachControllers use (they have no native tool loop).
  It cannot supply arguments, so parameterized tools never reach API/mobile.

**Why:** an `event_lookup(query)` tool (single named/dated event lookup beyond the
capped `events()` snapshot) needed an argument. Adding an argument-taking tool
requires: a per-tool JSON-Schema in `parameterSchemas()` wired into
`functionDefinitions()`, `run($tool,$user,$args=[])` gaining the args param, and
the web native loop decoding `$call['function']['arguments']` (a JSON string) and
passing it through. Do NOT add such a tool to `pickToolsForQuestion` — a keyword
route can't produce the argument, and API/mobile would call it with no query.

**How to apply:** parameter-less data tool → just add to `tools()` + a `run()`
match arm. Argument-taking tool → also add a `parameterSchemas()` entry; accept it
web-only via native tool-calling; treat API/mobile parity as a separate task
(would need adding a native tool loop there).

Note: `AiMindFeatureAdapter::events()` snapshot cap = 6 soonest upcoming + fill to
8 with most-recent past. Anything outside that window is invisible to the grounded
prompt — the reason a dedicated on-demand lookup was needed.
