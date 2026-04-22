import { Platform } from "react-native";
import * as LocalAuthentication from "expo-local-authentication";

export type BiometricCapability = {
  supported: boolean;
  hasHardware: boolean;
  isEnrolled: boolean;
  types: LocalAuthentication.AuthenticationType[];
  label: string;
};

const UNSUPPORTED: BiometricCapability = {
  supported: false,
  hasHardware: false,
  isEnrolled: false,
  types: [],
  label: "Biometric unlock",
};

function describeTypes(
  types: LocalAuthentication.AuthenticationType[],
): string {
  const T = LocalAuthentication.AuthenticationType;
  if (types.includes(T.FACIAL_RECOGNITION)) {
    return Platform.OS === "ios" ? "Face ID" : "Face unlock";
  }
  if (types.includes(T.FINGERPRINT)) {
    return Platform.OS === "ios" ? "Touch ID" : "Fingerprint";
  }
  if (types.includes(T.IRIS)) return "Iris unlock";
  return "Biometric unlock";
}

export async function getBiometricCapability(): Promise<BiometricCapability> {
  if (Platform.OS === "web") return UNSUPPORTED;
  try {
    const [hasHardware, isEnrolled, types] = await Promise.all([
      LocalAuthentication.hasHardwareAsync(),
      LocalAuthentication.isEnrolledAsync(),
      LocalAuthentication.supportedAuthenticationTypesAsync(),
    ]);
    return {
      supported: hasHardware && isEnrolled,
      hasHardware,
      isEnrolled,
      types,
      label: describeTypes(types),
    };
  } catch {
    return UNSUPPORTED;
  }
}

export type BiometricPromptResult =
  | { ok: true }
  | { ok: false; reason: "cancel" | "unavailable" | "lockout" | "error"; message?: string };

export async function promptBiometric(
  promptMessage: string,
): Promise<BiometricPromptResult> {
  if (Platform.OS === "web") {
    return { ok: false, reason: "unavailable" };
  }
  try {
    const cap = await getBiometricCapability();
    if (!cap.supported) {
      return { ok: false, reason: "unavailable" };
    }
    const res = await LocalAuthentication.authenticateAsync({
      promptMessage,
      cancelLabel: "Use another method",
      disableDeviceFallback: false,
    });
    if (res.success) return { ok: true };
    const err = (res as { error?: string }).error ?? "";
    if (err === "user_cancel" || err === "system_cancel" || err === "app_cancel" || err === "user_fallback") {
      return { ok: false, reason: "cancel" };
    }
    if (err === "lockout" || err === "lockout_permanent") {
      return { ok: false, reason: "lockout", message: "Too many attempts" };
    }
    return { ok: false, reason: "error", message: err || undefined };
  } catch (e) {
    return {
      ok: false,
      reason: "error",
      message: e instanceof Error ? e.message : String(e),
    };
  }
}
