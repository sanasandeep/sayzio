package expo.modules.ziotelephony

import android.Manifest
import android.app.role.RoleManager
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.CallLog
import android.provider.Settings
import android.telecom.TelecomManager
import androidx.core.content.ContextCompat
import expo.modules.kotlin.Promise
import expo.modules.kotlin.exception.Exceptions
import expo.modules.kotlin.modules.Module
import expo.modules.kotlin.modules.ModuleDefinition

/**
 * Small Android-only telephony helper for the Zio Dialer:
 *  - device call-log read (Recent tab merge)
 *  - dual-SIM discovery + SIM-targeted outgoing calls (TelecomManager)
 *  - installed-package detection + package-targeted URL opens
 *    (WhatsApp vs WhatsApp Business chooser)
 *
 * Every function degrades gracefully (empty list / false) when a permission
 * is missing so the JS side never needs try/catch for the common paths.
 */
class ZioTelephonyModule : Module() {
  private val context
    get() = appContext.reactContext ?: throw Exceptions.ReactContextLost()

  private fun has(perm: String) =
    ContextCompat.checkSelfPermission(context, perm) == PackageManager.PERMISSION_GRANTED

  private val ROLE_REQUEST_CODE = 4471
  private var pendingRolePromise: Promise? = null

  override fun definition() = ModuleDefinition {
    Name("ZioTelephony")

    // Most-recent-first device call log. Requires READ_CALL_LOG (granted at
    // runtime by the JS side); returns [] when not granted.
    Function("getCallLog") { limit: Int ->
      val out = mutableListOf<Map<String, Any?>>()
      if (!has(Manifest.permission.READ_CALL_LOG)) return@Function out
      val max = if (limit in 1..500) limit else 100
      try {
        context.contentResolver.query(
          CallLog.Calls.CONTENT_URI,
          arrayOf(
            CallLog.Calls.NUMBER,
            CallLog.Calls.TYPE,
            CallLog.Calls.DATE,
            CallLog.Calls.DURATION,
            CallLog.Calls.CACHED_NAME,
          ),
          null,
          null,
          CallLog.Calls.DATE + " DESC",
        )?.use { c ->
          while (c.moveToNext() && out.size < max) {
            out.add(
              mapOf(
                "number" to (c.getString(0) ?: ""),
                "type" to c.getInt(1),
                "date" to c.getLong(2),
                "duration" to c.getLong(3),
                "name" to c.getString(4),
              ),
            )
          }
        }
      } catch (_: SecurityException) {
        // Permission revoked mid-flight — behave as "no access".
      }
      out
    }

    // Call-capable phone accounts (one per active SIM on dual-SIM phones).
    // Requires READ_PHONE_STATE; returns [] when not granted/unavailable.
    Function("getCallAccounts") {
      val empty = emptyList<Map<String, Any?>>()
      if (!has(Manifest.permission.READ_PHONE_STATE)) return@Function empty
      val tm = context.getSystemService(TelecomManager::class.java) ?: return@Function empty
      try {
        tm.callCapablePhoneAccounts.mapIndexed { idx, handle ->
          val label = try {
            tm.getPhoneAccount(handle)?.label?.toString()
          } catch (_: SecurityException) {
            null
          }
          mapOf(
            "index" to idx,
            "label" to (label?.takeIf { it.isNotBlank() } ?: "SIM ${idx + 1}"),
            "id" to handle.id,
          )
        }
      } catch (_: SecurityException) {
        empty
      }
    }

    // Place a call, optionally pinned to a specific call-capable account
    // (accountIndex from getCallAccounts; pass -1 for the system default).
    // Requires CALL_PHONE. Returns false when the call could not be placed.
    Function("placeCall") { number: String, accountIndex: Int ->
      if (number.isBlank() || !has(Manifest.permission.CALL_PHONE)) return@Function false
      val tm = context.getSystemService(TelecomManager::class.java) ?: return@Function false
      val extras = Bundle()
      if (accountIndex >= 0 && has(Manifest.permission.READ_PHONE_STATE)) {
        try {
          val accounts = tm.callCapablePhoneAccounts
          if (accountIndex < accounts.size) {
            extras.putParcelable(TelecomManager.EXTRA_PHONE_ACCOUNT_HANDLE, accounts[accountIndex])
          }
        } catch (_: SecurityException) {
          // Fall through — call on the default account instead.
        }
      }
      try {
        tm.placeCall(Uri.fromParts("tel", number, null), extras)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Needs a matching <queries> manifest entry (added via config plugin)
    // for the probe to see the package on Android 11+.
    Function("isPackageInstalled") { pkg: String ->
      try {
        context.packageManager.getPackageInfo(pkg, 0)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Open a URL pinned to a specific app package (e.g. WhatsApp Business).
    Function("openUrlWithPackage") { pkg: String, url: String ->
      try {
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url)).apply {
          setPackage(pkg)
          addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        context.startActivity(intent)
        true
      } catch (_: Exception) {
        false
      }
    }

    // ── Incoming-call caller-ID alert (Truecaller-style overlay) ──────────

    // Whether this device supports the alert at all (needs the
    // call-screening role, Android 10+).
    Function("isCallerIdAlertSupported") {
      Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q &&
        context.getSystemService(RoleManager::class.java)
          ?.isRoleAvailable(RoleManager.ROLE_CALL_SCREENING) == true
    }

    // "Display over other apps" permission state.
    Function("hasOverlayPermission") {
      Settings.canDrawOverlays(context)
    }

    // Send the user to the system "Display over other apps" settings page
    // for this app; the JS side re-checks on foreground.
    Function("openOverlayPermissionSettings") {
      try {
        val intent = Intent(
          Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
          Uri.parse("package:${context.packageName}"),
        ).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
        context.startActivity(intent)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Whether we currently hold the "caller ID & spam app" role.
    Function("hasCallScreeningRole") {
      if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return@Function false
      context.getSystemService(RoleManager::class.java)
        ?.isRoleHeld(RoleManager.ROLE_CALL_SCREENING) == true
    }

    // Fire the system prompt asking the user to set Zio Dialer as the
    // caller-ID & spam app. Resolves true when the role was granted.
    AsyncFunction("requestCallScreeningRole") { promise: Promise ->
      if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
        promise.resolve(false)
        return@AsyncFunction
      }
      val rm = context.getSystemService(RoleManager::class.java)
      if (rm == null || !rm.isRoleAvailable(RoleManager.ROLE_CALL_SCREENING)) {
        promise.resolve(false)
        return@AsyncFunction
      }
      if (rm.isRoleHeld(RoleManager.ROLE_CALL_SCREENING)) {
        promise.resolve(true)
        return@AsyncFunction
      }
      val activity = appContext.currentActivity
      if (activity == null) {
        promise.resolve(false)
        return@AsyncFunction
      }
      try {
        pendingRolePromise?.resolve(false)
        pendingRolePromise = promise
        activity.startActivityForResult(
          rm.createRequestRoleIntent(RoleManager.ROLE_CALL_SCREENING),
          ROLE_REQUEST_CODE,
        )
      } catch (_: Exception) {
        pendingRolePromise = null
        promise.resolve(false)
      }
    }

    OnActivityResult { _, payload ->
      if (payload.requestCode == ROLE_REQUEST_CODE) {
        val p = pendingRolePromise
        pendingRolePromise = null
        if (p != null) {
          val held = try {
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q &&
              context.getSystemService(RoleManager::class.java)
                ?.isRoleHeld(RoleManager.ROLE_CALL_SCREENING) == true
          } catch (_: Exception) {
            false
          }
          p.resolve(held)
        }
      }
    }

    // User toggle persisted natively so the screening service can read it
    // without the JS runtime.
    Function("isCallerIdAlertEnabled") {
      CallerIdStore.isEnabled(context)
    }
    Function("setCallerIdAlertEnabled") { enabled: Boolean ->
      CallerIdStore.setEnabled(context, enabled)
    }

    // JS pushes the synced Sayzio contact directory here after each contact
    // sync: a JSON array of {n: number, name, photo?, org?} objects, so the
    // native lookup works while the app is dead.
    Function("setCallerDirectory") { json: String ->
      try {
        CallerIdStore.setDirectoryJson(context, json)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Queued identified incoming calls (appended by the screening service
    // while the JS runtime was dead) as a raw JSON array of
    // {n, name, org?, ts} objects. JS drains this into the Sayzio contact
    // history on foreground, then clears what it read via
    // clearIdentifiedCallQueue(count).
    Function("getIdentifiedCallQueue") {
      CallerIdStore.getIdentifiedCallQueueJson(context)
    }

    // Remove the first `count` queued events (count-based so calls that
    // ring mid-drain are never lost).
    Function("clearIdentifiedCallQueue") { count: Int ->
      try {
        CallerIdStore.removeIdentifiedCallQueueHead(context, count)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Numbers the user reported as spam from the overlay card while the JS
    // runtime was dead. The JS layer drains this queue to POST /dialer/flag
    // on app open/foreground, then force-refreshes the caller directory.
    Function("getPendingSpamReports") {
      try {
        CallerIdStore.getPendingSpamReports(context)
      } catch (_: Exception) {
        emptyList<String>()
      }
    }

    // Remove one number from the pending queue once the server accepted it.
    Function("removePendingSpamReport") { number: String ->
      try {
        CallerIdStore.removePendingSpamReport(context, number)
        true
      } catch (_: Exception) {
        false
      }
    }

    // Preview the floating card with a fake ringing number — used by the
    // settings screen so users can see what the alert looks like.
    Function("showTestCallerIdAlert") { number: String ->
      if (!Settings.canDrawOverlays(context)) return@Function false
      Thread {
        try {
          val info = CallerLookup.lookup(context, number)
          CallerIdOverlay.show(context, info)
        } catch (_: Exception) {
          // Best-effort preview.
        }
      }.start()
      true
    }

    // Manual dismiss hook (used when the preview should be cleared).
    Function("dismissCallerIdAlert") {
      CallerIdOverlay.dismiss()
    }
  }
}
