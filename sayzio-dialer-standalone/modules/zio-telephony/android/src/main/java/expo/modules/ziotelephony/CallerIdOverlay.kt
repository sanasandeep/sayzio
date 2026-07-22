package expo.modules.ziotelephony

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.telephony.PhoneStateListener
import android.telephony.TelephonyCallback
import android.telephony.TelephonyManager
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import java.net.HttpURLConnection
import java.net.URL
import kotlin.math.abs

/**
 * Truecaller-style floating caller-ID card drawn directly through
 * WindowManager (TYPE_APPLICATION_OVERLAY). Built entirely in native code
 * because the JS runtime may not be alive when a call rings.
 *
 * Lifecycle: shown by [ZioCallScreeningService] on ring; auto-dismisses
 * when the call is answered or ends (telephony call-state listener), on
 * a horizontal swipe, via the close button, or after a safety timeout.
 */
object CallerIdOverlay {
  private val main = Handler(Looper.getMainLooper())
  private var view: View? = null
  private var windowManager: WindowManager? = null
  private var stateListener: Any? = null
  private var telephony: TelephonyManager? = null
  private val timeoutRunnable = Runnable { dismiss() }

  // Brand palette (matches the dialer app's dark glass styling).
  private const val CARD_BG = 0xF01A1B26.toInt()
  private const val ACCENT = 0xFF3D6BFF.toInt()
  private const val TEXT_PRIMARY = 0xFFF4F5FA.toInt()
  private const val TEXT_MUTED = 0xFF9AA0B5.toInt()
  // Red warning palette for numbers the user flagged as spam/blocked.
  private const val WARN = 0xFFE5484D.toInt()
  private const val WARN_STROKE = 0x66E5484D
  private const val WARN_AVATAR = 0xFF8A2A2E.toInt()

