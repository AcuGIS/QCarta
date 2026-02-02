function load_select(id, name, arr){
	var obj = $('#' + id);
	if(arr.length === 0){
		return;
	}
	
	var opts = '';
	$.each(arr, function(x){
		opts += '<option value="' + arr[x] + '">' + arr[x] + '</option>' + "\n";
	});
	
	//change input to select
	obj.replaceWith(`<select class="form-select" id="`+ id + `" name="`+ name +`" multiple required>` + opts + `</select>`);
	$('#' + id).trigger('change');
}

function filter_match(f, prop_value){
  if((f.op == 'IN') && (!f.val.includes(prop_value)) ){
    return true;
  }else if((f.op == 'NOT IN') && (f.val.includes(prop_value)) ){
    return true;
  }else if((f.op == '<') && (f.val[0] >= prop_value)){
    return true;
  }else if((f.op == '<=') && (f.val[0] > prop_value)){
    return true;
  }else if((f.op == '>') && (f.val[0] <= prop_value)){
    return true;
  }else if((f.op == '>=') && (f.val[0] < prop_value)){
    return true;
  }else if((f.op == '=') && (f.val[0] != prop_value)){
    return true;
  }else if((f.op == '!=') && (f.val[0] == prop_value)){
    return true;
  }
  return false;
}

