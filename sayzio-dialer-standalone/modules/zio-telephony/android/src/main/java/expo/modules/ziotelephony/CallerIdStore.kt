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

  // Numbers the user reported as spam from the overlay card while the JS
  // runtime was dead. JS drains this queue to POST /dialer/flag on app
  // open/foreground, then force-refreshes the directory.
  private const val KEY_PENDING_REPORTS = "pending_spam_reports"
  // Local display-only spam overrides so the NEXT call from a just-reported
  // number warns immediately, before the server round-trip. Keyed by
  // normalizeKey; superseded once the synced directory carries the flag.
  private const val KEY_LOCAL_SPAM = "local_spam_keys"

  private fun prefs(context: Context): SharedPreferences =
    context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

  fun isEnabled(context: Context): Boolean = prefs(context).getBoolean(KEY_ENABLED, false)

  fun setEnabled(context: Context, enabled: Boolean) {
    prefs(context).edit().putBoolean(KEY_ENABLED, enabled).apply()
  }

  fun setDirectoryJson(context: Context, json: String) {
    prefs(context).edit().putString(KEY_DIRECTORY, json).apply()
  }

  // ── "Report spam" from the overlay (offline queue + local override) ────

  private fun readStringArray(context: Context, key: String): MutableList<String> {
    val out = mutableListOf<String>()
    val raw = prefs(context).getString(key, null) ?: return out
    try {
      val arr = JSONArray(raw)
      for (i in 0 until arr.length()) {
        val v = arr.optString(i, "")
        if (v.isNotBlank()) out.add(v)
      }
    } catch (_: Exception) {
      // Corrupt payload — start fresh.
    }
    return out
  }

  private fun writeStringArray(context: Context, key: String, values: List<String>) {
    val arr = JSONArray()
    for (v in values) arr.put(v)
    prefs(context).edit().putString(key, arr.toString()).apply()
  }

  /**
   * Queue a number the user reported as spam from the overlay and remember
   * a local display-only override so the next call warns immediately.
   * Display-only — never blocks or silences anything.
   */
  fun addSpamReport(context: Context, number: String) {
    val trimmed = number.trim()
    if (trimmed.isEmpty()) return
    val key = normalizeKey(trimmed)
    if (key.isEmpty()) return
    val pending = readStringArray(context, KEY_PENDING_REPORTS)
    if (pending.none { normalizeKey(it) == key }) {
      pending.add(trimmed)
      // Bound the queue defensively; oldest reports drop first.
      while (pending.size > 100) pending.removeAt(0)
      writeStringArray(context, KEY_PENDING_REPORTS, pending)
    }
    val local = readStringArray(context, KEY_LOCAL_SPAM)
    if (!local.contains(key)) {
      local.add(key)
      while (local.size > 200) local.removeAt(0)
      writeStringArray(context, KEY_LOCAL_SPAM, local)
    }
  }

  /** Numbers awaiting a POST /dialer/flag from the JS layer. */
  fun getPendingSpamReports(context: Context): List<String> =
    readStringArray(context, KEY_PENDING_REPORTS)

  /** Remove one number from the pending queue after a successful server POST. */
  fun removePendingSpamReport(context: Context, number: String) {
    val key = normalizeKey(number)
    if (key.isEmpty()) return
    val pending = readStringArray(context, KEY_PENDING_REPORTS)
    val next = pending.filter { normalizeKey(it) != key }
    if (next.size != pending.size) writeStringArray(context, KEY_PENDING_REPORTS, next)
  }

  private fun isLocallyFlaggedSpam(context: Context, key: String): Boolean =
    key.isNotEmpty() && readStringArray(context, KEY_LOCAL_SPAM).contains(key)

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

  /**
   * Look an incoming number up in the synced Sayzio directory, merged with
   * any local "reported spam from the overlay" override so a just-reported
   * number warns on its very next call, before the server sync lands.
   */
  fun lookup(context: Context, number: String): DirectoryEntry? {
    val key = normalizeKey(number)
    if (key.isEmpty()) return null
    val localSpam = isLocallyFlaggedSpam(context, key)
    val raw = prefs(context).getString(KEY_DIRECTORY, null)
    if (raw != null) {
      try {
        val arr = JSONArray(raw)
        for (i in 0 until arr.length()) {
          val o = arr.optJSONObject(i) ?: continue
          val n = o.optString("n", "")
          if (n.isNotEmpty() && normalizeKey(n) == key) {
            return DirectoryEntry(
              name = o.optString("name").takeIf { it.isNotBlank() },
              photoUrl = o.optString("photo").takeIf { it.isNotBlank() },
              organization = o.optString("org").takeIf { it.isNotBlank() },
              isSpam = o.optBoolean("spam", false) || localSpam,
              isBlocked = o.optBoolean("blocked", false),
            )
          }
        }
      } catch (_: Exception) {
        // Fall through to the local-override-only entry below.
      }
    }
    if (localSpam) {
      return DirectoryEntry(
        name = null,
        photoUrl = null,
        organization = null,
        isSpam = true,
        isBlocked = false,
      )
    }
    return null
  }

  // ── Incoming-call queue (CRM history + missed-call sync) ──────────────
  //
  // The screening service appends every incoming call here while the JS
  // runtime is dead — identified callers (matched against the synced
  // Sayzio directory) carry their name; unknown numbers queue with no
  // name so no call is ever lost. When the app next foregrounds, the JS
  // side drains the queue (contact history for matched callers, a
  // recent-unknown-callers list otherwise) and clears what it read.

  /**
   * Append one incoming call `{n, name?, org?, ts}` to the native queue
   * ([name] is null for unidentified callers). Oldest entries drop first
   * past [MAX_QUEUED_CALLS].
   */
  @Synchronized
  fun appendIdentifiedCall(
    context: Context,
    number: String,
    name: String?,
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
          if (!name.isNullOrBlank()) put("name", name)
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