  fun show(context: Context, info: CallerLookup.Result) {
    val app = context.applicationContext
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(app)) return
    main.post {
      try {
        dismissInternal()
        val wm = app.getSystemService(Context.WINDOW_SERVICE) as WindowManager
        val card = buildCard(app, info)
        val params = WindowManager.LayoutParams(
          WindowManager.LayoutParams.MATCH_PARENT,
          WindowManager.LayoutParams.WRAP_CONTENT,
          if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
          else
            @Suppress("DEPRECATION") WindowManager.LayoutParams.TYPE_PHONE,
          WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
            WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON,
          PixelFormat.TRANSLUCENT,
        )
        params.gravity = Gravity.TOP
        params.y = dp(app, 48)
        wm.addView(card, params)
        view = card
        windowManager = wm
        watchCallState(app)
        // Safety net: never linger more than 60s even if state events are missed.
        main.removeCallbacks(timeoutRunnable)
        main.postDelayed(timeoutRunnable, 60_000)
      } catch (_: Exception) {
        // Overlay is best-effort — never crash the screening service.
      }
    }
  }

  fun dismiss() {
    main.post { dismissInternal() }
  }

  private fun dismissInternal() {
    main.removeCallbacks(timeoutRunnable)
    val v = view
    val wm = windowManager
    view = null
    windowManager = null
    if (v != null && wm != null) {
      try {
        wm.removeView(v)
      } catch (_: Exception) {
        // Already gone.
      }
    }
    stopWatchingCallState()
  }

  // ── Call-state auto-dismiss ─────────────────────────────────────────────

  @SuppressLint("MissingPermission")
  private fun watchCallState(app: Context) {
    try {
      val tm = app.getSystemService(Context.TELEPHONY_SERVICE) as TelephonyManager
      telephony = tm
      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        val cb = object : TelephonyCallback(), TelephonyCallback.CallStateListener {
          override fun onCallStateChanged(state: Int) {
            if (state != TelephonyManager.CALL_STATE_RINGING) dismiss()
          }
        }
        tm.registerTelephonyCallback(app.mainExecutor, cb)
        stateListener = cb
      } else {
        @Suppress("DEPRECATION")
        val listener = object : PhoneStateListener() {
          @Deprecated("Deprecated in Java")
          override fun onCallStateChanged(state: Int, phoneNumber: String?) {
            if (state != TelephonyManager.CALL_STATE_RINGING) dismiss()
          }
        }
        @Suppress("DEPRECATION")
        tm.listen(listener, PhoneStateListener.LISTEN_CALL_STATE)
        stateListener = listener
      }
    } catch (_: Exception) {
      // Fall back to the timeout + manual dismissal.
    }
  }

  private fun stopWatchingCallState() {
    val tm = telephony ?: return
    val l = stateListener
    telephony = null
    stateListener = null
    try {
      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && l is TelephonyCallback) {
        tm.unregisterTelephonyCallback(l)
      } else if (l is PhoneStateListener) {
        @Suppress("DEPRECATION")
        tm.listen(l, PhoneStateListener.LISTEN_NONE)
      }
    } catch (_: Exception) {
      // Nothing to clean up.
    }
  }

  // ── Card construction ───────────────────────────────────────────────────

  private fun dp(c: Context, v: Int): Int = (v * c.resources.displayMetrics.density).toInt()

  @SuppressLint("ClickableViewAccessibility")
  private fun buildCard(app: Context, info: CallerLookup.Result): View {
    val pad = dp(app, 16)
    // Display-only warning state — the call is always let through.
    val flagged = info.isSpam || info.isBlocked

    val root = FrameLayout(app)
    root.setPadding(dp(app, 12), 0, dp(app, 12), 0)

    val card = LinearLayout(app).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
      setPadding(pad, pad, pad, pad)
      background = GradientDrawable().apply {
        setColor(CARD_BG)
        cornerRadius = dp(app, 20).toFloat()
        if (flagged) setStroke(dp(app, 2), WARN_STROKE)
        else setStroke(dp(app, 1), 0x333D6BFF)
      }
      elevation = dp(app, 12).toFloat()
    }

    // Avatar: photo when available, otherwise an accent circle with initial.
    val avatarSize = dp(app, 56)
    val avatar = FrameLayout(app)
    val avatarBg = GradientDrawable().apply {
      shape = GradientDrawable.OVAL
      setColor(
        when {
          flagged -> WARN_AVATAR
          info.name != null -> ACCENT
          else -> 0xFF3A3D4D.toInt()
        },
      )
    }
    val initialView = TextView(app).apply {
      text = if (flagged && info.name == null) "!"
      else (info.name?.trim()?.firstOrNull()?.uppercaseChar() ?: '?').toString()
      setTextColor(Color.WHITE)
      textSize = 24f
      typeface = Typeface.DEFAULT_BOLD
      gravity = Gravity.CENTER
      background = avatarBg
    }
    avatar.addView(
      initialView,
      FrameLayout.LayoutParams(avatarSize, avatarSize),
    )
    val photoView = ImageView(app).apply {
      scaleType = ImageView.ScaleType.CENTER_CROP
      visibility = View.GONE
      clipToOutline = true
      background = GradientDrawable().apply {
        shape = GradientDrawable.OVAL
        setColor(Color.TRANSPARENT)
      }
    }
    avatar.addView(photoView, FrameLayout.LayoutParams(avatarSize, avatarSize))
    loadPhoto(app, info) { bmp ->
      if (bmp != null && view != null) {
        photoView.setImageBitmap(circleCrop(bmp))
        photoView.visibility = View.VISIBLE
      }
    }

    // Text column.
    val col = LinearLayout(app).apply {
      orientation = LinearLayout.VERTICAL
      setPadding(dp(app, 14), 0, dp(app, 8), 0)
    }
    val title = TextView(app).apply {
      text = info.name
        ?: if (flagged) (if (info.isSpam) "Likely spam" else "Blocked number")
        else "Unknown caller"
      setTextColor(if (flagged) WARN else TEXT_PRIMARY)
      textSize = 18f
      typeface = Typeface.DEFAULT_BOLD
      maxLines = 1
    }
    col.addView(title)

    // Red warning line for numbers the user flagged (display-only).
    if (flagged) {
      val warnLabel = when {
        info.isSpam && info.isBlocked -> "⚠ Likely spam · You blocked this number"
        info.isSpam -> "⚠ Likely spam — you flagged this number"
        else -> "⚠ You blocked this number"
      }
      val warn = TextView(app).apply {
        text = warnLabel
        setTextColor(WARN)
        textSize = 12f
        typeface = Typeface.DEFAULT_BOLD
        maxLines = 1
        setPadding(0, dp(app, 2), 0, 0)
      }
      col.addView(warn)
    }

    val subtitleBits = mutableListOf<String>()
    if (info.name != null) subtitleBits.add(info.number)
    info.organization?.let { subtitleBits.add(it) }
    info.locationHint?.let { subtitleBits.add(it) }
    if (info.name == null && info.locationHint == null) subtitleBits.add(info.number)
    val subtitle = TextView(app).apply {
      text = subtitleBits.distinct().joinToString(" · ").ifBlank { "Incoming call" }
      setTextColor(TEXT_MUTED)
      textSize = 13f
      maxLines = 1
    }
    col.addView(subtitle)

    val contextLine = info.lastInteraction
      ?: info.source?.let { "Matched from your $it".replaceFirst("Matched from your Device contact", "From your phone contacts").replaceFirst("Matched from your Sayzio contact", "From your Sayzio contacts") }
      ?: "No previous calls with this number"
    val ctx = TextView(app).apply {
      text = contextLine
      setTextColor(if (info.lastInteraction != null) 0xFF7EA0FF.toInt() else TEXT_MUTED)
      textSize = 12f
      maxLines = 1
      setPadding(0, dp(app, 3), 0, 0)
    }
    col.addView(ctx)

    // Badge row: "Zio Dialer" brand tag.
    val brand = TextView(app).apply {
      text = "ZIO DIALER"
      setTextColor(ACCENT)
      textSize = 9f
      typeface = Typeface.DEFAULT_BOLD
      letterSpacing = 0.12f
      setPadding(0, dp(app, 4), 0, 0)
    }
    col.addView(brand)

    // "Report spam" action for not-yet-flagged numbers. Display-only: it
    // queues the report for the app to sync (POST /dialer/flag) and marks
    // the number locally so the NEXT call shows the red warning — the
    // ringing call itself is never blocked or silenced.
    if (!flagged && info.number.isNotBlank()) {
      val report = TextView(app).apply {
        text = "⚠ Report spam"
        setTextColor(WARN)
        textSize = 12f
        typeface = Typeface.DEFAULT_BOLD
        setPadding(dp(app, 10), dp(app, 5), dp(app, 10), dp(app, 5))
        background = GradientDrawable().apply {
          setColor(0x1AE5484D)
          cornerRadius = dp(app, 12).toFloat()
          setStroke(dp(app, 1), WARN_STROKE)
        }
      }
      report.setOnClickListener {
        try {
          CallerIdStore.addSpamReport(app, info.number)
        } catch (_: Exception) {
          // Best-effort — never crash the overlay.
        }
        report.text = "✓ Reported — future calls will warn"
        report.setOnClickListener(null)
        report.isClickable = false
        report.alpha = 0.85f
      }
      val reportWrap = LinearLayout(app).apply {
        orientation = LinearLayout.HORIZONTAL
        setPadding(0, dp(app, 8), 0, 0)
        addView(
          report,
          LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.WRAP_CONTENT,
            LinearLayout.LayoutParams.WRAP_CONTENT,
          ),
        )
      }
      col.addView(reportWrap)
    }

    // Close button.
    val close = TextView(app).apply {
      text = "✕"
      setTextColor(TEXT_MUTED)
      textSize = 16f
      setPadding(dp(app, 10), dp(app, 6), dp(app, 6), dp(app, 6))
      setOnClickListener { dismiss() }
    }

    card.addView(avatar, LinearLayout.LayoutParams(avatarSize, avatarSize))
    card.addView(col, LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f))
    card.addView(close)

    // Swipe-to-dismiss (horizontal drag past a threshold).
    var downX = 0f
    var dragging = false
    card.setOnTouchListener { v, ev ->
      when (ev.actionMasked) {
        MotionEvent.ACTION_DOWN -> {
          downX = ev.rawX
          dragging = false
          true
        }
        MotionEvent.ACTION_MOVE -> {
          val delta = ev.rawX - downX
          if (abs(delta) > dp(app, 8)) dragging = true
          v.translationX = delta
          v.alpha = 1f - (abs(delta) / (v.width.coerceAtLeast(1) * 1.2f)).coerceIn(0f, 0.8f)
          true
        }
        MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
          val delta = ev.rawX - downX
          if (abs(delta) > dp(app, 96)) {
            dismiss()
          } else {
            v.animate().translationX(0f).alpha(1f).setDuration(150).start()
            if (!dragging && ev.actionMasked == MotionEvent.ACTION_UP) {
              // Treat a plain tap outside the close button as harmless.
            }
          }
          true
        }
        else -> false
      }
    }

    root.addView(
      card,
      FrameLayout.LayoutParams(
        FrameLayout.LayoutParams.MATCH_PARENT,
        FrameLayout.LayoutParams.WRAP_CONTENT,
      ),
    )
    return root
  }

  /** Load device-contact photo or remote Sayzio photo off the main thread. */
  private fun loadPhoto(app: Context, info: CallerLookup.Result, cb: (Bitmap?) -> Unit) {
    val contactUri = info.contactPhotoUri
    val remoteUrl = info.remotePhotoUrl
    if (contactUri == null && remoteUrl == null) return
    Thread {
      var bmp: Bitmap? = null
      try {
        if (contactUri != null) {
          app.contentResolver.openInputStream(Uri.parse(contactUri))?.use {
            bmp = BitmapFactory.decodeStream(it)
          }
        }
        if (bmp == null && remoteUrl != null && remoteUrl.startsWith("https://")) {
          val conn = URL(remoteUrl).openConnection() as HttpURLConnection
          conn.connectTimeout = 3000
          conn.readTimeout = 3000
          conn.inputStream.use { bmp = BitmapFactory.decodeStream(it) }
          conn.disconnect()
        }
      } catch (_: Exception) {
        bmp = null
      }
      val result = bmp
      main.post { cb(result) }
    }.start()
  }

  private fun circleCrop(src: Bitmap): Bitmap {
    val size = minOf(src.width, src.height)
    val x = (src.width - size) / 2
    val y = (src.height - size) / 2
    val squared = Bitmap.createBitmap(src, x, y, size, size)
    val out = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
    val canvas = android.graphics.Canvas(out)
    val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG)
    val rect = android.graphics.Rect(0, 0, size, size)
    canvas.drawARGB(0, 0, 0, 0)
    canvas.drawCircle(size / 2f, size / 2f, size / 2f, paint)
    paint.xfermode = android.graphics.PorterDuffXfermode(android.graphics.PorterDuff.Mode.SRC_IN)
    canvas.drawBitmap(squared, rect, rect, paint)
    return out
  }
}
