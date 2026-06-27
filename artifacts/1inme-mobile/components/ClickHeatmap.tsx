import {
  forwardRef,
  useImperativeHandle,
  useMemo,
  useRef,
} from "react";
import { Platform, View, type StyleProp, type ViewStyle } from "react-native";

// Lazy-require so the web bundle never tries to evaluate the native module.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

export type HeatPoint = {
  lat: number;
  lng: number;
  count: number;
};

export type LivePulse = {
  id: number;
  lat: number;
  lng: number;
};

export type ClickHeatmapHandle = {
  addLivePoints: (points: LivePulse[]) => void;
};

export type ClickHeatmapProps = {
  points: HeatPoint[];
  maxWeight: number;
  height?: number;
  style?: StyleProp<ViewStyle>;
};

function buildHtml(points: HeatPoint[], maxWeight: number): string {
  const max = Math.max(1, maxWeight);
  // Normalize each weight to a 0.15–1.0 intensity so single clicks still show.
  const data = JSON.stringify(
    points
      .filter((p) => isFinite(p.lat) && isFinite(p.lng))
      .map((p) => [p.lat, p.lng, Math.max(0.15, Math.min(1, p.count / max))]),
  );

  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  html, body, #map { height: 100%; margin: 0; padding: 0; background: #11131c; }
  .leaflet-control-attribution { font-size: 8px; }
  .live-pulse {
    width: 16px; height: 16px; border-radius: 50%;
    background: rgba(125, 155, 255, 0.95);
    box-shadow: 0 0 0 rgba(125,155,255,0.7);
    animation: livePulse 1.8s ease-out infinite;
  }
  @keyframes livePulse {
    0%   { box-shadow: 0 0 0 0 rgba(125,155,255,0.55); transform: scale(0.7); }
    70%  { box-shadow: 0 0 0 22px rgba(125,155,255,0); transform: scale(1); }
    100% { box-shadow: 0 0 0 0 rgba(125,155,255,0); transform: scale(0.7); }
  }
</style>
</head><body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
  var heatPoints = ${data};
  var map = L.map('map', {
    zoomControl: true, attributionControl: true,
    worldCopyJump: true
  }).setView([20, 0], 1);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    subdomains: 'abcd',
    attribution: '&copy; OpenStreetMap &copy; CARTO'
  }).addTo(map);

  if (heatPoints.length) {
    L.heatLayer(heatPoints, {
      radius: 22, blur: 18, maxZoom: 12, max: 1.0,
      gradient: { 0.2: '#3d6bff', 0.45: '#7d9bff', 0.7: '#ffd166', 1.0: '#ef476f' }
    }).addTo(map);
    var bounds = heatPoints.map(function (p) { return [p[0], p[1]]; });
    if (bounds.length > 1) {
      map.fitBounds(bounds, { padding: [28, 28], maxZoom: 12 });
    } else {
      map.setView(bounds[0], 6);
    }
  }

  // Live pins pushed from the host via injectJavaScript. Each pin pulses for
  // a few seconds then removes itself so the map doesn't accumulate markers.
  var seenLive = {};
  window.addLivePoints = function (pts) {
    try {
      (pts || []).forEach(function (p) {
        if (!p || seenLive[p.id]) return;
        if (!isFinite(p.lat) || !isFinite(p.lng)) return;
        seenLive[p.id] = true;
        var icon = L.divIcon({
          className: '',
          html: '<div class="live-pulse"></div>',
          iconSize: [16, 16], iconAnchor: [8, 8]
        });
        var m = L.marker([p.lat, p.lng], { icon: icon, interactive: false }).addTo(map);
        setTimeout(function () { try { map.removeLayer(m); } catch (e) {} }, 6500);
      });
    } catch (e) {}
    return true;
  };

  setTimeout(function () { map.invalidateSize(); }, 120);
</script>
</body></html>`;
}

/**
 * Read-only Leaflet click heatmap (mobile parity with the web
 * /links/{link}/heatmap surface). Renders aggregated click coordinates as a
 * heat layer over dark CARTO tiles. The host can push freshly-arrived clicks
 * via the imperative `addLivePoints` handle to animate a live pulse on top of
 * the static heat layer without rebuilding the map.
 */
export const ClickHeatmap = forwardRef<ClickHeatmapHandle, ClickHeatmapProps>(
  function ClickHeatmap({ points, maxWeight, height = 260, style }, ref) {
    const webRef = useRef<import("react-native-webview").WebView>(null);

    // Rebuild the HTML only when the static dataset changes (not on live polls).
    const html = useMemo(
      () => buildHtml(points, maxWeight),
      [points, maxWeight],
    );

    useImperativeHandle(
      ref,
      () => ({
        addLivePoints: (pts: LivePulse[]) => {
          if (!pts.length || !webRef.current) return;
          const json = JSON.stringify(pts);
          webRef.current.injectJavaScript(
            `window.addLivePoints && window.addLivePoints(${json}); true;`,
          );
        },
      }),
      [],
    );

    if (!WebView) return null;

    return (
      <View
        style={[{ height, width: "100%", backgroundColor: "#11131c", borderRadius: 12, overflow: "hidden" }, style]}
      >
        <WebView
          ref={webRef}
          originWhitelist={["*"]}
          source={{ html }}
          style={{ flex: 1, backgroundColor: "#11131c" }}
          javaScriptEnabled
        />
      </View>
    );
  },
);
