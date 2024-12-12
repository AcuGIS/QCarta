<!doctype html>
<html>
  <head>
    <title>WMS GetFeatureInfo</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.0.2/leaflet.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.0.2/leaflet.js"></script></script>
    <style type="text/css">
      html, body, #map {
        margin: 0px;
        height: 100%;
        width: 100%;
      }
    </style>

<style type="text/css">
      html, body, #map {
        margin: 0px;
        height: 100%;
        width: 100%;
      }

      .sidebar {
        max-width: 300px;
        background: white;
        max-height: 400px;
        overflow-x: hidden;
        overflow-y: auto;
        display: none;
      }
      .sidebar .close {
          position: absolute;
          right: 0;
      }

.leaflet-clickable {
  cursor: crosshair !important;
}
/* Change cursor when over entire map */
.leaflet-container {
  cursor: pointer !important;
}

    </style>
  </head>
  <body>
    <div id="map"></div>
    
    <script src="https://code.jquery.com/jquery-1.10.1.min.js"></script>
    <script src="L.BetterWMS.js"></script>
    <script>
      var map = L.map('map', {
        center: [55.3781,3.4360],
        zoom: 6
      });
      

 var customControl = L.Control.extend({
      options: {
        position: 'topleft' // set the position of the control
      },

      onAdd: function (map) {
        // Create a container div for the control
        var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');

        // Add content to the container
        container.innerHTML = `<div class="sidebar">
                <a href="#" class="btn btn-sm mt-1 mx-3 close" id="fg-close-it" onclick="$(this).closest('.sidebar').hide()">X</a>
                <div class="table-container px-3 py-4"></div>
            </div>`;
				L.DomEvent.disableClickPropagation(container);	// Prevent click events propagation to map
				L.DomEvent.disableScrollPropagation(container);
        return container;
      }
    });
    map.addControl(new customControl());

      var url = 'https://quailserver.webgis1.com/mproxy/service?access_key=07e3c5ff-e84c-415d-bb7f-47f710c8307c';


      
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png').addTo(map);
      
      L.tileLayer.betterWms(url, {
        layers: 'bgsgrid',
        transparent: true,
        format: 'image/png'
      }).addTo(map);
    </script>
  </body>
</html>
