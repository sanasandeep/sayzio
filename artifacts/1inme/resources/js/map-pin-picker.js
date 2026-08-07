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
    '<stop offset="0%" stop-color="#90acff"/><stop offset="100%" stop-color="#3d6bff"/>' +
    "</linearGradient></defs>" +
    '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#mpp-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
    '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
    '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#3d6bff">1</text>' +
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

      /* ---- Autocomplete (filter-as-you-type) --------------------------- */
      suggestions: [],
      _suggestTimer: null,

      suggestPlaces: function () {
        var q = (this.address || "").trim();
        clearTimeout(this._suggestTimer);
        // Always bump the request id so any in-flight response is discarded —
        // including when the input just became too short / empty, otherwise a
        // stale response would repopulate suggestions for a cleared field.
        var reqId = (this._suggestReq = (this._suggestReq || 0) + 1);
        if (q.length < 3 || /^https?:\/\//i.test(q)) {
          this.suggestions = [];
          return;
        }
        var self = this;
        // Suggestions go through our own cached, throttled server proxy —
        // public Nominatim forbids client-side autocomplete, so the browser
        // must never call it per keystroke.
        this._suggestTimer = setTimeout(function () {
          fetch("/user/geo/suggest?q=" + encodeURIComponent(q), {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
          })
            .then(function (r) {
              return r.ok ? r.json() : null;
            })
            .then(function (d) {
              // Ignore stale responses (user kept typing / picked a result).
              if (reqId !== self._suggestReq) return;
              self.suggestions = (d && d.suggestions) || [];
            })
            .catch(function () {});
        }, 350);
      },

      // Single cancellation path for every dismissal (outside click, Escape,
      // choosing a result, pasting a link): kills the pending debounce AND
      // invalidates any request already in flight so a late Nominatim
      // response can never repopulate a dropdown the user dismissed.
      dismissSuggestions: function () {
        clearTimeout(this._suggestTimer);
        this._suggestReq = (this._suggestReq || 0) + 1;
        this.suggestions = [];
      },

      chooseSuggestion: function (s) {
        this.dismissSuggestions();
        this._suppressMapSync = true;
        this.address = s.label;
        this.lat = round6(parseFloat(s.lat));
        this.lng = round6(parseFloat(s.lng));
        this._suppressMapSync = false;
        this._placeMarker(parseFloat(s.lat), parseFloat(s.lng));
      },

      _placeMarker: function (lat, lng) {
        if (this.mpMarker) {
          this.mpMarker.setLatLng([lat, lng]);
          this.mpMarker.setOpacity(1);
        }
        if (this.mpMap) this.mpMap.setView([lat, lng], 15, { animate: false });
      },

      /* ---- Pasted map links / addresses -------------------------------- */
      // Pull a place name and/or coordinates out of a pasted Google Maps /
      // Apple Maps / OSM style URL. Returns { name, lat, lng } (nulls when
      // absent) or null when nothing recognizable was found.
      // Only URLs on recognized map hosts are treated as map links; anything
      // else (an arbitrary site that happens to carry ?q=/?address=) must
      // fall through to a normal paste.
      _isMapHost: function (text) {
        var m = text.match(/^https?:\/\/([^\/?#]+)/i);
        if (!m) return false;
        var host = m[1].toLowerCase().replace(/:\d+$/, "");
        return (
          /(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/.test(host) || // google.com, google.co.uk, maps.google.de …
          /(^|\.)goo\.gl$/.test(host) || // maps.app.goo.gl short links
          /(^|\.)apple\.com$/.test(host) || // maps.apple.com
          /(^|\.)openstreetmap\.org$/.test(host) ||
          /(^|\.)osm\.org$/.test(host)
        );
      },

      _decodeSeg: function (seg) {
        try {
          return decodeURIComponent(seg).replace(/\+/g, " ").trim();
        } catch (e) {
          return seg.replace(/\+/g, " ").trim();
        }
      },

      _validCoords: function (lat, lng) {
        return isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
      },

      extractFromMapUrl: function (text) {
        if (!this._isMapHost(text)) return null;
        var name = null,
          lat = null,
          lng = null,
          m;
        var COORD_SEG = /^-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/;
        // Normalize URL-encoding in the query string so encoded coordinate
        // separators (e.g. ?q=40.71%2C-74.00) parse like literal ones.
        try {
          text = text.replace(/%2C/gi, ",").replace(/%20/g, " ");
        } catch (e) {}
        // /maps/place/<Name>/...
        m = text.match(/\/maps\/place\/([^\/@?]+)/i);
        if (m) name = this._decodeSeg(m[1]);
        // /maps/search/<query>/... (Google canonical search URLs)
        if (!name) {
          m = text.match(/\/maps\/search\/([^\/@?#]+)/i);
          if (m && !COORD_SEG.test(this._decodeSeg(m[1]))) name = this._decodeSeg(m[1]);
        }
        // /maps/dir/<origin>/<destination>/... — the DESTINATION (last
        // waypoint segment before any /@viewport or /data suffix) is the
        // place the user cares about. A textual destination becomes the
        // name; a coordinate destination becomes the selected lat/lng.
        // The origin is never promoted to the name. Handles omitted
        // origins too (/maps/dir//<destination>).
        var destLat = null,
          destLng = null;
        if (!name) {
          // Capture stops at the /@viewport, /data=!… suffix, query or hash —
          // only true waypoint segments remain.
          m = text.match(/\/maps\/dir\/((?:(?!\/data=)[^@?#])*)/i);
          if (m) {
            var segs = m[1].split("/").filter(function (s) {
              return s !== "" && !/^data=/.test(s);
            });
            if (segs.length) {
              var dest = this._decodeSeg(segs[segs.length - 1]);
              if (COORD_SEG.test(dest)) {
                var dparts = dest.split(",");
                var dlat = parseFloat(dparts[0]),
                  dlng = parseFloat(dparts[1]);
                if (this._validCoords(dlat, dlng)) {
                  destLat = dlat;
                  destLng = dlng;
                }
              } else if (dest) {
                name = dest;
              }
            }
          }
        }
        // Coordinates, most precise first: pinned !3d/!4d, then an explicit
        // coordinate directions destination, then viewport/query fallbacks.
        m = text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/); // precise pin
        if (!m && destLat !== null) m = [null, String(destLat), String(destLng)];
        if (!m) m = text.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/); // viewport @lat,lng
        if (!m)
          m = text.match(
            /[?&](?:q|ll|sll|center|query|address|destination|daddr)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i,
          );
        // OSM: ?mlat=<lat>&mlon=<lng> or #map=<zoom>/<lat>/<lng>
        if (!m) {
          var mlat = text.match(/[?&]mlat=(-?\d+(?:\.\d+)?)/i);
          var mlon = text.match(/[?&]mlon=(-?\d+(?:\.\d+)?)/i);
          if (mlat && mlon) m = [null, mlat[1], mlon[1]];
        }
        if (!m) m = text.match(/#map=\d+\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)/);
        if (m) {
          lat = parseFloat(m[1]);
          lng = parseFloat(m[2]);
          if (!this._validCoords(lat, lng)) lat = lng = null;
        }
        // ?q= / ?query= (Google), ?address= (Apple Maps), ?destination= /
        // ?daddr= (directions) carrying a non-coordinate textual place —
        // may accompany coordinates (e.g. Apple Maps ?ll=…&q=Name).
        if (!name) {
          m = text.match(/[?&](?:q|query|address|destination|daddr)=([^&#]+)/i);
          if (m && !COORD_SEG.test(this._decodeSeg(m[1]))) name = this._decodeSeg(m[1]);
        }
        if (!name && lat === null) return null;
        return { name: name, lat: lat, lng: lng };
      },

      // Bind as @paste on the address input. Non-URL pastes fall through to
      // the browser's normal behavior; map URLs are intercepted and resolved
      // into a readable place name/address.
      handleLocationPaste: function (evt) {
        var text = "";
        try {
          text = (evt.clipboardData && evt.clipboardData.getData("text")) || "";
        } catch (e) {}
        text = text.trim();
        if (!/^https?:\/\//i.test(text)) return; // plain text → default paste
        if (!this._isMapHost(text)) return; // unrelated URL → default paste, untouched
        var info = this.extractFromMapUrl(text);
        if (!info) {
          // Recognized map host but unreadable link (e.g. maps.app.goo.gl
          // short link — can't be expanded client-side). Let the browser
          // paste it untouched so nothing is lost, and tell the user what
          // would work.
          if (window.showToast)
            window.showToast(
              "Couldn't read a place from that link — try pasting the full map URL or the address itself.",
            );
          return;
        }
        evt.preventDefault();
        // Also invalidates any in-flight suggestion request so it can't
        // clobber the pasted result.
        this.dismissSuggestions();
        var self = this;
        if (info && info.lat !== null) {
          this._suppressMapSync = true;
          this.lat = round6(info.lat);
          this.lng = round6(info.lng);
          this._suppressMapSync = false;
          this._placeMarker(info.lat, info.lng);
          if (info.name) {
            // Keep the human place name, append the resolved street address.
            this.address = info.name;
            fetch(
              "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" +
                info.lat +
                "&lon=" +
                info.lng,
              { headers: { Accept: "application/json" } },
            )
              .then(function (r) {
                return r.ok ? r.json() : null;
              })
              .then(function (d) {
                if (d && d.display_name && self.address === info.name) {
                  var dn = d.display_name;
                  self.address =
                    dn.toLowerCase().indexOf(info.name.toLowerCase()) === 0
                      ? dn
                      : info.name + ", " + dn;
                }
              })
              .catch(function () {});
          } else {
            this.reverseGeocode(info.lat, info.lng);
          }
        } else if (info && info.name) {
          this.address = info.name;
          this.searchQuery = info.name;
          this.searchAddress();
        }
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
