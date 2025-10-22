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

// Helper functions for QGIS relations
function _layerFromFeatureId(f){
  if (f && typeof f.id === 'string' && f.id.includes('.')) return f.id.split('.')[0];
  return f?.properties?._layerName || null;
}

// Pretty relations: table layout, hide noisy fields, human labels
// Pretty relations: table layout, hide noisy fields, human labels
function renderRelationsHTML(feature){
  const rels = Array.isArray(RELATIONS) ? RELATIONS : [];
  const parentLayer = _layerFromFeatureId(feature);
  if (!parentLayer) return '';

  const forParent = rels.filter(r => r.parent_layer === parentLayer);
  if (!forParent.length) return '';

  const props = feature.properties || {};

  // helpers local to this renderer
  const humanize = (s='') => s.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
  const isUuidish = v => typeof v === 'string' && /[0-9a-f-]{32,}/i.test(v);
  const isSystemKey = k => /^(fid|id|geom|geometry|apiary_uuid|field_uuid|uuid)$/i.test(k);
  const fmt = (v) => {
    if (v == null) return '';
    if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return new Date(v).toLocaleDateString();
    return v;
  };

  const sections = forParent.map(rel => {
    const realParentField = (window.resolveFieldName || (()=>{}))(parentLayer, rel.parent_field) || rel.parent_field;
    const parentRaw = props[realParentField] ?? props[rel.parent_field];
    const parentVal = (parentRaw === undefined || parentRaw === null) ? '' : String(parentRaw);

    const key = `${rel.parent_layer}|${rel.parent_field}|${rel.child_layer}|${rel.child_field}`;
    const idxMap = window.relationIndexes?.[key];
    const children = idxMap ? (idxMap.get(parentVal) || []) : [];

    // choose columns: prefer child_list_fields; else infer
    let columns = (rel.child_list_fields || '')
      .split(',').map(s => s.trim()).filter(Boolean);

    if (!columns.length && children.length) {
      const sample = children[0]?.properties || {};
      columns = Object.keys(sample)
        .filter(k => !isSystemKey(k) && !isUuidish(sample[k]))
        .slice(0, 6);
      if (!columns.length) columns = Object.keys(sample).slice(0, 4);
    }

    const rowsHtml = children.length ? children.map((cf, i) => {
      const cp = cf.properties || {};
      const tds = columns.map(col => {
        const v = fmt(cp[col]);
        return `<td title="${v ?? ''}">${v ?? ''}</td>`;
      }).join('');
      return `<tr><td class="rel-idx">${i+1}</td>${tds}</tr>`;
    }).join('') : `<tr><td class="text-muted" colspan="${Math.max(1, columns.length)+1}">No related records.</td></tr>`;

    return `
      <div class="relation-block">
        <div class="relation-title">
          <span><i class="fa fa-link me-1"></i>${rel.name || rel.child_layer}</span>
          <span class="badge bg-secondary">${children.length}</span>
        </div>
        <div class="relation-list collapsed">
          <div class="table-responsive rel-table-wrap">
            <table class="table table-sm rel-table mb-0">
              <thead>
                <tr>
                  <th class="rel-idx">#</th>
                  ${columns.map(c => `<th>${humanize(c)}</th>`).join('')}
                </tr>
              </thead>
              <tbody>${rowsHtml}</tbody>
            </table>
          </div>
        </div>
      </div>`;
  }).join('');

  return `<div class="popup-relations"><h6 class="mt-2 mb-1">Relations</h6>${sections}</div>`;
} // ? this brace closes the function

// Accordion with TOGGLE behavior (global, defined once)
document.addEventListener('click', (e) => {
  const title = e.target.closest('.relation-title');
  if (!title) return;

  const container = title.closest('.popup-relations');
  if (!container) return;

  const list = title.nextElementSibling;
  const wasOpen = list && !list.classList.contains('collapsed');

  container.querySelectorAll('.relation-list')
    .forEach(l => l.classList.add('collapsed'));

  if (!wasOpen && list) list.classList.remove('collapsed');
});







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
          
          // Pan map by fixed pixels so the visual center stays consistent
          const _isCollapsed = sidebar.classList.contains('collapsed');
          if (_isCollapsed) {
            // Sidebar just closed ? move right
            try { map.panBy([SIDEBAR_SHIFT_X, 0], { animate: true }); } catch (e) {}
          } else {
            // Sidebar just opened ? move left
            try { map.panBy([-SIDEBAR_SHIFT_X, 0], { animate: true }); } catch (e) {}
          }
