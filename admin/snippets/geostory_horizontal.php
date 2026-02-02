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
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
            z-index: 1;
            height: 100vh;
            width: 100vw;
        }
        .story-section {
            flex: 0 0 100vw;
            height: 100vh;
            scroll-snap-align: start;
            scroll-snap-stop: always;
            position: relative;
            width: 100vw;
            overflow: hidden;
        }
        .map-section {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #e0e0e0;
            z-index: 1;
        }
        .layer-content {
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
            background: white;
            overflow-y: auto;
            padding: 40px;
            box-sizing: border-box;
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
        .navigation {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 25px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ccc;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .nav-dot.active {
            background: #007bff;
            transform: scale(1.2);
        }
        .nav-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .nav-button:hover {
            background: #0056b3;
        }
        .nav-button:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        #featureInfoContent {
            margin-top: 20px;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
        }
        #featureInfoContent div {
            margin-bottom: 8px;
        }
        #featureInfoContent strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="story">
        STORY_CONTENT
    </div>
    <div class="navigation" id="navigation">
        <button class="nav-button" id="prevButton">Previous</button>
        <div id="navDots" style="display:none"></div>
        <button class="nav-button" id="nextButton">Next</button>
    </div>
    <div class="feature-info-panel" id="featureInfoPanel">
        <button class="close-btn" id="closeFeatureInfo">&times;</button>
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

    // Add showFeatureInfo for sidebar info panel
    function showFeatureInfo(properties) {
        const featureInfoPanel = document.getElementById('featureInfoPanel');
        const featureInfoContent = document.getElementById('featureInfoContent');
        let html = '';
        for (const key in properties) {
            html += `<div><strong>${key}:</strong> ${properties[key]}</div>`;
        }
        featureInfoContent.innerHTML = html;
        featureInfoPanel.style.display = 'block';
    }
    
    // Function to get basemap for a section (moved outside DOMContentLoaded for global access)
    function getBasemapForSection(section, basemaps) {
        if (section.basemap_id && basemaps[section.basemap_id]) {
            return basemaps[section.basemap_id];
        }
        // Return default basemap if none specified
        return {
            name: 'OpenStreetMap',
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            type: 'xyz',
            attribution: '&copy; OpenStreetMap contributors',
            min_zoom: 0,
            max_zoom: 18
        };
    }
    
    function initMap(sectionId, layerData) {
        const map = L.map(`map-section-${sectionId}`, {
            center: [0, 0],
            zoomControl: false,
            zoom: 2
        });

        // Add basemap layer based on section configuration
        const basemap = getBasemapForSection(layerData, basemaps);
        
        if (basemap.type === 'xyz') {
            L.tileLayer(basemap.url, {
                attribution: basemap.attribution,
                minZoom: basemap.min_zoom || 0,
                maxZoom: basemap.max_zoom || 18
            }).addTo(map);
        }

        if (layerData.type === 'wms') {
            const wmsLayer = L.tileLayer.wms(layerData.url + '<?=$access_key?>', {
                layers: layerData.name,
                format: 'image/png',
                transparent: true
            }).addTo(map);
        } else if ((layerData.type === 'upload' && layerData.geojson) || (layerData.type === 'pg')) {
            try {
                // console_log('Processing upload section:', layerData);
                // console_log('Section type:', layerData.type);
                // console_log('Section data:', JSON.stringify(layerData, null, 2));
                
                if(layerData.type === 'upload'){
                  geojs_url = `data_filep.php?f=section${sectionId}.geojson`;
                }else{
                  geojs_url = `../../layers/${layerData.pg_layer_id}/geojson.php`;
                }
                // console_log('Section URL:', geojs_url);
                
                fetch(geojs_url + '<?=$access_key?>')
                  .then(function(response) {  return response.json(); })
                  .then(function(geojson_data) {
                    const geojsonLayer = L.geoJSON(geojson_data, {
                        style: function(feature) {
                            const geometry = feature.geometry.type;
                            const style = {
                                fillColor: layerData.style?.fillColor || '#3388ff',
                                fillOpacity: layerData.style?.fillOpacity || 0.4,
                                color: layerData.style?.strokeColor || '#000000',
                                weight: layerData.style?.strokeWidth || 1
                            };
    
                            // Add point styling
                            if (geometry === 'Point' || geometry === 'MultiPoint') {
                                style.radius = layerData.style?.pointRadius || 5;
                            }
    
                            return style;
                        },
                        pointToLayer: function(feature, latlng) {
                            const geometry = feature.geometry.type;
                            const style = {
                                fillColor: layerData.style?.fillColor || '#3388ff',
                                fillOpacity: layerData.style?.fillOpacity || 0.4,
                                color: layerData.style?.strokeColor || '#000000',
                                weight: layerData.style?.strokeWidth || 1,
                                radius: layerData.style?.pointRadius || 5
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
                                // Open popup on click
                                layer.on('click', function(e) {
                                    showFeatureInfo(feature.properties);
                                });
                            }
                        }
                    }).addTo(map);
    
                    if (geojsonLayer.getBounds().isValid()) {
                        map.fitBounds(geojsonLayer.getBounds());
                    }
                  });

            } catch (error) {
                console.error('Error loading GeoJSON:', error);
            }
        }

        return map;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const layers = LAYERS_JSON;
        let maps = {};
        let currentSection = 0;
        const totalSections = document.querySelectorAll('.story-section').length;
        
        const closeFeatureInfo = document.getElementById('closeFeatureInfo');
        closeFeatureInfo.onclick = function() {
            featureInfoPanel.style.display = 'none';
        };

        // For each section, initialize a map in its own container
        layers.forEach((section, index) => {
            // console_log('DEBUG: Processing section', index, section);
            if (section.type === 'wms' || section.type === 'upload' || section.type === 'pg') {
                const mapId = `map-section-${index}`;
                const mapElement = document.getElementById(mapId);
                // console_log('DEBUG: Found map element for', mapId, mapElement);
                
                if (mapElement) {
                    // Initialize map with proper settings
                    const map = initMap(index, section);

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

                    // Initialize layer content if it exists
                    const storySection = mapElement.closest('.story-section');
                    if (storySection) {
                        const layerContent = storySection.querySelector('.layer-content');
                        if (layerContent && section.content) {
                            layerContent.innerHTML = section.content;
                            layerContent.style.display = 'block';
                        }
                    }
                    
                    if (section.type === 'wms'){
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

                        let baseUrl = section.url.replace(/^@+/, '').trim();
                        if (baseUrl.endsWith('?') || baseUrl.endsWith('&')) {
                            baseUrl = baseUrl.slice(0, -1);
                        }
                        const url = baseUrl + (baseUrl.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();

                        fetch(url)
                            .then(response => response.text())
                            .then(html => { 
                                if (html && html.trim() !== '') {
                                    const featureInfoPanel = document.getElementById('featureInfoPanel');
                                    const featureInfoContent = document.getElementById('featureInfoContent');
                                    featureInfoContent.innerHTML = html;
                                    featureInfoPanel.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching feature info:', error);
                            });
                    });
                    }

                    // Fit bounds or use map_center/map_zoom
                    if (section.bounds) {
                        if (section.bounds.crs === 'EPSG:3857') {
                            const sw = mercatorToLatLng(section.bounds.west, section.bounds.south);
                            const ne = mercatorToLatLng(section.bounds.east, section.bounds.north);
                            map.fitBounds([sw, ne]);
                        } else {
                            map.fitBounds([
                                [section.bounds.south, section.bounds.west],
                                [section.bounds.north, section.bounds.east]
                            ]);
                        }
                    } else if (section.map_center && section.map_zoom) {
                        const [lat, lng] = section.map_center.split(',').map(Number);
                        map.setView([lat, lng], parseInt(section.map_zoom));
                    }

                    maps[mapId] = map;
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

        // Navigation
        const story = document.querySelector('.story');
        const navigation = document.getElementById('navigation');
        const navDots = document.getElementById('navDots');
        const prevButton = document.getElementById('prevButton');
        const nextButton = document.getElementById('nextButton');
        const sections = document.querySelectorAll('.story-section');
        let currentIndex = 0;

        // Create navigation dots
        sections.forEach((_, index) => {
            const dot = document.createElement('div');
            dot.className = 'nav-dot';
            dot.onclick = () => {
                currentIndex = index;
                updateNavigation();
                story.scrollTo({
                    left: index * window.innerWidth,
                    behavior: 'smooth'
                });
            };
            navDots.appendChild(dot);
        });

        // Update navigation state
        function updateNavigation() {
            // Update dots
            document.querySelectorAll('.nav-dot').forEach((dot, index) => {
                dot.classList.toggle('active', index === currentIndex);
            });

            // Update buttons
            prevButton.disabled = currentIndex === 0;
            nextButton.disabled = currentIndex === sections.length - 1;
        }

        // Previous button click
        prevButton.onclick = () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateNavigation();
                story.scrollTo({
                    left: currentIndex * window.innerWidth,
                    behavior: 'smooth'
                });
            }
        };

        // Next button click
        nextButton.onclick = () => {
            if (currentIndex < sections.length - 1) {
                currentIndex++;
                updateNavigation();
                story.scrollTo({
                    left: currentIndex * window.innerWidth,
                    behavior: 'smooth'
                });
            }
        };

        // Update active dot on scroll
        story.addEventListener('scroll', () => {
            const newIndex = Math.round(story.scrollLeft / window.innerWidth);
            if (newIndex !== currentIndex) {
                currentIndex = newIndex;
                updateNavigation();
            }
        });

        // Initialize navigation
        updateNavigation();

        // Add keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' && !prevButton.disabled) {
                prevButton.click();
            } else if (e.key === 'ArrowRight' && !nextButton.disabled) {
                nextButton.click();
            }
        });
    });
    </script>
</body>
</html>
