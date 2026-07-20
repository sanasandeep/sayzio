package expo.modules.ziotelephony

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.provider.CallLog
import android.telecom.TelecomManager
import androidx.core.content.ContextCompat
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
  }
}