break;
        case 'viewDataBtn':
        case 'viewDataBtnIcon':
          document.getElementById('sql-tab').click();
          break;
        case 'clearQueryBtn':
        case 'clearQueryBtnIcon':
          clearSavedQuery();
          break;
        case 'btn_update_filter':
          onSavedPropFilterChange();
          break;
        case 'btn_clear_filter':
          onSavedPropFilterClear();
          break;
        default:
          break;
      }
  });
});

document.addEventListener('change', function(event) {
    // Check if the clicked element is one of our buttons
    switch(event.target.id){
      case 'filter_op':
        onSavedPropFilterOpChange();
        break;
      default:
        break;
    }
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
    let u = layer?.url || WMS_SVC_URL;
    if(layerConfigs[index].filter_param){
      const delim = u.includes('?') ? '&' : '?';
      u += delim + 'FILTER=' + layerConfigs[index].filter_param;
    }
      
    
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
      
      // Create navigation-based popup content (keeping tab structure for Edit compatibility)
      if(features.length > 0){
      const popupContent = `
        <div class="popup-content">
          <div class="popup-navigation">
            <button class="nav-btn prev-btn" id="prev-btn" ${features.length <= 1 ? 'disabled' : ''}>
              <span class="nav-icon">‹</span>
            </button>
            <div class="nav-counter">
              <span class="nav-icon-list">☰</span>
              <span class="nav-text">1 of ${features.length}</span>
            </div>
            <button class="nav-btn next-btn" id="next-btn" ${features.length <= 1 ? 'disabled' : ''}>
              <span class="nav-icon">›</span>
            </button>
          </div>
          <ul class="nav nav-tabs" role="tablist" style="display: none;">
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
                    ${renderRelationsHTML(feature)}
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
        
        // Add navigation functionality
        setTimeout(() => {
          const popupElement = document.querySelector('.custom-popup');
          if (popupElement && features.length > 1) {
            let currentIndex = 0;
            const totalFeatures = features.length;
            
            const prevBtn = popupElement.querySelector('#prev-btn');
            const nextBtn = popupElement.querySelector('#next-btn');
            const navText = popupElement.querySelector('.nav-text');
            const tabPanes = popupElement.querySelectorAll('.tab-pane');
            const tabButtons = popupElement.querySelectorAll('.nav-link');
            
            function updateNavigation() {
              // Update counter
              navText.textContent = `${currentIndex + 1} of ${totalFeatures}`;
              
              // Show/hide tab panes
              tabPanes.forEach((pane, index) => {
                pane.classList.toggle('show', index === currentIndex);
                pane.classList.toggle('active', index === currentIndex);
              });
              
              // Update tab buttons
              tabButtons.forEach((btn, index) => {
                btn.classList.toggle('active', index === currentIndex);
                btn.setAttribute('aria-selected', index === currentIndex ? 'true' : 'false');
              });
              
              // Update button states
              prevBtn.disabled = currentIndex === 0;
              nextBtn.disabled = currentIndex === totalFeatures - 1;
            }
            
            // Add event listeners
            prevBtn.addEventListener('click', () => {
              if (currentIndex > 0) {
                currentIndex--;
                updateNavigation();
              }
            });
            
            nextBtn.addEventListener('click', () => {
              if (currentIndex < totalFeatures - 1) {
                currentIndex++;
                updateNavigation();
              }
            });
            
            // Initialize navigation state
            updateNavigation();
          }
        }, 100);
      }
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

// Make map globally accessible for basemap manager
window.map = map;

// Shift amount used when opening/closing the sidebar
const SIDEBAR_SHIFT_X = 300;


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
const sidebarWidth =
  (document.getElementById('sidebar')?.offsetWidth) ?? 360; // CSS says 360px
map.fitBounds(
  [[bbox.miny, bbox.minx],[bbox.maxy, bbox.maxx]],
  {
    paddingTopLeft: [sidebarWidth + 24, 12],
    paddingBottomRight: [12, 12]
  }
);

// Create a single base layer that we'll update
// Initialize basemap manager after map is ready
if (window.basemapManager) {
  console.log('Setting map in basemap manager...');
  window.basemapManager.setMap(map);
} else {
  console.log('Basemap manager not ready yet, will set map later');
  // Wait for basemap manager to be ready
  const checkBasemapManager = setInterval(() => {
    if (window.basemapManager) {
      console.log('Basemap manager ready, setting map...');
      window.basemapManager.setMap(map);
      clearInterval(checkBasemapManager);
    }
  }, 100);
}


// Define base layer URLs (kept for compatibility, but basemap switching is now handled by basemap_manager.js)
const baseLayerUrls = {
    carto: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    osm: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    esri: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
};

layerConfigs.forEach((cfg, idx) => {
  // Create WMS layer
  const wmsLayer = L.tileLayer.wms(WMS_SVC_URL, {
    layers: cfg.name,
    format: 'image/png',
    transparent: true,
    version: '1.1.0'
 }).setZIndex(100 + idx).addTo(map);
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

// NOTE: push relation child layers after map is initialized, since they have no geometry and are invisible
RELATIONS.forEach((rel, idx) => {
  if(layerConfigs.some(cfg => cfg.typename === rel.child_layer)){
    return;
  }
  let name = rel.child_layer;
  if(WMS_SVC_URL.startsWith('/mproxy')){
    const dotpos = layerConfigs[0].name.indexOf('.');
    if(dotpos >= 0){  // if layers are exposed
      name = layerConfigs[0].name.substr(0,dotpos);
    }else{
      //name = layerConfigs[0].name;
      return; // all features are in layerWfsFeatures[0]
    }
  }else{
	  overlayLayers[name] = null;
  }
  r = {'name': name, 'color':null, 'typename': rel.child_layer, 'label':null, 'filter':null};
  layerConfigs.push(r);
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

function filterValuesSetup() {
  function ensureChipsFromSelect() {
    const sel = document.getElementById('filter_values');
    const host = document.getElementById('filter_chips');
    if (!sel || !host) return;
    host.innerHTML = '';
    Array.from(sel.options).forEach((opt, idx) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'md-chip';
      chip.setAttribute('role', 'option');
      chip.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
      chip.dataset.value = opt.value;
      chip.dataset.index = String(idx);
      chip.innerHTML = '<span class="md-check">✓</span><span class="md-text"></span>';
      chip.querySelector('.md-text').textContent = opt.text;
      chip.addEventListener('click', () => {
        const nowSelected = chip.getAttribute('aria-selected') !== 'true';
        chip.setAttribute('aria-selected', nowSelected ? 'true' : 'false');
        opt.selected = nowSelected;
        opt.dispatchEvent(new Event('change', { bubbles: true }));
      });
      host.appendChild(chip);
    });
  }

  function filterChips(query) {
    const host = document.getElementById('filter_chips');
    if (!host) return;
    const q = (query || '').toLowerCase().trim();
    Array.from(host.children).forEach(chip => {
      const text = chip.querySelector('.md-text')?.textContent?.toLowerCase() || '';
      chip.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
  }

  function syncChipsFromSelectSelection() {
    const sel = document.getElementById('filter_values');
    const host = document.getElementById('filter_chips');
    if (!sel || !host) return;
    Array.from(sel.options).forEach((opt, idx) => {
      const chip = host.querySelector('[data-index="'+idx+'"]');
      if (chip) chip.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
    });
  }

  // Observe select mutations (options replaced dynamically by app code)
  const valuesSelect = document.getElementById('filter_values');
  if (valuesSelect) {
    const mo = new MutationObserver((mut) => {
      const optionChanged = mut.some(m => m.type === 'childList');
      if (optionChanged) ensureChipsFromSelect();
    });
    mo.observe(valuesSelect, { childList: true });
    valuesSelect.addEventListener('change', syncChipsFromSelectSelection);
  }

  // Hook up search field
  const search = document.getElementById('filter_values_search');
  if (search) {
    search.addEventListener('input', (e) => filterChips(e.target.value));
  }

  // Rebuild chips each time the modal opens and hide any enhancers for #filter_values
  const modal = document.getElementById('filter_modal');
  function hideEnhancersForValuesSelect() {
    const sel = document.getElementById('filter_values');
    if (!sel) return;
    ['previousElementSibling','nextElementSibling'].forEach(k => {
      const sib = sel[k];
      if (!sib) return;
      const cls = String(sib.className || '');
      if (/select2|bootstrap-select/.test(cls)) {
        sib.style.display = 'none';
        sib.style.visibility = 'hidden';
      }
    });
  }
  if (modal) {
    modal.addEventListener('shown.bs.modal', function() {
      ensureChipsFromSelect();
      hideEnhancersForValuesSelect();
    });
  }

  // "Clear" button: clear selection
  const clearBtn = document.getElementById('btn_clear_filter');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      const sel = document.getElementById('filter_values');
      if (!sel) return;
      Array.from(sel.options).forEach(o => { o.selected = false; });
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      ensureChipsFromSelect();
    });
  }

  // --- Filter indicator on the tab/trigger ---
  function getFilterState() {
    const sel = document.getElementById('filter_values');
    const selectedCount = sel ? Array.from(sel.options).filter(o => o.selected).length : 0;
    const prop = (document.getElementById('filter_property')?.value || '').trim();
    return { selectedCount, hasActive: selectedCount > 0 || !!prop };
  }

  function setFilterIndicator(active, count) {
    const triggers = document.querySelectorAll('[data-bs-target="#filter_modal"], [href="#filter_modal"]');
    triggers.forEach(el => {
      let badge = el.querySelector('.md-filter-badge');
      if (active) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'md-filter-badge';
          badge.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v2l-7 7v5l-4-2v-3L3 7V5z"></path></svg><span class="md-filter-count"></span>';
          el.appendChild(badge);
        }
        const cnt = badge.querySelector('.md-filter-count');
        if (cnt) cnt.textContent = String(count || 0);
        badge.style.display = '';
      } else if (badge) {
        badge.remove();
      }
    });
  }

  document.getElementById('btn_update_filter')?.addEventListener('click', () => {
    const st = getFilterState();
    setFilterIndicator(st.hasActive, st.selectedCount);
  });

  document.getElementById('btn_clear_filter')?.addEventListener('click', () => {
    setFilterIndicator(false, 0);
  });

  const initIndicator = () => {
    const st = getFilterState();
    setFilterIndicator(st.hasActive, st.selectedCount);
    hideEnhancersForValuesSelect();
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initIndicator);
  } else {
    initIndicator();
  }
};

// Inline the modal flow: when code calls showEditModal(featureId, featureData),
// we make the popup editable and POST using the same endpoint as the modal.


  // Map popup labels -> DB column names (adjust if your labels differ)
  function labelToDb(label) {
    var m = {
      'Beekeeper':'beekeeper',
      'Number of Boxes':'nbr_of_boxes',
      'Species of Bees':'bee_species',
      'Amount of Bees':'bee_amount',
      'Photo':'picture',
      'Kind of Disease':'kind_of_disease',
      'Yearly Harvest (kg)':'average_harvest',
      'Area mostly used':'area_id',
      'ID':'uuid',
      'Source':'source',
      'Quality':'quality',
      'X':'x','Y':'y','Z':'z',
      'Horizontal accuracy':'horizontal_accuracy',
      'Nb. of satellites':'nr_used_satellites',
      'Fix status':'fix_status_descr',
      'infected':'infected'
    };
    return m[label] || label.toLowerCase().replace(/\s+/g,'_');
  }
  function dbToLabel(db) {
    var r = {
      beekeeper:'Beekeeper',
      nbr_of_boxes:'Number of Boxes',
      bee_species:'Species of Bees',
      bee_amount:'Amount of Bees',
      picture:'Photo',
      kind_of_disease:'Kind of Disease',
      average_harvest:'Yearly Harvest (kg)',
      area_id:'Area mostly used',
      uuid:'ID',
      source:'Source',
      quality:'Quality',
      x:'X', y:'Y', z:'Z',
      horizontal_accuracy:'Horizontal accuracy',
      nr_used_satellites:'Nb. of satellites',
      fix_status_descr:'Fix status',
      infected:'infected'
    };
    return r[db] || db;
  }

  function extractOriginal(tabPane) {
    var out = {}, rows = tabPane.querySelectorAll('.popup-row');
    for (var i=0;i<rows.length;i++){
      var lab = rows[i].querySelector('.popup-label');
      var val = rows[i].querySelector('.popup-value');
      if (!lab || !val) continue;
      var label = (lab.textContent||'').replace(/:\s*$/,'').trim();
      var db = labelToDb(label);
      var t = (val.textContent||'').trim();
      out[db] = (t === '(NULL)') ? '' : t;
    }
    return out;
  }

  function makeEditable(tabPane) {
    var rows = tabPane.querySelectorAll('.popup-row');
    for (var i=0;i<rows.length;i++){
      var lab = rows[i].querySelector('.popup-label');
      var val = rows[i].querySelector('.popup-value');
      if (!lab || !val) continue;
      var label = (lab.textContent||'').replace(/:\s*$/,'').trim();
      // skip read-onlys
      if (/^(fid|uuid|geom|geometry)$/i.test(label)) continue;

      var db = labelToDb(label);
      var current = (val.textContent||'').trim();
      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control form-control-sm inline-edit-input';
      input.name = db;
      input.value = (current === '(NULL)') ? '' : current;
      val.innerHTML = ''; val.appendChild(input);
      rows[i].classList.add('editing');
    }
  }

  function restoreReadOnly(tabPane, original) {
    var rows = tabPane.querySelectorAll('.popup-row');
    for (var i=0;i<rows.length;i++){
      var lab = rows[i].querySelector('.popup-label');
      var val = rows[i].querySelector('.popup-value');
      var inp = val && val.querySelector('.inline-edit-input');
      if (!lab || !val || !inp) continue;
      var label = (lab.textContent||'').replace(/:\s*$/,'').trim();
      var db = labelToDb(label);
      val.textContent = (original[db] != null ? original[db] : '');
      rows[i].classList.remove('editing');
    }
  }

  function applyUpdates(tabPane, updates) {
    var rows = tabPane.querySelectorAll('.popup-row');
    for (var k in updates) if (Object.prototype.hasOwnProperty.call(updates,k)) {
      var label = dbToLabel(k);
      for (var i=0;i<rows.length;i++){
        var lab = rows[i].querySelector('.popup-label');
        var val = rows[i].querySelector('.popup-value');
        if (!lab || !val) continue;
        var ltxt = (lab.textContent||'').replace(/:\s*$/,'').trim();
        if (ltxt === label) {
          val.textContent = (updates[k] == null ? '' : String(updates[k]));
          rows[i].classList.remove('editing');
          break;
        }
      }
    }
  }

  // POST using the SAME endpoint as your modal: relative 'api/oapif_update.php'
  function quickSave(featureId, changes, tabPane) {
    var layer = String(featureId).split('.')[0]; // featureId is like "Apiary.34" from the modal flow
    var body = { collection:'auto', id:featureId, layer_id:layerId, updates:changes, layerHint:layer };

    fetch('../../admin/action/oapif_update.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'Accept':'application/json' },
      body: JSON.stringify(body)
    })
    .then(function(res){ return res.text().then(function(t){ return {ok:res.ok, ct:res.headers.get('content-type')||'', text:t};});})
    .then(function(r){
      if (!r.ok) { console.error('Save failed', r.text.slice(0,400)); alert('Save failed (HTTP). See console.'); return; }
      if (r.ct.indexOf('application/json') !== -1) {
        var data; try { data = JSON.parse(r.text); } catch(e){ data = null; }
        if (data && data.type==='Feature') { applyUpdates(tabPane, changes); finish(); return; }
        if (data && data.error) { alert('Error: '+data.error); return; }
      }
      if (/<TransactionResponse/i.test(r.text) && /<totalUpdated>\s*[1-9]/i.test(r.text)) { applyUpdates(tabPane, changes); finish(); return; }
      console.warn('Unexpected response:', r.text.slice(0,400)); alert('Save might not have applied. See console.');
      function finish(){ if (window.disableInlineEditing) window.disableInlineEditing(tabPane); }
    })
    .catch(function(err){ console.error('Fetch error', err); alert('Save failed: '+err.message); });
  }

  // Replace the modal opener with inline editing
function replaceModalOpener(){
  var origShow = window.showEditModal;
  window.showEditModal = function (featureId /*, featureData */) {
    // find the active popup pane
    var pane = document.querySelector('.leaflet-popup-content .tab-pane.active') ||
               document.querySelector('.leaflet-popup-content .tab-pane') ||
               document.querySelector('.leaflet-popup-content') ||
               document.querySelector('.custom-popup');
    if (!pane) return false;

    // snapshot originals and enter edit mode
    var original = extractOriginal(pane);
    window.currentEditData = { featureId: featureId, originalData: original };
    makeEditable(pane);

    // swap the button to Save/Cancel
    var editBtn = pane.querySelector('.edit-button');
    if (editBtn) {
      var wrap = document.createElement('div');
      wrap.className = 'd-flex gap-2 mt-2'; wrap.style.width='100%';

      var save = document.createElement('button');
      save.className = 'btn btn-success btn-sm flex-fill';
      save.innerHTML = 'Save';
      save.onclick = function(ev){
        ev.preventDefault();
        // collect changes vs original
        var inputs = pane.querySelectorAll('.inline-edit-input');
        var updates = {}, changed=false;
        for (var i=0;i<inputs.length;i++){
          var name = inputs[i].name;
          var now  = inputs[i].value;
          var before = original[name] != null ? original[name] : original[labelToDb(name)];
          if (String(now) !== String(before)) { updates[name] = (now === '' ? null : now); changed=true; }
        }
        if (!changed) { alert('No changes detected'); return; }
        quickSave(featureId, updates, pane);
      };

      var cancel = document.createElement('button');
      cancel.className = 'btn btn-secondary btn-sm flex-fill';
      cancel.innerHTML = 'Cancel';
      cancel.onclick = function(ev){ ev.preventDefault(); restoreReadOnly(pane, original); };

      editBtn.parentNode.replaceChild(wrap, editBtn);
      wrap.appendChild(save); wrap.appendChild(cancel);
    }

    // do NOT open any modal
    return false;
  };
}
