/**
 * Collection Cards
 *
 * Manages per-card elevation charts and card ↔ map cross-highlight for the
 * collection template. The shared Leaflet map is handled by adventure-map.js —
 * this file only wires up the card-specific interactions.
 *
 * Reuses the same event names as elevation-profile.js / adventure-map.js:
 *   Listens:  trackHighlight  { trackIndex }                (fired by adventure-map.js)
 *             elevationHover  { trackIndex, pointIndex }    (fired by adventure-map.js)
 *   Dispatches: mapHover      { trackIndex, pointIndex }    (consumed by adventure-map.js)
 */

(function() {
    'use strict';

    var TRACK_COLORS = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#e11d48'];

    // Registry of per-card chart instances
    // Each entry: { trackIndices: number[], chart: Chart }
    var _cardCharts = [];
    var _suppressMapHover = false;

    document.addEventListener('DOMContentLoaded', function() {
        initAllElevationCharts();
        bindCardHover();
        bindCardPhotoHover();
        listenForMapEvents();
    });

    // ── Elevation charts ──────────────────────────────────────────────────────

    function hexToRgba(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function initAllElevationCharts() {
        var canvases = document.querySelectorAll('.collection-elevation-canvas');
        canvases.forEach(function(canvas) {
            var profileData = canvas.getAttribute('data-elevation-profile');
            if (!profileData) return;

            var elevationProfile;
            try {
                elevationProfile = JSON.parse(profileData);
            } catch (e) {
                return;
            }
            if (!elevationProfile.length) return;

            // Group points by (global) trackIndex
            var byTrack = {};
            elevationProfile.forEach(function(point) {
                var ti = point.trackIndex !== undefined ? point.trackIndex : 0;
                if (!byTrack[ti]) byTrack[ti] = [];
                byTrack[ti].push(point);
            });

            var trackIndices = Object.keys(byTrack).map(Number).sort(function(a, b) { return a - b; });
            var isMultiTrack = trackIndices.length > 1;
            var maxDistance  = elevationProfile[elevationProfile.length - 1].distance;
            var ctx = canvas.getContext('2d');

            var datasets = trackIndices.map(function(ti, dsIdx) {
                var points = byTrack[ti];
                var color  = TRACK_COLORS[ti % TRACK_COLORS.length];
                var grad   = ctx.createLinearGradient(0, 0, 0, 300);
                grad.addColorStop(0, hexToRgba(color, 0.25));
                grad.addColorStop(1, hexToRgba(color, 0.03));

                return {
                    label: isMultiTrack ? 'Track ' + (dsIdx + 1) : 'Elevation (m)',
                    data: points.map(function(p) {
                        return { x: p.distance, y: p.elevation, pointIndex: p.pointIndex };
                    }),
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
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    transitions: {
                        active: { animation: { duration: 0 } }
                    },
                    onHover: function(event, activeElements, chart) {
                        if (_suppressMapHover) return;
                        if (!activeElements || activeElements.length === 0) {
                            document.dispatchEvent(new CustomEvent('mapHover', {
                                detail: { trackIndex: null, pointIndex: null }
                            }));
                            return;
                        }
                        var el = activeElements[0];
                        var ti = trackIndices[el.datasetIndex];
                        var pt = chart.data.datasets[el.datasetIndex].data[el.index];
                        document.dispatchEvent(new CustomEvent('mapHover', {
                            detail: { trackIndex: ti, pointIndex: pt.pointIndex }
                        }));
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    return 'Distance: ' + context[0].parsed.x + ' km';
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
                            min: 0,
                            max: maxDistance,
                            title: {
                                display: false
                            },
                            grid: { display: false },
                            ticks: { font: { size: 10 } }
                        },
                        y: {
                            title: { display: false },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });

            _cardCharts.push({ trackIndices: trackIndices, chart: chart });
        });
    }

    // ── Map event listeners ───────────────────────────────────────────────────

    function listenForMapEvents() {
        // Polyline hover: bold the matching card's dataset, dim others
        document.addEventListener('trackHighlight', function(e) {
            var active = e.detail.trackIndex;
            _cardCharts.forEach(function(entry) {
                var ownsActive = active !== null && entry.trackIndices.indexOf(active) >= 0;
                entry.chart.data.datasets.forEach(function(ds, i) {
                    if (active === null) {
                        ds.borderWidth = 2;
                    } else if (ownsActive) {
                        ds.borderWidth = (entry.trackIndices[i] === active) ? 4 : 2;
                    } else {
                        ds.borderWidth = 1;
                    }
                });
                entry.chart.update('none');
            });
        });

        // Map mousemove: activate tooltip on the chart that owns the hovered track
        document.addEventListener('elevationHover', function(e) {
            _suppressMapHover = true;
            var d = e.detail;

            _cardCharts.forEach(function(entry) {
                if (!d || d.trackIndex === null) {
                    entry.chart.setActiveElements([]);
                    entry.chart.tooltip.setActiveElements([], { x: 0, y: 0 });
                    entry.chart.update('none');
                    return;
                }

                var dsIdx = entry.trackIndices.indexOf(d.trackIndex);
                if (dsIdx < 0) return;

                var data  = entry.chart.data.datasets[dsIdx].data;
                var ptIdx = -1;
                for (var i = 0; i < data.length; i++) {
                    if (data[i].pointIndex === d.pointIndex) { ptIdx = i; break; }
                }
                // Nearest fallback for trackpoints without elevation
                if (ptIdx < 0) {
                    var bestDiff = Infinity;
                    for (var j = 0; j < data.length; j++) {
                        var diff = Math.abs(data[j].pointIndex - d.pointIndex);
                        if (diff < bestDiff) { bestDiff = diff; ptIdx = j; }
                    }
                }
                if (ptIdx < 0) return;

                var meta = entry.chart.getDatasetMeta(dsIdx);
                var pos  = { x: meta.data[ptIdx].x, y: meta.data[ptIdx].y };
                entry.chart.setActiveElements([{ datasetIndex: dsIdx, index: ptIdx }]);
                entry.chart.tooltip.setActiveElements([{ datasetIndex: dsIdx, index: ptIdx }], pos);
                entry.chart.update('none');
            });

            _suppressMapHover = false;
        });
    }

    // ── Card hover ↔ map track highlight ─────────────────────────────────────

    function bindCardHover() {
        var cards = document.querySelectorAll('.collection-route-card');
        cards.forEach(function(card) {
            var offset = parseInt(card.dataset.routeGlobalOffset, 10);
            var count  = parseInt(card.dataset.routeTrackCount,  10);

            card.addEventListener('mouseenter', function() {
                if (window.adventureMap) window.adventureMap.highlightTrackRange(offset, count, true);
            });
            card.addEventListener('mouseleave', function() {
                if (window.adventureMap) window.adventureMap.highlightTrackRange(offset, count, false);
            });
        });
    }

    // ── Card photo strip hover ↔ map marker ──────────────────────────────────

    function bindCardPhotoHover() {
        var imgs = document.querySelectorAll('.collection-route-card img[data-photo-lat]');
        imgs.forEach(function(img) {
            var lat = parseFloat(img.dataset.photoLat);
            var lon = parseFloat(img.dataset.photoLon);
            img.addEventListener('mouseenter', function() {
                if (window.adventureMap) window.adventureMap.showPhotoMarker(lat, lon);
            });
            img.addEventListener('mouseleave', function() {
                if (window.adventureMap) window.adventureMap.hidePhotoMarker();
            });
        });
    }

})();
