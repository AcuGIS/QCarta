
let map;
let uploadedFiles = {};
let currentVectorLayer = null;

// Initialize map
function initMap() {
    map = new ol.Map({
        target: 'map',
        layers: [
            new ol.layer.Tile({
                source: new ol.source.OSM()
            })
        ],
        view: new ol.View({
            center: ol.proj.fromLonLat([0, 0]),
            zoom: 2
        })
    });
}

// Handle file uploads
document.getElementById('shapefile').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;

    try {
        console.log('Loading file:', file.name);
        const fileExt = file.name.split('.').pop().toLowerCase();
        let geojson = null;

        if (fileExt === 'zip') {
            // Process shapefile
            const zip = await JSZip.loadAsync(file);
            console.log('Zip contents:', Object.keys(zip.files));
            
            const files = {};
            
            // Extract required files from zip
            for (const [filename, zipEntry] of Object.entries(zip.files)) {
                const ext = filename.split('.').pop().toLowerCase();
                console.log('Processing file:', filename, 'with extension:', ext);
                if (['shp', 'dbf', 'shx'].includes(ext)) {
                    console.log('Loading file:', filename);
                    files[ext] = await zipEntry.async('arraybuffer');
                    console.log('File loaded:', filename, 'size:', files[ext].byteLength);
                }
            }

            // Check if we have all required files
            if (files.shp && files.dbf && files.shx) {
                console.log('All required files found, processing shapefile...');
                try {
                    // Validate file sizes
                    if (files.shp.byteLength === 0 || files.dbf.byteLength === 0 || files.shx.byteLength === 0) {
                        throw new Error('One or more files are empty');
                    }

                    // Create a new source for each attempt
                    const source = await shapefile.open(files.shp, files.dbf, files.shx);
                    console.log('Shapefile opened successfully');

                    // Read all features
                    const features = [];
                    let result;
                    while (!(result = await source.read()).done) {
                        if (result.value && result.value.type) {
                            features.push(result.value);
                        }
                    }

                    if (features.length === 0) {
                        throw new Error('No valid features found in the shapefile');
                    }

                    // Create GeoJSON
                    geojson = {
                        type: 'FeatureCollection',
                        features: features
                    };
                } catch (shapefileError) {
                    console.error('Error processing shapefile:', shapefileError);
                    throw new Error('Error processing shapefile: ' + shapefileError.message);
                }
            } else {
                const missingFiles = [];
                if (!files.shp) missingFiles.push('.shp');
                if (!files.dbf) missingFiles.push('.dbf');
                if (!files.shx) missingFiles.push('.shx');
                throw new Error('The zip file is missing required components: ' + missingFiles.join(', '));
            }
        } else if (fileExt === 'geojson') {
            // Process GeoJSON file
            const text = await file.text();
            geojson = JSON.parse(text);
            
            // Validate GeoJSON
            if (!geojson.type || !geojson.features) {
                throw new Error('Invalid GeoJSON structure');
            }
        } else if (fileExt === 'gpx') {
            // Process GPX file
            const text = await file.text();
            const parser = new DOMParser();
            const gpxDoc = parser.parseFromString(text, 'text/xml');
            
            // Convert GPX to GeoJSON
            geojson = toGeoJSON.gpx(gpxDoc);
        } else {
            throw new Error('Unsupported file format');
        }

        console.log('GeoJSON generated with', geojson.features.length, 'features');

        // Store the GeoJSON in the hidden input
        document.getElementById('geojson').value = JSON.stringify(geojson);
        console.log('GeoJSON stored successfully');

        // Add the GeoJSON to the map
        const vectorSource = new ol.source.Vector({
            features: new ol.format.GeoJSON().readFeatures(geojson)
        });

        // Remove existing vector layer if any
        if (currentVectorLayer) {
            map.removeLayer(currentVectorLayer);
        }

        // Create new vector layer with styling
        currentVectorLayer = new ol.layer.Vector({
            source: vectorSource,
            style: new ol.style.Style({
                fill: new ol.style.Fill({
                    color: document.getElementById('fillColor').value + 
                            Math.round(parseFloat(document.getElementById('fillOpacity').value) * 255).toString(16).padStart(2, '0')
                }),
                stroke: new ol.style.Stroke({
                    color: document.getElementById('strokeColor').value,
                    width: parseFloat(document.getElementById('strokeWidth').value)
                })
            })
        });

        map.addLayer(currentVectorLayer);
        map.getView().fit(vectorSource.getExtent(), {
            padding: [50, 50, 50, 50]
        });

        console.log('Map updated with new layer');
    } catch (error) {
        console.error('Error processing file:', error);
        console.error('Error details:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        alert('Error processing file: ' + error.message);
    }
});

// Add event listeners for style controls
document.getElementById('fillColor').addEventListener('change', updateLayerStyle);
document.getElementById('strokeColor').addEventListener('change', updateLayerStyle);
document.getElementById('strokeWidth').addEventListener('change', updateLayerStyle);
document.getElementById('fillOpacity').addEventListener('input', updateLayerStyle);
document.getElementById('pointRadius').addEventListener('change', updateLayerStyle);

function updateLayerStyle() {
    if (!currentVectorLayer) return;

    const fillColor = document.getElementById('fillColor').value + 
                    Math.round(parseFloat(document.getElementById('fillOpacity').value) * 255).toString(16).padStart(2, '0');
    const strokeColor = document.getElementById('strokeColor').value;
    const strokeWidth = parseFloat(document.getElementById('strokeWidth').value);
    const pointRadius = parseFloat(document.getElementById('pointRadius').value);

    currentVectorLayer.setStyle(function(feature) {
        const geometry = feature.getGeometry();
        const type = geometry.getType();

        // Create style based on geometry type
        const style = new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: strokeColor,
                width: strokeWidth
            })
        });

        // Add fill for polygons
        if (type === 'Polygon' || type === 'MultiPolygon') {
            style.setFill(new ol.style.Fill({
                color: fillColor
            }));
        }
        // Add circle for points
        else if (type === 'Point' || type === 'MultiPoint') {
            style.setImage(new ol.style.Circle({
                radius: pointRadius,
                fill: new ol.style.Fill({
                    color: fillColor
                }),
                stroke: new ol.style.Stroke({
                    color: strokeColor,
                    width: strokeWidth
                })
            }));
        }
        // Add fill for lines
        else if (type === 'LineString' || type === 'MultiLineString') {
            style.setStroke(new ol.style.Stroke({
                color: fillColor,
                width: strokeWidth
            }));
        }

        return style;
    });
}

// Initialize map when page loads
window.onload = initMap;

$(document).ready(function() {
    $('#content').summernote({
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});