// ---------- Relations helpers (robust resolution) ----------
function normalizeLayerName(n){
  if (!n) return '';
  return String(n).toLowerCase().replace(/^public\./,'').replace(/^\"|\"$/g,'');
}
function getFeatureLayerName(feat){
  if (feat && typeof feat.id === 'string' && feat.id.includes('.')) return feat.id.split('.')[0];
  return feat?.properties?._layerName || null;
}

// Build once and reuse: normalized name -> layer index
let __layerNameIndex = null;
function buildLayerNameIndex(){
  const idx = new Map();
  (layerConfigs || []).forEach((cfg, i) => {
    const ns = normalizeLayerName(cfg.typename).split(',');
    ns.forEach((n1, index) => {
      if (n1) idx.set(n1, i);
    });
    const feats = layerWfsFeatures?.[i] || [];
    if (feats.length){
      const n2 = normalizeLayerName(getFeatureLayerName(feats[0]));
      if (n2 && !idx.has(n2)) idx.set(n2, i);
    }
  });
  __layerNameIndex = idx;
  return idx;
}
function getLayerIndexByAnyName(layerName){
  if (!__layerNameIndex) buildLayerNameIndex();
  const n = normalizeLayerName(layerName);
  if (__layerNameIndex.has(n)) return __layerNameIndex.get(n);
  // last resort: exact match on config array
  const exact = (layerConfigs || []).findIndex(l => l.name === layerName);
  return exact;
}

// Map QGIS field/alias -> actual GeoJSON property key (now robust to name mismatches)
function resolveFieldName(layerName, field) {
  if (!field) return field;
  try {
    // 1) FIELD_MAPS (lowercased alias/real -> real)
    const mmap = (window.FIELD_MAPS && window.FIELD_MAPS[layerName]) ? window.FIELD_MAPS[layerName] : null;
    const lc = field.toLowerCase();
    if (mmap && mmap[lc]) return mmap[lc];

    // 2) Probe the actual features from whichever index maps to this layer
    const layerIdx = getLayerIndexByAnyName(layerName);
    const feats = layerIdx >= 0 ? (layerWfsFeatures[layerIdx] || []) : [];
    if (feats.length && feats[0] && feats[0].properties) {
      const keys = Object.keys(feats[0].properties);
      const k = keys.find(k => k.toLowerCase() === lc);
      if (k) return k;
    }
  } catch (_) {}
  return field;
}
window.resolveFieldName = window.resolveFieldName || resolveFieldName;

// Build relation indexes from backend-provided RELATIONS
window.relationIndexes = {};
function buildRelationIndexesFromBackend(){
  window.relationIndexes = {};
  const rels = Array.isArray(RELATIONS) ? RELATIONS : [];
  if (!rels.length) return;

  // keep index fresh for current session
  buildLayerNameIndex();

  rels.forEach(rel => {
    // find child layer index even if names differ (schema prefix / quotes / shortname)
    const childIdx = getLayerIndexByAnyName(rel.child_layer);
    const childFeatures = (childIdx >= 0 ? (layerWfsFeatures[childIdx] || []) : []);
    const m = new Map();
    childFeatures.forEach(f => {
      const realChildField = resolveFieldName(rel.child_layer, rel.child_field);
      const raw = f?.properties?.[realChildField] ?? f?.properties?.[rel.child_field];
      if (raw === undefined || raw === null) return; // skip bad keys
      const key = String(raw);
      if (!key) return; // skip empty string to avoid collapsing buckets
      if (!m.has(key)) m.set(key, []);
      m.get(key).push(f);
    });
    const k = `${rel.parent_layer}|${rel.parent_field}|${rel.child_layer}|${rel.child_field}`;
    window.relationIndexes[k] = m;
  });
}

// Define essential functions
function updateChart(features, groupBy, valueField) {
  const bounds = map.getBounds();
  const counts = {};
  const visible = features.filter(f => {
    try {
      const geom = f.geometry;
      const coords = geom.type === 'Point' ? geom.coordinates : turf.centroid(f).geometry.coordinates;
      const latlng = L.latLng(coords[1], coords[0]);
      return bounds.contains(latlng);
    } catch {
      return false;
    }
  });

  visible.forEach(f => {
    const key = f.properties[groupBy] || 'Unknown';
    const val = parseFloat(f.properties[valueField]) || 1;
    counts[key] = (counts[key] || 0) + val;
  });

  const labels = Object.keys(counts);
  const values = Object.values(counts);

  const ctx = document.createElement("canvas");
  const container = document.getElementById("multiChartContainer");
  container.innerHTML = "";
  container.appendChild(ctx);

  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, {
    type: document.getElementById('chartType').value,
    data: {
      labels: labels,
      datasets: [{
        label: `Sum of ${valueField}`,
        data: values,
        backgroundColor: "rgba(75, 192, 192, 0.5)",
        borderColor: "rgba(75, 192, 192, 1)",
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      scales: document.getElementById('chartType').value === 'pie' ? {} : {
        x: { ticks: { maxRotation: 60, minRotation: 30 } },
        y: { beginAtZero: true }
      }
    }
  });
  isChartsDataDirty = false;
}






function populateDropdowns(properties, selectedGroup = null, selectedValue = null) {
  const keys = Object.keys(properties);
  const groupBy = document.getElementById('groupBy');
  const valueField = document.getElementById('valueField');
  
  groupBy.innerHTML = "";
  valueField.innerHTML = "";

  keys.forEach(k => {
    const groupOption = new Option(k, k);
    const valueOption = new Option(k, k);
    if (k === selectedGroup) groupOption.selected = true;
    if (k === selectedValue) valueOption.selected = true;
    groupBy.add(groupOption);
    valueField.add(valueOption);
  });

  if (!selectedGroup && keys.length > 0) {
    groupBy.value = keys.includes("pri_neigh") ? "pri_neigh" : keys[0];
  }
  if (!selectedValue && keys.length > 0) {
    valueField.value = keys.includes("pri_neigh") ? "pri_neigh" : keys[0];
  }
}

function createPlotlyChart(features) {
  if (!features || features.length === 0) {
    console.warn('No features available for Plotly chart');
    return;
  }

  const bounds = map.getBounds();
  const visibleFeatures = features.filter(f => {
    try {
      const geom = f.geometry;
      const coords = geom.type === 'Point' ? geom.coordinates : turf.centroid(f).geometry.coordinates;
      const latlng = L.latLng(coords[1], coords[0]);
      return bounds.contains(latlng);
    } catch {
      return false;
    }
  });

  const xField = document.getElementById('plotlyXField').value;
  const yField = document.getElementById('plotlyYField').value;
  const chartType = document.getElementById('plotlyChartType').value;

  const xValues = [];
  const yValues = [];

  visibleFeatures.forEach(feature => {
    if (feature.properties) {
      const xVal = feature.properties[xField];
      const yVal = feature.properties[yField];
      if (xVal !== undefined && yVal !== undefined) {
        xValues.push(xVal);
        yValues.push(typeof yVal === 'string' ? parseFloat(yVal) || 0 : yVal);
      }
    }
  });

  const data = [{
    x: xValues,
    y: yValues,
    type: chartType,
    mode: chartType === 'scatter' || chartType === 'line' ? 'markers' : undefined,
    marker: {
      size: 10,
      color: '#8ebad9'
    }
  }];

  const layout = {
    title: `${yField} by ${xField} (Visible Features)`,
    xaxis: {
      title: xField,
      tickangle: 45
    },
    yaxis: {
      title: yField
    },
    showlegend: false,
    height: 400,
    margin: {
      l: 50,
      r: 20,
      t: 50,
      b: 100
    },
    autosize: true
  };

  const config = {
    responsive: true,
    displayModeBar: false
  };

  try {
    Plotly.newPlot('plotlyChart', data, layout, config);
  } catch (error) {
    console.error('Error creating Plotly chart:', error);
  }
}

function populatePlotlyFields(properties) {
  const xField = document.getElementById('plotlyXField');
  const yField = document.getElementById('plotlyYField');
  const keys = Object.keys(properties);
  
  xField.innerHTML = '';
  yField.innerHTML = '';

  keys.forEach(k => {
    xField.add(new Option(k, k));
    yField.add(new Option(k, k));
  });

  if (keys.includes('pri_neigh')) {
    xField.value = 'pri_neigh';
  }
  if (keys.includes('shape_area')) {
    yField.value = 'shape_area';
  }
}

function reload_datatable(){
  layerConfigs.forEach((cfg, i) => {
    const layer = overlayLayers[cfg.name];
    if(layer && map.hasLayer(layer)){
      isDataTableDirty[i] = true; // make table dirty, if its visible, so we can reload it
      if(!isDataTableVisible(i)){
        console.log('reload_datatable: datatable' + i + ' is hidden, skipping reload, marking as dirty');
      }else{
        updateDataTable(i);
      }
    }else{
      isDataTableDirty[i] = false;
    }
  });
}

function updateDataTable(li) {
  const features = layerWfsFeatures[li];
  if(!isDataTableVisible(li)){
    console.log('updateDataTable: datatable' + li + ' is hidden, skipping update, marking as dirty');
    isDataTableDirty[li] = true;
    return;
  }
  
  if(!isDataTableDirty[li]){
    console.log('updateDataTable: datatable' + li + ' is not dirty, skipping update');
    return;
  }
  isDataTableDirty[li] = false;
  console.log('updateDataTable called with features:', features);

  const tbl   = document.getElementById("dataTable" + li);
  const thead = tbl.getElementsByTagName('thead')[0];
  const tbody = tbl.getElementsByTagName('tbody')[0];
  
  if (!features || !Array.isArray(features) || features.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3">No data available</td></tr>';
    return;
  }
  

  // Get current filter state
  const checkedIndices = new Set();
  tbody.querySelectorAll('.row-checkbox:checked').forEach(cb => {
    checkedIndices.add(parseInt(cb.dataset.index));
  });
  const selectAllChecked = document.getElementById('selectAll')?.checked || false;
  const isFiltered = filteredFeatures.length > 0;

  const bounds = map.getBounds();
  const visibleFeatures = features.filter(f => {
    try {
      if (!f || !f.geometry) {
        console.warn('Invalid feature:', f);
        return false;
      }
      const geom = f.geometry;
      const coords = geom.type === 'Point' ? geom.coordinates : turf.centroid(f).geometry.coordinates;
      const latlng = L.latLng(coords[1], coords[0]);
      return bounds.contains(latlng);
    } catch (error) {
      console.error('Error processing feature:', error, f);
      return false;
    }
  });

  if (!visibleFeatures.length || !visibleFeatures[0].properties) {
    tbody.innerHTML = '<tr><td colspan="3">No visible features</td></tr>';
    console.warn('No visible features with properties');
    return;
  }
  
  const keys = Object.keys(visibleFeatures[0].properties);
  thead.innerHTML = '<tr><th><input type="checkbox" id="selectAll" class="form-check-input"></th>' + 
                      keys.map(k => `<th>${k}</th>`).join('') + '<th>Zoom</th></tr>';
  
  tbody.innerHTML = visibleFeatures.map((f, i) => {
    if (!f || !f.properties) {
      console.warn('Invalid feature at index', i, f);
      return '';
    }
    
    if(layerConfigs[li].filter){
      for (const [prop_k, ov] of Object.entries(layerConfigs[li].filter)) {
        if(filter_match(ov, f.properties[prop_k])){
          return '';
        }
      }
    }
    const row = keys.map(k => `<td>${f.properties[k]}</td>`).join('');
    const zoomBtn = `<button class='btn btn-sm btn-outline-primary' onclick='zoomToRowFeature(${li}, ${i})'>Zoom</button>`;
    const isChecked = checkedIndices.has(i) ? 'checked' : '';
    const shouldShow = isFiltered ? filteredFeatures.includes(f) : true;
    return `<tr style="display: ${shouldShow ? '' : 'none'}"><td><input type="checkbox" class="form-check-input row-checkbox" data-index="${i}" ${isChecked}></td>${row}<td>${zoomBtn}</td></tr>`;
  }).join('');

  const selectAll = thead.getElementsByTagName('input')[0]; // get selectAll
  if (selectAll) {
    selectAll.checked = selectAllChecked;
  }

  selectAll.addEventListener('change', function() {
    const checkboxes = tbody.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
  });
}

function zoomToRowFeature(li, index) {
  const f = layerWfsFeatures[li][index];
  zoomToFeature(f);
}

function isDataTableVisible(table_index) {
  const tbl_tab = document.getElementById("tab-table" + table_index);
  return tbl_tab && tbl_tab.classList.contains('active');
}

function isChartsTabVisible(){
  return $('#charts-tab').attr('aria-selected') == 'true';
}

function isPlotlyTabVisible(){
  return $('#plotly-tab').attr('aria-selected') == 'true';
}

function toggleDataTable() {
  const panel = document.getElementById("dataTablePanel");
  panel.style.display = panel.style.display === "none" ? "block" : "none";
  
  if (panel.style.display === "block") {
    reload_datatable();
    if(!document.getElementById("filterControls")){
      const headerDiv = panel.querySelector('.d-flex');
      if (headerDiv) {
        headerDiv.innerHTML += `
          <div id="filterControls" class="ms-2">
            <button class="btn btn-sm btn-primary" onclick="applyFilter()">Filter</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilter()">Clear</button>
          </div>
        `;
      }
    }
  }
}

function applyFilter() {
  const checkboxes = document.querySelectorAll('.row-checkbox:checked');
  const selectedIndices = Array.from(checkboxes).map(cb => parseInt(cb.dataset.index));
  
  if (selectedIndices.length === 0) {
    clearFilter();
    return;
  }

  filteredFeatures = selectedIndices.map(i => chartFeatures[i]);

  pointLayer.clearLayers();
  pointLayer.addData(filteredFeatures);

  pointLayer.setStyle({
    weight: 2,
    fillOpacity: 0.3,
    color: '#ff0000'
  });

  const tbody = document.getElementById("dataTableBody");
  const rows = tbody.getElementsByTagName('tr');
  for (let i = 0; i < rows.length; i++) {
    const checkbox = rows[i].querySelector('.row-checkbox');
    if (checkbox) {
      rows[i].style.display = checkbox.checked ? '' : 'none';
    }
  }
}

function clearFilter() {
  filteredFeatures = [];
  pointLayer.clearLayers();
  pointLayer.addData(chartFeatures);
  pointLayer.setStyle({
    weight: 1,
    fillOpacity: 0,
    color: '#000'
  });

  const tbody = document.getElementById("dataTableBody");
  const rows = tbody.getElementsByTagName('tr');
  for (let i = 0; i < rows.length; i++) {
    rows[i].style.display = '';
  }

  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.checked = false;
  }
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
}

// Add the missing loadChartConfig function
function loadChartConfig(configFile) {
  console.log('Loading chart config:', configFile);
  fetch('store_filep.php?f=' + configFile)
    .then(response => response.text())
    .then(xmlText => {
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(xmlText, "text/xml");
      
      // Extract configuration from XML
      const config = {
        title: xmlDoc.querySelector('Option[name="plot_layout"] Option[name="title"]')?.getAttribute('value') || '',
        xField: xmlDoc.querySelector('Option[name="x_field"]')?.getAttribute('value') || '',
        yField: xmlDoc.querySelector('Option[name="y_field"]')?.getAttribute('value') || '',
        chartType: xmlDoc.querySelector('Option[name="chart_type"]')?.getAttribute('value') || 'scatter'
      };
      
      console.log('Loaded chart config:', config);
      
      // Update UI with loaded configuration
      if (config.xField) {
        const xFieldSelect = document.getElementById('plotlyXField');
        if (xFieldSelect) xFieldSelect.value = config.xField;
      }
      
      if (config.yField) {
        const yFieldSelect = document.getElementById('plotlyYField');
        if (yFieldSelect) yFieldSelect.value = config.yField;
      }
      
      if (config.chartType) {
        const chartTypeSelect = document.getElementById('plotlyChartType');
        if (chartTypeSelect) chartTypeSelect.value = config.chartType;
      }
      
      // Update the chart with new configuration
      if (allFeatures && allFeatures.length > 0) {
        createPlotlyChart(allFeatures);
      }
    })
    .catch(error => {
      console.error('Error loading chart config:', error);
    });
}

function fetchDataAndBuildChart() {
  console.log('Starting data fetch...');
  console.log('Using layerId:', layerId);
  const urls = layerConfigs.map(cfg => 
    WMS_SVC_URL + `&layers=${encodeURIComponent(cfg.name)}&service=WFS&version=1.1.0&request=GetFeature&typeName=${encodeURIComponent(cfg.typename)}&OUTPUTFORMAT=application/json`
  );
  console.log('WFS URLs:', urls);

  Promise.all(urls.map(url => fetch(url).then(r => r.json())))
    .then(results => {
      console.log('WFS Results:', results);
      
      
      
      // Debug: Log each layer's features
      results.forEach((result, index) => {
        
        layerWfsFeatures[index] = Array.isArray(result?.features) ? result.features : [];
        
        console.log(`Layer ${layerConfigs[index].name}:`, {
          featureCount: result?.features?.length || 0,
          firstFeature: result?.features?.[0] || null,
          geometryType: result?.features?.[0]?.geometry?.type || 'none'
        });
      });

      // Build relation indexes now that features are loaded
      try { buildRelationIndexesFromBackend(); } catch (e) { console.warn('Relation index build failed:', e); }

      // Initialize Chart tab layer selector
      const layerSelect = document.getElementById("layerSelect");
      if(layerSelect){
        layerSelect.innerHTML = "";
        layerConfigs.forEach((cfg, i) => {
          const opt = document.createElement("option");
          opt.value = i;
          opt.text = cfg.label;
          layerSelect.appendChild(opt);
        });
        
        // Add event listeners for Chart controls
        layerSelect.addEventListener("change", updateChartTab);
        groupBy.addEventListener("change", updateChartTab);
        valueField.addEventListener("change", updateChartTab);
        chartType.addEventListener("change", updateChartTab);
        map.on("moveend", updateChartTab);
        
        // Update the chart tab to show the selected layer
        layerSelect.value = 0;
        updateChartTab();
      }

      const dtPanel = document.getElementById("dataTablePanel");
      if(dtPanel){
        map.on("moveend", reload_datatable);
      }

      // Initialize Plotly tab layer selector only if it exists
      const plotlyLayerSelect = document.getElementById("plotlyLayerSelect");
      if (plotlyLayerSelect) {
        plotlyLayerSelect.innerHTML = "";
        layerConfigs.forEach((cfg, i) => {
          if(cfg.label){
            const opt = document.createElement("option");
            opt.value = i;
            opt.text = cfg.label;
            plotlyLayerSelect.appendChild(opt);
          }
        });
        
        
        // Add event listeners for Plotly controls only if they exist
        plotlyLayerSelect.addEventListener("change", updatePlotlyTab);
        document.getElementById('plotlyConfig').addEventListener('change', function() {
          loadChartConfig(this.value);
        });
        document.getElementById('plotlyChartType').addEventListener('change', () => createPlotlyChart(allFeatures));
        document.getElementById('plotlyXField').addEventListener('change', () => createPlotlyChart(allFeatures));
        document.getElementById('plotlyYField').addEventListener('change', () => createPlotlyChart(allFeatures));
        // Add map moveend event for Plotly updates
        map.on("moveend", () => {
          if (document.getElementById('plotly-tab').classList.contains('active')) {
            createPlotlyChart(allFeatures);
          }
        });
        
        plotlyLayerSelect.value = 0;
        updatePlotlyTab();
        
        // Load initial chart configuration
        const xml_config = document.getElementById('plotlyConfig').value;
        loadChartConfig(xml_config);
      }

      // Add tab change event listener
      document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
          if (e.target.id === 'charts-tab') {
            updateChartTab();
          } else if (e.target.id === 'plotly-tab') {
            updatePlotlyTab();
          }
        });
      });

    })
    .catch(error => {
      console.error('Error fetching data:', error);
    });
}

