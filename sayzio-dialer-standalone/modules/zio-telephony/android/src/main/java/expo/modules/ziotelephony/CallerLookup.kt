package expo.modules.ziotelephony

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.provider.CallLog
import android.provider.ContactsContract
import androidx.core.content.ContextCompat

/**
 * Resolves an incoming number to everything the caller-ID card shows:
 * device-contact name/photo, synced Sayzio directory match, a country
 * hint derived from the E.164 prefix, and the most recent call-log
 * interaction with that number ("You called them 28 mins ago").
 *
 * Pure lookups only — no network. Runs on a background thread from the
 * call-screening service, degrades to null fields when a permission is
 * missing.
 */
object CallerLookup {

  data class Result(
    val number: String,
    val name: String?,
    /** content:// photo URI from device contacts (loadable via resolver). */
    val contactPhotoUri: String?,
    /** https photo URL from the synced Sayzio directory. */
    val remotePhotoUrl: String?,
    val organization: String?,
    /** "Sayzio contact" / "Device contact" / null (unknown). */
    val source: String?,
    val locationHint: String?,
    val lastInteraction: String?,
    /** User flagged this number as spam in Sayzio (display-only warning). */
    val isSpam: Boolean = false,
    /** User flagged this number as blocked in Sayzio (display-only warning). */
    val isBlocked: Boolean = false,
  )

  private fun has(context: Context, perm: String) =
    ContextCompat.checkSelfPermission(context, perm) == PackageManager.PERMISSION_GRANTED

  fun lookup(context: Context, rawNumber: String): Result {
    val number = rawNumber.trim()

    var name: String? = null
    var photoUri: String? = null
    var source: String? = null

    // 1) Device contacts (PhoneLookup handles formatting differences).
    if (number.isNotEmpty() && has(context, Manifest.permission.READ_CONTACTS)) {
      try {
        val uri = Uri.withAppendedPath(
          ContactsContract.PhoneLookup.CONTENT_FILTER_URI,
          Uri.encode(number),
        )
        context.contentResolver.query(
          uri,
          arrayOf(
            ContactsContract.PhoneLookup.DISPLAY_NAME,
            ContactsContract.PhoneLookup.PHOTO_URI,
          ),
          null,
          null,
          null,
        )?.use { c ->
          if (c.moveToFirst()) {
            name = c.getString(0)?.takeIf { it.isNotBlank() }
            photoUri = c.getString(1)
            if (name != null) source = "Device contact"
          }
        }
      } catch (_: Exception) {
        // Treat as no match.
      }
    }

    // 2) Synced Sayzio directory (works even with no device-contact match).
    var remotePhoto: String? = null
    var organization: String? = null
    var isSpam = false
    var isBlocked = false
    val dir = CallerIdStore.lookup(context, number)
    if (dir != null) {
      if (name == null && dir.name != null) {
        name = dir.name
        source = "Sayzio contact"
      }
      remotePhoto = dir.photoUrl
      organization = dir.organization
      isSpam = dir.isSpam
      isBlocked = dir.isBlocked
    }

    return Result(
      number = number,
      name = name,
      contactPhotoUri = photoUri,
      remotePhotoUrl = remotePhoto,
      organization = organization,
      source = source,
      locationHint = countryHint(number),
      lastInteraction = lastInteraction(context, number),
      isSpam = isSpam,
      isBlocked = isBlocked,
    )
  }

