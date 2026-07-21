package expo.modules.ziotelephony

import android.telecom.Call
import android.telecom.CallScreeningService

/**
 * Bound by the system while Zio Dialer holds the caller-ID & spam role
 * (RoleManager.ROLE_CALL_SCREENING). We never block or silence anything —
 * every call is allowed through untouched; the service only exists to learn
 * that a call is ringing so the floating caller-ID card can appear.
 */
class ZioCallScreeningService : CallScreeningService() {
  override fun onScreenCall(callDetails: Call.Details) {
    // Always let the call through — spam blocking is out of scope.
    respondToCall(callDetails, CallResponse.Builder().build())

    try {
      // Only incoming calls get the alert (the role also screens outgoing
      // on some OEMs).
      if (callDetails.callDirection != Call.Details.DIRECTION_INCOMING) return
      if (!CallerIdStore.isEnabled(this)) return

      val number = callDetails.handle?.schemeSpecificPart?.trim().orEmpty()
      // Unknown/private numbers still show a card with the "unknown" state.
      Thread {
        try {
          val info = CallerLookup.lookup(this, number)
          CallerIdOverlay.show(this, info)
        } catch (_: Exception) {
          // Alert is best-effort; the call itself is already allowed.
        }
      }.start()
    } catch (_: Exception) {
      // Never interfere with call delivery.
    }
  }
}
