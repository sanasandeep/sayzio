---
name: VP9-alpha WebM transparency verification
description: How to (and how NOT to) verify a transparent VP9-alpha WebM; why ffmpeg/automated reviewers report false "opaque"
---

A transparent VP9-alpha WebM (e.g. a keyed hero mascot clip) cannot be verified with ffmpeg's *native* vp9 decoder. The native decoder never exposes the alpha plane, so:

- `ffprobe` reports the stream `pix_fmt` as `yuv420p` (NOT `yuva420p`) even though alpha exists.
- `alphaextract` fails with `Requested planes not available`.
- Decoding a single frame and sampling background pixels shows alpha **255 / opaque white** — a FALSE NEGATIVE.

This trips up automated code reviewers (they conclude "opaque background, users will see a box").

**Correct verification** — force the libvpx decoder (same one Chrome/Firefox use):
- `ffmpeg -c:v libvpx-vp9 -i clip.webm -frames:v 1 -vf alphaextract a.png` → background alpha 0, mascot 255.
- Composite over a known fill and sample corners:
  `ffmpeg -f lavfi -i color=c=0x14182a:s=WxH -c:v libvpx-vp9 -i clip.webm -frames:v 1 -filter_complex "[0][1]overlay" out.png` → corners = the fill color (transparent), foreground = mascot.
- Confirm the WebM carries the signal: `ffprobe -show_entries stream_tags=alpha_mode` → `alpha_mode=1`.

**Why:** browsers decode VP9 alpha via libvpx + the `alpha_mode=1` Matroska tag; ffmpeg's built-in decoder does not. A correct file looks opaque to every naive ffmpeg check, so there is NO VP9-alpha encoding that passes an `alphaextract`-without-`-c:v libvpx-vp9` test.

**How to apply:** when delivering/keying a transparent WebM, verify with the libvpx decoder + over-dark composite, and pre-empt the reviewer false-negative in the commit message / skip_validation_reason. Encode with `format=rgba,colorkey=...,format=yuva420p -c:v libvpx-vp9 -pix_fmt yuva420p`; the `format=rgba` before colorkey matters (colorkey on YUV input silently fails to key).
