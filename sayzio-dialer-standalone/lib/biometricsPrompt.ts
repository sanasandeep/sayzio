import { Platform } from "react-native";
import { showAlert } from "@/lib/webAlert";

type AuthLike = {
  shouldOfferBiometricEnrollment: () => Promise<boolean>;
  enableBiometricUnlock: () => Promise<
    { ok: true } | { ok: false; reason: string; message?: string }
  >;
  dismissBiometricEnrollmentPrompt: () => Promise<void>;
  biometricCapability: { label: string } | null;
  refreshBiometricCapability: () => Promise<{ label: string; supported: boolean }>;
};

// One-shot post sign-in prompt that asks the user whether to enable
// biometric unlock. Safe to call after every successful sign-in — it's a
// no-op when biometrics are unsupported, already enabled, or the user has
// previously chosen "Don't ask again".
export async function maybeOfferBiometricEnrollment(auth: AuthLike) {
  if (Platform.OS === "web") return;
  const should = await auth.shouldOfferBiometricEnrollment();
  if (!should) return;
  const cap = await auth.refreshBiometricCapability();
  if (!cap.supported) return;
  const label = cap.label || "biometric unlock";
  showAlert(
    `Unlock with ${label}?`,
    `Use ${label} next time so you don't need to sign in again.`,
    [
      {
        text: "Don't ask again",
        style: "destructive",
        onPress: () => {
          auth.dismissBiometricEnrollmentPrompt().catch(() => {});
        },
      },
      { text: "Not now", style: "cancel" },
      {
        text: "Enable",
        onPress: async () => {
          const res = await auth.enableBiometricUnlock();
          if (!res.ok && res.reason !== "cancel") {
            showAlert(
              "Couldn't enable",
              res.message ?? "Please try again from Settings.",
            );
          }
        },
      },
    ],
  );
}
