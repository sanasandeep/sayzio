import { MandatoryNameModal } from "@/components/MandatoryNameModal";
import { useAuth } from "@/contexts/AuthContext";

/**
 * Global resilience gate for the mandatory display-name prompt.
 *
 * A brand-new account (auto-created via OTP verify or social sign-in) may
 * dismiss the immediate name prompt on the auth screen, or background the app
 * before saving a name. The web `RequiresNameMiddleware` blocks such users
 * server-side, but the mobile (token) auth path is not covered by it — and the
 * `/auth/me` endpoint never echoes `needs_name`, so the requirement cannot be
 * recovered from the server. Instead, `applySession` persists a sticky
 * `needs_name` flag that survives a cold launch, surfaced here as
 * `isNameRequired`.
 *
 * Mounted inside the signed-in tabs layout so the prompt re-appears whenever a
 * name-less account lands in the app — on re-open, cold launch, or after a
 * dismissed modal — without stacking on top of the per-screen prompts that
 * run during the auth flow itself, or covering the splash / lock screens.
 */
export function NameRequiredGate() {
  const { isNameRequired, locked } = useAuth();

  return (
    <MandatoryNameModal
      visible={isNameRequired && !locked}
      // The modal clears the sticky flag on a successful save, which flips
      // `isNameRequired` false and hides it — nothing else to do here.
      onSaved={() => {}}
    />
  );
}
