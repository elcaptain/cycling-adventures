/**
 * Adventure Map JavaScript
 *
 * Initializes Leaflet map with GPX tracks and photo markers.
 * Supports multiple tracks (one per day for bikepacking trips).
 * Hovering a track dispatches a 'trackHighlight' event for cross-highlighting
 * with the elevation profile chart.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initializeAdventureMap();
    });

    function initializeAdventureMap() {
        const mapContainer = document.getElementById('adventure-map');

        if (!mapContainer) {
            return;
        }

        const tracksData = mapContainer.getAttribute('data-tracks');
        const photosData = mapContainer.getAttribute('data-photos');

        if (!tracksData) {
            console.warn('No tracks data found');
            return;
        }

        let tracks = [];
        let photos = [];

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

        const map = L.map('adventure-map');

        L.tileLayer('https://api.maptiler.com/maps/landscape-v4/{z}/{x}/{y}.png?key=sNKRl7DPdDNCUS9KExvS', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18,
        }).addTo(map);

        drawTracks(map, tracks);

        if (photos.length > 0) {
            addPhotoMarkers(map, photos);
            bindContentImageHover();
        }

        fitMapToTracks(map, tracks);
    }

    /**
     * Draw all GPX tracks on the map.
     * Each track gets a white outline + coloured inner polyline.
     * Hovering thickens the line and dispatches a trackHighlight event.
     */
    function drawTracks(map, tracks) {
        tracks.forEach(function(track) {
            const latLngs = track.trackpoints.map(function(p) {
                return [p.lat, p.lon];
            });

            // White outline for legibility against any tile background
            L.polyline(latLngs, {
                color: '#ffffff',
                weight: 8,
                opacity: 1,
                smoothFactor: 1
            }).addTo(map);

            // Coloured inner line
            const line = L.polyline(latLngs, {
                color: track.color,
                weight: 4,
                opacity: 1,
                smoothFactor: 1
            }).addTo(map);

            line.on('mouseover', function() {
                this.setStyle({ weight: 7 });
                document.dispatchEvent(new CustomEvent('trackHighlight', {
                    detail: { trackIndex: track.index }
                }));
            });

            line.on('mouseout', function() {
                this.setStyle({ weight: 4 });
                document.dispatchEvent(new CustomEvent('trackHighlight', {
                    detail: { trackIndex: null }
                }));
            });
        });

        // Start marker on first point of first track, finish on last point of last track
        const firstTrack = tracks[0];
        const lastTrack  = tracks[tracks.length - 1];

        if (firstTrack.trackpoints.length > 0) {
            const sp = firstTrack.trackpoints[0];
            L.marker([sp.lat, sp.lon], { icon: createCustomIcon('🚴', 'bg-green-500') })
             .addTo(map)
             .bindPopup('<strong>Start</strong>');
        }

        if (lastTrack.trackpoints.length > 0) {
            const ep = lastTrack.trackpoints[lastTrack.trackpoints.length - 1];
            L.marker([ep.lat, ep.lon], { icon: createCustomIcon('🏁', 'bg-red-500') })
             .addTo(map)
             .bindPopup('<strong>Finish</strong>');
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

            const marker = L.marker([photo.lat, photo.lon], {
                icon: createCustomIcon('📷', 'white')
            }).addTo(map);

            const popupContent = `
                <div class="photo-marker-popup">
                    <img
                        src="${photo.thumb}"
                        alt="Photo ${index + 1}"
                        style="max-width: 200px; display: block; border-radius: 8px;"
                    />
                </div>
            `;

            marker.bindPopup(popupContent, {
                maxWidth: 220,
                minWidth: 200,
                className: 'photo-popup'
            });

            marker.on('popupopen', function(e) {
                const img = e.popup.getElement().querySelector('img');
                if (img) {
                    if (!img.complete) img.addEventListener('load', () => e.popup.update());
                    else e.popup.update();
                }
            });

            const photoKey = basename(photo.url);
            marker.photoKey = photoKey;
            photoMarkerIndex.set(photoKey, marker);
        });
    }

    // Map of filename -> Leaflet marker for content-image hover sync
    const photoMarkerIndex = new Map();

    function basename(urlOrName) {
        if (!urlOrName) return '';
        try {
            const u = new URL(urlOrName, window.location.origin);
            return u.pathname.split('/').pop();
        } catch (e) {
            return urlOrName.split('/').pop();
        }
    }

    function bindContentImageHover() {
        const imgs = document.querySelectorAll('.content-block img[data-photo-filename], .photo-gallery-image[data-photo-filename]');
        imgs.forEach(function(img) {
            const imgKey = basename(img.dataset.photoFilename || img.getAttribute('src'));
            let openTimeout;

            img.addEventListener('mouseenter', function() {
                const marker = photoMarkerIndex.get(imgKey);
                if (marker) {
                    openTimeout = setTimeout(() => marker.openPopup(), 60);
                }
            });
            img.addEventListener('mouseleave', function() {
                clearTimeout(openTimeout);
                const marker = photoMarkerIndex.get(imgKey);
                if (marker) marker.closePopup();
            });
            img.addEventListener('focus', function() {
                const marker = photoMarkerIndex.get(imgKey);
                if (marker) marker.openPopup();
            });
            img.addEventListener('blur', function() {
                const marker = photoMarkerIndex.get(imgKey);
                if (marker) marker.closePopup();
            });
            img.addEventListener('click', function(e) {
                const marker = photoMarkerIndex.get(imgKey);
                if (marker) {
                    if (marker.isPopupOpen && marker.isPopupOpen()) marker.closePopup();
                    else marker.openPopup();
                    e.preventDefault();
                }
            });
        });
    }

    function createCustomIcon(emoji, bgColor) {
        return L.divIcon({
            html: `
                <div class="flex items-center justify-center w-8 h-8 ${bgColor} text-white rounded-full shadow-lg text-lg">
                    ${emoji}
                </div>
            `,
            className: 'custom-marker',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -16]
        });
    }

    function fitMapToTracks(map, tracks) {
        const allPoints = [];
        tracks.forEach(function(track) {
            track.trackpoints.forEach(function(p) {
                allPoints.push([p.lat, p.lon]);
            });
        });

        if (allPoints.length === 0) return;

        map.fitBounds(L.latLngBounds(allPoints), { padding: [50, 50] });
    }

})();