// Function to update Chart tab
function updateChartTab() {
  if(!isChartsTabVisible()){
    console.log('updateChartTab: charts tab is hidden, skipping update, marking as dirty');
    isChartsDataDirty = true;
    return;
  }
  const selectedIndex = parseInt(layerSelect.value);
  console.log('updateChartTab - Selected layer index:', selectedIndex);
  
  const features = (layerConfigs[selectedIndex].filter) ? layerWfsFeatures[selectedIndex].filter(f => 
      {
        for (const [prop_k, ov] of Object.entries(layerConfigs[selectedIndex].filter)) {
          if(filter_match(ov, f.properties[prop_k])){
            return false;
          }
        }
        return true;
      }
    )
    : layerWfsFeatures[selectedIndex] || [];
  
  console.log('updateChartTab - Features for selected layer:', {
      count: features.length,
      firstFeature: features[0] || null,
      layerName: layerConfigs[selectedIndex]?.name
  });

  chartFeatures = features;  // Store features for Chart/Data Table
  
  if (features.length > 0 && features[0]?.properties) {
      console.log('updateChartTab - Updating dropdowns and chart with valid features');
      populateDropdowns(features[0].properties, groupBy.value, valueField.value);
      updateChart(features, groupBy.value, valueField.value);
  } else {
      console.warn('updateChartTab - No valid features available');
      // Clear or reset chart as needed
  }
  
  // Always call updateDataTable - it now has proper error handling
  updateDataTable(selectedIndex);
}

