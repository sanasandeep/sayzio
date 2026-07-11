/**
 * RevenueCat integration removed.
 *
 * All plan upgrades and coin purchases now redirect to the website's pricing
 * page in the OS external browser. This file is kept as a no-op shim so that
 * any stale imports don't break the build during the transition period, but
 * none of its exports are used by live screens.
 */

export function initializeRevenueCat(): void {}
export function isRevenueCatConfigured(): boolean {
  return false;
}

import React from "react";

export function SubscriptionProvider({
  children,
}: {
  children: React.ReactNode;
}): React.ReactElement {
  return <>{children}</>;
}
