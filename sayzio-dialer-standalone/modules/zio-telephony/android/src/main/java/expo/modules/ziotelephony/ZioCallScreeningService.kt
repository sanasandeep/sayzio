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
        val info = try {
          CallerLookup.lookup(this, number)
        } catch (_: Exception) {
          null
        }
        // Every incoming call with a number becomes CRM history: queue the
        // event natively so the app can sync it when it next opens (the JS
        // runtime is dead while a call rings). Identified callers carry the
        // directory name; unknown numbers queue with no name so the app can
        // show a recent-calls list and offer "save as contact".
        try {
          if (number.isNotEmpty()) {
            CallerIdStore.appendIdentifiedCall(
              this,
              number,
              info?.name,
              info?.organization,
              System.currentTimeMillis(),
            )
          }
        } catch (_: Exception) {
          // Queueing is best-effort.
        }
        try {
          // If the lookup itself failed, still show the card in its
          // "unknown caller" state rather than skipping the alert.
          val display = info ?: CallerLookup.Result(
            number = number,
            name = null,
            contactPhotoUri = null,
            remotePhotoUrl = null,
            organization = null,
            source = null,
            locationHint = null,
            lastInteraction = null,
          )
          CallerIdOverlay.show(this, display)
        } catch (_: Exception) {
          // Alert is best-effort; the call itself is already allowed.
        }
      }.start()
    } catch (_: Exception) {
      // Never interfere with call delivery.
    }
  }
}
