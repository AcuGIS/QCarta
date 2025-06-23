// Define global variables
const overlayLayers = {};     // Create WMS layers
const layerWfsFeatures = [];  // Store WFS feature references for each layer

let allFeatures = [];
let chartFeatures = [];
let chartInstance = null;
let filteredFeatures = [];
let isDataTableDirty = [];
let isChartsDataDirty = true;
let isPlotlyDataDirty = true;
let isSavedQueryClick = false;

// disable logging to console
console.log = function () {};

document.addEventListener('DOMContentLoaded', function() {
  
  console.log('DOM loaded, attaching event listeners');

  // Use event delegation for the buttons
  document.addEventListener('click', function(event) {
      // Check if the clicked element is one of our buttons
      switch(event.target.id){
        case 'limitMapToResults':
        case 'limitMapToResultsIcon':
        case 'limitMapToResultsModal':
        case 'limitMapToResultsModalIcon':
          console.log('Limit Map button clicked:', event.target.id);
          if (window.lastQueryResults) {
              console.log('Found query results, calling limitMapToResults');
              limitMapToResults(window.lastQueryResults);
          } else {
              console.log('No query results found');
          }
          break;
        
        case 'clearMapFromResults':
        case 'clearMapFromResultsIcon':
          pointLayer.clearLayers();
          polygonLayer.clearLayers();
          break;
        
        case 'openInModal':
        case 'openInModal2':
        case 'openInModalIcon':
        case 'openInModalIcon2':
          console.log('Open in Modal button clicked');
          const modal = new bootstrap.Modal(document.getElementById('sqlResultsModal'));
          modal.show();
          break;
        case 'exportResults':
        case 'exportResultsIcon':
        case 'exportResultsModal':
        case 'exportResultsModalIcon':
          if (window.lastQueryResults) {
            exportToCSV(window.lastQueryResults);
          }
          break;
        case 'charts-tab':
          console.log('Charts tab clicked');
          if(isChartsDataDirty){
            updateChartTab();
          }
          break;
        case 'plotly-tab':
          console.log('Plotly tab clicked');
          if(isPlotlyDataDirty){
            updatePlotlyTab();
          }
          break;
        case 'sidebarToggle':
        case 'sidebarToggleI':
          let sidebar         = document.getElementById('sidebar');
          let sidebarToggle   = document.getElementById('sidebarToggle');
          let sidebarToggleI  = document.getElementById('sidebarToggleI');
          
          sidebar.classList.toggle('collapsed');
          sidebarToggle.classList.toggle('collapsed');
          sidebarToggleI.classList.toggle('fa-chevron-right');
          sidebarToggleI.classList.toggle('fa-chevron-left');
          break;
        case 'viewDataBtn':
        case 'viewDataBtnIcon':
          document.getElementById('sql-tab').click();
          break;
        case 'clearQueryBtn':
        case 'clearQueryBtnIcon':
          clearSavedQuery();
          break;
        default:
          break;
      }
  });
});

