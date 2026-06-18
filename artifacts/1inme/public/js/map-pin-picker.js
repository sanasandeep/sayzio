/*
 * Reusable "drop a pin to fill address + lat/lng" map picker for the web app.
 *
 * Defines a single global Alpine.js data factory — window.mapPinPicker(initial)
 * — that owns a Leaflet map, a draggable gradient pin, OpenStreetMap Nominatim
 * reverse/forward geocoding and the reactive `address` / `lat` / `lng` state a
 * host form binds its inputs to.
 *
 * Originally lived inline on the Dialer Identity manual-location editor; lifted
 * here so other coordinate editors (e.g. the biolink Map/Location block) reuse
 * the exact same proven logic instead of reimplementing it.
 *
 * Usage (inside any Alpine component):
 *   x-data="mapPinPicker({ address: '...', lat: '...', lng: '...' })"
 *   ...bind inputs to `address`, `lat`, `lng` (add @input="syncMapFromInputs()"
 *      on the lat/lng inputs), give the map container x-ref="map", and toggle
 *      the picker with @click="toggleMap()" / x-show="showMap".
 *
 * Leaflet itself is loaded lazily on first open from the self-hosted vendor
 * bundle (never a CDN — SRI drift breaks it), so host pages only need to
 * include this small file.
 */
(function () {
  "use strict";

  var PIN_SVG =
    '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
    '<defs><linearGradient id="mpp-g" x1="0" y1="0" x2="0" y2="1">' +
    '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
    "</linearGradient></defs>" +
    '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#mpp-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
    '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
    '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#7c3aed">1</text>' +
    "</svg>";

  // Lazily load the self-hosted Leaflet bundle (CSS + JS). Calls `cb` once
  // window.L is available — immediately if it already is, on the existing
  // tag's load if a load is already in flight, otherwise after injecting it.
  function ensureLeaflet(cb) {
    if (window.L) {
      cb();
      return;
    }
    if (!document.getElementById("mpp-leaflet-css")) {
      var link = document.createElement("link");
      link.id = "mpp-leaflet-css";
      link.rel = "stylesheet";
      link.href = "/css/vendor/leaflet.min.css";
      document.head.appendChild(link);
    }
    var existing = document.getElementById("mpp-leaflet-js");
    if (existing) {
      existing.addEventListener("load", cb);
      return;
    }
    var s = document.createElement("script");
    s.id = "mpp-leaflet-js";
    s.src = "/js/vendor/leaflet.min.js";
    s.onload = cb;
    document.head.appendChild(s);
  }

  function round6(n) {
    return (Math.round(n * 1e6) / 1e6).toString();
  }

  window.mapPinPicker = function (initial) {
    initial = initial || {};
    return {
      address: initial.address != null ? String(initial.address) : "",
      lat: initial.lat != null && initial.lat !== "" ? String(initial.lat) : "",
      lng: initial.lng != null && initial.lng !== "" ? String(initial.lng) : "",
      showMap: false,
      searchQuery: "",
      mpMap: null,
      mpMarker: null,
      _suppressMapSync: false,
      _geoTimer: null,

      toggleMap: function () {
        this.showMap = !this.showMap;
        if (this.showMap) {
          var self = this;
          ensureLeaflet(function () {
            self.$nextTick(function () {
              self.initMap();
            });
          });
        }
      },

      _coord: function (v) {
        var n = parseFloat(v);
        return isFinite(n) ? n : null;
      },

      initMap: function () {
        if (typeof L === "undefined" || !this.$refs.map) return;
        if (this.mpMap) {
          var existing = this.mpMap;
          setTimeout(function () {
            existing.invalidateSize();
          }, 60);
          return;
        }

        var lat = this._coord(this.lat);
        var lng = this._coord(this.lng);
        var hasPoint = lat !== null && lng !== null;
        var center = hasPoint ? [lat, lng] : [20, 0];
        var zoom = hasPoint ? 15 : 2;

        var map = L.map(this.$refs.map, {
          center: center,
          zoom: zoom,
          scrollWheelZoom: true,
          zoomControl: true,
        });
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        var icon = L.divIcon({
          className: "",
          html: '<div class="mpp-marker">' + PIN_SVG + "</div>",
          iconSize: [30, 40],
          iconAnchor: [15, 40],
        });

        var marker = L.marker(center, { icon: icon, draggable: true }).addTo(map);
        if (!hasPoint) marker.setOpacity(0);

        var self = this;
        marker.on("dragend", function () {
          var p = marker.getLatLng();
          self.applyPoint(p.lat, p.lng, false);
        });
        map.on("click", function (e) {
          marker.setLatLng(e.latlng);
          marker.setOpacity(1);
          self.applyPoint(e.latlng.lat, e.latlng.lng, false);
        });

        this.mpMap = map;
        this.mpMarker = marker;
        setTimeout(function () {
          map.invalidateSize();
        }, 80);
      },

      applyPoint: function (lat, lng, recenter) {
        this._suppressMapSync = true;
        this.lat = round6(lat);
        this.lng = round6(lng);
        this._suppressMapSync = false;
        if (recenter && this.mpMap)
          this.mpMap.setView([lat, lng], Math.max(this.mpMap.getZoom(), 15), {
            animate: false,
          });
        this.reverseGeocode(lat, lng);
      },

      syncMapFromInputs: function () {
        if (this._suppressMapSync || !this.mpMap || !this.mpMarker) return;
        var lat = this._coord(this.lat);
        var lng = this._coord(this.lng);
        if (lat === null || lng === null) return;
        this.mpMarker.setLatLng([lat, lng]);
        this.mpMarker.setOpacity(1);
        this.mpMap.setView([lat, lng], Math.max(this.mpMap.getZoom(), 13), {
          animate: false,
        });
      },

      reverseGeocode: function (lat, lng) {
        var self = this;
        clearTimeout(this._geoTimer);
        this._geoTimer = setTimeout(function () {
          fetch(
            "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" +
              lat +
              "&lon=" +
              lng,
            { headers: { Accept: "application/json" } },
          )
            .then(function (r) {
              return r.ok ? r.json() : null;
            })
            .then(function (d) {
              if (d && d.display_name) self.address = d.display_name;
            })
            .catch(function () {});
        }, 250);
      },

      searchAddress: function () {
        var q = (this.searchQuery || "").trim();
        if (!q) return;
        var self = this;
        fetch(
          "https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=" +
            encodeURIComponent(q),
          { headers: { Accept: "application/json" } },
        )
          .then(function (r) {
            return r.ok ? r.json() : null;
          })
          .then(function (d) {
            if (!d || !d.length) {
              if (window.showToast) window.showToast("No matching place found");
              return;
            }
            var lat = parseFloat(d[0].lat),
              lng = parseFloat(d[0].lon);
            self._suppressMapSync = true;
            self.lat = round6(lat);
            self.lng = round6(lng);
            if (d[0].display_name) self.address = d[0].display_name;
            self._suppressMapSync = false;
            if (self.mpMarker) {
              self.mpMarker.setLatLng([lat, lng]);
              self.mpMarker.setOpacity(1);
            }
            if (self.mpMap) self.mpMap.setView([lat, lng], 15, { animate: false });
          })
          .catch(function () {});
      },
    };
  };
})();
