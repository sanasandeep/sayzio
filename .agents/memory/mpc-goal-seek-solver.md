---
name: MPC goal-seek solver
description: Marketing Plan Calculator goal-seek linear solve and its e2e fixture pitfall
---
The MPC editor's goal-seek back-solves budget via two model builds (budget 0 → fixed/organic contribution; probe → slope), all metrics being linear in the budget input.
**Why:** the default plan's fixed Sayzio row alone yields ~₹10.7 Cr revenue / ~900 customers / ~5,200 leads per year, so any test target below that hits the "organic" (₹0 budget) state, not "ok" — pick targets well above the organic contribution.
**How to apply:** e2e/feature tests for goal-seek or similar target-driven features must first size targets against the fixture's zero-budget baseline. Also: currency-toggle assertions can't use a pure rate ratio because the fixed offset is subtracted before dividing by slope — assert applied-budget outcomes instead.