function tabbedPopup(e){
  const bounds = map.getBounds();
  const size = map.getSize();
  const point = e.containerPoint;
  
  // Get all visible layers
  const visibleLayers = layerConfigs.filter(cfg => {
    const layer = overlayLayers[cfg.name];
    return layer && map.hasLayer(layer);
  });

  if (visibleLayers.length === 0) {
    console.log('No visible layers to query');
    return;
  }

  console.log('Visible layers:', visibleLayers.map(l => l.name));

  // Calculate bbox and point coordinates
  const sw = bounds.getSouthWest();
  const ne = bounds.getNorthEast();
  const bbox = [sw.lng, sw.lat, ne.lng, ne.lat].join(',');
  
  // group layers by URL
  let urlNames = {};
  visibleLayers.forEach((layer, index) => {
    // if they have URL, they are externally added layers by WMS Loader
    const u = layer?.url || WMS_SVC_URL;  
    if(u in urlNames){
      urlNames[u].push(layer.name); // append name to array
    }else{
      urlNames[u] = [layer.name];
    }
  });
  
  // build query URLs with layer names joined to make a single query per URL
  let urls = [];
  for( var u in urlNames ) {
    const layers = urlNames[u].map(name => encodeURIComponent(name)).join(',');
    
    const delim = u.includes('?') ? '&' : '?';
    const url = u + delim + `SERVICE=WMS&` +
      `VERSION=1.1.1&` +
      `REQUEST=GetFeatureInfo&` +
      `LAYERS=${layers}&` +
      `QUERY_LAYERS=${layers}&` +
      `BBOX=${bbox}&` +
      `HEIGHT=${size.y}&` +
      `WIDTH=${size.x}&` +
      `FORMAT=image/png&` +
      `INFO_FORMAT=application/json&` +
      `SRS=EPSG:4326&` +
      `X=${Math.round(point.x)}&` +
      `Y=${Math.round(point.y)}&` +
      `FEATURE_COUNT=10&`;
      
      urls.push(url);
  }
  
  // make all requests and show popup
  Promise.all(urls.map(url => fetch(url).then(r => r.json())))
    .then(results => {
      
      let features = [];
      results.forEach((result, index) => {
        if(result.features){
          features = features.concat(result.features);
        };
      });
      
      // Create tabbed popup content
      const popupContent = `
        <div class="popup-content">
          <ul class="nav nav-tabs" role="tablist">
            ${features.map((feature, index) => `
              <li class="nav-item" role="presentation">
                <button class="nav-link ${index === 0 ? 'active' : ''}" 
                        id="popup-${feature.id}-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#popup-${feature.id}" 
                        type="button" 
                        role="tab"
                        aria-selected="${index === 0 ? 'true' : 'false'}">
                  ${feature.id}
                </button>
              </li>
            `).join('')}
          </ul>
          <div class="tab-content p-2">
            ${features.map((feature, index) => `
              <div class="tab-pane fade ${index === 0 ? 'show active' : ''}" 
                   id="popup-${feature.id}" 
                   role="tabpanel">
                  <div class="popup-section">
                    <div class="popup-body">
                      ${Object.entries(feature.properties)
                        .filter(([key]) => key !== 'name')
                        .map(([key, value]) => {
                          if((typeof value === 'string') && value.match(/DCIM\/.*\.(jpg|jpeg|png|webp|gif)/i)){
                            value = `<a href="img_filep.php?f=${value}" target="_blank"><img src="img_filep.php?f=${value}" alt="${value}" height="100"/></a>`;
                          }
                          return `
                          <div class="popup-row">
                            <span class="popup-label">${key}:</span>
                            <span class="popup-value">${value}</span>
                          </div>
                        `}).join('')}
                    </div>
                  </div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
  
      // Show popup at clicked location
      L.popup({
        maxWidth: 350,
        maxHeight: 400,
        className: 'custom-popup',
        closeButton: true,
        autoPan: true,
        autoPanPadding: [50, 50]
      })
        .setLatLng(e.latlng)
        .setContent(popupContent)
        .openOn(map);
      
    })
    .catch(error => {
      console.error('Error fetching data:', error);
    });
}

// Initialize map
const map = L.map('map', {
  zoomControl: false,  // Disable default zoom control
  cursor: 'pointer'    // Add cursor style
});

// Add cursor style to map container
document.getElementById('map').style.cursor = 'pointer';

// Initialize feature layers with proper styling
const pointLayer = L.geoJSON(null, {
  onEachFeature: (feature, layer) => {
    //bind click
    layer.on({
        click: tabbedPopup
    });
  },
  pointToLayer: (feature, latlng) => {
    return L.circleMarker(latlng, {
      radius: 8,
      fillColor: '#000',
      color: '#000',
      weight: 1,
      opacity: 0.3,
      fillOpacity: 0.1
    });
  }
}).addTo(map);

const polygonLayer = L.geoJSON(null, {
  onEachFeature: (feature, layer) => {  
    //bind click
    layer.on({
        click: tabbedPopup
    });
  },
  style: () => ({ weight: 1, fillOpacity: 0, color: '#000' })
}).addTo(map);

// Add a debug event listener to the feature layer
pointLayer.on('add', (e) => {
  console.log('Feature layer added to map');
  console.log('Layer features:', e.target.getLayers());
});

pointLayer.on('layeradd', (e) => {
  console.log('Layer added:', e.layer);
  console.log('Layer type:', e.layer.feature?.geometry?.type);
});

// Add legend
const legend = L.control({position: 'bottomright'});
legend.onAdd = function (map) {
  const div = L.DomUtil.create('div', 'info legend');
  div.innerHTML = `<img src="proxy_qgis.php?SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=` + url_layers + `&FORMAT=image/png">`;
  return div;
};
legend.addTo(map);

