/**
 * Elevation Profile Chart
 *
 * Renders an elevation profile chart using Chart.js.
 * For multi-day bikepacking trips, each day's track is a separate dataset
 * in its own colour. Hovering a track line on the map dispatches a
 * 'trackHighlight' event that thickens the matching elevation segment.
 */

(function() {
    'use strict';

    // Same palette as adventure-map.js and the PHP plugin
    var TRACK_COLORS = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#e11d48'];

    document.addEventListener('DOMContentLoaded', function() {
        initializeElevationChart();
    });

    function hexToRgba(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function initializeElevationChart() {
        var canvas = document.getElementById('elevation-chart');

        if (!canvas) {
            return;
        }

        var profileData = canvas.getAttribute('data-elevation-profile');

        if (!profileData) {
            console.warn('No elevation profile data found');
            return;
        }

        var elevationProfile;
        try {
            elevationProfile = JSON.parse(profileData);
        } catch (error) {
            console.error('Error parsing elevation profile data:', error);
            return;
        }

        // Group elevation points by trackIndex
        var byTrack = {};
        elevationProfile.forEach(function(point) {
            var ti = point.trackIndex !== undefined ? point.trackIndex : 0;
            if (!byTrack[ti]) byTrack[ti] = [];
            byTrack[ti].push(point);
        });

        var trackIndices = Object.keys(byTrack).map(Number).sort(function(a, b) { return a - b; });
        var isMultiTrack = trackIndices.length > 1;
        // Exact total distance — last point has the highest cumulative distance
        var maxDistance = elevationProfile[elevationProfile.length - 1].distance;
        var ctx = canvas.getContext('2d');

        // Build one Chart.js dataset per track
        var datasets = trackIndices.map(function(ti) {
            var points = byTrack[ti];
            var color  = TRACK_COLORS[ti % TRACK_COLORS.length];
            var grad   = ctx.createLinearGradient(0, 0, 0, 300);
            grad.addColorStop(0, hexToRgba(color, 0.25));
            grad.addColorStop(1, hexToRgba(color, 0.03));

            return {
                label: isMultiTrack ? 'Day ' + (ti + 1) : 'Elevation (m)',
                data: points.map(function(p) { return { x: p.distance, y: p.elevation }; }),
                borderColor: color,
                backgroundColor: grad,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            };
        });

        var chart = new Chart(ctx, {
            type: 'line',
            data: { datasets: datasets },
            options: {
                parsing: false,
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        displayColors: isMultiTrack,
                        callbacks: {
                            title: function(context) {
                                return 'Distance: ' + context[0].parsed.x + ' km';
                            },
                            label: function(context) {
                                var prefix = isMultiTrack ? 'Day ' + (context.datasetIndex + 1) + ': ' : '';
                                return prefix + 'Elevation: ' + context.parsed.y + ' m';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        min: 0,
                        max: maxDistance,
                        title: {
                            display: true,
                            text: 'Distance (km)',
                            font: { size: 14, weight: 'bold' }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Elevation (m)',
                            font: { size: 14, weight: 'bold' }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });

        // Cross-highlight: when a map track is hovered, thicken its elevation segment
        document.addEventListener('trackHighlight', function(e) {
            var active = e.detail.trackIndex;
            chart.data.datasets.forEach(function(ds, i) {
                if (active === null) {
                    ds.borderWidth = 2;
                } else {
                    ds.borderWidth = (i === active) ? 4 : 1;
                }
            });
            chart.update('none'); // skip animation for immediate response
        });
    }

})();
