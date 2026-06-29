---
name: WhisperService audio filename quirk
description: Why raw-bytes audio fed to WhisperService can be mis-decoded by OpenAI, and how to avoid it.
---

`WhisperService::transcribe($user, $audio)` accepts either an `UploadedFile`
or a raw byte string. For a raw string, `audioName()` hardcodes the multipart
filename to `audio.webm`.

**Why this matters:** OpenAI's transcription API infers the audio container
format from the filename extension. Feeding non-webm bytes (e.g. WhatsApp
voice notes, which are OGG/Opus) as a raw string sends them labelled
`audio.webm`, which can cause format mis-detection / transcription failure.

**How to apply:** when transcribing audio whose real format isn't webm, wrap
the bytes in an `Illuminate\Http\UploadedFile` (temp file, test-mode `true`)
with a filename carrying the correct extension derived from the media MIME
(e.g. `.ogg` for `audio/ogg`), then pass that UploadedFile to `transcribe()`.
Don't pass raw OGG bytes directly.