  /** Most recent call-log row for this number → human context line. */
  private fun lastInteraction(context: Context, number: String): String? {
    if (!has(context, Manifest.permission.READ_CALL_LOG)) return null
    val key = CallerIdStore.normalizeKey(number)
    if (key.isEmpty()) return null
    try {
      context.contentResolver.query(
        CallLog.Calls.CONTENT_URI,
        arrayOf(CallLog.Calls.NUMBER, CallLog.Calls.TYPE, CallLog.Calls.DATE),
        null,
        null,
        CallLog.Calls.DATE + " DESC",
      )?.use { c ->
        var scanned = 0
        while (c.moveToNext() && scanned < 500) {
          scanned++
          val n = c.getString(0) ?: continue
          if (CallerIdStore.normalizeKey(n) != key) continue
          val type = c.getInt(1)
          val date = c.getLong(2)
          val ago = timeAgo(date) ?: return null
          return when (type) {
            CallLog.Calls.OUTGOING_TYPE -> "You called them $ago"
            CallLog.Calls.INCOMING_TYPE -> "They called you $ago"
            CallLog.Calls.MISSED_TYPE -> "Missed their call $ago"
            CallLog.Calls.REJECTED_TYPE -> "You declined their call $ago"
            else -> "Last call $ago"
          }
        }
      }
    } catch (_: Exception) {
      // No context line.
    }
    return null
  }

  private fun timeAgo(epochMillis: Long): String? {
    val delta = System.currentTimeMillis() - epochMillis
    if (delta < 0) return null
    val mins = delta / 60_000
    return when {
      mins < 1 -> "just now"
      mins < 60 -> "$mins min${if (mins == 1L) "" else "s"} ago"
      mins < 60 * 24 -> {
        val h = mins / 60
        "$h hour${if (h == 1L) "" else "s"} ago"
      }
      else -> {
        val d = mins / (60 * 24)
        if (d > 365) null else "$d day${if (d == 1L) "" else "s"} ago"
      }
    }
  }

  // Small static E.164 country-code map — a rough "where is this number
  // from" hint, only shown for full international numbers.
  private val COUNTRY_CODES: List<Pair<String, String>> = listOf(
    // Longer prefixes first so they win over the "1"/"7" catch-alls.
    "852" to "Hong Kong", "853" to "Macau", "886" to "Taiwan",
    "971" to "United Arab Emirates", "972" to "Israel", "966" to "Saudi Arabia",
    "965" to "Kuwait", "974" to "Qatar", "973" to "Bahrain", "968" to "Oman",
    "880" to "Bangladesh", "977" to "Nepal", "94" to "Sri Lanka",
    "92" to "Pakistan", "91" to "India", "98" to "Iran",
    "90" to "Türkiye", "20" to "Egypt", "27" to "South Africa",
    "234" to "Nigeria", "254" to "Kenya", "233" to "Ghana",
    "212" to "Morocco", "216" to "Tunisia", "213" to "Algeria",
    "61" to "Australia", "64" to "New Zealand", "65" to "Singapore",
    "60" to "Malaysia", "62" to "Indonesia", "63" to "Philippines",
    "66" to "Thailand", "84" to "Vietnam", "81" to "Japan",
    "82" to "South Korea", "86" to "China",
    "44" to "United Kingdom", "353" to "Ireland", "33" to "France",
    "49" to "Germany", "39" to "Italy", "34" to "Spain", "351" to "Portugal",
    "31" to "Netherlands", "32" to "Belgium", "41" to "Switzerland",
    "43" to "Austria", "46" to "Sweden", "47" to "Norway", "45" to "Denmark",
    "358" to "Finland", "48" to "Poland", "420" to "Czechia",
    "30" to "Greece", "36" to "Hungary", "40" to "Romania",
    "380" to "Ukraine", "7" to "Russia/Kazakhstan",
    "52" to "Mexico", "55" to "Brazil", "54" to "Argentina",
    "56" to "Chile", "57" to "Colombia", "51" to "Peru",
    "1" to "US/Canada",
  )

  private fun countryHint(number: String): String? {
    val trimmed = number.trim()
    val digits = when {
      trimmed.startsWith("+") -> trimmed.drop(1).filter { it.isDigit() }
      trimmed.startsWith("00") -> trimmed.drop(2).filter { it.isDigit() }
      else -> return null // Local-format numbers carry no derivable country.
    }
    if (digits.length < 7) return null
    for ((code, country) in COUNTRY_CODES) {
      if (digits.startsWith(code)) return country
    }
    return null
  }
}