// Function to update Plotly tab
function updatePlotlyTab() {
  if(!isPlotlyTabVisible()){
    console.log('updatePlotlyTab: plotly tab is hidden, skipping update, marking as dirty');
    isPlotlyDataDirty = true;
    return;
  }
  const selectedIndex = parseInt(plotlyLayerSelect.value);
  const features = layerWfsFeatures[selectedIndex] || [];
  console.log('Selected features for plotly:', {
    layer: layerConfigs[selectedIndex].name,
    count: features.length,
    firstFeature: features[0] || null
  });
  allFeatures = features;  // Store features for Plotly
  if (features.length > 0) {
    populatePlotlyFields(features[0].properties);
    createPlotlyChart(features);
  } else {
    console.warn("No features for selected layer");
  }
  isPlotlyDataDirty = false;
}

// Function to show the Add Layer modal
function showAddLayerModal() {
  
  // clear select
  var select = document.getElementById("wmsLayers");
  var length = select.options.length;
  for (i = length-1; i >= 0; i--) {
    select.options[i] = null;
  }
  select.disabled = true;
  
  const modal = new bootstrap.Modal(document.getElementById('addLayerModal'));
  modal.show();
}

function parseWmsLayers(){
  const wmsUrl = document.getElementById('wmsUrl').value;
  
  fetch(wmsUrl + '?SERVICE=WMS&REQUEST=GetCapabilities').then(function (response) {
      return response.text();
  }).then(function (wms_xml) {
    const parser = new DOMParser();
    const dom = parser.parseFromString(wms_xml, 'application/xml');
    
    xpath = '//*[local-name()="Layer"][@queryable="1"][not(.//*[local-name()="Layer"])]/*[local-name()="Name"]';
    
    let targets = dom.evaluate(xpath, dom, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
    let layers = Array.from({ length: targets.snapshotLength }, (_, index) => targets.snapshotItem(index).textContent);
    layers.sort();
    
    let layerSelect = document.getElementById('wmsLayers');
    
    for(let i = 0; i < layers.length; i++) {
        let el = document.createElement("option");
        el.textContent = layers[i];
        el.value = layers[i];
        layerSelect.appendChild(el);
    }
    layerSelect.disabled = false;
  });
}

// Function to add a WMS layer
function addWmsLayer() {
  const wmsUrl = document.getElementById('wmsUrl').value;
  const layerName = Array.from(document.getElementById('wmsLayers').selectedOptions).map(({ value }) => value).join(',');
  
  if (!wmsUrl || !layerName) {
    alert('Please enter both WMS URL and Layer Name');
    return;
  }
  
  try {
    // Create the WMS layer
    const wmsLayer = L.tileLayer.wms(wmsUrl, {
      layers: layerName,
      format: 'image/png',
      transparent: true,
      version: '1.1.0'
    }).addTo(map);
    
    // Add to overlay layers
    overlayLayers[layerName] = wmsLayer;
    
    layerConfigs.push({"name": layerName, "color":"#000", "typename":layerName,"label":layerName, "url": wmsUrl});
    
    // Create layer item in the sidebar
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
        // Clear WFS features for this layer
        const features = pointLayer.getLayers().filter(layer => 
          layer.feature && layer.feature.properties && 
          layer.feature.properties._layerName === cfg.name
        );
        features.forEach(feature => pointLayer.removeLayer(feature));
        
        const polygonFeatures = polygonLayer.getLayers().filter(layer => 
          layer.feature && layer.feature.properties && 
          layer.feature.properties._layerName === cfg.name
        );
        polygonFeatures.forEach(feature => polygonLayer.removeLayer(feature));
        
        visibilityIcon.className = 'fas fa-eye-slash layer-visibility';
      } else {
        map.addLayer(wmsLayer);
        // Reload WFS features for this layer
        const wfsUrl = WFS_SVC_URL
          `&SERVICE=WFS&` +
          `VERSION=1.1.0&` +
          `REQUEST=GetFeature&` +
          `TYPENAME=${encodeURIComponent(cfg.name)}&` +
          `SRSNAME=EPSG:4326&` +
          `MAXFEATURES=1000&` +
          `OUTPUTFORMAT=application/json`;

        fetch(wfsUrl)
          .then(response => response.json())
          .then(data => {
            let layerIndex = layerConfigs.length - 1;
            if (data.features && data.features.length > 0) {
              layerWfsFeatures[layerIndex] = data.features;
              updateDataTable();
            }else{
              layerWfsFeatures[layerIndex] = null;
            }
          })
          .catch(error => console.error(`Error loading WFS features for ${cfg.name}:`, error));
        
        visibilityIcon.className = 'fas fa-eye layer-visibility';
      }
    });
    
    const nameSpan = document.createElement('span');
    nameSpan.textContent = layerName;
    
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
    
    // Remove layer option
    const removeDiv = document.createElement('div');
    removeDiv.className = 'layer-menu-item';
    removeDiv.innerHTML = '<i class="fas fa-trash"></i> Remove layer';
    removeDiv.addEventListener('click', () => {
      map.removeLayer(wmsLayer);
      li.remove();
      delete overlayLayers[layerName];
      menuContent.classList.remove('show');
    });
    
    menuContent.appendChild(opacityDiv);
    menuContent.appendChild(removeDiv);
    
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
        menuContent.style.left = `${rect.left - 200 + rect.width}px`;
      }
    });
    
    rightDiv.appendChild(menuIcon);
    rightDiv.appendChild(menuContent);
    
    li.appendChild(leftDiv);
    li.appendChild(rightDiv);
    document.getElementById('layerToggleList').appendChild(li);
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addLayerModal'));
    modal.hide();
    
    // Clear the form
    document.getElementById('wmsUrl').value = '';
    document.getElementById('wmsLayers').value = '';
    
  } catch (error) {
    console.error('Error adding WMS layer:', error);
    alert('Error adding WMS layer: ' + error.message);
  }
}

