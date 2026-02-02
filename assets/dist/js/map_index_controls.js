document.addEventListener('DOMContentLoaded', function() {
  // Add custom zoom control
  const zoomControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      
      // Zoom in button
      const zoomIn = L.DomUtil.create('a', 'leaflet-control-zoom-in', container);
      zoomIn.innerHTML = '<i class="fas fa-plus"></i>';
      zoomIn.title = 'Zoom in';
      zoomIn.style.width = '30px';
      zoomIn.style.height = '30px';
      zoomIn.style.lineHeight = '30px';
      zoomIn.style.textAlign = 'center';
      zoomIn.style.textDecoration = 'none';
      zoomIn.style.color = 'black';
      zoomIn.style.backgroundColor = 'white';
      zoomIn.style.border = '2px solid rgba(0,0,0,0.2)';
      zoomIn.style.borderRadius = '4px 4px 0 0';
      zoomIn.style.cursor = 'pointer';
      
      // Zoom out button
      const zoomOut = L.DomUtil.create('a', 'leaflet-control-zoom-out', container);
      zoomOut.innerHTML = '<i class="fas fa-minus"></i>';
      zoomOut.title = 'Zoom out';
      zoomOut.style.width = '30px';
      zoomOut.style.height = '30px';
      zoomOut.style.lineHeight = '30px';
      zoomOut.style.textAlign = 'center';
      zoomOut.style.textDecoration = 'none';
      zoomOut.style.color = 'black';
      zoomOut.style.backgroundColor = 'white';
      zoomOut.style.border = '2px solid rgba(0,0,0,0.2)';
      zoomOut.style.borderTop = 'none';
      zoomOut.style.borderRadius = '0 0 4px 4px';
      zoomOut.style.cursor = 'pointer';
      
      // Add dark mode listener
      const updateTheme = () => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        const buttons = [zoomIn, zoomOut];
        buttons.forEach(button => {
          button.style.backgroundColor = isDarkMode ? '#2d2d2d' : 'white';
          button.style.color = isDarkMode ? '#fff' : 'black';
          button.style.borderColor = isDarkMode ? '#404040' : 'rgba(0,0,0,0.2)';
        });
      };
      
      // Initial theme setup
      updateTheme();
      
      // Listen for theme changes
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class') {
            updateTheme();
          }
        });
      });
      observer.observe(document.body, { attributes: true });
      
      // Add click handlers
      L.DomEvent.on(zoomIn, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(zoomIn, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(zoomIn, 'click', function() {
        map.zoomIn();
      });
      
      L.DomEvent.on(zoomOut, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(zoomOut, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(zoomOut, 'click', function() {
        map.zoomOut();
      });
      
      return container;
    }
  });
  
  // Add the zoom control to the map first
  // map.addControl(new zoomControl()); // Commented out to hide zoom control
  
  // Add zoom to extent control first (at the top)
  const extentControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      const button = L.DomUtil.create('a', 'leaflet-control-zoom-in', container);
      button.innerHTML = '<i class="fas fa-expand"></i>';
      button.title = 'Zoom to extent';
      button.style.width = '30px';
      button.style.height = '30px';
      button.style.lineHeight = '30px';
      button.style.textAlign = 'center';
      button.style.textDecoration = 'none';
      button.style.color = 'black';
      button.style.backgroundColor = 'white';
      button.style.border = '2px solid rgba(0,0,0,0.2)';
      button.style.borderRadius = '4px';
      button.style.cursor = 'pointer';
  
      L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(button, 'click', function() {
        map.fitBounds([
          [bbox.miny, bbox.minx],
          [bbox.maxy, bbox.maxx]
        ]);
      });
  
      return container;
    }
  });
  map.addControl(new extentControl());
  
  // Add theme switcher control
  const themeControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      const button = L.DomUtil.create('a', 'leaflet-control-theme', container);
      button.innerHTML = '<i class="fas fa-moon"></i>';
      button.title = 'Toggle Theme';
      button.style.width = '30px';
      button.style.height = '30px';
      button.style.lineHeight = '30px';
      button.style.textAlign = 'center';
      button.style.textDecoration = 'none';
      button.style.color = 'black';
      button.style.backgroundColor = 'white';
      button.style.border = '2px solid rgba(0,0,0,0.2)';
      button.style.borderRadius = '4px';
      button.style.cursor = 'pointer';
      
      // Add dark mode listener
      const updateTheme = () => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        button.style.backgroundColor = isDarkMode ? '#2d2d2d' : 'white';
        button.style.color = isDarkMode ? '#fff' : 'black';
        const icon = button.querySelector('i');
        if (icon) {
          icon.className = isDarkMode ? 'fas fa-sun' : 'fas fa-moon';
        }
      };
      
      // Initial theme setup
      updateTheme();
      
      // Listen for theme changes
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class') {
            updateTheme();
          }
        });
      });
      observer.observe(document.body, { attributes: true });
      
      L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(button, 'click', function() {
        toggleTheme();
      });
      
      return container;
    }
  });
  
  // Add the theme control to the map
  // map.addControl(new themeControl()); // Commented out to hide theme selector
  
  // Add export control
  const exportControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      const button = L.DomUtil.create('a', 'leaflet-control-export', container);
      button.innerHTML = '<i class="fas fa-download"></i>';
      button.title = 'Export Layer';
      
      // Create format selection dropdown
      const formatSelect = L.DomUtil.create('select', 'leaflet-control-export-format', container);
      formatSelect.style.display = 'none';
      
      const formats = [
        { value: 'json', label: 'GeoJSON' },
        { value: 'gpkg', label: 'GeoPackage' },
        { value: 'shp', label: 'Shapefile' }
      ];
      
      formats.forEach(format => {
        const option = L.DomUtil.create('option', '', formatSelect);
        option.value = format.value;
        option.textContent = format.label;
      });
      
      L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(button, 'click', function() {
        // Toggle format selection
        formatSelect.style.display = formatSelect.style.display === 'none' ? 'block' : 'none';
      });
      
      L.DomEvent.on(formatSelect, 'change', async function() {
        const selectedFormat = this.value;
        
        // Get all visible layers
        const cfgs = layerConfigs.filter(cfg => {
          const layer = overlayLayers[cfg.name];
          return cfg.name && map.hasLayer(layer);
        });
        
        if (cfgs.length > 0) {
          try {
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            // Get the current map bounds
            const bounds = map.getBounds();
            const bbox = [
              bounds.getSouthWest().lng,
              bounds.getSouthWest().lat,
              bounds.getNorthEast().lng,
              bounds.getNorthEast().lat
            ].join(',');
            
            // Fetch data for each layer using WFS
            const layerData = await Promise.all(cfgs.map(async cfg => {
              const wfsUrl = WMS_SVC_URL +
                '&service=WFS' +
                '&version=1.1.0' +
                '&request=GetFeature' +
                '&layers=' + cfg.name +
                '&typeName=' + cfg.typename +
                '&srsname=EPSG:4326' +
                '&bbox=' + bbox +
                '&outputFormat=' + (selectedFormat === 'json' ? 'application/json' : 'application/x-' + selectedFormat) +
                '&maxFeatures=1000';
              
              console.log('WFS URL:', wfsUrl); // Debug log
              
              const response = await fetch(wfsUrl);
              if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
              }
              
              if (selectedFormat === 'json') {
                const data = await response.json();
                return {
                  layer: cfg.name,
                  data: data
                };
              } else {
                // For binary formats, create a download link
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", url);
                downloadAnchorNode.setAttribute("download", `${cfg.typename}.${selectedFormat}`);
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
                window.URL.revokeObjectURL(url);
                return null;
              }
            }));
            
            if (selectedFormat === 'json') {
              // Create download link for JSON
              const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(layerData, null, 2));
              const downloadAnchorNode = document.createElement('a');
              downloadAnchorNode.setAttribute("href", dataStr);
              downloadAnchorNode.setAttribute("download", "map_data.json");
              document.body.appendChild(downloadAnchorNode);
              downloadAnchorNode.click();
              downloadAnchorNode.remove();
            }
            
            // Hide format selection
            formatSelect.style.display = 'none';
          } catch (error) {
            console.error('Error exporting data:', error);
            alert('Error exporting data: ' + error.message);
          } finally {
            // Reset button state
            button.innerHTML = '<i class="fas fa-download"></i>';
            button.disabled = false;
          }
        } else {
          alert('No layers found to export');
        }
      });
      
      return container;
    }
  });
  
  // Store export control globally for tab button access (but don't add to map)
  window.exportControl = exportControl;
  console.log('Export control created and stored globally:', window.exportControl);
  
  // Add browser print control with error handling
  try {
    console.log('Initializing browser print control...');
    const printControl = L.control.browserPrint({
      position: 'topright',
      title: 'Print map',
      documentTitle: qgsTitle,
      printLayer: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
      closePopupsOnPrint: false
    });
    
    // Add the control to the map normally
    map.addControl(printControl);
    
    // Wait for the control to be added to the map
    setTimeout(() => {
      const printButton = document.querySelector('.leaflet-control-browser-print a');
      if (printButton) {
        printButton.innerHTML = '<i class="fas fa-print"></i>';
        console.log('Print button styled');
      }
    }, 100);
    
    console.log('Browser print control added to map');
  } catch (printError) {
    console.error('Error initializing browser print control:', printError);
  }

  // Add QGIS print layout control with error handling
  try {
    console.log('Initializing QGIS print control...');
    var locationFilter = null;
    if(printLayout.length > 0) {
      locationFilter = new L.LocationFilter({qgisTemplate: printLayout}).addTo(map);
      console.log('QGIS print control added to map');
    } else {
      console.log('No print layout available');
    }
  } catch (qgisPrintError) {
    console.error('Error initializing QGIS print control:', qgisPrintError);
  }

  // Add Add Layer control
  const addLayerControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      const button = L.DomUtil.create('a', 'leaflet-control-add-layer', container);
      button.innerHTML = '<i class="fas fa-globe"></i>';
      button.title = 'Add WMS Layer';
      button.style.width = '30px';
      button.style.height = '30px';
      button.style.lineHeight = '30px';
      button.style.textAlign = 'center';
      button.style.textDecoration = 'none';
      button.style.color = 'black';
      button.style.backgroundColor = 'white';
      button.style.border = '2px solid rgba(0,0,0,0.2)';
      button.style.borderRadius = '4px';
      button.style.cursor = 'pointer';
      
      // Add dark mode listener
      const updateTheme = () => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        button.style.backgroundColor = isDarkMode ? '#2d2d2d' : 'white';
        button.style.color = isDarkMode ? '#fff' : 'black';
        const icon = button.querySelector('i.fa-plus');
        if (icon) {
          icon.style.color = isDarkMode ? '#fff' : 'black';
        }
      };
      
      // Initial theme setup
      updateTheme();
      
      // Listen for theme changes
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class') {
            updateTheme();
          }
        });
      });
      observer.observe(document.body, { attributes: true });
      
      L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(button, 'click', function() {
        showAddLayerModal();
      });
      
      return container;
    }
  });
  // map.addControl(new addLayerControl()); // Commented out - now handled by tab button

  // Add geolocate control
  const geolocateControl = L.Control.extend({
    options: {
      position: 'topright'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      const button = L.DomUtil.create('a', 'leaflet-control-geolocate', container);
      button.innerHTML = '<i class="fas fa-location-arrow"></i>';
      button.title = 'Geolocate';
      button.style.width = '30px';
      button.style.height = '30px';
      button.style.lineHeight = '30px';
      button.style.textAlign = 'center';
      button.style.textDecoration = 'none';
      button.style.color = 'black';
      button.style.backgroundColor = 'white';
      button.style.border = '2px solid rgba(0,0,0,0.2)';
      button.style.borderRadius = '4px';
      button.style.cursor = 'pointer';
      
      // Add dark mode listener
      const updateTheme = () => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        button.style.backgroundColor = isDarkMode ? '#2d2d2d' : 'white';
        button.style.color = isDarkMode ? '#fff' : 'black';
        const icon = button.querySelector('i');
        if (icon) {
          icon.style.color = isDarkMode ? '#fff' : 'black';
        }
      };
      
      // Initial theme setup
      updateTheme();
      
      // Listen for theme changes
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class') {
            updateTheme();
          }
        });
      });
      observer.observe(document.body, { attributes: true });
      
      L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
      L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
      L.DomEvent.on(button, 'click', function() {
        if (navigator.geolocation) {
          button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
          navigator.geolocation.getCurrentPosition(
            function(position) {
              const lat = position.coords.latitude;
              const lng = position.coords.longitude;
              map.setView([lat, lng], 15);
              L.marker([lat, lng]).addTo(map)
                .bindPopup('Your location')
                .openPopup();
              button.innerHTML = '<i class="fas fa-location-arrow"></i>';
            },
            function(error) {
              console.error('Error getting location:', error);
              alert('Error getting your location: ' + error.message);
              button.innerHTML = '<i class="fas fa-location-arrow"></i>';
            }
          );
        } else {
          alert('Geolocation is not supported by your browser');
        }
      });
      
      return container;
    }
  });

  // Add the geolocate control to the map
  map.addControl(new geolocateControl()); // Commented out - now handled by tab button

  // Add coordinate display
  const coordDisplay = L.Control.extend({
    options: {
      position: 'bottomleft'
    },
    onAdd: function(map) {
      const container = L.DomUtil.create('div', 'leaflet-control leaflet-control-coordinates');
      container.style.backgroundColor = 'white';
      container.style.padding = '5px 10px';
      container.style.borderRadius = '4px';
      container.style.border = '2px solid rgba(0,0,0,0.2)';
      container.style.fontSize = '12px';
      container.style.fontFamily = 'Arial, sans-serif';
      container.style.color = '#333';
      container.style.minWidth = '200px';
      container.style.textAlign = 'center';
      
      // Add dark mode listener
      const updateTheme = () => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        container.style.backgroundColor = isDarkMode ? '#2d2d2d' : 'white';
        container.style.color = isDarkMode ? '#fff' : '#333';
        container.style.borderColor = isDarkMode ? '#404040' : 'rgba(0,0,0,0.2)';
      };
      
      // Initial theme setup
      updateTheme();
      
      // Listen for theme changes
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class') {
            updateTheme();
          }
        });
      });
      observer.observe(document.body, { attributes: true });
      
      map.on('mousemove', function(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        container.innerHTML = `Lat: ${lat}° N, Lng: ${lng}° E`;
      });
      
      return container;
    }
  });

  // Add the coordinate display to the map
  map.addControl(new coordDisplay());
  
  // Initialize controls with error handling
  let measureControl, printControl;
  try {
    // Add custom measurement control
    const measureControl = L.Control.extend({
      options: {
        position: 'topright'
      },
      onAdd: function(map) {
        const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
        const button = L.DomUtil.create('a', 'measure-control', container);
        button.innerHTML = '<i class="fas fa-ruler"></i>';
        button.title = 'Measure distance';
        
        let measureMode = false;
        let measurePoints = [];
        let measureLine = null;
        let measureTooltip = null;
        let totalDistance = 0;
  
        function updateMeasureTooltip(latlng) {
          if (measureTooltip) {
            measureTooltip.setLatLng(latlng);
          } else {
            measureTooltip = L.marker(latlng, {
              icon: L.divIcon({
                className: 'measure-tooltip',
                html: 'Click to start measuring',
                iconSize: [100, 20],
                iconAnchor: [50, 10]
              })
            }).addTo(map);
          }
        }
  
        function updateMeasureLine() {
          if (measureLine) {
            map.removeLayer(measureLine);
          }
          if (measurePoints.length > 1) {
            measureLine = L.polyline(measurePoints, {
              color: '#3f51b5',
              weight: 2,
              opacity: 0.7
            }).addTo(map);
          }
        }
  
        function calculateDistance() {
          totalDistance = 0;
          for (let i = 1; i < measurePoints.length; i++) {
            totalDistance += measurePoints[i-1].distanceTo(measurePoints[i]);
          }
          return totalDistance;
        }
  
        function finishMeasurement() {
          if (measurePoints.length > 1) {
            const distance = calculateDistance();
            const popup = L.popup({
              className: 'measure-result-popup',
              closeButton: false,
              autoPan: false
            })
            .setLatLng(measurePoints[measurePoints.length - 1])
            .setContent(`Total distance: ${(distance / 1000).toFixed(2)} km`)
            .openOn(map);
          }
          measureMode = false;
          measurePoints = [];
          if (measureLine) map.removeLayer(measureLine);
          if (measureTooltip) map.removeLayer(measureTooltip);
          measureLine = null;
          measureTooltip = null;
          totalDistance = 0;
          map.dragging.enable();
        }
  
        L.DomEvent.on(button, 'click', L.DomEvent.stopPropagation);
        L.DomEvent.on(button, 'click', L.DomEvent.preventDefault);
        L.DomEvent.on(button, 'click', function() {
          if (!measureMode) {
            measureMode = true;
            map.dragging.disable();
            button.style.backgroundColor = '#3f51b5';
            button.style.color = 'white';
          } else {
            finishMeasurement();
            button.style.backgroundColor = '';
            button.style.color = '';
          }
        });
  
        map.on('click', function(e) {
          if (measureMode) {
            measurePoints.push(e.latlng);
            updateMeasureLine();
            updateMeasureTooltip(e.latlng);
            
            if (measurePoints.length > 1) {
              const segmentDistance = measurePoints[measurePoints.length - 2].distanceTo(measurePoints[measurePoints.length - 1]);
              const totalDistance = calculateDistance();
              measureTooltip.setIcon(L.divIcon({
                className: 'measure-tooltip',
                html: `Segment: ${(segmentDistance / 1000).toFixed(2)} km<br>Total: ${(totalDistance / 1000).toFixed(2)} km`,
                iconSize: [150, 40],
                iconAnchor: [75, 20]
              }));
            }
          }
        });
  
        map.on('dblclick', function(e) {
          if (measureMode) {
            L.DomEvent.stop(e);
            finishMeasurement();
            button.style.backgroundColor = '';
            button.style.color = '';
          }
        });
  
        return container;
      }
    });
    map.addControl(new measureControl());
  
  } catch (error) {
    console.error('Error initializing controls:', error);
  }

  // Add the export control to the map (moved to be the last control)
  // map.addControl(new exportControl()); // This line is now redundant as exportControlInstance is global
  
  // Add click handlers for the new tab buttons
  // Wait a bit for controls to be fully initialized
  setTimeout(() => {
    console.log('Setting up tab button handlers...');
    console.log('Available controls:', {
      exportControl: window.exportControl,
      exportControlInstance: exportControl
    });
    
    // Export tab button handler
    const exportTab = document.getElementById('export-tab');
    if (exportTab) {
      console.log('Export tab found, adding click handler');
      exportTab.addEventListener('click', function() {
        console.log('Export tab clicked');
        
        // Create a temporary export control instance to show the format selection
        if (window.exportControl) {
          console.log('Using export control class to create instance');
          
          // Create a temporary container for the export functionality
          const tempContainer = document.createElement('div');
          tempContainer.style.position = 'fixed';
          tempContainer.style.top = '50%';
          tempContainer.style.left = '50%';
          tempContainer.style.transform = 'translate(-50%, -50%)';
          tempContainer.style.backgroundColor = 'white';
          tempContainer.style.padding = '20px';
          tempContainer.style.border = '2px solid #ccc';
          tempContainer.style.borderRadius = '8px';
          tempContainer.style.zIndex = '9999';
          tempContainer.style.boxShadow = '0 4px 8px rgba(0,0,0,0.3)';
          
          // Create the export control content
          const exportInstance = new window.exportControl();
          const controlContainer = exportInstance.onAdd(map);
          
          // Clone the control content to our temporary container
          tempContainer.innerHTML = controlContainer.innerHTML;
          
          // Add close button
          const closeBtn = document.createElement('button');
          closeBtn.innerHTML = '×';
          closeBtn.style.position = 'absolute';
          closeBtn.style.top = '5px';
          closeBtn.style.right = '10px';
          closeBtn.style.border = 'none';
          closeBtn.style.background = 'none';
          closeBtn.style.fontSize = '20px';
          closeBtn.style.cursor = 'pointer';
          closeBtn.onclick = () => document.body.removeChild(tempContainer);
          tempContainer.appendChild(closeBtn);
          
          // Add title
          const title = document.createElement('h5');
          title.textContent = 'Export Map Data';
          title.style.marginTop = '0';
          title.style.marginBottom = '15px';
          tempContainer.insertBefore(title, tempContainer.firstChild);
          
          // Add to page
          document.body.appendChild(tempContainer);
          
          // Re-attach event listeners to the cloned elements
          const clonedButton = tempContainer.querySelector('.leaflet-control-export');
          const clonedSelect = tempContainer.querySelector('.leaflet-control-export-format');
          
          if (clonedButton && clonedSelect) {
            // Show format selection when button is clicked
            clonedButton.onclick = function() {
              clonedSelect.style.display = clonedSelect.style.display === 'none' ? 'block' : 'none';
            };
            
            // Handle format selection
            clonedSelect.onchange = async function() {
              const selectedFormat = this.value;
              
              // Get all visible layers
              const cfgs = layerConfigs.filter(cfg => {
                const layer = overlayLayers[cfg.name];
                return cfg.name && map.hasLayer(layer);
              });
              
              if (cfgs.length > 0) {
                try {
                  // Show loading state
                  clonedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                  clonedButton.disabled = true;
                  
                  // Get the current map bounds
                  const bounds = map.getBounds();
                  const bbox = [
                    bounds.getSouthWest().lng,
                    bounds.getSouthWest().lat,
                    bounds.getNorthEast().lng,
                    bounds.getNorthEast().lat
                  ].join(',');
                  
                  // Fetch data for each layer using WFS
                  const layerData = await Promise.all(cfgs.map(async cfg => {
                    const wfsUrl = WMS_SVC_URL +
                      '&service=WFS' +
                      '&version=1.1.0' +
                      '&request=GetFeature' +
                      '&layers=' + cfg.name +
                      '&typeName=' + cfg.typename +
                      '&srsname=EPSG:4326' +
                      '&bbox=' + bbox +
                      '&outputFormat=' + (selectedFormat === 'json' ? 'application/json' : 'application/x-' + selectedFormat) +
                      '&maxFeatures=1000';
                    
                    console.log('WFS URL:', wfsUrl);
                    
                    const response = await fetch(wfsUrl);
                    if (!response.ok) {
                      throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    if (selectedFormat === 'json') {
                      const data = await response.json();
                      return {
                        layer: cfg.name,
                        data: data
                      };
                    } else {
                      // For binary formats, create a download link
                      const blob = await response.blob();
                      const url = window.URL.createObjectURL(blob);
                      const downloadAnchorNode = document.createElement('a');
                      downloadAnchorNode.setAttribute("href", url);
                      downloadAnchorNode.setAttribute("download", `${cfg.typename}.${selectedFormat}`);
                      document.body.appendChild(downloadAnchorNode);
                      downloadAnchorNode.click();
                      downloadAnchorNode.remove();
                      window.URL.revokeObjectURL(url);
                      return null;
                    }
                  }));
                  
                  if (selectedFormat === 'json') {
                    // Create download link for JSON
                    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(layerData, null, 2));
                    const downloadAnchorNode = document.createElement('a');
                    downloadAnchorNode.setAttribute("href", dataStr);
                    downloadAnchorNode.setAttribute("download", "map_data.json");
                    document.body.appendChild(downloadAnchorNode);
                    downloadAnchorNode.click();
                    downloadAnchorNode.remove();
                  }
                  
                  // Hide format selection and close modal
                  clonedSelect.style.display = 'none';
                  document.body.removeChild(tempContainer);
                  
                } catch (error) {
                  console.error('Error exporting data:', error);
                  alert('Error exporting data: ' + error.message);
                } finally {
                  // Reset button state
                  clonedButton.innerHTML = '<i class="fas fa-download"></i>';
                  clonedButton.disabled = false;
                }
              } else {
                alert('No layers found to export');
              }
            };
          }
          
        } else {
          console.log('Export control not available');
          alert('Export functionality not available');
        }
      });
    } else {
      console.log('Export tab not found');
    }
    
    // WMS Loader tab button handler
    const wmsLoaderTab = document.getElementById('wms-loader-tab');
    if (wmsLoaderTab) {
      console.log('WMS Loader tab found, adding click handler');
      wmsLoaderTab.addEventListener('click', function() {
        console.log('WMS Loader tab clicked');
        // Show the WMS layer modal
        showAddLayerModal();
      });
    } else {
      console.log('WMS Loader tab not found');
    }

    // Geolocate tab button handler
    const geolocateTab = document.getElementById('geolocate-tab');
    if (geolocateTab) {
      console.log('Geolocate tab found, adding click handler');
      geolocateTab.addEventListener('click', function() {
        console.log('Geolocate tab clicked');
        
        // Check geolocation permission status first
        if (navigator.permissions && navigator.permissions.query) {
          navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
            console.log('Geolocation permission status:', result.state);
            
            if (result.state === 'denied') {
              alert('Geolocation permission is denied. Please check your browser settings and allow location access for this site.');
              return;
            } else if (result.state === 'prompt') {
              console.log('Geolocation permission will be prompted');
            } else if (result.state === 'granted') {
              console.log('Geolocation permission is granted');
            }
            
            // Proceed with geolocation
            triggerGeolocation();
          });
        } else {
          console.log('Permissions API not supported, proceeding with geolocation');
          triggerGeolocation();
        }
        
        function triggerGeolocation() {
          // Trigger geolocation functionality directly
          if (navigator.geolocation) {
            console.log('Geolocation API is supported');
            
            // Show loading state on the tab button
            const originalIcon = geolocateTab.querySelector('i');
            const originalClass = originalIcon.className;
            originalIcon.className = 'fas fa-spinner fa-spin';
            
            const options = {
              enableHighAccuracy: true,
              timeout: 10000,
              maximumAge: 60000 // 1 minute cache
            };
            
            console.log('Requesting geolocation with options:', options);
            
            navigator.geolocation.getCurrentPosition(
              function(position) {
                console.log('Geolocation successful:', position);
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                
                console.log(`Location: ${lat}, ${lng} (accuracy: ${accuracy}m)`);
                
                map.setView([lat, lng], 15);
                L.marker([lat, lng]).addTo(map)
                  .bindPopup(`Your location<br>Accuracy: ${accuracy}m`)
                  .openPopup();
                
                // Reset button state
                originalIcon.className = originalClass;
              },
              function(error) {
                console.error('Geolocation error:', error);
                console.error('Error code:', error.code);
                console.error('Error message:', error.message);
                
                let errorMessage = 'Error getting your location: ';
                switch(error.code) {
                  case error.PERMISSION_DENIED:
                    errorMessage += 'Permission denied. Please allow location access in your browser settings.';
                    break;
                  case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information unavailable.';
                    break;
                  case error.TIMEOUT:
                    errorMessage += 'Request timed out.';
                    break;
                  default:
                    errorMessage += error.message;
                }
                
                alert(errorMessage);
                
                // Reset button state
                originalIcon.className = originalClass;
              },
              options
            );
          } else {
            console.log('Geolocation API not supported');
            alert('Geolocation is not supported by your browser');
          }
        }
      });
    } else {
      console.log('Geolocate tab not found');
    }
  }, 1000); // Wait 1 second for controls to be fully initialized
  
  // Function to show WMS layer modal
  function showAddLayerModal() {
    const modal = new bootstrap.Modal(document.getElementById('addLayerModal'));
    modal.show();
  }
  
  // Make the function globally available
  window.showAddLayerModal = showAddLayerModal;
  
  });

