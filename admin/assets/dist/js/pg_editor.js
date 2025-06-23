
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
document.getElementById('pg_layer_id').addEventListener('change', async function(e) {
    if(this.value == ''){
      return;
    }
    let pg_layer_id = this.value;
    try {

      fetch(`../layers/${pg_layer_id}/geojson.php` + '?access_key=' + access_key)
        .then(function(response) {  return response.json(); })
        .then(function(geojson) {
  
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
        });
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
  var pg_select = document.getElementById('pg_layer_id');
  pg_select.dispatchEvent(new Event('change'));

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
