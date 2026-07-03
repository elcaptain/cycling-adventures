/**
 * Adventure Map JavaScript
 *
 * Initializes Leaflet map with GPX tracks and photo markers.
 * Supports multiple tracks (one per day for bikepacking trips).
 *
 * Events dispatched on document:
 *   trackHighlight  { trackIndex: number|null }         — track line hover (consumed by elevation-profile.js)
 *   elevationHover  { trackIndex: number|null,
 *                     pointIndex: number|null }         — mousemove on track (consumed by elevation-profile.js)
 *
 * Events listened to:
 *   mapHover        { trackIndex: number|null,
 *                     pointIndex: number|null }         — chart hover (fired by elevation-profile.js)
 */

(function() {
    'use strict';

    // Module-level state shared between drawTracks and the mapHover listener
    var _map    = null;
    var _tracks = null;
    var _hoverMarker = null;
    var _suppressElevationHover = false;
    var _hideEndpoints = false;
    var _trackLines = {}; // trackIndex → Leaflet polyline (used by highlightTrackRange)
    var _photoHoverMarker = null;
    var _trackNameMarker  = null;

    document.addEventListener('DOMContentLoaded', function() {
        initializeAdventureMap();
    });

    function initializeAdventureMap() {
        var mapContainer = document.getElementById('adventure-map');

        if (!mapContainer) {
            return;
        }

        var tracksData = mapContainer.getAttribute('data-tracks');
        var photosData = mapContainer.getAttribute('data-photos');
        _hideEndpoints = mapContainer.dataset.hideEndpoints === 'true';

        if (!tracksData) {
            console.warn('No tracks data found');
            return;
        }

        var tracks = [];
        var photos = [];

        try {
            tracks = JSON.parse(tracksData);
            if (photosData) {
                photos = JSON.parse(photosData);
            }
        } catch (error) {
            console.error('Error parsing map data:', error);
            return;
        }

        if (tracks.length === 0) {
            return;
        }

        var map = L.map('adventure-map', { zoomControl: false });

        L.tileLayer('https://api.maptiler.com/maps/landscape-v4/{z}/{x}/{y}.png?key=sNKRl7DPdDNCUS9KExvS', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18,
        }).addTo(map);

        // Place zoom + recenter on the right side. Recenter is added after zoom
        // so Leaflet stacks it directly below in the same top-right corner.
        L.control.zoom({ position: 'topright' }).addTo(map);
        addRecenterControl(map, tracks);

        drawTracks(map, tracks);

        if (photos.length > 0) {
            addPhotoMarkers(map, photos);
            bindContentImageHover();
        }

        fitMapToTracks(map, tracks);

        // Store references for use in the mapHover listener below
        _map    = map;
        _tracks = tracks;

        // Hover circle marker — created once, added/removed on demand
        _hoverMarker = L.circleMarker([0, 0], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: '#ffffff',
            fillOpacity: 0.9,
            interactive: false,   // never intercepts mouse events from the polyline below
            pane: 'markerPane'    // above the overlay pane where polylines live
        });

        // Listen for elevation profile hover → place circle on corresponding map position
        document.addEventListener('mapHover', function(e) {
            _suppressElevationHover = true;
            var d = e.detail;

            if (!d || d.trackIndex === null) {
                if (_hoverMarker && _map.hasLayer(_hoverMarker)) {
                    _map.removeLayer(_hoverMarker);
                }
                _suppressElevationHover = false;
                return;
            }

            var track = null;
            for (var i = 0; i < _tracks.length; i++) {
                if (_tracks[i].index === d.trackIndex) { track = _tracks[i]; break; }
            }
            if (!track) { _suppressElevationHover = false; return; }

            var tp = track.trackpoints[d.pointIndex];
            if (!tp) { _suppressElevationHover = false; return; }

            _hoverMarker.setLatLng([tp.lat, tp.lon]);
            _hoverMarker.setStyle({ fillColor: track.color });
            if (!_map.hasLayer(_hoverMarker)) _hoverMarker.addTo(_map);

            _suppressElevationHover = false;
        });
    }

    /**
     * Find the index of the trackpoint in trackpoints[] closest to latlng,
     * using projected pixel coordinates for fast comparison.
     */
    function findNearestPointOnTrack(trackpoints, latlng) {
        var minDist = Infinity;
        var bestIdx = -1;
        var zoom   = _map.getZoom();
        var cursor = _map.project(latlng, zoom);
        trackpoints.forEach(function(tp, i) {
            var pt = _map.project(L.latLng(tp.lat, tp.lon), zoom);
            var d  = (cursor.x - pt.x) * (cursor.x - pt.x) + (cursor.y - pt.y) * (cursor.y - pt.y);
            if (d < minDist) { minDist = d; bestIdx = i; }
        });
        return bestIdx;
    }

    /**
     * Draw all GPX tracks on the map.
     * Each track gets a white outline + coloured inner polyline.
     * Hovering thickens the line (trackHighlight) and tracks the cursor position (elevationHover).
     */
    function drawTracks(map, tracks) {
        tracks.forEach(function(track) {
            var latLngs = track.trackpoints.map(function(p) {
                return [p.lat, p.lon];
            });

            // White outline for legibility against any tile background
            var outlineLine = L.polyline(latLngs, {
                color: '#ffffff',
                weight: 8,
                opacity: 1,
                smoothFactor: 1
            }).addTo(map);

            // Coloured inner line
            var line = L.polyline(latLngs, {
                color: track.color,
                weight: 4,
                opacity: 1,
                smoothFactor: 1
            }).addTo(map);
            _trackLines[track.index] = { outline: outlineLine, inner: line };

            line.on('mouseover', function() {
                this.setStyle({ weight: 7 });
                outlineLine.bringToFront();
                this.bringToFront();
                Object.keys(_trackLines).forEach(function(idxStr) {
                    var idx   = parseInt(idxStr, 10);
                    var entry = _trackLines[idx];
                    if (!entry) return;
                    var isActive = idx === track.index;
                    entry.inner.setStyle({ opacity: isActive ? 1 : 0.25 });
                    entry.outline.setStyle({ opacity: isActive ? 1 : 0.1 });
                });
                if (track.routeName && latLngs.length > 0) {
                    var labelIcon = L.divIcon({
                        className: '',
                        html: '<div style="background:rgba(0,0,0,0.72);color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;width:200px;word-wrap:break-word;pointer-events:none">' + track.routeName + '</div>',
                        iconSize: null,
                        iconAnchor: [0, 0]
                    });
                    if (!_trackNameMarker) {
                        _trackNameMarker = L.marker(latLngs[0], { icon: labelIcon, interactive: false }).addTo(_map);
                    } else {
                        _trackNameMarker.setLatLng(latLngs[0]);
                        _trackNameMarker.setIcon(labelIcon);
                        if (!_map.hasLayer(_trackNameMarker)) _trackNameMarker.addTo(_map);
                    }
                }
                document.dispatchEvent(new CustomEvent('trackHighlight', {
                    detail: { trackIndex: track.index }
                }));
            });

            line.on('mouseout', function() {
                this.setStyle({ weight: 4 });
                Object.keys(_trackLines).forEach(function(idxStr) {
                    var entry = _trackLines[parseInt(idxStr, 10)];
                    if (entry) {
                        entry.inner.setStyle({ opacity: 1 });
                        entry.outline.setStyle({ opacity: 1 });
                    }
                });
                if (_trackNameMarker && _map && _map.hasLayer(_trackNameMarker)) {
                    _map.removeLayer(_trackNameMarker);
                }
                if (_map && _hoverMarker && _map.hasLayer(_hoverMarker)) {
                    _map.removeLayer(_hoverMarker);
                }
                document.dispatchEvent(new CustomEvent('trackHighlight', {
                    detail: { trackIndex: null }
                }));
                document.dispatchEvent(new CustomEvent('elevationHover', {
                    detail: { trackIndex: null, pointIndex: null }
                }));
            });

            line.on('mousemove', function(e) {
                if (_suppressElevationHover) return;
                var idx = findNearestPointOnTrack(track.trackpoints, e.latlng);
                if (idx < 0) return;
                var tp = track.trackpoints[idx];
                _hoverMarker.setLatLng([tp.lat, tp.lon]);
                _hoverMarker.setStyle({ fillColor: track.color });
                if (!_map.hasLayer(_hoverMarker)) _hoverMarker.addTo(_map);
                document.dispatchEvent(new CustomEvent('elevationHover', {
                    detail: { trackIndex: track.index, pointIndex: idx }
                }));
            });
        });

        // Start/finish markers — skipped when data-hide-endpoints is set (e.g. collections)
        if (!_hideEndpoints) {
            var firstTrack = tracks[0];
            var lastTrack  = tracks[tracks.length - 1];

            if (firstTrack.trackpoints.length > 0) {
                var sp = firstTrack.trackpoints[0];
                L.marker([sp.lat, sp.lon], { icon: createSvgIcon('start') })
                 .addTo(map)
                 .bindPopup('<strong>Start</strong>');
            }

            if (lastTrack.trackpoints.length > 0) {
                var ep = lastTrack.trackpoints[lastTrack.trackpoints.length - 1];
                L.marker([ep.lat, ep.lon], { icon: createSvgIcon('end') })
                 .addTo(map)
                 .bindPopup('<strong>Finish</strong>');
            }
        }
    }

    /**
     * Add photo markers to the map
     */
    function addPhotoMarkers(map, photos) {
        photos.forEach(function(photo, index) {
            if (!photo.lat || !photo.lon) {
                return;
            }

            var marker = L.marker([photo.lat, photo.lon], {
                icon: createSvgIcon('photo')
            }).addTo(map);

            var popupContent = '<div class="photo-marker-popup"><img src="' + photo.thumb + '" alt="Photo ' + (index + 1) + '" style="max-width: 200px; display: block; border-radius: 8px;" /></div>';

            marker.bindPopup(popupContent, {
                maxWidth: 220,
                minWidth: 200,
                className: 'photo-popup'
            });

            marker.on('popupopen', function(e) {
                setMarkerActive(marker, true);
                var img = e.popup.getElement().querySelector('img');
                if (img) {
                    if (!img.complete) img.addEventListener('load', function() { e.popup.update(); });
                    else e.popup.update();
                }
            });
            marker.on('popupclose', function() {
                setMarkerActive(marker, false);
            });

            var photoKey = basename(photo.url);
            marker.photoKey = photoKey;
            photoMarkerIndex.set(photoKey, marker);
        });
    }

    // Map of filename -> Leaflet marker for content-image hover sync
    var photoMarkerIndex = new Map();

    function basename(urlOrName) {
        if (!urlOrName) return '';
        try {
            var u = new URL(urlOrName, window.location.origin);
            return u.pathname.split('/').pop();
        } catch (e) {
            return urlOrName.split('/').pop();
        }
    }

    function setMarkerActive(marker, active) {
        if (!marker) return;
        marker.setZIndexOffset(active ? 1000 : 0);
        var wrap = marker.getElement();
        if (!wrap) return;
        var pill = wrap.querySelector('.adventure-marker');
        if (pill) pill.classList.toggle('is-active', active);
    }

    function bindContentImageHover() {
        var imgs = document.querySelectorAll('.content-block img[data-photo-filename], .photo-gallery-image[data-photo-filename]');
        imgs.forEach(function(img) {
            var imgKey = basename(img.dataset.photoFilename || img.getAttribute('src'));

            img.addEventListener('mouseenter', function() {
                setMarkerActive(photoMarkerIndex.get(imgKey), true);
            });
            img.addEventListener('mouseleave', function() {
                setMarkerActive(photoMarkerIndex.get(imgKey), false);
            });
            img.addEventListener('focus', function() {
                setMarkerActive(photoMarkerIndex.get(imgKey), true);
            });
            img.addEventListener('blur', function() {
                setMarkerActive(photoMarkerIndex.get(imgKey), false);
            });
        });
    }

    // Map of marker variant → SVG asset path (matches Figma design's marker icons)
    var MARKER_ICONS = {
        photo: '/assets/icons/marker-photo.svg',
        start: '/assets/icons/marker-start.svg',
        end:   '/assets/icons/marker-end.svg',
        sleep: '/assets/icons/marker-sleep.svg'
    };

    function createSvgIcon(variant) {
        var iconUrl = MARKER_ICONS[variant] || MARKER_ICONS.photo;
        // Icon is rendered as a div with a CSS mask so its color (background-color)
        // and the pill bg can be swapped on hover via the `.is-active` class.
        var iconStyle = '-webkit-mask-image:url(' + iconUrl + ');mask-image:url(' + iconUrl + ')';
        return L.divIcon({
            html: '<div class="adventure-marker adventure-marker--' + variant + '">' +
                  '<div class="adventure-marker-icon" style="' + iconStyle + '"></div>' +
                  '</div>',
            className: 'adventure-marker-wrap',
            iconSize: [20, 20],
            iconAnchor: [10, 10],
            popupAnchor: [0, -10]
        });
    }

    function fitMapToTracks(map, tracks) {
        var allPoints = [];
        tracks.forEach(function(track) {
            track.trackpoints.forEach(function(p) {
                allPoints.push([p.lat, p.lon]);
            });
        });

        if (allPoints.length === 0) return;

        map.fitBounds(L.latLngBounds(allPoints), { padding: [20, 20] });
    }

    /**
     * Adds a "recenter" control below the zoom buttons that re-fits the map
     * to all tracks (the original view).
     */
    function addRecenterControl(map, tracks) {
        var Recenter = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function() {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-recenter');
                var button = L.DomUtil.create('a', '', container);
                button.href = '#';
                button.title = 'Reset view';
                button.setAttribute('role', 'button');
                button.setAttribute('aria-label', 'Reset view');
                button.innerHTML =
                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" ' +
                    'stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
                    'stroke-linejoin="round" aria-hidden="true">' +
                    '<path d="M9 3H5a2 2 0 0 0-2 2v4"/>' +
                    '<path d="M15 3h4a2 2 0 0 1 2 2v4"/>' +
                    '<path d="M9 21H5a2 2 0 0 1-2-2v-4"/>' +
                    '<path d="M15 21h4a2 2 0 0 0 2-2v-4"/>' +
                    '</svg>';

                L.DomEvent.on(button, 'click', function(e) {
                    L.DomEvent.preventDefault(e);
                    L.DomEvent.stopPropagation(e);
                    fitMapToTracks(map, tracks);
                });
                L.DomEvent.disableClickPropagation(container);

                return container;
            }
        });
        new Recenter().addTo(map);
    }

    // Public API — used by collection-cards.js to dim/restore tracks and show photo markers
    window.adventureMap = {
        showPhotoMarker: function(lat, lon) {
            if (!_map) return;
            if (!_photoHoverMarker) {
                _photoHoverMarker = L.marker([lat, lon], { icon: createSvgIcon('photo') });
            } else {
                _photoHoverMarker.setLatLng([lat, lon]);
            }
            if (!_map.hasLayer(_photoHoverMarker)) _photoHoverMarker.addTo(_map);
        },
        hidePhotoMarker: function() {
            if (_photoHoverMarker && _map && _map.hasLayer(_photoHoverMarker)) {
                _map.removeLayer(_photoHoverMarker);
            }
        },
        highlightTrackRange: function(globalOffset, trackCount, active) {
            var endIndex = globalOffset + trackCount;
            Object.keys(_trackLines).forEach(function(idxStr) {
                var idx   = parseInt(idxStr, 10);
                var entry = _trackLines[idx];
                if (!entry) return;
                var inCard = idx >= globalOffset && idx < endIndex;
                if (active) {
                    entry.inner.setStyle({ opacity: inCard ? 1 : 0.25 });
                    entry.outline.setStyle({ opacity: inCard ? 1 : 0.1 });
                    if (inCard) {
                        entry.outline.bringToFront();
                        entry.inner.bringToFront();
                    }
                } else {
                    entry.inner.setStyle({ opacity: 1 });
                    entry.outline.setStyle({ opacity: 1 });
                }
            });
        }
    };

})();
