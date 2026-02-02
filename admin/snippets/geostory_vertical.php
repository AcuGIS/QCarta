<?php
	require('../../admin/incl/index_prefix.php');
	require('../../admin/incl/qgis.php');
    require('../../admin/class/basemap.php');

	$access_key = '';
    if(empty($_GET['access_key'])){
		$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
  		$content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key=SECRET_KEY&ip='.$_SERVER['REMOTE_ADDR']);
  		$auth = json_decode($content);
  		$access_key = '?access_key='.$auth->access_key;
	}else{
	    $access_key = '?access_key='.$_GET['access_key'];
	}
	// Load basemap data for WMS sections
	$basemap_data = [];
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$basemap_obj = new basemap_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	$basemap_rows = isset($_SESSION[SESS_USR_KEY]) ? $basemap_obj->getRows() : $basemap_obj->getPublic();
	while($basemap_row = pg_fetch_assoc($basemap_rows)) {
	    $basemap_data[$basemap_row['id']] = [
	        'id' => $basemap_row['id'],
	        'name' => $basemap_row['name'],
	        'url' => $basemap_row['url'],
	        'type' => $basemap_row['type'],
	        'attribution' => $basemap_row['attribution'],
	        'min_zoom' => $basemap_row['min_zoom'],
	        'max_zoom' => $basemap_row['max_zoom']
	    ];
	}
	pg_free_result($basemap_rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        html, body, .story, .story-section, .map-section {
            width: 100vw;
            height: 100vh;
            min-width: 100vw;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        html, body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            overflow: auto;
        }
        .story {
            display: block;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
            z-index: 1;
            overflow-y: auto;
            scroll-snap-type: y mandatory;
        }
        .story-section {
            position: relative;
            margin: 0;
            background: #f8f8f8;
            box-shadow: none;
            padding: 0;
            box-sizing: border-box;
            display: block;
            overflow: visible;
            flex-shrink: 0;
            scroll-snap-align: start;
            scroll-snap-stop: always;
        }
        .story-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .map-section {
            position: relative;
            background: #e0e0e0;
            z-index: 1;
            flex: 0 0 auto;
        }
        .layer-content, .upload-content {
            position: absolute;
            top: 40px;
            left: 40px;
            width: 400px;
            max-width: 90vw;
            max-height: calc(100vh - 160px);
            background: rgba(255,255,255,0.5);
            color: #666;
            padding: 1.5em;
            overflow-y: auto;
            z-index: 10;
            font-size: 1.2em;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .content-section {
            width: 100%;
            height: 100vh;
            margin: 0;
            background: white;
            overflow-y: auto;
            padding: 40px;
            box-sizing: border-box;
            border-radius: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .content-section .content-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }
        .content-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .content-body {
            margin-top: 15px;
        }
        .content-body img {
            max-width: 100%;
            height: auto;
        }
        .content-body iframe {
            max-width: 100%;
            border: none;
        }
        .feature-info-panel {
            position: fixed;
            top: 40px;
            right: 24px;
            width: 500px;
            background: rgba(255,255,255,0.97);
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            border-radius: 10px;
            padding: 32px;
            z-index: 10000;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
            display: none;
        }
        .feature-info-panel .close-btn {
            position: absolute;
            top: 16px;
            right: 24px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="story">
        STORY_CONTENT
    </div>
    <div class="feature-info-panel" id="featureInfoPanel" style="display:none;">
        <button class="close-btn" id="closeFeatureInfo" style="position:absolute;top:16px;right:24px;background:none;border:none;font-size:1.5rem;color:#888;cursor:pointer;">&times;</button>
        <div id="featureInfoContent"></div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/shapefile@0.6.6/dist/shapefile.js"></script>
    <script>
    const basemaps = <?=json_encode($basemap_data)?>;
    
    function mercatorToLatLng(x, y) {
        var R = 6378137.0;
        var lon = x / R * 180 / Math.PI;
        var lat = (2 * Math.atan(Math.exp(y / R)) - Math.PI / 2) * 180 / Math.PI;
        return [lat, lon];
    }

    document.addEventListener('DOMContentLoaded', function() {
        const maps = {};
        const layers = LAYERS_JSON;
        const featureInfoPanel = document.getElementById('featureInfoPanel');
        const featureInfoContent = document.getElementById('featureInfoContent');
        const closeFeatureInfo = document.getElementById('closeFeatureInfo');
        
        console.log('DEBUG: Initializing maps with layers:', layers);
        console.log('DEBUG: Available basemaps:', basemaps);
        
        // Function to get basemap for a section
        function getBasemapForSection(section) {
            if (section.basemap_id && basemaps[section.basemap_id]) {
                return basemaps[section.basemap_id];
            }
            // Return default basemap if none specified
            return basemaps[0];
        }
        
        if (closeFeatureInfo) {
            closeFeatureInfo.onclick = function() {
                featureInfoPanel.style.display = 'none';
            };
        }

        // For each section, initialize a map in its own container
        layers.forEach((section, index) => {
            console.log('DEBUG: Processing section', index, section);
            if (section.type === 'wms' || section.type === 'upload') {
                const mapId = `map-section-${index}`;
                const mapElement = document.getElementById(mapId);
                console.log('DEBUG: Found map element for', mapId, mapElement);
                
                if (mapElement) {
                    // Initialize map with proper settings
                    const map = L.map(mapId, {
                        zoomControl: false,
                        scrollWheelZoom: false,
                        dragging: true,
                        attributionControl: true
                    }).setView([0, 0], 2);

                    // Add basemap layer based on section configuration
                    const basemap = getBasemapForSection(section);
                    console.log('DEBUG: Using basemap for section:', basemap);
                    
                    if (basemap.type === 'xyz') {
                        L.tileLayer(basemap.url, {
                            attribution: basemap.attribution,
                            minZoom: basemap.min_zoom || 0,
                            maxZoom: basemap.max_zoom || 18
                        }).addTo(map);
                    }

                    // Enable zoom only when Control key is pressed
                    map.on('wheel', function(e) {
                        if (e.originalEvent.ctrlKey) {
                            e.originalEvent.preventDefault();
                            if (e.originalEvent.deltaY < 0) {
                                map.zoomIn();
                            } else {
                                map.zoomOut();
                            }
                        }
                    });

                    if (section.type === 'wms') {
                        try {
                            const wmsUrl = section.url.replace(/^@+/, '').trim();
                            console.log('DEBUG: Adding WMS layer', wmsUrl, section.name);
                            
                            const wmsLayer = L.tileLayer.wms(wmsUrl + '<?=$access_key?>', {
                                layers: section.name,
                                format: 'image/png',
                                transparent: true,
                                identify: true
                            }).addTo(map);

                            console.log('DEBUG: Layer bounds:', section.bounds, 'CRS:', section.bounds && section.bounds.crs);
                            if (
                                section.bounds &&
                                typeof section.bounds.south === 'number' &&
                                typeof section.bounds.west === 'number' &&
                                typeof section.bounds.north === 'number' &&
                                typeof section.bounds.east === 'number'
                            ) {
                                if (section.bounds.crs === 'EPSG:3857') {
                                    const sw = mercatorToLatLng(section.bounds.west, section.bounds.south);
                                    const ne = mercatorToLatLng(section.bounds.east, section.bounds.north);
                                    map.fitBounds([sw, ne]);
                                } else {
                                    const bounds = [
                                        [section.bounds.south, section.bounds.west],
                                        [section.bounds.north, section.bounds.east]
                                    ];
                                    map.fitBounds(bounds);
                                }
                                setTimeout(function() {
                                    map.panBy([-524, 0], {animate: false});
                                }, 300);
                            } else if (section.map_center && section.map_zoom) {
                                const parts = section.map_center.split(',');
                                if (parts.length === 2) {
                                    const lat = parseFloat(parts[0]);
                                    const lng = parseFloat(parts[1]);
                                    const zoom = parseInt(section.map_zoom, 10);
                                    map.setView([lat, lng], zoom);
                                } else {
                                    map.setView([37.8, -96], 4);
                                }
                            } else {
                                map.setView([37.8, -96], 4); // fallback to US center/zoom
                            }

                            // Initialize layer content if it exists
                            const storySection = mapElement.closest('.story-section');
                            if (storySection) {
                                const layerContent = storySection.querySelector('.layer-content');
                                if (layerContent && section.content) {
                                    layerContent.innerHTML = section.content;
                                    layerContent.style.display = 'block';
                                }
                            }

                            // Add click handler for feature info
                            map.on('click', function(e) {
                                const point = map.latLngToContainerPoint(e.latlng, map.getZoom());
                                const size = map.getSize();
                                const bounds = map.getBounds();
                                const bbox = `${bounds.getSouth()},${bounds.getWest()},${bounds.getNorth()},${bounds.getEast()}`;
                                
                                const params = {
                                    REQUEST: 'GetFeatureInfo',
                                    SERVICE: 'WMS',
                                    CRS: 'EPSG:4326',
                                    STYLES: '',
                                    TRANSPARENT: true,
                                    VERSION: '1.3.0',
                                    FORMAT: 'image/png',
                                    BBOX: bbox,
                                    HEIGHT: size.y,
                                    WIDTH: size.x,
                                    LAYERS: section.name,
                                    QUERY_LAYERS: section.name,
                                    INFO_FORMAT: 'text/html',
                                    I: Math.round(point.x),
                                    J: Math.round(point.y),
                                    FEATURE_COUNT: 10
                                };

                                let baseUrl = wmsUrl;
                                if (baseUrl.endsWith('?') || baseUrl.endsWith('&')) {
                                    baseUrl = baseUrl.slice(0, -1);
                                }
                                const url = baseUrl + (baseUrl.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();

                                fetch(url)
                                    .then(response => response.text())
                                    .then(html => {
                                        if (html && html.trim() !== '') {
                                            featureInfoContent.innerHTML = html;
                                            featureInfoPanel.style.display = 'block';
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching feature info:', error);
                                    });
                            });

                            maps[mapId] = map;
                            console.log('DEBUG: Map initialized for', mapId);
                        } catch (error) {
                            console.error('Error adding WMS layer:', error);
                        }
                    } else if (section.type === 'upload') {
                        try {
                            console.log('Processing upload section:', section);
                            console.log('Section type:', section.type);
                            console.log('Section data:', JSON.stringify(section, null, 2));
                            
                            if (!section.geojson) {
                                console.error('No GeoJSON data found in section:', section);
                                throw new Error('No GeoJSON data found in upload section');
                            }
                            
                            const geojson = JSON.parse(section.geojson);
                            console.log('Parsed GeoJSON:', geojson.type, 'with', geojson.features?.length, 'features');
                            
                            if (!geojson.type || !geojson.features) {
                                throw new Error('Invalid GeoJSON structure');
                            }

                            const geojsonLayer = L.geoJSON(geojson, {
                                style: function(feature) {
                                    const geometry = feature.geometry.type;
                                    const style = {
                                        fillColor: section.style?.fillColor || '#3388ff',
                                        fillOpacity: section.style?.fillOpacity || 0.4,
                                        color: section.style?.strokeColor || '#000000',
                                        weight: section.style?.strokeWidth || 1
                                    };

                                    // Add point styling
                                    if (geometry === 'Point' || geometry === 'MultiPoint') {
                                        style.radius = section.style?.pointRadius || 5;
                                    }

                                    return style;
                                },
                                pointToLayer: function(feature, latlng) {
                                    const geometry = feature.geometry.type;
                                    const style = {
                                        fillColor: section.style?.fillColor || '#3388ff',
                                        fillOpacity: section.style?.fillOpacity || 0.4,
                                        color: section.style?.strokeColor || '#000000',
                                        weight: section.style?.strokeWidth || 1,
                                        radius: section.style?.pointRadius || 5
                                    };
                                    
                                    return L.circleMarker(latlng, {
                                        radius: style.radius,
                                        fillColor: style.fillColor,
                                        color: style.color,
                                        weight: style.weight,
                                        fillOpacity: style.fillOpacity
                                    });
                                },
                                onEachFeature: function(feature, layer) {
                                    if (feature.properties) {
                                        layer.on('click', function(e) {
                                            // Format the properties as HTML
                                            const content = Object.entries(feature.properties)
                                                .map(([key, value]) => `<div><strong>${key}:</strong> ${value}</div>`)
                                                .join('');
                                            
                                            featureInfoContent.innerHTML = content;
                                            featureInfoPanel.style.display = 'block';
                                        });
                                    }
                                }
                            }).addTo(map);

                            // Initialize layer content if it exists
                            const storySection = mapElement.closest('.story-section');
                            if (storySection) {
                                const layerContent = storySection.querySelector('.upload-content');
                                if (layerContent && section.content) {
                                    layerContent.innerHTML = section.content;
                                    layerContent.style.display = 'block';
                                }
                            }

                            // Fit map to the bounds of the GeoJSON
                            map.fitBounds(geojsonLayer.getBounds());
                            console.log('Upload layer added successfully');
                        } catch (error) {
                            console.error('Error processing upload:', error);
                            console.error('Section data:', section);
                        }
                    }
                } else {
                    console.error('Map element not found:', mapId);
                }
            }
        });

        // Initial resize to ensure maps are properly sized
        setTimeout(() => {
            Object.values(maps).forEach(map => {
                map.invalidateSize();
                map.setView(map.getCenter(), map.getZoom());
            });
        }, 500);

        // Handle window resize
        window.addEventListener('resize', () => {
            Object.values(maps).forEach(map => {
                map.invalidateSize();
                map.setView(map.getCenter(), map.getZoom());
            });
        });
    });
    </script>
</body>
</html>
