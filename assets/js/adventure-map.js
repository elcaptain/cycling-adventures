/**
 * Adventure Map JavaScript
 *
 * Initializes Leaflet map with GPX track and photo markers
 * Runs on adventure detail pages
 */

(function() {
    'use strict';

    // Initialize map when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeAdventureMap();
    });

    /**
     * Initialize the adventure map
     */
    function initializeAdventureMap() {
        const mapContainer = document.getElementById('adventure-map');

        // Check if map container exists on this page
        if (!mapContainer) {
            return;
        }

        // Get data from data attributes
        const trackpointsData = mapContainer.getAttribute('data-trackpoints');
        const photosData = mapContainer.getAttribute('data-photos');

        if (!trackpointsData) {
            console.warn('No trackpoints data found');
            return;
        }

        // Parse JSON data
        let trackpoints;
        let photos = [];

        try {
            trackpoints = JSON.parse(trackpointsData);
            if (photosData) {
                photos = JSON.parse(photosData);
            }
        } catch (error) {
            console.error('Error parsing map data:', error);
            return;
        }

        // Initialize Leaflet map
        const map = L.map('adventure-map');

        // Add OpenStreetMap tile layer
        // Note: For production, consider using your own tile server or a paid service
        L.tileLayer('https://api.maptiler.com/maps/landscape-v4/{z}/{x}/{y}.png?key=sNKRl7DPdDNCUS9KExvS', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18,
        }).addTo(map);

        // Draw GPX track on map
        if (trackpoints && trackpoints.length > 0) {
            drawTrack(map, trackpoints);
        }

        // Add photo markers to map
        if (photos && photos.length > 0) {
            addPhotoMarkers(map, photos);
            bindContentImageHover();
        }

        // Fit map to show entire track
        fitMapToTrack(map, trackpoints);
    }

    /**
     * Draw the GPX track on the map
     *
     * @param {L.Map} map Leaflet map instance
     * @param {Array} trackpoints Array of track points with lat, lon, elevation
     */
    function drawTrack(map, trackpoints) {
        // Convert trackpoints to Leaflet LatLng array
        const latLngs = trackpoints.map(function(point) {
            return [point.lat, point.lon];
        });

        // Create polyline for the track
        const trackOutLine = L.polyline(latLngs, {
            color: '#ffffff', // ride-primary color
            weight: 8,
            opacity: 1,
            smoothFactor: 1
        }).addTo(map);
        const trackLine = L.polyline(latLngs, {
            color: '#2563eb', // ride-primary color
            weight: 4,
            opacity: 1,
            smoothFactor: 1
        }).addTo(map);

        // Add start marker
        if (trackpoints.length > 0) {
            const startPoint = trackpoints[0];
            L.marker([startPoint.lat, startPoint.lon], {
                icon: createCustomIcon('🚴', 'bg-green-500')
            }).addTo(map)
              .bindPopup('<strong>Start</strong>');
        }

        // Add end marker
        if (trackpoints.length > 1) {
            const endPoint = trackpoints[trackpoints.length - 1];
            L.marker([endPoint.lat, endPoint.lon], {
                icon: createCustomIcon('🏁', 'bg-red-500')
            }).addTo(map)
              .bindPopup('<strong>Finish</strong>');
        }
    }

    /**
     * Add photo markers to the map
     *
     * @param {L.Map} map Leaflet map instance
     * @param {Array} photos Array of photos with lat, lon, url, thumb
     */
    function addPhotoMarkers(map, photos) {
        photos.forEach(function(photo, index) {
            if (!photo.lat || !photo.lon) {
                return;
            }

            // Create marker for photo
            const marker = L.marker([photo.lat, photo.lon], {
                icon: createCustomIcon('📷', 'white')
            }).addTo(map);

            // Create popup with photo thumbnail
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
            // In addPhotoMarkers, when creating each marker, register it:
            // assume `photo` has `filename` or `thumb`/`url` values; pick a key:
            const photoKey = basename(photo.url);
            marker.photoKey = photoKey;
            photoMarkerIndex.set(photoKey, marker);
        });
    }

    // global map of key -> marker
const photoMarkerIndex = new Map();

// helper to normalise filename for matching (strip query params)
function basename(urlOrName) {
  if (!urlOrName) return '';
  // if it's a full url, take last path segment
  try {
    const u = new URL(urlOrName, window.location.origin);
    return u.pathname.split('/').pop();
  } catch (e) {
    return urlOrName.split('/').pop();
  }
}

// After markers are created, bind hover handlers to content images:
function bindContentImageHover() {
  const imgs = document.querySelectorAll('.content-block img[data-photo-filename], .photo-gallery-image[data-photo-filename]');
  imgs.forEach((img) => {
    const imgKey = basename(img.dataset.photoFilename || img.getAttribute('src'));
    let openTimeout;
    img.addEventListener('mouseenter', () => {
      const marker = photoMarkerIndex.get(imgKey);
      if (marker) {
        // optionally pan map to marker
        // map.panTo(marker.getLatLng());
        // small delay to avoid flicker
        openTimeout = setTimeout(() => marker.openPopup(), 60);
      }
    });
    img.addEventListener('mouseleave', () => {
      clearTimeout(openTimeout);
      const marker = photoMarkerIndex.get(imgKey);
      if (marker) marker.closePopup();
    });

    // also support focus (keyboard) and touch/click fallback
    img.addEventListener('focus', () => {
      const marker = photoMarkerIndex.get(imgKey);
      if (marker) marker.openPopup();
    });
    img.addEventListener('blur', () => {
      const marker = photoMarkerIndex.get(imgKey);
      if (marker) marker.closePopup();
    });
    img.addEventListener('click', (e) => {
      // on touch devices, toggle popup
      const marker = photoMarkerIndex.get(imgKey);
      if (marker) {
        if (marker.isPopupOpen && marker.isPopupOpen()) marker.closePopup(); else marker.openPopup();
        e.preventDefault();
      }
    });
  });
}

    /**
     * Create a custom icon with emoji
     *
     * @param {string} emoji Emoji character
     * @param {string} bgColor Background color class
     * @return {L.DivIcon} Leaflet DivIcon
     */
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

    /**
     * Fit map bounds to show entire track
     *
     * @param {L.Map} map Leaflet map instance
     * @param {Array} trackpoints Array of track points
     */
    function fitMapToTrack(map, trackpoints) {
        if (!trackpoints || trackpoints.length === 0) {
            return;
        }

        // Create bounds from all trackpoints
        const bounds = L.latLngBounds(
            trackpoints.map(function(point) {
                return [point.lat, point.lon];
            })
        );

        // Fit map to bounds with some padding
        map.fitBounds(bounds, {
            padding: [50, 50]
        });
    }

})();
