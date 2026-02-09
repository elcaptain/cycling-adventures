/**
 * Elevation Profile Chart
 *
 * Renders an elevation profile chart using Chart.js
 * Designed to be extensible for future interactive features
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initializeElevationChart();
    });

    function initializeElevationChart() {
        const canvas = document.getElementById('elevation-chart');

        if (!canvas) {
            return; // No elevation chart on this page
        }

        // Get elevation profile data from data attribute
        const profileData = canvas.getAttribute('data-elevation-profile');

        if (!profileData) {
            console.warn('No elevation profile data found');
            return;
        }

        let elevationProfile;
        try {
            elevationProfile = JSON.parse(profileData);
        } catch (error) {
            console.error('Error parsing elevation profile data:', error);
            return;
        }

        // Extract distances and elevations for Chart.js
        const distances = elevationProfile.map(point => point.distance);
        const elevations = elevationProfile.map(point => point.elevation);

        // Create gradient fill for area under the line
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.3)');   // ride-primary with opacity
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.05)');  // fade to transparent

        // Initialize Chart.js
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: distances,
                datasets: [{
                    label: 'Elevation (m)',
                    data: elevations,
                    borderColor: '#2563eb',      // ride-primary color
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,                // Smooth curve
                    pointRadius: 0,              // Hide individual points
                    pointHoverRadius: 5,         // Show on hover (for future interactivity)
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false   // Hide legend, it's obvious what the chart shows
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return 'Distance: ' + context[0].label + ' km';
                            },
                            label: function(context) {
                                return 'Elevation: ' + context.parsed.y + ' m';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: {
                            display: true,
                            text: 'Distance (km)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Elevation (m)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }

})();