// Update theme switcher functionality
function toggleTheme() {
  const body = document.body;
  
  if (body.classList.contains('dark-mode')) {
    body.classList.remove('dark-mode');
    localStorage.setItem('theme', 'light');
  } else {
    body.classList.add('dark-mode');
    localStorage.setItem('theme', 'dark');
  }
}

// Update search functionality with more debugging
function searchFeatures(query) {
  console.log('Searching for:', query);
  const resultsDiv = document.querySelector('.search-results');
  
  if (!query) {
    resultsDiv.style.display = 'none';
    // remove any layers created during clicks on search results
    pointLayer.clearLayers();
    polygonLayer.clearLayers();
    return;
  }

  const results = [];
  
  console.log('Total layers to search:', layerWfsFeatures.length);

  layerWfsFeatures.forEach((features, layer_index) => {
    features.forEach((feature, feat_index) => {
      if (feature && feature.properties) {
        // Search through all properties
        const matches = Object.entries(feature.properties).some(([key, value]) => {
          return value && value.toString().toLowerCase().includes(query.toLowerCase());
        });
  
        if (matches) {
          results.push({
            layer_index: layer_index,
            feature_index: feat_index
          });
        }
      }
    });
  });

  console.log('Search results:', results);

  if (results.length > 0) {
    resultsDiv.innerHTML = results.map((result, index) => {
      const feature = layerWfsFeatures[result.layer_index][result.feature_index];
      console.log(feature);
      // Create a summary of the feature's properties
      const summary = Object.entries(feature.properties)
        .map(([key, value]) => `<strong>${key}:</strong> ${value}`)
        .join('<br>');
      
      return `
        <div class="search-result-item" onclick="zoomToSearchResult(${index})">
          ${summary}
        </div>
      `;
    }).join('');
    resultsDiv.style.display = 'block';

    // Store the results for use in zoomToSearchResult
    window.searchResults = results;
  } else {
    resultsDiv.innerHTML = '<div class="search-result-item">No results found</div>';
    resultsDiv.style.display = 'block';
  }
}