// Set initial extent
map.fitBounds([
  [bbox.miny, bbox.minx],
  [bbox.maxy, bbox.maxx]
]);

// Create a single base layer that we'll update
const baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '  OpenStreetMap contributors'
}).addTo(map);

// Define base layer URLs
const baseLayerUrls = {
  osm: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  carto: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
  esri: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
};

// Handle basemap changes
document.getElementById("basemapSelect").addEventListener("change", function() {
  const selected = this.value;
  baseLayer.setUrl(baseLayerUrls[selected]);
});

layerConfigs.forEach((cfg, idx) => {
  // Create WMS layer
  const wmsLayer = L.tileLayer.wms(WMS_SVC_URL, {
    layers: cfg.name,
    format: 'image/png',
    transparent: true,
    version: '1.1.0'
 }).setZIndex(idx + 1).addTo(map);
  overlayLayers[cfg.name] = wmsLayer;
  isDataTableDirty[idx] = true;

  const li = document.createElement('div');
  li.className = 'layer-item';

  // Left side with visibility icon and name
  const leftDiv = document.createElement('div');
  leftDiv.className = 'layer-left';
  
  const visibilityIcon = document.createElement('i');
  visibilityIcon.className = 'fas fa-eye layer-visibility';
  visibilityIcon.title = 'Toggle layer visibility';
  visibilityIcon.addEventListener('click', () => {
    if (map.hasLayer(wmsLayer)) {
      map.removeLayer(wmsLayer);
      visibilityIcon.className = 'fas fa-eye-slash layer-visibility';
    } else {
      map.addLayer(wmsLayer);
      visibilityIcon.className = 'fas fa-eye layer-visibility';
    }

    updateDataTable(idx);
  });

  const nameSpan = document.createElement('span');
  nameSpan.textContent = cfg.label;
  
  leftDiv.appendChild(visibilityIcon);
  leftDiv.appendChild(nameSpan);

  // Right side with menu
  const rightDiv = document.createElement('div');
  rightDiv.className = 'layer-right';
  
  const menuIcon = document.createElement('i');
  menuIcon.className = 'fas fa-ellipsis-vertical layer-menu';
  menuIcon.title = 'Layer options';
  
  const menuContent = document.createElement('div');
  menuContent.className = 'layer-menu-content';
  
  // Opacity control
  const opacityDiv = document.createElement('div');
  opacityDiv.className = 'layer-menu-item';
  opacityDiv.innerHTML = `
    <i class="fas fa-adjust"></i>
    <div style="flex: 1">
      <div>Opacity</div>
      <input type="range" min="0" max="1" step="0.1" value="1" style="width: 100%" 
        onInput="this.parentElement.previousElementSibling.style.opacity = this.value">
    </div>
  `;
  opacityDiv.querySelector('input').addEventListener('input', (e) => {
    wmsLayer.setOpacity(parseFloat(e.target.value));
  });
  
  // Zoom to layer
  const zoomDiv = document.createElement('div');
  zoomDiv.className = 'layer-menu-item';
  zoomDiv.innerHTML = '<i class="fas fa-search"></i> Zoom to layer';
  zoomDiv.addEventListener('click', () => {
    // Use the bbox from PHP for this layer
    const layerName = cfg.name;

    map.fitBounds([
      [bbox.miny, bbox.minx],
      [bbox.maxy, bbox.maxx]
    ]);
    
    menuContent.classList.remove('show');
  });
  
  menuContent.appendChild(opacityDiv);
  menuContent.appendChild(zoomDiv);
  
  // Toggle menu on click
  menuIcon.addEventListener('click', (e) => {
    e.stopPropagation();
    
    // Close all other open menus first
    document.querySelectorAll('.layer-menu-content.show').forEach(menu => {
      if (menu !== menuContent) {
        menu.classList.remove('show');
      }
    });
    
    // Toggle current menu
    menuContent.classList.toggle('show');
    
    if (menuContent.classList.contains('show')) {
      // Position the menu next to the icon
      const rect = menuIcon.getBoundingClientRect();
      menuContent.style.top = `${rect.bottom + 5}px`;
      menuContent.style.left = `${rect.left - 200 + rect.width}px`; // Align right edge with icon
    }
  });
  
  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.layer-menu') && !e.target.closest('.layer-menu-content')) {
      document.querySelectorAll('.layer-menu-content.show').forEach(menu => {
        menu.classList.remove('show');
      });
    }
  });
  
  rightDiv.appendChild(menuIcon);
  rightDiv.appendChild(menuContent);

  li.appendChild(leftDiv);
  li.appendChild(rightDiv);
  document.getElementById('layerToggleList').appendChild(li);
});

