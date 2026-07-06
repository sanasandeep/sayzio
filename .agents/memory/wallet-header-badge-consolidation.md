---
name: Coin wallet is a single header badge, not a sidebar surface
description: Coin balance lives only in the top-header icon+badge; sidebar Wallet nav and the Account-section "Available coins" card were removed to avoid duplicate coin surfaces.
---

The coin wallet balance has exactly one persistent surface: a compact icon+badge in
the shared `<header>` of `user/layouts/app.blade.php` (used for both desktop and
mobile, since there is only one header partial gated by `lg:hidden`/`hidden lg:flex`
on individual controls, not the header itself).

- Badge color is data-driven, not a fixed accent: red when `balance < wallet.low_balance_threshold`,
  green otherwise. When `WalletService::isEnabled()` is false, the badge still renders
  (reads "0", forced red) rather than disappearing — the link still points at
  `user.wallet.show`, which renders its own "locked by admin" state.
- Do NOT reintroduce a Wallet entry in the sidebar `<aside>` or the mobile drawer, and
  do NOT reintroduce the "Available coins" card in the collapsible Account section
  (desktop `$__showAccount` / mobile `$__mShowAccount`) — both were intentionally
  removed as duplicate coin surfaces. That Account section is now badges-only.
- The live-refresh script (`[data-wallet-badge]`, listens for `wallet:refresh` /
  `wallet:balance` custom events, polls `route('user.wallet.balance')`) recolors the
  badge using the `low` boolean the endpoint returns (or a `data-wallet-threshold`
  attribute on an ancestor as a fallback) — any future coin-changing action should
  dispatch one of those events rather than hand-rolling its own repaint.
- A separate, pre-existing "AI usage" tile on the dashboard also links to
  `user.wallet.show` (shows the coin balance funding AI features) — that is NOT a
  duplicate surface to remove; it's contextual, not an account-level balance display.