function zoomToSearchResult(index) {
  console.log('Zooming to search result:', index);
  const result = window.searchResults[index];
  if (result) {
    const feature = layerWfsFeatures[result.layer_index][result.feature_index];
    
    zoomToFeature(feature);
  }
}

function zoomToFeature(feature){
  pointLayer.clearLayers();
  polygonLayer.clearLayers();

  let layer = null;
  if (feature.geometry.type === 'Point') {
    layer = pointLayer.addData(feature);
  } else {
    layer = polygonLayer.addData(feature);
  }
  
  // Clear any existing highlights
  pointLayer.setStyle({
    radius: 8,
    fillColor: '#000',
    color: '#000',
    weight: 1,
    opacity: 0.3,
    fillOpacity: 0.1
  });
  
  polygonLayer.setStyle({
    weight: 1,
    fillOpacity: 0,
    color: '#000'
  });

  // Highlight the selected feature
  layer.setStyle({
    fillColor: '#ff7800',
    color: '#ff7800',
    weight: 2,
    opacity: 1,
    fillOpacity: 0.6
  });

  // Zoom to the feature
  try {
    const bounds = layer.getBounds();
    map.fitBounds(bounds, { padding: [50, 50] });
  } catch (e) {
    // For point features that don't have bounds
    const latLng = layer.getLatLng();
    map.setView(latLng, 15);
  }

  // Open the popup
  layer.openPopup();
}

function toggleBookmarkList() {
  console.log('Toggling bookmark list');
  const list = document.querySelector('.bookmark-list');
  const currentDisplay = list.style.display;
  list.style.display = currentDisplay === 'none' ? 'block' : 'none';
  console.log('Bookmark list display:', list.style.display);
  updateBookmarkList();
}

function updateBookmarkList() {
  console.log('Updating bookmark list:', bookmarks);
  const list = document.querySelector('.bookmark-list');
  if (bookmarks.length === 0) {
    list.innerHTML = '<div class="bookmark-item">No bookmarks saved</div>';
  } else {
    list.innerHTML = bookmarks.map((bookmark, index) => `
      <div class="bookmark-item">
        <div onclick="goToBookmark(${index})" style="cursor: pointer; flex-grow: 1;">
          ${bookmark.name}
        </div>
        <button onclick="deleteBookmark(${index})" class="btn btn-sm btn-danger">Delete</button>
      </div>
    `).join('');
  }
}

function addBookmark() {
  console.log('Adding bookmark');
  const name = prompt('Enter bookmark name:');
  if (name) {
    const center = map.getCenter();
    console.log('Current map center:', center);
    const bookmark = {
      name: name,
      center: [center.lat, center.lng],
      zoom: map.getZoom()
    };
    console.log('Saving bookmark:', bookmark);
    bookmarks.push(bookmark);
    try {
      localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
      console.log('Bookmarks saved to localStorage:', bookmarks);
      updateBookmarkList();
      // Show the bookmark list after saving
      const list = document.querySelector('.bookmark-list');
      list.style.display = 'block';
    } catch (error) {
      console.error('Error saving bookmark:', error);
    }
  }
}

function goToBookmark(index) {
  console.log('Going to bookmark:', index);
  const bookmark = bookmarks[index];
  console.log('Bookmark data:', bookmark);
  if (bookmark) {
    const center = L.latLng(bookmark.center[0], bookmark.center[1]);
    console.log('Setting view to:', center, 'zoom:', bookmark.zoom);
    map.setView(center, bookmark.zoom);
  }
}

function deleteBookmark(index) {
  console.log('Deleting bookmark:', index);
  bookmarks.splice(index, 1);
  try {
    localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
    console.log('Bookmarks after deletion:', bookmarks);
    updateBookmarkList();
  } catch (error) {
    console.error('Error deleting bookmark:', error);
  }
}

