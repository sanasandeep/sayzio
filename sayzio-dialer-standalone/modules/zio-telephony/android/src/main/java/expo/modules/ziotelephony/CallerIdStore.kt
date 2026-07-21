package expo.modules.ziotelephony

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONArray

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
          )
        }
      }
      null
    } catch (_: Exception) {
      null
    }
  }
}
