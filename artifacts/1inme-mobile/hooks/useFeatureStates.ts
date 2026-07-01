import { useQuery } from "@tanstack/react-query";

import {
  featureStates,
  HREF_FEATURE_KEY,
  type FeatureState,
} from "@/lib/api/featureStates";

/**
 * Shared, read-only accessor for the app-wide "Coming soon" feature-state
 * system. Fetches every catalogue feature's resolved state once (cached) and
 * exposes lookups by feature key or by profile-menu `href`, so any surface
 * can render a "Soon" badge and reroute to the branded preview screen.
 *
 * Fails OPEN: until the data resolves (or if it can't be loaded) nothing is
 * reported as coming soon, so we never hide a working feature behind a badge.
 */
export type FeatureStatesGate = {
  isLoading: boolean;
  byKey: Map<string, FeatureState>;
  stateForKey: (key: string) => FeatureState | null;
  stateForHref: (href: string) => FeatureState | null;
  isComingSoonKey: (key: string) => boolean;
  isComingSoonHref: (href: string) => boolean;
};

export function useFeatureStates(): FeatureStatesGate {
  const q = useQuery({
    queryKey: ["feature-states"],
    queryFn: () => featureStates.list(),
    staleTime: 5 * 60 * 1000,
  });

  const list: FeatureState[] = q.data ?? [];
  const byKey = new Map<string, FeatureState>(list.map((f) => [f.key, f]));

  function stateForKey(key: string): FeatureState | null {
    return byKey.get(key) ?? null;
  }

  function stateForHref(href: string): FeatureState | null {
    const key = HREF_FEATURE_KEY[href];
    return key ? (byKey.get(key) ?? null) : null;
  }

  function isComingSoonKey(key: string): boolean {
    return stateForKey(key)?.status === "coming_soon";
  }

  function isComingSoonHref(href: string): boolean {
    return stateForHref(href)?.status === "coming_soon";
  }

  return {
    isLoading: q.isLoading,
    byKey,
    stateForKey,
    stateForHref,
    isComingSoonKey,
    isComingSoonHref,
  };
}
