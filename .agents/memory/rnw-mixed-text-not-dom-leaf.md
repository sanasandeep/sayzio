---
name: RNW mixed <Text> is not an exact-match DOM leaf
description: In react-native-web e2e, a <Text> mixing raw strings with nested <Text> never renders as a single exact-text DOM leaf; key assertions off standalone leaves.
---

In the 1inme-mobile Playwright e2e harnesses that identify elements by exact
text (leaf = `el.children.length === 0` with matching `textContent`), a
react-native-web `<Text>` that mixes a raw string with nested `<Text>` spans
(e.g. the Plans "Plan upgrades are completed on the website. Open pricing
page in your browser…" banner) renders the raw strings as text NODES inside a
parent element that also has element children. So there is NO element whose
`textContent` equals just the leading sentence — an exact-match leaf lookup
(or Playwright `getByText(..., { exact: true })`) for that substring times out.

**Why:** RNW flattens `<Text>{"str"}<Text>…</Text></Text>` into one span with
mixed text-node + element children; only pure single-string `<Text>` becomes a
childless leaf.

**How to apply:** For mount-readiness / presence assertions, key off a
standalone single-string `<Text>` leaf that always renders — e.g. a plan name
("Pro"), a toggle label, or the banner TITLE (`<Text>Downgrade scheduled</Text>`)
— not a mixed sentence with an inline link. The sibling upgrade e2e keys off
"Pro" for exactly this reason.