// Add export functionality
function exportToCSV(data) {
    // Get column headers
    const headers = data.columns;
    
    // Create CSV content
    let csvContent = headers.join(',') + '\n';
    
    // Add rows
    data.rows.forEach(row => {
        const rowData = headers.map(header => {
            const value = row[header];
            // Handle special characters and wrap in quotes if needed
            if (value === null || value === undefined) return '';
            const stringValue = String(value);
            if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
                return '"' + stringValue.replace(/"/g, '""') + '"';
            }
            return stringValue;
        });
        csvContent += rowData.join(',') + '\n';
    });
    
    // Create and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'sql_results.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add map limiting functionality
function limitMapToResults(data) {
    console.log('Limiting map to results:', data);
    
    // Get the first column name (assuming it's the ID or key column)
    const keyColumn = data.columns[0];
    console.log('Using key column:', keyColumn);
    
    // Create a Set of IDs from the results
    const resultIds = new Set(data.rows.map(row => row[keyColumn]));
    console.log('Result IDs:', Array.from(resultIds));
    
    let matchedFeatures = 0;
    let totalFeatures = 0;
    
    pointLayer.clearLayers();
    polygonLayer.clearLayers();
    
    // Filter the map features
    layerWfsFeatures.forEach((features, layer_index) => {

      // check if features have our keyColumn
      if (features[0].properties?.[keyColumn] !== undefined) {
        
        totalFeatures += features.length;
        
        features.forEach((feature, feat_index) => {
          if (feature && feature.properties) {
            
            featureId = feature.properties[keyColumn];
            console.log('Checking feature:', featureId, layerConfigs[layer_index].name);
            
            if (resultIds.has(featureId)) {
              matchedFeatures++;
              
              let layer = null;
              if (feature.geometry.type === 'Point') {
                layer = pointLayer.addData(feature);
              } else {
                layer = polygonLayer.addData(feature);
              }
              
              // Highlight the selected feature
              layer.setStyle({
                fillColor: '#000000',
                color: '#ff7800',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.6
              });
            }
          }
        });
      }
    });
    
    console.log(`Matched ${matchedFeatures} out of ${totalFeatures} features`);
    
    // Clear any existing highlights
    if(matchedFeatures > 0){

      
      const bounds = polygonLayer.getBounds().extend(pointLayer.getBounds());
      map.fitBounds(bounds, { padding: [50, 50] });
      console.log('Fitting map info: ' + bounds);
    }

    
    // Show a notification
    const notification = L.control({ position: 'topright' });
    notification.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'leaflet-control leaflet-bar');
        div.innerHTML = `<div class="alert alert-info" role="alert">
            Matched ${matchedFeatures} out of ${totalFeatures} features
        </div>`;
        return div;
    };
    notification.addTo(map);
    
    // Remove notification after 5 seconds
    setTimeout(() => {
        map.removeControl(notification);
    }, 5000);
}

function onSavedQueryClick(element){
  const name = element.getAttribute('data-name');
  const sqlEditor = document.querySelector('#sql textarea');
  sqlEditor.value = atob(element.getAttribute('data-sql'));
  
  const modalTitle = document.getElementById('sqlResultsModalLabel');
  modalTitle.textContent = name;
  
  // Set the database type
  const databaseTypeSelect = document.getElementById('databaseType');
  databaseTypeSelect.value = element.getAttribute('data-database_type');
  
  isSavedQueryClick = true;
  document.getElementById('executeQuery').click();
  
  // Show the clear and view data buttons
  document.getElementById('clearQueryBtn').style.display = 'block';
  document.getElementById('viewDataBtn').style.display = 'block';
  document.getElementById('openInModal2').style.display = 'block';
  document.getElementById('exportResults2').style.display = 'block';
}

function onSavedPropFilterClick(element){
  const filter_id = element.getAttribute('data-id');
  const feature = element.getAttribute('data-feature');
  const property = element.getAttribute('data-property');


// Point the badge at THIS saved filter item
  const modalEl = document.getElementById('filter_modal');
  if (modalEl) {
    modalEl.dataset.filterIndicatorTarget =
      '.saved_prop_filter[data-id="' + filter_id + '"]';
  }


 
  let values = [];
  let selected_ov = null;
  layerConfigs.forEach((cfg, layer_index) => {
    if(cfg.typename == feature){
      
      $.ajax({
					type: "GET",
					url: 'store_filep.php?f=fv_' + feature + '_' + property + '.json',
					success: function(values){
						if(typeof values[0] === 'string' || values[0] instanceof String){
             	values.sort();
            }else{
              values.sort(function(a, b){return a-b});
            }
        
            if(cfg.filter && cfg.filter[property]){
              selected_ov = cfg.filter[property];
            }
            
            document.getElementById('filter_property_p').textContent = property;
            
            document.getElementById('filter_feature').value = feature;
            document.getElementById('filter_property').value = property;
            
            load_select('filter_values', 'filter_values[]', values);
            if(selected_ov){
              $('#filter_op').val(selected_ov.op);
              $('#filter_values').val(selected_ov.val);
            }else{
              $('#filter_op').val('IN');
            }
            $('#filter_op').trigger('change');
          
            $('#filter_modal').modal('show');
					},
					error: function(XMLHttpRequest, textStatus, errorThrown) {
            alert("Error: " + errorThrown); 
					}
			});
    }
  });

}


// --- Saved Filter Badge (self-contained, per-target) ---
(function() {
  // Remember which saved filter button was clicked
  document.addEventListener('click', function(e) {
    const btn = e.target.closest && e.target.closest('.saved_prop_filter');
    if (btn) {
      window.__lastSavedFilterSel = '.saved_prop_filter[data-id="' + (btn.dataset.id || '') + '"]';
    }
  }, true);

  function removeBadgeForSelector(targetSelector) {
    if (!targetSelector) return;
    const el = document.querySelector(targetSelector);
    el?.querySelector('.md-filter-badge')?.remove();
  }

  function updateBadgeForSelector(targetSelector, count) {
    if (!targetSelector) return;
    const target = document.querySelector(targetSelector);
    if (!target) return;

    // If nothing selected, remove just this target's badge
    if (!count || count <= 0) {
      removeBadgeForSelector(targetSelector);
      return;
    }

    // Otherwise add/update this target's badge
    let badge = target.querySelector('.md-filter-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'md-filter-badge';
      badge.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v2l-7 7v5l-4-2v-3L3 7V5z"></path></svg><span class="md-filter-count"></span>';
      target.appendChild(badge);
    }
    const cnt = badge.querySelector('.md-filter-count');
    if (cnt) cnt.textContent = String(count);
  }

  function getSelectedValueCount() {
    // Prefer the chips (Material UI)
    const chips = document.querySelectorAll('#filter_chips .md-chip[aria-selected="true"]');
    if (chips.length) return chips.length;

    // jQuery multiselect fallback
    if (window.$) { 
      const arr = $('#filter_values').val() || [];
      return Array.isArray(arr) ? arr.length : 0;
    }

    // Plain select fallback
    const sel = document.getElementById('filter_values');
    return sel ? Array.from(sel.options).filter(o => o.selected).length : 0;
  }

  function currentTargetSelector() {
    return (document.getElementById('filter_modal')?.dataset?.filterIndicatorTarget) ||
           window.__lastSavedFilterSel;
  }

  // Delegated click: Update ? update/add badge only for THIS saved filter
  document.addEventListener('click', function(e) {
    if (!e.target.closest) return;
    if (e.target.closest('#btn_update_filter')) {
      setTimeout(function() {
        const targetSel = currentTargetSelector();
        const count = getSelectedValueCount();
        updateBadgeForSelector(targetSel, count);
      }, 0);
    }
  }, true);

  // Delegated click: Clear ? remove badge only for THIS saved filter
  document.addEventListener('click', function(e) {
    if (!e.target.closest) return;
    if (e.target.closest('#btn_clear_filter')) {
      const targetSel = currentTargetSelector();
      removeBadgeForSelector(targetSel);
    }
  }, true);
})();



