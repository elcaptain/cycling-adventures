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
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
        }).addTo(map);

        // Draw GPX track on map
        if (trackpoints && trackpoints.length > 0) {
            drawTrack(map, trackpoints);
        }

        // Add photo markers to map
        if (photos && photos.length > 0) {
            addPhotoMarkers(map, photos);
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
        const trackLine = L.polyline(latLngs, {
            color: '#2563eb', // ride-primary color
            weight: 4,
            opacity: 0.8,
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
                icon: createCustomIcon('📷', 'bg-blue-500')
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
                className: 'photo-popup'
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