// Initialize the map with data
try {
  console.log('Initializing map with data...');
  fetchDataAndBuildChart();
} catch (error) {
  console.error('Error initializing data:', error);
}


// Check for saved theme preference
document.addEventListener('DOMContentLoaded', () => {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    document.body.classList.add('dark-mode');
  }
});

// Update bookmark functionality with more debugging
let bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');

// Initialize bookmark functionality
document.addEventListener('DOMContentLoaded', () => {
  console.log('Initializing bookmark functionality');
  
  // Load bookmarks from localStorage
  try {
    const savedBookmarks = localStorage.getItem('bookmarks');
    console.log('Loaded bookmarks from localStorage:', savedBookmarks);
    if (savedBookmarks) {
      bookmarks = JSON.parse(savedBookmarks);
      console.log('Parsed bookmarks:', bookmarks);
    }
  } catch (error) {
    console.error('Error loading bookmarks:', error);
    bookmarks = [];
  }

  // Initialize bookmark list
  updateBookmarkList();
});

// Add click handler to get features from all layers
map.on('click', function(e) {
  tabbedPopup(e);
});

// SQL Query functionality
document.getElementById('executeQuery')?.addEventListener('click', function() {
    const query = document.getElementById('sqlQuery').value;
    const databaseType = document.getElementById('databaseType').value;
    
    if (!query) {
        document.getElementById('queryError').textContent = 'Please enter a query';
        document.getElementById('queryError').style.display = 'block';
        return;
    }

    // Show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Executing...';
    document.getElementById('queryError').style.display = 'none';

    // Send the query to the server
    fetch('../../admin/action/execute_query.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${layerId}&query=${encodeURIComponent(query)}&databaseType=${encodeURIComponent(databaseType)}`
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        this.disabled = false;
        this.innerHTML = 'Execute Query';

        if (data.error) {
            document.getElementById('queryError').textContent = data.error;
            document.getElementById('queryError').style.display = 'block';
            return;
        }

        // Display results
        const thead = document.getElementById('queryResultsHeader');
        const tbody = document.getElementById('queryResultsBody');
        
        // Clear previous results
        thead.innerHTML = '';
        tbody.innerHTML = '';

        // Create header row
        const headerRow = document.createElement('tr');
        data.columns.forEach(column => {
            const th = document.createElement('th');
            th.textContent = column;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);

        // Create data rows
        data.rows.forEach(row => {
            const tr = document.createElement('tr');
            data.columns.forEach(column => {
                const td = document.createElement('td');
                td.textContent = row[column] !== null ? row[column] : '';
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });

        // Show action buttons
        document.getElementById('openInModal').style.display = 'inline-block';
        document.getElementById('exportResults').style.display = 'inline-block';
        document.getElementById('limitMapToResults').style.display = 'inline-block';
        document.getElementById('clearMapFromResults').style.display = 'inline-block';

        // Store the results for use in other functions
        window.lastQueryResults = data;

        // Also update the modal results
        const modalThead = document.getElementById('modalResultsHeader');
        const modalTbody = document.getElementById('modalResultsBody');
        
        // Clear previous results
        modalThead.innerHTML = '';
        modalTbody.innerHTML = '';

        // Create header row
        const modalHeaderRow = document.createElement('tr');
        data.columns.forEach(column => {
            const th = document.createElement('th');
            th.textContent = column;
            modalHeaderRow.appendChild(th);
        });
        modalThead.appendChild(modalHeaderRow);

        // Create data rows
        data.rows.forEach(row => {
            const tr = document.createElement('tr');
            data.columns.forEach(column => {
                const td = document.createElement('td');
                td.textContent = row[column] !== null ? row[column] : '';
                tr.appendChild(td);
            });
            modalTbody.appendChild(tr);
        });
        
        if(isSavedQueryClick){
          isSavedQueryClick = false;
          document.getElementById('limitMapToResults').click();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('queryError').textContent = 'An error occurred while executing the query';
        document.getElementById('queryError').style.display = 'block';
        this.disabled = false;
        this.innerHTML = 'Execute Query';
    });
});