function onSavedPropFilterChange(){
  const feature  = document.getElementById('filter_feature').value;
  const property = document.getElementById('filter_property').value;
  const op       = document.getElementById("filter_op").value;
  const values = [];
  
  
  const options = document.getElementById('filter_values').options;
  for (var i=0, iLen = options.length; i < iLen; i++) {  
    if (options[i].selected) {
      values.push(options[i].value);
    }
  }
  console.log(values);
  
  layerConfigs.forEach((cfg, layer_index) => {
    if(cfg.typename == feature){
      
      if(cfg.filter == null){
        cfg.filter = {};
      }
      
      if(values.length > 0){
        cfg.filter[property] = {'op': op, 'val': values};
      }else{
        delete cfg.filter[property];
      }
      
      updateDataTable(layer_index);
      updateChartTab();
      
      const url = (overlayLayers[cfg.name]._url.startsWith('/mproxy/service'))
          ? URL.parse(overlayLayers[cfg.name]._url, window.location.origin)
          : URL.parse(overlayLayers[cfg.name]._url, window.location.href.slice(0, -9));
      
      if(cfg.filter && (Object.keys(cfg.filter).length > 0)){
        let f_prop_values = [];
        for (const [k, f] of Object.entries(cfg.filter)) {
          let f_values = (typeof f.val[0] === 'string' || f.val[0] instanceof String) ? '\'' + f.val.join('\' , \'') + '\'' : f.val.join(' , ');
          if((f.op == 'IN') || (f.op == 'NOT IN')){
            f_prop_values.push('"' + k + '" ' + f.op +' ( ' + f_values + ' )');
          }else{
            f_prop_values.push('"' + k + '" ' + f.op + ' ' + f_values);
          }
        }
        cfg.filter_param = cfg.typename + ': ' + f_prop_values.join(' AND ');

        url.searchParams.set('FILTER', cfg.filter_param);
      }else{
        url.searchParams.delete('FILTER');
        cfg.filter = null;
        cfg.filter_param = null;
      }
      // refresh map layer URL
      overlayLayers[cfg.name].setUrl(url.toString());
      console.log(overlayLayers[cfg.name]._url);
    }
  });
  
  $('#filter_modal').modal('hide');
}

function onSavedPropFilterClear(){
  var elements = document.getElementById("filter_values").options;
  for(var i = 0; i < elements.length; i++){
    if(elements[i].selected){
      elements[i].selected = false;
    }
  }
  onSavedPropFilterChange();
}

function onSavedPropFilterOpChange(element){
  let op = document.getElementById("filter_op").value;
  let sel = document.getElementById("filter_values");
  if((op == 'IN') || (op == 'NOT IN')){
    sel.setAttribute('multiple', true);
  }else{
    sel.removeAttribute('multiple');
  }
}

function onSavedReportClick(element){
  const name = element.getAttribute('data-name');
  const query = atob(element.getAttribute('data-sql'));
  const databaseType = element.getAttribute('data-database_type');
  
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

      // Store the results for use in other functions
      window.lastQueryResults = data;

      // Also update the modal results
      const modalTitle = document.getElementById('sqlResultsModalLabel');
      const modalThead = document.getElementById('modalResultsHeader');
      const modalTbody = document.getElementById('modalResultsBody');
      
      modalTitle.textContent = name;
      
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
      
      // hide limit to map button
      document.getElementById('limitMapToResultsModal').style.display = 'none';
      
      // Show results modal
      const modal = new bootstrap.Modal(document.getElementById('sqlResultsModal'));
      modal.show();
  })
  .catch(error => {
      console.error('Error:', error);
      document.getElementById('queryError').textContent = 'An error occurred while executing the query';
      document.getElementById('queryError').style.display = 'block';
      this.disabled = false;
      this.innerHTML = 'Execute Query';
  });
  
 
}

function clearSavedQuery() {
    // Clear the map layers
    pointLayer.clearLayers();
    polygonLayer.clearLayers();
    
    // Clear the SQL query textarea
    document.querySelector('#sql textarea').value = '';
    
    // Clear any query results
    document.getElementById('queryResultsHeader').innerHTML = '';
    document.getElementById('queryResultsBody').innerHTML = '';
    
    // Hide the clear and view data buttons
    document.getElementById('clearQueryBtn').style.display = 'none';
    document.getElementById('viewDataBtn').style.display = 'none';
    document.getElementById('openInModal2').style.display = 'none';
    document.getElementById('exportResults2').style.display = 'none';
    
    // Reset the map view to initial bbox
    map.fitBounds([
        [bbox.miny, bbox.minx],
        [bbox.maxy, bbox.maxx]
    ]);
    
    // Clear any active query styling
    const savedQueries = document.querySelectorAll('.saved_layer_query');
    savedQueries.forEach(query => query.classList.remove('active'));
}
