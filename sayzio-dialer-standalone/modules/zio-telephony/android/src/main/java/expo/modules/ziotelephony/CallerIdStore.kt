package expo.modules.ziotelephony

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONArray
import org.json.JSONObject

/**
 * SharedPreferences-backed state for the incoming-call caller-ID alert.
 *
 * Everything the native side needs to work while the JS runtime is dead:
 *  - the user's on/off toggle,
 *  - a compact directory of the user's synced Sayzio contacts
 *    (number -> name/photo/org), written by the JS layer after each
 *    contact sync so lookups never need the network.
 *
 * Numbers are indexed by their trailing digits (see [normalizeKey]) so
 * "+1 555 123 4567", "5551234567" and "001-555-123-4567" all match.
 */
object CallerIdStore {
  private const val PREFS = "zio_caller_id"
  private const val KEY_ENABLED = "enabled"
  private const val KEY_DIRECTORY = "directory_json"
  private const val KEY_CALL_QUEUE = "identified_call_queue_json"

  /** Oldest events are dropped once the queue grows past this. */
  private const val MAX_QUEUED_CALLS = 50

  private fun prefs(context: Context): SharedPreferences =
    context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

  fun isEnabled(context: Context): Boolean = prefs(context).getBoolean(KEY_ENABLED, false)

  fun setEnabled(context: Context, enabled: Boolean) {
    prefs(context).edit().putBoolean(KEY_ENABLED, enabled).apply()
  }

  fun setDirectoryJson(context: Context, json: String) {
    prefs(context).edit().putString(KEY_DIRECTORY, json).apply()
  }

  data class DirectoryEntry(
    val name: String?,
    val photoUrl: String?,
    val organization: String?,
    /** User flagged this number as spam (display-only, never blocks). */
    val isSpam: Boolean = false,
    /** User flagged this number as blocked (display-only, never blocks). */
    val isBlocked: Boolean = false,
  )

  /** Last-9-digits key so differing country-code formats still match. */
  fun normalizeKey(number: String): String {
    val digits = number.filter { it.isDigit() }
    return if (digits.length > 9) digits.takeLast(9) else digits
  }

  /** Look an incoming number up in the synced Sayzio directory. */
  fun lookup(context: Context, number: String): DirectoryEntry? {
    val key = normalizeKey(number)
    if (key.isEmpty()) return null
    val raw = prefs(context).getString(KEY_DIRECTORY, null) ?: return null
    return try {
      val arr = JSONArray(raw)
      for (i in 0 until arr.length()) {
        val o = arr.optJSONObject(i) ?: continue
        val n = o.optString("n", "")
        if (n.isNotEmpty() && normalizeKey(n) == key) {
          return DirectoryEntry(
            name = o.optString("name").takeIf { it.isNotBlank() },
            photoUrl = o.optString("photo").takeIf { it.isNotBlank() },
            organization = o.optString("org").takeIf { it.isNotBlank() },
            isSpam = o.optBoolean("spam", false),
            isBlocked = o.optBoolean("blocked", false),
          )
        }
      }
      null
    } catch (_: Exception) {
      null
    }
  }

  // ── Identified-call queue (CRM history sync) ──────────────────────────
  //
  // The screening service appends every incoming call it could identify
  // (matched against the synced Sayzio directory) here, while the JS
  // runtime is dead. When the app next foregrounds, the JS side drains
  // the queue into the Sayzio contact history and clears what it read.

  /**
   * Append one identified incoming call `{n, name, org?, ts}` to the
   * native queue. Oldest entries drop first past [MAX_QUEUED_CALLS].
   */
  @Synchronized
  fun appendIdentifiedCall(
    context: Context,
    number: String,
    name: String,
    organization: String?,
    timestampMs: Long,
  ) {
    try {
      val raw = prefs(context).getString(KEY_CALL_QUEUE, null)
      val arr = try {
        if (raw.isNullOrBlank()) JSONArray() else JSONArray(raw)
      } catch (_: Exception) {
        JSONArray()
      }
      arr.put(
        JSONObject().apply {
          put("n", number)
          put("name", name)
          if (!organization.isNullOrBlank()) put("org", organization)
          put("ts", timestampMs)
        },
      )
      // Keep only the newest MAX_QUEUED_CALLS entries.
      val trimmed = if (arr.length() > MAX_QUEUED_CALLS) {
        JSONArray().also { out ->
          for (i in arr.length() - MAX_QUEUED_CALLS until arr.length()) {
            out.put(arr.get(i))
          }
        }
      } else {
        arr
      }
      prefs(context).edit().putString(KEY_CALL_QUEUE, trimmed.toString()).apply()
    } catch (_: Exception) {
      // Queueing is best-effort; never interfere with call handling.
    }
  }

  /** Raw JSON array of queued identified calls (always valid JSON). */
  @Synchronized
  fun getIdentifiedCallQueueJson(context: Context): String {
    val raw = prefs(context).getString(KEY_CALL_QUEUE, null) ?: return "[]"
    return try {
      JSONArray(raw).toString()
    } catch (_: Exception) {
      "[]"
    }
  }

  /**
   * Remove the first [count] queued events (the ones the JS side just
   * drained). Count-based so calls that ring during a drain survive.
   */
  @Synchronized
  fun removeIdentifiedCallQueueHead(context: Context, count: Int) {
    if (count <= 0) return
    try {
      val raw = prefs(context).getString(KEY_CALL_QUEUE, null) ?: return
      val arr = JSONArray(raw)
      val out = JSONArray()
      for (i in count until arr.length()) out.put(arr.get(i))
      prefs(context).edit().putString(KEY_CALL_QUEUE, out.toString()).apply()
    } catch (_: Exception) {
      // Leave the queue untouched on parse failure.
    }
  }
}
