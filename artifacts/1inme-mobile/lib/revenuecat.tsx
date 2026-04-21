import Constants from "expo-constants";
import React, { createContext, useContext, useEffect, useMemo } from "react";
import { Platform } from "react-native";
import Purchases, {
  type CustomerInfo,
  type PurchasesOffering,
  type PurchasesOfferings,
  type PurchasesPackage,
} from "react-native-purchases";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/contexts/AuthContext";

// Public RevenueCat SDK keys per platform. These are NOT secret — RC
// validates purchases server-side using the secret key set in
// REVENUECAT_REST_API_KEY (consumed by the Laravel API). Configure them
// per the seedRevenueCat.ts script in @workspace/scripts.
const REVENUECAT_TEST_API_KEY = process.env.EXPO_PUBLIC_REVENUECAT_TEST_API_KEY;
const REVENUECAT_IOS_API_KEY = process.env.EXPO_PUBLIC_REVENUECAT_IOS_API_KEY;
const REVENUECAT_ANDROID_API_KEY =
  process.env.EXPO_PUBLIC_REVENUECAT_ANDROID_API_KEY;

let configured = false;

function pickApiKey(): string | null {
  if (
    __DEV__ ||
    Platform.OS === "web" ||
    Constants.executionEnvironment === "storeClient"
  ) {
    return REVENUECAT_TEST_API_KEY ?? null;
  }
  if (Platform.OS === "ios") return REVENUECAT_IOS_API_KEY ?? null;
  if (Platform.OS === "android") return REVENUECAT_ANDROID_API_KEY ?? null;
  return REVENUECAT_TEST_API_KEY ?? null;
}

/**
 * Fire once from the root layout. Safe to call before sign-in — we'll
 * `logIn(userId)` later inside SubscriptionProvider so RevenueCat keeps
 * the same app_user_id the API server uses to verify entitlements.
 */
export function initializeRevenueCat(): void {
  if (configured) return;
  const apiKey = pickApiKey();
  if (!apiKey) {
    // No keys yet — RevenueCat is optional until the user finishes
    // setup. Plans screen will surface a friendly message.
    return;
  }
  Purchases.setLogLevel(Purchases.LOG_LEVEL.WARN);
  Purchases.configure({ apiKey });
  configured = true;
}

export function isRevenueCatConfigured(): boolean {
  return configured;
}

type SubscriptionContextValue = {
  configured: boolean;
  isLoading: boolean;
  customerInfo: CustomerInfo | null;
  offerings: PurchasesOfferings | null;
  currentOffering: PurchasesOffering | null;
  activeEntitlements: string[];
  purchase: (pkg: PurchasesPackage) => Promise<CustomerInfo>;
  restore: () => Promise<CustomerInfo>;
  isPurchasing: boolean;
  isRestoring: boolean;
};

const Context = createContext<SubscriptionContextValue | null>(null);

export function SubscriptionProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const { user } = useAuth();
  const qc = useQueryClient();

  // Bind the RevenueCat user to the API user so that GET /subscribers
  // on the backend returns the matching entitlements.
  useEffect(() => {
    if (!configured) return;
    if (user?.id) {
      Purchases.logIn(String(user.id)).catch(() => {});
    } else {
      Purchases.logOut().catch(() => {});
    }
  }, [user?.id]);

  const customerInfoQuery = useQuery({
    enabled: configured,
    queryKey: ["revenuecat", "customer-info", user?.id ?? null],
    queryFn: () => Purchases.getCustomerInfo(),
    staleTime: 60_000,
  });

  const offeringsQuery = useQuery({
    enabled: configured,
    queryKey: ["revenuecat", "offerings"],
    queryFn: () => Purchases.getOfferings(),
    staleTime: 5 * 60_000,
  });

  const purchaseMutation = useMutation({
    mutationFn: async (pkg: PurchasesPackage) => {
      const { customerInfo } = await Purchases.purchasePackage(pkg);
      return customerInfo;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["revenuecat", "customer-info"] });
    },
  });

  const restoreMutation = useMutation({
    mutationFn: () => Purchases.restorePurchases(),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["revenuecat", "customer-info"] });
    },
  });

  const value = useMemo<SubscriptionContextValue>(() => {
    const info = customerInfoQuery.data ?? null;
    return {
      configured,
      isLoading: customerInfoQuery.isLoading || offeringsQuery.isLoading,
      customerInfo: info,
      offerings: offeringsQuery.data ?? null,
      currentOffering: offeringsQuery.data?.current ?? null,
      activeEntitlements: info ? Object.keys(info.entitlements.active) : [],
      purchase: purchaseMutation.mutateAsync,
      restore: restoreMutation.mutateAsync,
      isPurchasing: purchaseMutation.isPending,
      isRestoring: restoreMutation.isPending,
    };
  }, [
    customerInfoQuery.data,
    customerInfoQuery.isLoading,
    offeringsQuery.data,
    offeringsQuery.isLoading,
    purchaseMutation.mutateAsync,
    purchaseMutation.isPending,
    restoreMutation.mutateAsync,
    restoreMutation.isPending,
  ]);

  return <Context.Provider value={value}>{children}</Context.Provider>;
}

export function useSubscription(): SubscriptionContextValue {
  const ctx = useContext(Context);
  if (!ctx) {
    throw new Error("useSubscription must be used within SubscriptionProvider");
  }
  return ctx;
}
