// Define global variables
const overlayLayers = {};     // Create WMS / tile overlay layers

/** When true, map draws QGIS overlays via /api/tiles; WMS_SVC_URL remains for GetFeatureInfo, WFS, search. */
const USE_TILES = true;

function layerIdToQgisPath(layerId) {
  return QGIS_MAP_PATH;
}
const layerWfsFeatures = [];  // Store WFS feature references for each layer

let allFeatures = [];
let chartFeatures = [];
let chartInstance = null;
let filteredFeatures = [];
let isDataTableDirty = [];
let isChartsDataDirty = true;
let isPlotlyDataDirty = true;
let isSavedQueryClick = false;

let activeEditLayer = null;
let activeEditFeatureId = null;

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

  return `
<div class="qc-section qc-collapsible popup-relations">
  <div class="qc-collapsible-header">
    <span><i class="fas fa-project-diagram"></i> Relations</span>
    <span class="qc-toggle">▶</span>
  </div>
  <div class="qc-collapsible-body collapsed">
    ${sections}
  </div>
</div>`;
}

/** Plotly host per tab pane (class-based; legacy docked panel still uses #feature-plotly-chart). */
function renderChartHTML(_feature) {
  return `
<div class="qc-section qc-collapsible popup-chart qc-collapsible-chart">
  <div class="qc-collapsible-header">
    <span><i class="fa-solid fa-chart-line"></i> Chart</span>
    <span class="qc-toggle">▶</span>
  </div>
  <div class="qc-collapsible-body collapsed">
    <div class="qc-feature-plotly-chart" style="height:440px;min-height:440px;"></div>
    <div class="qc-feature-plotly-chart-message small text-muted" style="display:none;"></div>
  </div>
</div>`;
}

function qcResolveFeaturePlotlyHost() {
  const fc = document.getElementById('featureContent');
  if (fc) {
    const pane =
      fc.querySelector('.tab-pane.active') ||
      fc.querySelector('.tab-pane.show.active') ||
      fc.querySelector('.tab-pane.show') ||
      fc.querySelector('.tab-pane');
    if (pane) {
      const el = pane.querySelector('.qc-feature-plotly-chart');
      if (el) return el;
    }
  }
  return document.getElementById('feature-plotly-chart');
}

function qcResolveFeaturePlotlyMessage() {
  const fc = document.getElementById('featureContent');
  if (fc) {
    const pane =
      fc.querySelector('.tab-pane.active') ||
      fc.querySelector('.tab-pane.show.active') ||
      fc.querySelector('.tab-pane.show') ||
      fc.querySelector('.tab-pane');
    if (pane) {
      const el = pane.querySelector('.qc-feature-plotly-chart-message');
      if (el) return el;
    }
  }
  return document.getElementById('feature-plotly-chart-message');
}

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

// Collapsible sections (safe, isolated)
document.addEventListener('click', (e) => {
  const header = e.target.closest('.qc-collapsible-header');
  if (!header) return;

  const container = header.closest('.qc-collapsible');
  if (!container) return;
  const body = container.querySelector('.qc-collapsible-body');
  if (!body) return;
  const toggle = header.querySelector('.qc-toggle');

  const isCollapsed = body.classList.contains('collapsed');

  if (isCollapsed) {
    body.classList.remove('collapsed');
    body.style.display = '';
    if (toggle) toggle.textContent = '▼';
    // If a Plotly chart is inside a just-opened collapsible, force a layout pass.
    if (body.querySelector('.qc-feature-plotly-chart')) {
      setTimeout(() => {
        try {
          const plotHost = body.querySelector('.qc-feature-plotly-chart') || qcResolveFeaturePlotlyHost();
          if (!plotHost || typeof Plotly === 'undefined' || !Plotly.Plots || typeof Plotly.Plots.resize !== 'function') return;
          Plotly.Plots.resize(plotHost);
        } catch (err) {}
      }, 40);
    }
  } else {
    body.classList.add('collapsed');
    body.style.display = 'none';
    if (toggle) toggle.textContent = '▶';
  }
});







// Quiet routine script chatter on map pages (errors still use console.error where present).
var __qcConsoleLog = function () {};
console.log = function () {};

let qcSelectOutsideBound = false;

function initCustomSelects() {
  document.querySelectorAll('#sidebar select').forEach(select => {
    if (select.dataset.qcSelectInit === '1' || select.multiple || select.disabled) {
      return;
    }
    select.dataset.qcSelectInit = '1';

    select.style.display = 'none';

    const wrapper = document.createElement('div');
    wrapper.className = 'qc-select';

    const trigger = document.createElement('div');
    trigger.className = 'qc-select-trigger';
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('role', 'button');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const menu = document.createElement('div');
    menu.className = 'qc-select-menu';
    menu.setAttribute('role', 'listbox');

    function syncTriggerLabel() {
      const opt = select.options[select.selectedIndex];
      trigger.textContent = opt ? (opt.text || opt.value || '') : '';
    }

    function rebuildQcMenu() {
      menu.innerHTML = '';
      Array.from(select.options).forEach((option, idx) => {
        const item = document.createElement('div');
        item.className = 'qc-option';
        item.textContent = option.text || option.value || '';
        item.setAttribute('role', 'option');
        item.setAttribute('tabindex', '-1');

        item.addEventListener('click', () => {
          select.selectedIndex = idx;
          syncTriggerLabel();
          menu.classList.remove('open');
          trigger.setAttribute('aria-expanded', 'false');
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        menu.appendChild(item);
      });
    }

    let moScheduled = false;
    function onSelectOptionsMutated() {
      if (moScheduled) return;
      moScheduled = true;
      requestAnimationFrame(() => {
        moScheduled = false;
        rebuildQcMenu();
        syncTriggerLabel();
        menu.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      });
    }

    const optionObserver = new MutationObserver(onSelectOptionsMutated);
    optionObserver.observe(select, { childList: true, subtree: true });

    select.addEventListener('change', syncTriggerLabel);

    rebuildQcMenu();
    syncTriggerLabel();

    function openMenu() {
      document.querySelectorAll('#sidebar .qc-select-menu.open').forEach(m => {
        if (m !== menu) m.classList.remove('open');
      });
      document.querySelectorAll('#sidebar .qc-select-trigger').forEach(t => {
        if (t !== trigger) t.setAttribute('aria-expanded', 'false');
      });
      menu.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      menu.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (menu.classList.contains('open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    trigger.addEventListener('keydown', (e) => {
      const items = Array.from(menu.querySelectorAll('.qc-option'));
      if (e.key === 'Escape') {
        closeMenu();
        trigger.focus();
        return;
      }
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (menu.classList.contains('open')) {
          const focused = menu.querySelector('.qc-option:focus');
          if (focused) focused.click();
          else if (items[select.selectedIndex]) items[select.selectedIndex].click();
        } else {
          openMenu();
          if (items[select.selectedIndex]) items[select.selectedIndex].focus();
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!menu.classList.contains('open')) {
          openMenu();
          if (items[select.selectedIndex]) items[select.selectedIndex].focus();
        } else {
          let cur = items.indexOf(document.activeElement);
          if (cur < 0) cur = select.selectedIndex;
          const next = Math.min(cur + 1, items.length - 1);
          if (items[next]) items[next].focus();
        }
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!menu.classList.contains('open')) {
          openMenu();
          if (items[select.selectedIndex]) items[select.selectedIndex].focus();
        } else {
          let cur = items.indexOf(document.activeElement);
          if (cur < 0) cur = select.selectedIndex;
          const prev = Math.max(cur - 1, 0);
          if (items[prev]) items[prev].focus();
        }
        return;
      }
    });

    menu.addEventListener('keydown', (e) => {
      const items = Array.from(menu.querySelectorAll('.qc-option'));
      const cur = items.indexOf(document.activeElement);
      if (e.key === 'Escape') {
        e.preventDefault();
        closeMenu();
        trigger.focus();
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = Math.min(cur + 1, items.length - 1);
        if (items[next]) items[next].focus();
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = Math.max(cur - 1, 0);
        if (items[prev]) items[prev].focus();
        return;
      }
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (cur >= 0) items[cur].click();
        return;
      }
    });

    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);

    select.parentNode.insertBefore(wrapper, select.nextSibling);
  });

  if (!qcSelectOutsideBound) {
    qcSelectOutsideBound = true;
    document.addEventListener('click', (e) => {
      document.querySelectorAll('#sidebar .qc-select-menu').forEach(menu => {
        const wrap = menu.closest('.qc-select');
        if (wrap && !wrap.contains(e.target)) {
          menu.classList.remove('open');
          const tr = wrap.querySelector('.qc-select-trigger');
          if (tr) tr.setAttribute('aria-expanded', 'false');
        }
      });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('#sidebar .qc-select-menu.open').forEach(menu => {
        menu.classList.remove('open');
        const tr = menu.parentNode && menu.parentNode.querySelector('.qc-select-trigger');
        if (tr) tr.setAttribute('aria-expanded', 'false');
      });
    });
  }
}

function cleanValue(v) {
  if (Array.isArray(v)) return v[0]; // unwrap arrays
  if (typeof v === 'string') {
    // remove wrapping parentheses ONLY if entire string is wrapped
    if (v.startsWith('(') && v.endsWith(')')) {
      return v.slice(1, -1);
    }
  }
  return v;
}

document.addEventListener('DOMContentLoaded', function() {
  
  console.log('DOM loaded, attaching event listeners');

  initCustomSelects();

  // Use event delegation for the buttons
  document.addEventListener('click', function(event) {
      const t = event.target;
      // Property filter modal: match clicks on inner nodes (icons/text), not only event.target.id
      if (t && t.closest) {
        if (t.closest('#btn_update_filter')) {
          onSavedPropFilterChange();
          return;
        }
        if (t.closest('#btn_clear_filter')) {
          onSavedPropFilterClear();
          return;
        }
      }
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

async function fetchJSONSafe(url) {
  try {
    const res = await fetch(url);

    if (!res.ok) throw new Error(String(res.status));

    const text = await res.text();
    if (!text) throw new Error('Empty response');

    return JSON.parse(text);
  } catch (e) {
    console.warn('Popup fetch failed:', e);
    return null;
  }
}

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
  visibleLayers.forEach((cfg) => {
    const leafletLayer = overlayLayers[cfg.name];
    let u = leafletLayer?.url || WMS_SVC_URL;
    const filterParam = cfg.filter_param != null ? cfg.filter_param : cfg.filter;
    if (filterParam) {
      const delim = u.includes('?') ? '&' : '?';
      u += delim + 'FILTER=' + filterParam;
    }

    // Same plain name as L.tileLayer.wms({ layers: ... }) — not project prefix / alias label
    const plainLayerName = stripLayerPrefix(cfg.name);
    if (u in urlNames) {
      urlNames[u].push(plainLayerName);
    } else {
      urlNames[u] = [plainLayerName];
    }
  });

  const wmsGfiPlainLayers = [];
  for (const u in urlNames) {
    wmsGfiPlainLayers.push.apply(wmsGfiPlainLayers, urlNames[u]);
  }
  window.__qcLastGfiWmsPlainLayers = wmsGfiPlainLayers.slice();
  try {
    __qcConsoleLog(
      'WMS GetFeatureInfo LAYERS (plain names, decoded):',
      wmsGfiPlainLayers.join(', ')
    );
  } catch (e) {}

  // build query URLs with layer names joined to make a single query per URL
  let urls = [];
  for( var u in urlNames ) {
    const layers = urlNames[u].map(name => encodeURIComponent(name)).join(',');
    try {
      __qcConsoleLog(
        'WMS GetFeatureInfo LAYERS param (decoded for this request):',
        urlNames[u].join(',')
      );
    } catch (e2) {}

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
      `WITH_GEOMETRY=TRUE&` +
      `X=${Math.round(point.x)}&` +
      `Y=${Math.round(point.y)}&` +
      `FEATURE_COUNT=10&`;
      
      urls.push(url);
  }
  
  // make all requests and show popup
  (async function () {
    const results = await Promise.all(urls.map((url) => fetchJSONSafe(url)));
    let features = [];
    results.forEach((result) => {
      if (result && result.features) {
        features = features.concat(result.features);
      }
    });

      // Full GFI feature list + geometry index (ids/HTML ids often disagree; QGIS may omit geometry without WITH_GEOMETRY)
      window.__qcLastGfiFeatures = features;
      window.__qcPopupFeatureGeometries = Object.create(null);
      features.forEach(function (f, i) {
        var geom = f.geometry;
        if (!geom || !geom.type) return;
        function regKey(k) {
          if (k == null || k === '') return;
          window.__qcPopupFeatureGeometries[String(k)] = geom;
        }
        if (f.id != null && f.id !== '') regKey(f.id);
        var p = f.properties || {};
        if (p.fid != null && p.fid !== '') regKey(p.fid);
        if (p.FID != null && p.FID !== '') regKey(p.FID);
        regKey('__idx_' + i);
        if (
          typeof layerConfigs !== 'undefined' &&
          Array.isArray(layerConfigs) &&
          layerConfigs.length === 1 &&
          p.fid != null &&
          p.fid !== ''
        ) {
          regKey(String(layerConfigs[0].name) + '.' + p.fid);
        }
      });
      window.__qcLastPopupClickLatLng = e.latlng;
      
      // Create navigation-based popup content (keeping tab structure for Edit compatibility)
      if (!features.length) {
        qcCloseFeaturePanel();
        return;
      }

      if(features.length > 0){
      function qcPopupTabId(feature) {
        if (feature && feature.id) return String(feature.id);
        if (feature && feature.properties && feature.properties.fid != null) {
          return String(feature.properties.fid);
        }
        return '';
      }
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
            ${features.map((feature, index) => {
              const tabId = qcPopupTabId(feature) || '__idx_' + index;
              return `
              <li class="nav-item" role="presentation">
                <button class="nav-link ${index === 0 ? 'active' : ''}" 
                        id="popup-${tabId}-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#popup-${tabId}" 
                        type="button" 
                        role="tab"
                        aria-selected="${index === 0 ? 'true' : 'false'}">
                  ${tabId}
                </button>
              </li>
            `;
            }).join('')}
          </ul>
          <div class="tab-content">
            ${features.map((feature, index) => {
              const tabId = qcPopupTabId(feature) || '__idx_' + index;
              return `
              <div class="tab-pane fade ${index === 0 ? 'show active' : ''}" 
                   id="popup-${tabId}" 
                   role="tabpanel">
                  <div class="popup-section">
                    <div class="popup-body">
                      <div class="popup-inner">
                      ${Object.entries(feature.properties)
                        .filter(([key]) => key !== 'name' && key.toLowerCase() !== 'uuid')
                        .map(([key, value]) => {
                          value = cleanValue(value);
                          if((typeof value === 'string') && value.match(/DCIM\/.*\.(jpg|jpeg|png|webp|gif)/i)){
                            value = `<a href="img_filep.php?f=${value}" target="_blank"><img src="img_filep.php?f=${value}" alt="${value}" height="100"/></a>`;
                          }
                          return `
                          <div class="popup-row">
                            <span class="popup-label">${key}:</span>
                            <span class="popup-value">${value}</span>
                          </div>
                        `}).join('')}
                      ${
                        qcCanShowFeatureEditActions()
                          ? `
                      <div class="qc-feature-actions ${qcMapHasPostgisEditLayer() ? '' : 'single'}">
                        <button type="button" class="qc-action-btn edit-button">
                          <i class="fas fa-pen"></i>
                          <span>Edit Attributes</span>
                        </button>
                        ${
                          qcMapHasPostgisEditLayer()
                            ? `
                        <button type="button" class="qc-action-btn qc-geom-edit-trigger">
                          <i class="fas fa-draw-polygon"></i>
                          <span>Edit Geometry</span>
                        </button>`
                            : ''
                        }
                      </div>`
                          : ''
                      }
                    ${renderRelationsHTML(feature)}
                    ${renderChartHTML(feature)}
                      </div>
                    </div>
                  </div>
              </div>
            `;
            }).join('')}
          </div>
        </div>
      `;
  
        // Panel is the primary feature UI (WMS/GFI values); chart uses WFS (see initTabbedNavigation).
        renderFeatureInfoPanel(popupContent);

      }
  })().catch(function (error) {
    console.error('Error fetching data:', error);
  });
}

function initTabbedNavigation(container) {
  if (!container) return;
  const popupContent = container.classList && container.classList.contains('popup-content')
    ? container
    : container.querySelector('.popup-content') || container;
  const prevBtn = popupContent.querySelector('.prev-btn');
  const nextBtn = popupContent.querySelector('.next-btn');
  const navText = popupContent.querySelector('.nav-text');
  const tabPanes = popupContent.querySelectorAll('.tab-pane');
  const tabButtons = popupContent.querySelectorAll('.nav-link');
  if (!prevBtn || !nextBtn || !navText || !tabPanes.length) return;
  if (popupContent.dataset.qcNavInit === '1') return;
  popupContent.dataset.qcNavInit = '1';
  let currentIndex = 0;
  const totalFeatures = tabPanes.length;

  function updateNavigation() {
    navText.textContent = `${currentIndex + 1} of ${totalFeatures}`;
    tabPanes.forEach((pane, index) => {
      pane.classList.toggle('show', index === currentIndex);
      pane.classList.toggle('active', index === currentIndex);
    });
    tabButtons.forEach((btn, index) => {
      btn.classList.toggle('active', index === currentIndex);
      btn.setAttribute('aria-selected', index === currentIndex ? 'true' : 'false');
    });
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === totalFeatures - 1;

    const gfiList = window.__qcLastGfiFeatures;
    if (Array.isArray(gfiList) && gfiList[currentIndex]) {
      const f = gfiList[currentIndex];
      void handleFeatureClickForPanel(f, qcResolveLayerMetaForGfiFeature(f));
    }

  }

  prevBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    if (currentIndex > 0) {
      currentIndex--;
      updateNavigation();
    }
  });
  nextBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    if (currentIndex < totalFeatures - 1) {
      currentIndex++;
      updateNavigation();
    }
  });
  updateNavigation();
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

const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

let geometryDrawControl = new L.Control.Draw({
  draw: {
    polygon: true,
    rectangle: true,
    polyline: false,
    circle: false,
    marker: false
  },
  edit: {
    featureGroup: drawnItems
  }
});

let geometryDrawControlOnMap = null;
let geometryEditTargetLayer = null;

// ===============================
// OpenLayers Geometry Editor
// ===============================

/** Pinned build (same family as admin/pg-editor.php); loads on demand if the map HTML omitted ol.js */
var QC_OPENLAYERS_JS = 'https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js';
var QC_OPENLAYERS_CSS = 'https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css';

var _qcOlLoadPromise = null;

function ensureOpenLayersLoaded() {
  if (typeof ol !== 'undefined') {
    return Promise.resolve();
  }
  if (_qcOlLoadPromise) {
    return _qcOlLoadPromise;
  }
  _qcOlLoadPromise = new Promise(function (resolve, reject) {
    if (!document.querySelector('link[data-qc-ol-css]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = QC_OPENLAYERS_CSS;
      link.setAttribute('data-qc-ol-css', '1');
      document.head.appendChild(link);
    }
    function finishOk() {
      if (typeof ol !== 'undefined') {
        resolve();
      } else {
        _qcOlLoadPromise = null;
        reject(new Error('OpenLayers script ran but global `ol` is missing'));
      }
    }
    var existing = document.querySelector('script[data-qc-ol-loader]');
    if (existing) {
      var n = 0;
      var t = setInterval(function () {
        n++;
        if (typeof ol !== 'undefined') {
          clearInterval(t);
          finishOk();
        } else if (n > 600) {
          clearInterval(t);
          _qcOlLoadPromise = null;
          reject(new Error('OpenLayers load timeout'));
        }
      }, 50);
      return;
    }
    var s = document.createElement('script');
    s.src = QC_OPENLAYERS_JS;
    s.async = true;
    s.setAttribute('data-qc-ol-loader', '1');
    s.onload = finishOk;
    s.onerror = function () {
      _qcOlLoadPromise = null;
      reject(new Error('Failed to load OpenLayers'));
    };
    document.head.appendChild(s);
  });
  return _qcOlLoadPromise;
}

let olMap = null;
let olVectorSource = null;
let olVectorLayer = null;
let olModify = null;
let olTranslate = null;
let olDraw = null;
let olSelect = null;
let olFeature = null;
/** When true, map pan is off so Modify/Translate can grab polygon edges without DragPan winning. */
let qcOlLineDragMode = false;

/** WFS GeoJSON feature id vs UI feature id (layer.fid, composite id, etc.) */
function qcWfsJsonFeatureMatchesEditId(geojsonFeature, featureId) {
  var want = String(featureId || '');
  if (!want || !geojsonFeature) return false;
  var fid = geojsonFeature.id != null ? String(geojsonFeature.id) : '';
  if (fid === want) return true;
  var sufWant = want.indexOf('.') >= 0 ? want.slice(want.lastIndexOf('.') + 1) : want;
  if (fid && fid.indexOf('.') >= 0 && fid.slice(fid.lastIndexOf('.') + 1) === sufWant) return true;
  if (fid === sufWant) return true;
  var p = geojsonFeature.properties || {};
  var raw = p.fid != null ? String(p.fid) : p.FID != null ? String(p.FID) : '';
  if (raw && (raw === sufWant || want === raw || want === fid)) return true;
  if (raw && want.indexOf('.') >= 0 && want.slice(want.lastIndexOf('.') + 1) === raw) return true;
  return false;
}

function qcOlVectorStyleFunction(feature) {
  var geom = feature.getGeometry();
  if (!geom) {
    return [];
  }
  var t = geom.getType();
  var sel = feature.get('isSelected') === true;

  if (t === 'Point' || t === 'MultiPoint') {
    if (sel) {
      return new ol.style.Style({
        image: new ol.style.Circle({
          radius: 8,
          fill: new ol.style.Fill({ color: '#ff0000' }),
          stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
        })
      });
    }
    return new ol.style.Style({
      image: new ol.style.Circle({
        radius: 6,
        fill: new ol.style.Fill({ color: '#666666' }),
        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
      })
    });
  }

  if (t === 'LineString' || t === 'MultiLineString') {
    return new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: sel ? '#ff0000' : '#888888',
        width: sel ? 4 : 2
      })
    });
  }

  if (sel) {
    return new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: '#ff0000',
        width: 3
      }),
      fill: new ol.style.Fill({
        color: 'rgba(255,0,0,0.22)'
      })
    });
  }
  return new ol.style.Style({
    stroke: new ol.style.Stroke({
      color: '#777777',
      width: 2
    }),
    fill: new ol.style.Fill({
      color: 'rgba(80,80,80,0.15)'
    })
  });
}

function qcSuspendOlModifyTranslate() {
  if (!olMap) return;
  try {
    if (olModify) olMap.removeInteraction(olModify);
  } catch (e) {}
  try {
    if (olTranslate) olMap.removeInteraction(olTranslate);
  } catch (e) {}
}

function qcSetOlDragPanEnabled(enabled) {
  if (!olMap || typeof ol === 'undefined' || !ol.interaction.DragPan) return;
  olMap.getInteractions().forEach(function (ix) {
    if (ix instanceof ol.interaction.DragPan) {
      ix.setActive(!!enabled);
    }
  });
}

/**
 * Modify vertex handles (OL's default style function always uses a Point/circle, not segment lines).
 * Slightly larger circles so corners stay visible on top of the filled polygon.
 */
function qcOlModifyStyleFunction() {
  return new ol.style.Style({
    image: new ol.style.Circle({
      radius: qcOlLineDragMode ? 8 : 7,
      fill: new ol.style.Fill({ color: '#ffffff' }),
      stroke: new ol.style.Stroke({ color: '#c62828', width: 2 })
    })
  });
}

function qcRebuildOlVertexInteractions() {
  if (!olMap || !olVectorLayer) return;
  qcSuspendOlModifyTranslate();
  olModify = null;
  olTranslate = null;
  var modifyTol = qcOlLineDragMode ? 32 : 22;

  // OpenLayers runs interactions in reverse order of addition (last added = handled first).
  // Add Translate first, Modify second, so Modify sees pointer events first and vertices/edges
  // win over "move whole polygon". In Drag lines mode, omit Translate entirely (reshape only).
  if (!qcOlLineDragMode) {
    try {
      olTranslate = new ol.interaction.Translate({
        layers: [olVectorLayer],
        hitTolerance: 12,
        filter: function (feat) {
          return feat.get('isSelected') === true;
        }
      });
      olMap.addInteraction(olTranslate);
    } catch (e) {}
  }

  if (olFeature && olFeature.getGeometry()) {
    try {
      olModify = new ol.interaction.Modify({
        features: new ol.Collection([olFeature]),
        pixelTolerance: modifyTol,
        style: qcOlModifyStyleFunction
      });
      olMap.addInteraction(olModify);
    } catch (e) {}
  }
}

function qcResumeOlModifyTranslate() {
  qcRebuildOlVertexInteractions();
}

var _qcOlSingleClickKey = null;

function qcSyncOlDragLinesButton(btn) {
  if (!btn) return;
  if (qcOlLineDragMode) {
    btn.className = 'btn btn-success';
    btn.innerText = '✓ Drag lines';
    btn.title =
      'On: reshape only — drag white corner dots or click an edge to add a point. Whole-polygon move is off. Map pan is off; click again to pan and move the whole shape.';
  } else {
    btn.className = 'btn btn-outline-primary';
    btn.innerText = 'Drag lines';
    btn.title =
      'Turn on to edit corners and edges without map panning; whole-polygon drag is disabled in this mode. Drag polygon fill when this is off to move the entire shape.';
  }
}

function qcToggleOlLineDragMode() {
  qcOlLineDragMode = !qcOlLineDragMode;
  qcSetOlDragPanEnabled(!qcOlLineDragMode);
  qcSyncOlDragLinesButton(document.getElementById('ol-drag-lines'));
  qcRebuildOlVertexInteractions();
}

/** Pixel hit test, then geometry distance fallback so borders are easier to grab. */
function qcFindOlVectorFeatureNearPixel(evt) {
  var found = null;
  olMap.forEachFeatureAtPixel(
    evt.pixel,
    function (feat, layer) {
      if (layer === olVectorLayer) {
        found = feat;
        return true;
      }
    },
    {
      layerFilter: function (layer) {
        return layer === olVectorLayer;
      },
      hitTolerance: qcOlLineDragMode ? 28 : 22
    }
  );
  if (found) return found;
  var coord = evt.coordinate;
  if (!coord || !olVectorSource) return null;
  var res = olMap.getView().getResolution();
  if (res == null || !(res > 0)) return null;
  var maxDist = res * (qcOlLineDragMode ? 26 : 20);
  var maxD2 = maxDist * maxDist;
  var best = null;
  var bestD2 = Infinity;
  olVectorSource.getFeatures().forEach(function (feat) {
    var g = feat.getGeometry();
    if (!g || typeof g.getClosestPoint !== 'function') return;
    var close = g.getClosestPoint(coord);
    var dx = close[0] - coord[0];
    var dy = close[1] - coord[1];
    var d2 = dx * dx + dy * dy;
    if (d2 <= maxD2 && d2 < bestD2) {
      bestD2 = d2;
      best = feat;
    }
  });
  return best;
}

function qcUnbindOlFeatureClickSelect() {
  if (_qcOlSingleClickKey != null && typeof ol !== 'undefined' && ol.Observable) {
    try {
      ol.Observable.unByKey(_qcOlSingleClickKey);
    } catch (e) {}
  }
  _qcOlSingleClickKey = null;
}

function qcBindOlFeatureClickSelect() {
  if (!olMap || !olVectorLayer || !olVectorSource) return;
  qcUnbindOlFeatureClickSelect();
  _qcOlSingleClickKey = olMap.on('singleclick', function (evt) {
    var found = qcFindOlVectorFeatureNearPixel(evt);
    if (!found) return;
    olVectorSource.getFeatures().forEach(function (f) {
      f.set('isSelected', f === found);
    });
    olFeature = found;
    qcRebuildOlVertexInteractions();
    try {
      console.log('Clicked feature:', found.getId ? found.getId() : '(no id)');
    } catch (e) {}
  });
}

map.on(L.Draw.Event.EDITED, function (e) {
  console.log('Geometry edited:', e.layers);
});

function isPostgisLayer(layerName) {
  return !!(layerName && window.LAYER_PROVIDER_BY_NAME && window.LAYER_PROVIDER_BY_NAME[layerName] === 'postgres');
}

/** At least one layer in this map uses PostGIS (geometry edit is offered in the UI). */
function qcMapHasPostgisEditLayer() {
  if (window.LAYER_PROVIDER === 'postgres') return true;
  var by = window.LAYER_PROVIDER_BY_NAME;
  if (by && typeof by === 'object') {
    for (var k in by) {
      if (Object.prototype.hasOwnProperty.call(by, k) && by[k] === 'postgres') {
        return true;
      }
    }
  }
  return false;
}

function qcCanShowFeatureEditActions() {
  // Prefer "logged in" gate for popup Edit visibility.
  // Support both global consts and window-assigned flags.
  if (typeof QC_IS_LOGGED_IN !== 'undefined') {
    return QC_IS_LOGGED_IN === true;
  }
  if (typeof window.QC_IS_LOGGED_IN !== 'undefined') {
    return window.QC_IS_LOGGED_IN === true;
  }
  if (typeof QC_CAN_FEATURE_EDIT !== 'undefined') {
    return QC_CAN_FEATURE_EDIT === true;
  }
  if (typeof window.QC_CAN_FEATURE_EDIT !== 'undefined') {
    return window.QC_CAN_FEATURE_EDIT === true;
  }
  return false;
}

/**
 * Resolve WFS / popup feature id for the feature at pane index (or single-pane fallback).
 */
function qcResolveFeatureIdForGeometryEdit(paneIndex, feats, paneEl) {
  var featureId = '';
  var featsArr = feats || [];
  if (featsArr[paneIndex]) {
    if (typeof qcResolveFeatureEditId === 'function') {
      featureId = qcResolveFeatureEditId(featsArr[paneIndex]) || '';
    }
    if (!featureId && featsArr[paneIndex].id != null && featsArr[paneIndex].id !== '') {
      featureId = String(featsArr[paneIndex].id);
    }
    if (!featureId && featsArr[paneIndex].properties) {
      var pr = featsArr[paneIndex].properties;
      var rawFid = pr.fid != null ? pr.fid : pr.FID;
      if (rawFid != null && typeof layerConfigs !== 'undefined' && layerConfigs.length === 1) {
        featureId = String(layerConfigs[0].name) + '.' + rawFid;
      } else if (rawFid != null) {
        featureId = String(rawFid);
      }
    }
  }
  if (!featureId && paneEl && paneEl.id) {
    featureId = String(paneEl.id).replace(/^popup-/, '').replace(/-tab$/, '');
  }
  return featureId;
}

/**
 * Wire the docked panel "Edit geometry" control to startGeometryEdit for the active tab.
 */
function qcWireDockedGeometryEditButton(contentRoot) {
  if (!qcCanShowFeatureEditActions()) return;
  if (!contentRoot) return;
  var scope =
    contentRoot.classList && contentRoot.classList.contains('popup-content')
      ? contentRoot
      : contentRoot.querySelector('.popup-content') || contentRoot;
  var btns = Array.from(scope.querySelectorAll('.qc-geom-edit-trigger'));
  if (!btns.length && qcMapHasPostgisEditLayer()) {
    var foot = document.createElement('div');
    foot.className = 'qc-feature-geom-edit-footer border-top px-2 py-2 mt-1';
    foot.style.background = 'rgba(248,249,250,0.95)';
    foot.innerHTML =
      '<button type="button" class="qc-action-btn qc-geom-edit-trigger" style="width:100%;justify-content:center;">' +
      '<i class="fas fa-draw-polygon" aria-hidden="true"></i>' +
      '<span>Edit Geometry</span>' +
      '</button>';
    scope.appendChild(foot);
    btns = Array.from(scope.querySelectorAll('.qc-geom-edit-trigger'));
  }
  if (!btns.length) return;
  btns.forEach(function (btn) {
    if (!btn || btn.dataset.qcBound === '1') return;
    btn.dataset.qcBound = '1';
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var feats = window.__qcLastGfiFeatures || [];
      var pane =
        scope.querySelector('.tab-pane.active') || scope.querySelector('.tab-pane');
      var idx = 0;
      var tabPanes = scope.querySelectorAll('.tab-pane');
      if (pane && tabPanes.length) {
        for (var i = 0; i < tabPanes.length; i++) {
          if (tabPanes[i] === pane) {
            idx = i;
            break;
          }
        }
      } else if (!tabPanes.length && feats.length === 1) {
        idx = 0;
      }
      var featureId = qcResolveFeatureIdForGeometryEdit(idx, feats, pane);
      if (!featureId) {
        alert('Could not determine which feature to edit.');
        return;
      }
      var layerNm =
        typeof qcInferLayerNameForFeatureId === 'function'
          ? qcInferLayerNameForFeatureId(featureId)
          : '';
      if (
        !layerNm &&
        typeof layerConfigs !== 'undefined' &&
        Array.isArray(layerConfigs) &&
        layerConfigs.length === 1
      ) {
        layerNm = String(layerConfigs[0].name || '');
      }
      startGeometryEdit(featureId, layerNm);
    });
  });
}

try {
  window.qcWireDockedGeometryEditButton = qcWireDockedGeometryEditButton;
} catch (e) {}

/**
 * Wire per-tab "Edit" (attributes) buttons to window.showEditModal(featureId, {}).
 */
function qcWireFeaturePanelAttributeEditButtons(contentRoot) {
  if (!qcCanShowFeatureEditActions()) return;
  if (!contentRoot || typeof window.showEditModal !== 'function') return;
  var scope =
    contentRoot.classList && contentRoot.classList.contains('popup-content')
      ? contentRoot
      : contentRoot.querySelector('.popup-content') || contentRoot;
  var panes = scope.querySelectorAll('.tab-pane');
  var feats = window.__qcLastGfiFeatures || [];
  panes.forEach(function (pane, idx) {
    var btn = pane.querySelector('.edit-button');
    if (!btn || btn.dataset.qcAttrBound === '1') return;
    btn.dataset.qcAttrBound = '1';
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var featureId = qcResolveFeatureIdForGeometryEdit(idx, feats, pane);
      if (!featureId) {
        alert('Could not determine which feature to edit.');
        return;
      }
      window.showEditModal(featureId, {});
    });
  });
}

try {
  window.qcWireFeaturePanelAttributeEditButtons = qcWireFeaturePanelAttributeEditButtons;
} catch (e) {}

function removeGeometrySaveButton() {
  var btn = document.getElementById('qc-save-geometry');
  if (btn) btn.remove();
}

function endGeometryEditMode() {
  removeGeometrySaveButton();
  geometryEditTargetLayer = null;
  try {
    delete drawnItems._qcActiveFid;
  } catch (e) {}
  drawnItems.clearLayers();
  if (geometryDrawControlOnMap && map && typeof map.removeControl === 'function') {
    try { map.removeControl(geometryDrawControlOnMap); } catch (e) {}
  }
  geometryDrawControlOnMap = null;
}

function addGeometrySaveButton() {
  var existing = document.getElementById('qc-save-geometry');
  if (existing) existing.remove();

  var btn = document.createElement('button');
  btn.id = 'qc-save-geometry';
  btn.innerText = '💾 Save Geometry';
  btn.style.position = 'absolute';
  btn.style.top = '10px';
  btn.style.right = '10px';
  btn.style.zIndex = '10000';
  btn.className = 'btn btn-success btn-sm';
  btn.onclick = saveEditedGeometry;

  document.body.appendChild(btn);
}

function saveEditedGeometry() {
  var layers = drawnItems.getLayers();

  if (!layers.length) {
    alert('No geometry to save');
    return;
  }

  let geojson;

  if (drawnItems._qcGeometryType === 'MultiPolygon') {
    const coords = [];

    drawnItems.eachLayer(layer => {
      const gj = layer.toGeoJSON();

      // Polygon → wrap into MultiPolygon structure
      coords.push(gj.geometry.coordinates);
    });

    geojson = {
      type: 'Feature',
      geometry: {
        type: 'MultiPolygon',
        coordinates: coords
      },
      properties: {}
    };

  } else {
    const layer = drawnItems.getLayers()[0];
    geojson = layer.toGeoJSON();
  }

  var fid = drawnItems._qcActiveFid;

  if (!fid) {
    alert('Missing feature ID');
    return;
  }

  var layerName = String(fid).includes('.') ? String(fid).split('.')[0] : null;

  __qcConsoleLog('Saving geometry:', geojson);

  fetch('../../admin/action/oapif_update.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json'
    },
    body: JSON.stringify({
      collection: 'auto',
      id: fid,
      layer_id: typeof layerId !== 'undefined' ? layerId : null,
      layerHint: layerName,
      geometry: geojson.geometry
    })
  })
    .then(function (r) {
      return r.text().then(function (t) {
        return { ok: r.ok, text: t, ct: r.headers.get('content-type') || '' };
      });
    })
    .then(function (x) {
      var res = null;
      if (x.ct.indexOf('application/json') !== -1) {
        try {
          res = JSON.parse(x.text);
        } catch (e) {
          res = null;
        }
      }
      if (res && res.error) {
        alert(res.error);
        return;
      }
      if (!x.ok) {
        alert('Failed to save geometry');
        return;
      }
      __qcConsoleLog('Geometry saved');
      if (res && res.geometry) {
        __qcConsoleLog(
          'Geometry returned after save (_geometry_source=' +
            (res._geometry_source || '?') +
            '):',
          JSON.stringify(res.geometry)
        );
      } else if (res) {
        __qcConsoleLog('Save response had no geometry key; check Network → oapif_update JSON');
      }

      removeGeometrySaveButton();
      endGeometryEditMode();

      var stamp = Date.now();
      function qcRefreshWmsOverlay(layer) {
        if (!layer) return;
        if (typeof layer.setParams === 'function') {
          layer.setParams({ _ts: stamp, _cb: stamp });
        }
        if (typeof layer.redraw === 'function') {
          layer.redraw();
        }
      }
      if (typeof overlayLayers === 'object' && overlayLayers !== null) {
        Object.keys(overlayLayers).forEach(function (k) {
          qcRefreshWmsOverlay(overlayLayers[k]);
        });
      } else if (typeof map !== 'undefined' && map && typeof map.eachLayer === 'function') {
        map.eachLayer(qcRefreshWmsOverlay);
      }
      if (typeof fetchDataAndBuildChart === 'function') {
        fetchDataAndBuildChart();
      }
    })
    .catch(function (err) {
      try {
        console.error(err);
      } catch (e) {}
      alert('Failed to save geometry');
    });
}

async function fetchFeatureFromWFS(featureId, layerName) {
  var fidStr = String(featureId);
  var fid = fidStr.includes('.') ? fidStr.split('.').pop() : fidStr;
  var rawTypeName = String(layerName || '').trim();
  var wfsTypeName = stripLayerPrefix(rawTypeName);
  var lastWms = window.__qcLastGfiWmsPlainLayers;
  var inLastGfi =
    Array.isArray(lastWms) &&
    lastWms.some(function (n) {
      return String(n) === wfsTypeName;
    });
  try {
    __qcConsoleLog(
      'WFS GetFeature TYPENAME: ' +
        wfsTypeName +
        (rawTypeName !== wfsTypeName ? ' (from raw "' + rawTypeName + '")' : '') +
        ' | last WMS GFI LAYERS: ' +
        (Array.isArray(lastWms) ? lastWms.join(', ') : '(none)') +
        ' | same plain name as WMS: ' +
        (Array.isArray(lastWms) ? (inLastGfi ? 'yes' : 'NO') : 'unknown')
    );
  } catch (e) {}

  var base = typeof WMS_SVC_URL !== 'undefined' ? WMS_SVC_URL : '';
  var sep = base.indexOf('?') >= 0 ? '&' : '?';
  var url =
    base +
    sep +
    'SERVICE=WFS' +
    '&VERSION=1.1.0' +
    '&REQUEST=GetFeature' +
    '&TYPENAME=' +
    encodeURIComponent(wfsTypeName) +
    '&OUTPUTFORMAT=application/json' +
    '&FEATUREID=' +
    encodeURIComponent(String(wfsTypeName) + '.' + String(fid));

  var res = await fetch(url);
  if (!res.ok) {
    try {
      __qcConsoleLog('WFS GetFeature HTTP', res.status);
    } catch (e) {}
    return null;
  }
  var json;
  try {
    json = await res.json();
  } catch (e) {
    return null;
  }
  if (!json.features || !json.features.length) {
    try {
      __qcConsoleLog('WFS feature not found');
    } catch (e) {}
    return null;
  }
  return json.features[0];
}

async function fetchFeaturesFromWFS(layerName, bboxComma) {
  var rawTypeName = String(layerName || '').trim();
  var typeName = stripLayerPrefix(rawTypeName);
  var base = typeof WMS_SVC_URL !== 'undefined' ? WMS_SVC_URL : '';
  var sep = base.indexOf('?') >= 0 ? '&' : '?';
  var url =
    base +
    sep +
    'SERVICE=WFS' +
    '&VERSION=1.1.0' +
    '&REQUEST=GetFeature' +
    '&TYPENAME=' +
    encodeURIComponent(typeName) +
    '&OUTPUTFORMAT=application/json' +
    '&SRSNAME=EPSG:4326' +
    '&BBOX=' +
    encodeURIComponent(bboxComma) +
    '&MAXFEATURES=500';

  var res = await fetch(url);
  if (!res.ok) {
    try {
      __qcConsoleLog('WFS GetFeature (bbox) HTTP', res.status);
    } catch (e) {}
    return [];
  }
  var json;
  try {
    json = await res.json();
  } catch (e) {
    return [];
  }
  return json.features || [];
}

function cleanCoords(coords) {
  if (!Array.isArray(coords)) return [];

  return coords
    .filter(pt =>
      Array.isArray(pt) &&
      pt.length >= 2 &&
      pt[0] !== null &&
      pt[1] !== null &&
      !isNaN(pt[0]) &&
      !isNaN(pt[1])
    )
    .map(pt => [Number(pt[0]), Number(pt[1])]);
}

function dedupeConsecutivePoints(ring) {
  if (!Array.isArray(ring) || !ring.length) return [];
  const out = [ring[0]];
  for (let i = 1; i < ring.length; i++) {
    const prev = out[out.length - 1];
    const cur = ring[i];
    if (!prev || !cur) continue;
    if (prev[0] !== cur[0] || prev[1] !== cur[1]) {
      out.push(cur);
    }
  }
  return out;
}

function createEditableLayerFromGeoJSON(featureId, geom) {
  if (!geom || !geom.type) return null;

  let layer = null;

  // POINT
  if (geom.type === 'Point') {
    const latlng = L.latLng(geom.coordinates[1], geom.coordinates[0]);
    layer = L.marker(latlng, { draggable: true });
  }

  // POLYGON
  else if (geom.type === 'Polygon') {
    const latlngs = geom.coordinates
      .map(ring => dedupeConsecutivePoints(cleanCoords(ring)))
      .filter(ring => ring.length >= 3)
      .map(ring => {
        const first = ring[0];
        const last = ring[ring.length - 1];
        if (first[0] !== last[0] || first[1] !== last[1]) {
          ring.push([...first]);
        }
        return ring.map(coord => L.latLng(coord[1], coord[0]));
      });

    if (!latlngs.length) return null;

    layer = L.polygon(latlngs, {
      weight: 2,
      color: '#c62828',
      fillOpacity: 0.12
    });
  } else if (geom.type === 'MultiPolygon') {
    const layers = [];

    geom.coordinates.forEach((polyCoords, index) => {
      const latlngs = polyCoords.map(ring =>
        ring.map(coord => L.latLng(coord[1], coord[0]))
      );

      const poly = L.polygon(latlngs, {
        weight: 2,
        color: '#c62828',
        fillOpacity: 0.12
      });

      poly._qcPartIndex = index; // track part
      layers.push(poly);
    });

    return layers;
  } else {
    return null;
  }

  layer.feature = {
    type: 'Feature',
    id: featureId,
    geometry: geom,
    properties: {}
  };

  return layer;
}

async function initOLEditor() {
  try {
    await ensureOpenLayersLoaded();
  } catch (e) {
    try {
      console.error(e);
    } catch (e2) {}
    throw e;
  }

  let container = document.getElementById('ol-editor');

  if (!container) {
    container = document.createElement('div');
    container.id = 'ol-editor';
    container.style.position = 'absolute';
    container.style.top = '0';
    container.style.left = '0';
    container.style.width = '100%';
    container.style.height = '100%';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }
  container.style.background = 'transparent';
  container.style.pointerEvents = 'auto';
  document.body.style.overflow = 'hidden';

  olVectorSource = new ol.source.Vector();

  olVectorLayer = new ol.layer.Vector({
    source: olVectorSource,
    style: qcOlVectorStyleFunction
  });

  // OpenLayers XYZ does not understand Leaflet-only placeholders like {s} / {r}.
  // Normalize the active Leaflet basemap URL template for OL tile loading.
  /** OSM and most XYZ tiles only serve z≤19; Leaflet can use higher zoom — clamp to avoid 400s */
  var OL_OSM_MAX_ZOOM = 19;
  window._qcOlMaxZoom = OL_OSM_MAX_ZOOM;
  const rawBasemapUrl = window.currentBasemapUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const olBasemapUrl = rawBasemapUrl
    .replace(/\{s\}/g, 'a')
    .replace(/\{r\}/g, '');

  olMap = new ol.Map({
    target: 'ol-editor',
    layers: [
      new ol.layer.Tile({
        source: new ol.source.XYZ({
          url: olBasemapUrl,
          maxZoom: OL_OSM_MAX_ZOOM
        })
      }),
      olVectorLayer
    ],
    view: new ol.View({
      center: ol.proj.fromLonLat([0, 0]),
      zoom: 2,
      minZoom: 0,
      maxZoom: OL_OSM_MAX_ZOOM,
      constrainResolution: true
    })
  });

  const snap = new ol.interaction.Snap({
    source: olVectorSource
  });
  olMap.addInteraction(snap);
}

function enableOLDraw(type) {
  if (!olMap || !olVectorSource) return;

  if (olSelect) {
    olMap.removeInteraction(olSelect);
    olSelect = null;
  }

  if (olDraw) {
    olMap.removeInteraction(olDraw);
    olDraw = null;
    qcResumeOlModifyTranslate();
  }

  qcSuspendOlModifyTranslate();

  olDraw = new ol.interaction.Draw({
    source: olVectorSource,
    type: type
  });

  olDraw.on('drawend', function (evt) {
    if (evt && evt.feature) {
      evt.feature.set('isSelected', false);
      evt.feature.set('_qcUserDrawn', true);
    }
    if (olMap && olDraw) {
      olMap.removeInteraction(olDraw);
      olDraw = null;
    }
    qcResumeOlModifyTranslate();
  });

  olMap.addInteraction(olDraw);

  console.log('Draw mode:', type);
}

function enableOLDelete() {
  if (!olMap || !olVectorSource) return;

  if (olDraw) {
    olMap.removeInteraction(olDraw);
    olDraw = null;
    qcResumeOlModifyTranslate();
  }

  if (olSelect) {
    olMap.removeInteraction(olSelect);
  }

  olSelect = new ol.interaction.Select();

  olMap.addInteraction(olSelect);

  olSelect.on('select', function (e) {
    e.selected.forEach(function (feature) {
      olVectorSource.removeFeature(feature);
    });
  });

  console.log('Delete mode enabled');
}

async function startGeometryEdit(featureId, layerName) {

  if (!isPostgisLayer(layerName)) {
    alert('Geometry editing only allowed for PostGIS layers');
    return;
  }

  // Keep OL basemap aligned with active Leaflet basemap when possible.
  map.eachLayer(function (lyr) {
    if (lyr instanceof L.TileLayer && !lyr.wmsParams && lyr._url) {
      window.currentBasemapUrl = lyr._url;
    }
  });

  try {
    await initOLEditor();
  } catch (e) {
    alert('Could not load the geometry editor (OpenLayers). Check your connection and try again.');
    return;
  }

  const center = map.getCenter();
  var zoom = map.getZoom();
  var olMaxZ =
    typeof window._qcOlMaxZoom === 'number' ? window._qcOlMaxZoom : 19;
  if (zoom > olMaxZ) zoom = olMaxZ;

  olMap.getView().setCenter(
    ol.proj.fromLonLat([center.lng, center.lat])
  );

  olMap.getView().setZoom(zoom);

  olVectorSource.clear();
  olFeature = null;

  const bounds = map.getBounds();
  var bboxComma = [
    bounds.getWest(),
    bounds.getSouth(),
    bounds.getEast(),
    bounds.getNorth()
  ].join(',');

  var wfsFeatures = await fetchFeaturesFromWFS(layerName, bboxComma);

  if (!wfsFeatures.length) {
    var single = await fetchFeatureFromWFS(featureId, layerName);
    console.log('BBOX WFS empty; single feature:', single);
    if (!single || !single.geometry) {
      alert('Could not load geometry');
      return;
    }
    wfsFeatures = [single];
  } else {
    console.log('WFS features in view:', wfsFeatures.length);
  }

  const format = new ol.format.GeoJSON();

  var selectedFeature = null;

  wfsFeatures.forEach(function (f) {
    if (!f || !f.geometry) return;
    var olF = format.readFeature(f, {
      featureProjection: 'EPSG:3857'
    });
    olF.set('isSelected', false);
    if (qcWfsJsonFeatureMatchesEditId(f, featureId)) {
      olF.set('isSelected', true);
      selectedFeature = olF;
    }
    olVectorSource.addFeature(olF);
  });

  if (!selectedFeature) {
    var first = olVectorSource.getFeatures()[0];
    if (first) {
      first.set('isSelected', true);
      selectedFeature = first;
    }
  }

  olFeature = selectedFeature;

  if (!olFeature || !olFeature.getGeometry()) {
    alert('Could not load geometry');
    return;
  }

  qcRebuildOlVertexInteractions();
  qcBindOlFeatureClickSelect();

  var allOlFeats = olVectorSource.getFeatures();
  var fullExtent = olVectorSource.getExtent();
  var fitOpts = { padding: [50, 50, 50, 50], maxZoom: olMaxZ, duration: 0 };
  if (
    allOlFeats.length > 1 &&
    fullExtent &&
    typeof ol.extent !== 'undefined' &&
    typeof ol.extent.isEmpty === 'function' &&
    !ol.extent.isEmpty(fullExtent)
  ) {
    olMap.getView().fit(fullExtent, fitOpts);
  } else {
    olMap.getView().fit(olFeature.getGeometry().getExtent(), fitOpts);
  }

  addOLControls(featureId);

  console.log('OpenLayers edit mode enabled');
}

function addOLControls(featureId) {
  addOLSaveButton(featureId);
  addOLCancelButton();

  const addPointBtn = document.createElement('button');
  addPointBtn.id = 'ol-add-point';
  addPointBtn.innerText = '➕ Point';
  addPointBtn.className = 'btn btn-primary';
  addPointBtn.style.position = 'absolute';
  addPointBtn.style.top = '10px';
  addPointBtn.style.left = '10px';
  addPointBtn.style.zIndex = '10001';
  addPointBtn.onclick = function () {
    enableOLDraw('Point');
  };
  document.body.appendChild(addPointBtn);

  const addPolyBtn = document.createElement('button');
  addPolyBtn.id = 'ol-add-poly';
  addPolyBtn.innerText = '⬟ Polygon';
  addPolyBtn.className = 'btn btn-primary';
  addPolyBtn.style.position = 'absolute';
  addPolyBtn.style.top = '50px';
  addPolyBtn.style.left = '10px';
  addPolyBtn.style.zIndex = '10001';
  addPolyBtn.onclick = function () {
    enableOLDraw('Polygon');
  };
  document.body.appendChild(addPolyBtn);

  var dragLinesExisting = document.getElementById('ol-drag-lines');
  if (dragLinesExisting) dragLinesExisting.remove();

  const dragLinesBtn = document.createElement('button');
  dragLinesBtn.id = 'ol-drag-lines';
  dragLinesBtn.type = 'button';
  dragLinesBtn.style.position = 'absolute';
  dragLinesBtn.style.top = '90px';
  dragLinesBtn.style.left = '10px';
  dragLinesBtn.style.zIndex = '10001';
  dragLinesBtn.style.minWidth = '110px';
  dragLinesBtn.style.textAlign = 'center';
  dragLinesBtn.onclick = function () {
    qcToggleOlLineDragMode();
  };
  qcOlLineDragMode = false;
  qcSyncOlDragLinesButton(dragLinesBtn);
  document.body.appendChild(dragLinesBtn);

  const deleteBtn = document.createElement('button');
  deleteBtn.id = 'ol-delete';
  deleteBtn.innerText = '🗑 Delete';
  deleteBtn.className = 'btn btn-danger';
  deleteBtn.style.position = 'absolute';
  deleteBtn.style.top = '130px';
  deleteBtn.style.left = '10px';
  deleteBtn.style.zIndex = '10001';
  deleteBtn.onclick = enableOLDelete;
  document.body.appendChild(deleteBtn);
}

function addOLSaveButton(featureId) {

  let btn = document.getElementById('ol-save');

  if (btn) btn.remove();

  btn = document.createElement('button');
  btn.id = 'ol-save';
  btn.innerText = '💾 Save Geometry';
  btn.className = 'btn btn-success';
  btn.style.position = 'absolute';
  btn.style.top = '10px';
  btn.style.right = '10px';
  btn.style.zIndex = '10001';

  btn.onclick = async function() {

    const format = new ol.format.GeoJSON();

    var features = olVectorSource.getFeatures().filter(function (f) {
      return f.get('isSelected') === true || f.get('_qcUserDrawn') === true;
    });
    if (!features.length && olFeature) {
      features = [olFeature];
    }

    const geojson = {
      type: 'FeatureCollection',
      features: features.map(function (f) {
        return format.writeFeatureObject(f, {
          featureProjection: 'EPSG:3857',
          dataProjection: 'EPSG:4326'
        });
      })
    };

    console.log('Saving geometry:', geojson);

    const layerName = String(featureId).includes('.') ? String(featureId).split('.')[0] : null;

    const res = await fetch('../../admin/action/oapif_update.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        collection: 'auto',
        id: featureId,
        layer_id: typeof layerId !== 'undefined' ? layerId : null,
        layerHint: layerName,
        features: geojson.features
      })
    });

    let payload = null;
    try {
      payload = await res.json();
    } catch (e) {
      payload = null;
    }
    if (payload && payload.error) {
      alert(payload.error);
      return;
    }
    if (!res.ok) {
      alert('Failed to save geometry');
      return;
    }

    console.log('Geometry saved successfully');

    // Refresh Leaflet overlays immediately so edited geometry appears without page reload.
    const stamp = Date.now();
    function qcRefreshWmsOverlay(layer) {
      if (!layer) return;
      if (typeof layer.setParams === 'function') {
        layer.setParams({ _ts: stamp, _cb: stamp });
      }
      if (typeof layer.redraw === 'function') {
        layer.redraw();
      }
    }
    if (typeof overlayLayers === 'object' && overlayLayers !== null) {
      Object.keys(overlayLayers).forEach(function (k) {
        qcRefreshWmsOverlay(overlayLayers[k]);
      });
    } else if (typeof map !== 'undefined' && map && typeof map.eachLayer === 'function') {
      map.eachLayer(qcRefreshWmsOverlay);
    }
    if (typeof fetchDataAndBuildChart === 'function') {
      fetchDataAndBuildChart();
    }

    closeOLEditor();
  };

  document.body.appendChild(btn);
}

function addOLCancelButton() {
  let btn = document.getElementById('ol-cancel');
  if (btn) btn.remove();

  btn = document.createElement('button');
  btn.id = 'ol-cancel';
  btn.innerText = 'Cancel';
  btn.className = 'btn btn-secondary';
  btn.style.position = 'absolute';
  btn.style.top = '10px';
  btn.style.right = '140px';
  btn.style.zIndex = '10001';

  btn.onclick = closeOLEditor;

  document.body.appendChild(btn);
}

function closeOLEditor() {
  qcUnbindOlFeatureClickSelect();
  qcOlLineDragMode = false;
  if (olMap) {
    qcSetOlDragPanEnabled(true);
    try {
      if (olDraw) {
        olMap.removeInteraction(olDraw);
        olDraw = null;
      }
      if (olSelect) {
        olMap.removeInteraction(olSelect);
        olSelect = null;
      }
      if (olTranslate) {
        olMap.removeInteraction(olTranslate);
        olTranslate = null;
      }
      if (olModify) {
        olMap.removeInteraction(olModify);
        olModify = null;
      }
      olMap.setTarget(null);
    } catch (e) {}
    olMap = null;
  }
  olVectorSource = null;
  olVectorLayer = null;
  olModify = null;
  olTranslate = null;
  olFeature = null;

  const el = document.getElementById('ol-editor');
  if (el) el.remove();

  const btn = document.getElementById('ol-save');
  if (btn) btn.remove();
  const cancelBtn = document.getElementById('ol-cancel');
  if (cancelBtn) cancelBtn.remove();
  ['ol-add-point', 'ol-add-poly', 'ol-drag-lines', 'ol-delete'].forEach(function (id) {
    const b = document.getElementById(id);
    if (b) b.remove();
  });
  document.body.style.overflow = '';
}

window.startGeometryEdit = startGeometryEdit;

function detachPointEditDrag(layer) {
  if (!layer || !layer._qcPointEditHandlers) return;
  var h = layer._qcPointEditHandlers;
  layer.off('mousedown', h.down);
  var st = h._state;
  if (st && st.move) L.DomEvent.off(document, 'mousemove', st.move);
  if (st && st.up) {
    L.DomEvent.off(document, 'mouseup', st.up);
    L.DomEvent.off(document, 'mouseleave', st.up);
  }
  try {
    if (typeof map !== 'undefined' && map && map.dragging) map.dragging.enable();
  } catch (e) {}
  layer._qcPointEditHandlers = null;
}

function cleanupPriorPointEdit() {
  resetPointEditState();
}

function resetPointEditState() {
  if (activeEditLayer && typeof map !== 'undefined' && map) {
    detachPointEditDrag(activeEditLayer);
    try {
      if (activeEditLayer.dragging && activeEditLayer.dragging.enabled()) {
        activeEditLayer.dragging.disable();
      }
    } catch (e) {}
    if (activeEditLayer instanceof L.CircleMarker) {
      activeEditLayer.setStyle({
        color: '#000',
        fillColor: '#000',
        weight: 1,
        opacity: 0.3,
        fillOpacity: 0.1
      });
    }
    if (activeEditLayer._qcPointEditTemp) {
      try { map.removeLayer(activeEditLayer); } catch (e2) {}
    }
  }
  activeEditLayer = null;
  activeEditFeatureId = null;
}

function featureIdsMatch(layerFeatId, popupFeatureId, props) {
  var a = String(popupFeatureId);
  var b = layerFeatId == null ? '' : String(layerFeatId);
  if (a === b) return true;
  var asuf = a.indexOf('.') >= 0 ? a.slice(a.lastIndexOf('.') + 1) : a;
  var bsuf = b.indexOf('.') >= 0 ? b.slice(b.lastIndexOf('.') + 1) : b;
  if (asuf !== '' && asuf === bsuf) return true;
  if (props && props.fid != null) {
    var pf = String(props.fid);
    if (pf === a || pf === asuf) return true;
  }
  if (props && props.FID != null) {
    var pF = String(props.FID);
    if (pF === a || pF === asuf) return true;
  }
  if (props && props.uuid != null) {
    var u = String(props.uuid);
    if (u === a || u === asuf) return true;
  }
  return false;
}

/** Geometry from the last WMS GetFeatureInfo response (may be the only copy on the client). */
function getQcPopupGeometryForFeatureId(featureId) {
  var g = window.__qcPopupFeatureGeometries;
  if (!g || featureId == null || featureId === '') return null;
  var want = String(featureId);
  if (g[want]) return g[want];
  for (var key in g) {
    if (!Object.prototype.hasOwnProperty.call(g, key)) continue;
    if (key.indexOf('__idx_') === 0) continue;
    if (featureIdsMatch(key, want, {})) return g[key];
    var ks = key.indexOf('.') >= 0 ? key.slice(key.lastIndexOf('.') + 1) : key;
    var ws = want.indexOf('.') >= 0 ? want.slice(want.lastIndexOf('.') + 1) : want;
    if (ws !== '' && ws === ks) return g[key];
  }
  return null;
}

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
window.currentBasemapUrl = baseLayerUrls.carto;

// Helper function to strip project prefix (e.g., "Bee_Map.") from layer names
// QGIS expects plain layer names like "Tracks", not "Bee_Map.Tracks"
function stripLayerPrefix(layerName) {
  if (!layerName) return layerName;
  // Remove any prefix before the last dot (e.g., "Bee_Map.Tracks" -> "Tracks")
  const parts = layerName.split('.');
  return parts.length > 1 ? parts[parts.length - 1] : layerName;
}

function parsePlotlyConfigFromXml(xmlText) {
  const parser = new DOMParser();
  const xml = parser.parseFromString(xmlText, 'text/xml');

  function findOption(name) {
    const opts = xml.querySelectorAll('Option[name]');
    for (const o of opts) {
      if (o.getAttribute('name') === name) {
        return o.getAttribute('value');
      }
    }
    return null;
  }

  return {
    type: (findOption('plot_type') || 'scatter').toLowerCase(),
    title: findOption('title') || '',
    xField: findOption('x_name') || '',
    yField: findOption('y_name') || '',
    xTitle: findOption('x_title') || '',
    yTitle: findOption('y_title') || ''
  };
}

async function fetchRawFeatureByIdWfs({ wfsUrl, typeName, featureId }) {
  const url =
    `${wfsUrl}${wfsUrl.includes('?') ? '&' : '?'}service=WFS&version=1.1.0&request=GetFeature` +
    `&typeName=${encodeURIComponent(typeName)}` +
    `&outputFormat=application/json` +
    `&featureID=${encodeURIComponent(featureId)}`;

  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('WFS request failed');

  const geojson = await res.json();
  if (!geojson.features?.length) throw new Error('No feature returned');

  return geojson.features[0];
}

async function fetchRawFeatureByPkWfs({ wfsUrl, typeName, pkField, pkValue }) {
  const cql = `${pkField}='${String(pkValue).replace(/'/g, "''")}'`;

  const url =
    `${wfsUrl}${wfsUrl.includes('?') ? '&' : '?'}service=WFS&version=1.1.0&request=GetFeature` +
    `&typeName=${encodeURIComponent(typeName)}` +
    `&outputFormat=application/json` +
    `&CQL_FILTER=${encodeURIComponent(cql)}`;

  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('WFS request failed');

  const geojson = await res.json();
  if (!geojson.features?.length) throw new Error('No feature returned');

  return geojson.features[0];
}

async function loadAllFeaturesForLayer(layerCfg) {
  if (!layerCfg || layerCfg.skipWfsFetch) return;
  const wfsUrl =
    layerCfg.wfsUrl ||
    (typeof WFS_SVC_URL !== 'undefined' ? WFS_SVC_URL : null) ||
    (typeof WMS_SVC_URL !== 'undefined' ? WMS_SVC_URL : null);
  const typeName =
    layerCfg.wfsTypeName || stripLayerPrefix(layerCfg.name || layerCfg.typename || '');
  if (!wfsUrl || !typeName) {
    layerCfg.allFeatures = [];
    return;
  }
  const url =
    `${wfsUrl}${wfsUrl.includes('?') ? '&' : '?'}service=WFS&version=1.1.0&request=GetFeature` +
    `&typeName=${encodeURIComponent(typeName)}` +
    `&outputFormat=application/json`;

  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('WFS layer load failed');
    const geojson = await res.json();
    const feats = geojson.features || [];
    layerCfg.allFeatures = feats.map(function (f) {
      if (!f.rawProperties && f.properties) {
        f.rawProperties = f.properties;
      }
      return f;
    });
  } catch (e) {
    try {
      console.error(e);
    } catch (e2) {}
    layerCfg.allFeatures = [];
  }
}

function resolveClickedFeatureIdentity(feature) {
  const props = feature?.properties || {};

  return {
    featureId: feature?.id || props.fid || props.id || null,
    pkField: props.__pk_field || 'id',
    pkValue: props.id ?? props.fid ?? null
  };
}

function renderFeatureChartWithContext(plotTarget, allFeatures, selectedFeature, config) {
  const root =
    plotTarget && typeof plotTarget === 'object' && plotTarget.nodeType === 1
      ? plotTarget
      : typeof plotTarget === 'string' && plotTarget
        ? document.getElementById(plotTarget)
        : null;
  if (!root) return;

  const xField = config.xField;
  const yField = config.yField;

  const list = Array.isArray(allFeatures) ? allFeatures : [];
  const values = [];

  list.forEach(function (f) {
    const props = f.rawProperties || f.properties || {};
    const val = Number(props[yField]);
    if (!isNaN(val)) values.push(val);
  });

  const selectedProps = selectedFeature.rawProperties || selectedFeature.properties || {};
  const selectedVal = Number(selectedProps[yField]);

  if (isNaN(selectedVal) || values.length === 0) {
    if (root) root.innerHTML = '';
    return;
  }

  const sorted = values.slice().sort(function (a, b) {
    return b - a;
  });
  const rank = sorted.findIndex(function (v) {
    return v === selectedVal;
  }) + 1;
  const total = sorted.length;

  const percentile = ((total - rank) / total) * 100;

  const rankText = '#' + rank + ' of ' + total;
  const pctText = percentile.toFixed(1) + ' percentile';

  let data = [];
  const type = (config.type || 'scatter').toLowerCase();

  if (type === 'bar') {
    const counts = {};

    list.forEach(function (f) {
      const props = f.rawProperties || f.properties || {};
      const x = props[xField];
      const y = Number(props[yField]);

      if (x != null && !isNaN(y)) {
        counts[x] = (counts[x] || 0) + y;
      }
    });

    const categories = Object.keys(counts);
    const valuesY = categories.map(function (c) {
      return counts[c];
    });

    data = [
      {
        type: 'bar',
        x: categories,
        y: valuesY,
        name: 'All'
      },
      {
        type: 'bar',
        x: [selectedProps[xField]],
        y: [selectedVal],
        name: 'Selected',
        marker: { color: 'red' }
      }
    ];
  } else if (type === 'pie') {
    const sum = values.reduce(function (a, b) {
      return a + b;
    }, 0);

    data = [{
      type: 'pie',
      labels: ['Selected', 'Other'],
      values: [selectedVal, Math.max(sum - selectedVal, 0)],
      textinfo: 'label+percent'
    }];
  } else {
    const allX = [];
    const allY = [];

    list.forEach(function (f) {
      const props = f.rawProperties || f.properties || {};
      const x = props[xField];
      const y = Number(props[yField]);

      if (x != null && !isNaN(y)) {
        allX.push(x);
        allY.push(y);
      }
    });

    data = [
      {
        type: 'scatter',
        mode: 'markers',
        x: allX,
        y: allY,
        name: 'All',
        opacity: 0.3
      },
      {
        type: 'scatter',
        mode: 'markers',
        x: [selectedProps[xField]],
        y: [selectedVal],
        name: 'Selected',
        marker: { size: 12, color: 'red' }
      }
    ];
  }

  const annotationText = rankText + ' • ' + pctText;

  if (typeof Plotly === 'undefined' || typeof Plotly.newPlot !== 'function') {
    if (root) root.innerHTML = '';
    return;
  }

  const isBar = type === 'bar';
  const xaxis = {
    title: config.xTitle || xField,
    automargin: true
  };
  if (isBar) {
    xaxis.tickangle = -35;
  }

  const layout = {
    title: config.title || '',
    xaxis,
    yaxis: {
      title: config.yTitle || yField,
      automargin: true
    },
    margin: isBar
      ? { t: 72, r: 24, b: 140, l: 72 }
      : { t: 64, r: 20, b: 88, l: 64 },
    annotations: [
      {
        text: annotationText,
        xref: 'paper',
        yref: 'paper',
        x: 1,
        y: 1.15,
        showarrow: false,
        align: 'right',
        font: {
          size: 12,
          color: '#444'
        }
      }
    ]
  };

  Plotly.newPlot(root, data, layout, {
    responsive: true,
    displayModeBar: false
  });

  try {
    if (root && typeof Plotly !== 'undefined' && Plotly.Plots && typeof Plotly.Plots.resize === 'function') {
      Plotly.Plots.resize(root);
    }
  } catch (e) {}
}

function qcBuildFeaturePanelLayerMeta(cfg) {
  const wfsBase =
    (cfg && cfg.wfsUrl) ||
    (typeof WFS_SVC_URL !== 'undefined' ? WFS_SVC_URL : null) ||
    (typeof WMS_SVC_URL !== 'undefined' ? WMS_SVC_URL : null);
  if (!cfg) {
    return {
      wfsUrl: wfsBase,
      wfsTypeName: '',
      plotlyXmlText: null,
      chartType: null,
      xField: null,
      yField: null,
      pkField: null,
      allFeatures: [],
      _sourceCfg: null
    };
  }
  const typeName = cfg.wfsTypeName || stripLayerPrefix(cfg.name || cfg.typename || '');
  return {
    wfsUrl: wfsBase,
    wfsTypeName: typeName,
    plotlyXmlText: cfg.plotlyXmlText || null,
    chartType: cfg.chartType || null,
    xField: cfg.xField || null,
    yField: cfg.yField || null,
    pkField: cfg.pkField || null,
    allFeatures: Array.isArray(cfg.allFeatures) ? cfg.allFeatures : [],
    _sourceCfg: cfg
  };
}

function qcResolveLayerMetaForGfiFeature(feature) {
  if (typeof layerConfigs === 'undefined' || !Array.isArray(layerConfigs) || !layerConfigs.length) {
    return qcBuildFeaturePanelLayerMeta(null);
  }
  const layerNm = _layerFromFeatureId(feature);
  let cfg = null;
  if (layerNm) {
    cfg =
      layerConfigs.find(function (c) {
        return stripLayerPrefix(String(c.name || '')) === stripLayerPrefix(String(layerNm));
      }) || null;
  }
  if (!cfg && layerConfigs.length === 1) {
    cfg = layerConfigs[0];
  }
  return qcBuildFeaturePanelLayerMeta(cfg);
}

function makePopupDraggable(el) {
  const header = el.querySelector('.qc-popup-header');
  if (!header) return;

  let isDragging = false;
  let offsetX = 0;
  let offsetY = 0;

  header.addEventListener('mousedown', (e) => {
    if (e.target.closest('.qc-popup-controls')) return;
    isDragging = true;
    offsetX = e.clientX - el.offsetLeft;
    offsetY = e.clientY - el.offsetTop;
  });

  document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;

    el.style.left = e.clientX - offsetX + 'px';
    el.style.top = e.clientY - offsetY + 'px';
    el.style.right = 'auto';
  });

  document.addEventListener('mouseup', () => {
    isDragging = false;
  });
}

function renderFeatureInfoPanel(html) {
  const popup = document.getElementById('featurePopup');
  if (!popup) {
    qcOpenFeaturePanelDocked(html);
    return;
  }

  if (map && typeof map.closePopup === 'function') {
    map.closePopup();
  }

  const body = popup.querySelector('.qc-popup-body');
  if (!body) return;

  const attrsHost = body.querySelector('.qc-popup-attrs');
  const injectRoot = attrsHost || body;
  injectRoot.innerHTML = html;

  popup.classList.remove('hidden');
  popup.setAttribute('aria-hidden', 'false');

  body.scrollTop = 0;
  initTabbedNavigation(injectRoot);
  qcWireFeaturePanelAttributeEditButtons(injectRoot);
  qcWireDockedGeometryEditButton(injectRoot);

  if (!popup.dataset.draggableInit) {
    makePopupDraggable(popup);
    popup.dataset.draggableInit = '1';
  }

  setTimeout(() => {
    try {
      const plotHost = qcResolveFeaturePlotlyHost();
      if (!plotHost || typeof Plotly === 'undefined' || !Plotly.Plots || typeof Plotly.Plots.resize !== 'function') return;
      Plotly.Plots.resize(plotHost);
    } catch (err) {}
  }, 80);
}

document.addEventListener('click', function (e) {
  if (e.target.id === 'qc-popup-close') {
    qcCloseFeaturePanel();
  }

  if (e.target.id === 'qc-popup-min') {
    const minBody = document.querySelector('#featurePopup .qc-popup-body');
    if (!minBody) return;

    if (minBody.style.display === 'none') {
      minBody.style.display = '';
    } else {
      minBody.style.display = 'none';
    }
  }
});

function qcLayerCfgForWfsTypeName(typeName) {
  if (typeof layerConfigs === 'undefined' || !Array.isArray(layerConfigs)) {
    return null;
  }
  const want = stripLayerPrefix(String(typeName || ''));
  return (
    layerConfigs.find(function (c) {
      if (c.skipWfsFetch) return false;
      const tn = stripLayerPrefix(String(c.wfsTypeName || c.name || c.typename || ''));
      return tn === want;
    }) || null
  );
}

function qcFindRawFeatureInAll(allFeatures, clickedFeature, pkField, pkValue) {
  if (!Array.isArray(allFeatures) || !allFeatures.length) return null;
  const wantId = clickedFeature && clickedFeature.id != null ? String(clickedFeature.id) : null;
  if (wantId) {
    const byId = allFeatures.find(function (f) {
      return f.id != null && String(f.id) === wantId;
    });
    if (byId) return byId;
  }
  if (pkValue != null && pkField) {
    return (
      allFeatures.find(function (f) {
        const p = f.rawProperties || f.properties || {};
        return String(p[pkField]) === String(pkValue);
      }) || null
    );
  }
  return null;
}

async function handleFeatureClickForPanel(clickedFeature, layerMeta) {
  const chartEl = qcResolveFeaturePlotlyHost();
  const msgEl = qcResolveFeaturePlotlyMessage();

  if (!chartEl) return;

  if (!layerMeta) {
    layerMeta = qcBuildFeaturePanelLayerMeta(null);
  }

  chartEl.innerHTML = '';
  if (msgEl) {
    msgEl.style.display = 'none';
    msgEl.textContent = '';
  }

  try {
    let config = null;

    if (layerMeta && layerMeta.plotlyXmlText) {
      config = parsePlotlyConfigFromXml(layerMeta.plotlyXmlText);
    }

    if (!config || !config.xField || !config.yField) {
      config = {
        type: String((layerMeta && layerMeta.chartType) || 'bar').toLowerCase(),
        xField: layerMeta && layerMeta.xField,
        yField: layerMeta && layerMeta.yField,
        xTitle: layerMeta && layerMeta.xField,
        yTitle: layerMeta && layerMeta.yField
      };
    }

    if (!config.xField || !config.yField) {
      throw new Error('Invalid chart config');
    }

    const layerCfg =
      layerMeta._sourceCfg ||
      qcLayerCfgForWfsTypeName(layerMeta.wfsTypeName) ||
      (layerConfigs && layerConfigs.length === 1 ? layerConfigs[0] : null);
    if (layerCfg && !layerCfg.skipWfsFetch) {
      if (!layerCfg.allFeatures || !layerCfg.allFeatures.length) {
        await loadAllFeaturesForLayer(layerCfg);
      }
      layerMeta.allFeatures = layerCfg.allFeatures || [];
    } else {
      layerMeta.allFeatures = layerMeta.allFeatures || [];
    }

    const identity = resolveClickedFeatureIdentity(clickedFeature);
    const pkField = (layerMeta && layerMeta.pkField) || identity.pkField;
    const props = clickedFeature.properties || {};
    const pkValue = props[pkField] ?? identity.pkValue;

    let rawFeature = qcFindRawFeatureInAll(layerMeta.allFeatures, clickedFeature, pkField, pkValue);

    if (!rawFeature && identity.featureId) {
      try {
        rawFeature = await fetchRawFeatureByIdWfs({
          wfsUrl: layerMeta.wfsUrl,
          typeName: layerMeta.wfsTypeName,
          featureId: identity.featureId
        });
      } catch (e) {}
    }

    if (!rawFeature && pkValue != null) {
      rawFeature = await fetchRawFeatureByPkWfs({
        wfsUrl: layerMeta.wfsUrl,
        typeName: layerMeta.wfsTypeName,
        pkField: pkField,
        pkValue: pkValue
      });
    }

    if (!rawFeature?.properties) {
      throw new Error('No raw feature data');
    }

    if (rawFeature && !rawFeature.rawProperties) {
      rawFeature.rawProperties = rawFeature.properties;
    }

    const selectedForChart = Object.assign({}, clickedFeature, {
      rawProperties: rawFeature.rawProperties || rawFeature.properties
    });

    renderFeatureChartWithContext(chartEl, layerMeta.allFeatures, selectedForChart, config);

  } catch (err) {
    try {
      console.error(err);
    } catch (e) {}
    if (chartEl) chartEl.innerHTML = '';
    if (msgEl) {
      msgEl.textContent = '';
      msgEl.style.display = 'none';
    }
  }
}

layerConfigs.forEach((cfg, idx) => {
  // Strip any prefix from layer name for WMS / tile requests (QGIS expects plain names)
  const plainLayerName = stripLayerPrefix(cfg.name);
  let wmsLayer;
  if (USE_TILES) {
    const tileUrl =
      `/api/tiles/{z}/{x}/{y}.png` +
      `?map=${encodeURIComponent(layerIdToQgisPath(layerId))}` +
      `&layers=${encodeURIComponent(plainLayerName)}` +
      `&meta=4&buffer=64`;
    wmsLayer = L.tileLayer(tileUrl, {
      tileSize: 256,
      maxZoom: 19,
      opacity: 1
    }).setZIndex(100 + idx).addTo(map);
  } else {
    // WMS_SVC_URL should already be /mproxy/service?map=... from getMproxyBaseUrl()
    wmsLayer = L.tileLayer.wms(WMS_SVC_URL, {
      layers: plainLayerName,
      format: 'image/png',
      transparent: true,
      version: '1.1.1'
    }).setZIndex(100 + idx).addTo(map);
  }
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

  if (!cfg.skipWfsFetch) {
    void loadAllFeaturesForLayer(cfg);
  }
});

// NOTE: push relation child layers after map is initialized:
// they are non-spatial (no map toggle) but still need WFS fetch for relation records.
RELATIONS.forEach((rel, idx) => {
  if(layerConfigs.some(cfg => cfg.typename === rel.child_layer)){
    return;
  }
  let name = rel.child_layer;
  // Use layer names directly - no prefix handling needed
  overlayLayers[name] = null;
  r = {
    name: name,
    color: null,
    typename: rel.child_layer,
    label: rel.child_layer,
    filter: null,
    skipWfsFetch: false
  };
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
      out[db] = (t === '(NULL)') ? '' : cleanValue(t);
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
      var current = cleanValue((val.textContent||'').trim());
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
      val.textContent = (original[db] != null ? String(cleanValue(original[db])) : '');
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
          val.textContent = (updates[k] == null ? '' : String(cleanValue(updates[k])));
          rows[i].classList.remove('editing');
          break;
        }
      }
    }
  }

  function refreshQgisLayersAfterEdit() {
    var stamp = Date.now();
    if (typeof map === 'undefined' || !map || typeof map.eachLayer !== 'function') return;
    map.eachLayer(function (layer) {
      if (
        layer &&
        typeof layer.setParams === 'function' &&
        layer._url &&
        (layer._url.indexOf('proxy_qgis.php') !== -1 || layer._url.indexOf('mproxy') !== -1)
      ) {
        layer.setParams({
          CACHE: 0,
          cache: 0,
          _refresh: stamp
        }, false);
      }
    });
  }
  window.refreshQgisLayersAfterEdit = refreshQgisLayersAfterEdit;

  // POST: OAPIF for PostGIS; direct file update for GPKG / SHP / OGR
  function quickSave(featureId, changes, tabPane) {
    var geometry = null;
    if (activeEditLayer && typeof activeEditLayer.getLatLng === 'function') {
      var latlng = activeEditLayer.getLatLng();
      geometry = {
        type: 'Point',
        coordinates: [latlng.lng, latlng.lat]
      };
    }

    try { console.warn('Saving geometry:', geometry); } catch (e) {}

    var layer = String(featureId).split('.')[0];
    var prov = (window.LAYER_PROVIDER_BY_NAME && Object.prototype.hasOwnProperty.call(window.LAYER_PROVIDER_BY_NAME, layer))
      ? window.LAYER_PROVIDER_BY_NAME[layer]
      : (window.LAYER_PROVIDER != null ? window.LAYER_PROVIDER : 'postgres');

    function finishEdit() {
      resetPointEditState();
      if (window.disableInlineEditing) window.disableInlineEditing(tabPane);
    }

    if (prov === 'postgres') {
      var body = { collection:'auto', id:featureId, layer_id:layerId, updates:changes, layerHint:layer };
      if (geometry) body.geometry = geometry;

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
          if (data && data.type==='Feature') { applyUpdates(tabPane, changes); refreshQgisLayersAfterEdit(); finishEdit(); return; }
          if (data && data.error) { alert('Error: '+data.error); return; }
        }
        if (/<TransactionResponse/i.test(r.text) && /<totalUpdated>\s*[1-9]/i.test(r.text)) { applyUpdates(tabPane, changes); refreshQgisLayersAfterEdit(); finishEdit(); return; }
        console.warn('Unexpected response:', r.text.slice(0,400)); alert('Save might not have applied. See console.');
      })
      .catch(function(err){ console.error('Fetch error', err); alert('Save failed: '+err.message); });
      return;
    }

    fetch('../../admin/action/qgis_file_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        featureId: featureId,
        layer: layer,
        updates: changes,
        layer_id: layerId,
        geometry: geometry
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.success) {
        applyUpdates(tabPane, changes);
        refreshQgisLayersAfterEdit();
        finishEdit();
      } else {
        alert('Save failed: ' + (data && data.error ? data.error : 'Unknown error'));
      }
    })
    .catch(function (err) {
      console.error('Fetch error', err);
      alert('Save failed: ' + err.message);
    });
  }

  /** Tab panes use id="popup-<featureId>". Duplicate HTML in #inspectBody + #featureContent would create duplicate IDs; always resolve inside #featureContent first (visible docked panel). */
  function qcFindTabPaneForFeatureId(fid) {
    if (fid == null || fid === '') return null;
    var full = 'popup-' + String(fid);
    var safeAttr = full.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    var sel = '[id="' + safeAttr + '"]';
    var fc = document.getElementById('featureContent');
    if (fc) {
      var n = fc.querySelector(sel);
      if (n) return n;
    }
    var ib = document.getElementById('inspectBody');
    if (ib) {
      var n2 = ib.querySelector(sel);
      if (n2) return n2;
    }
    return null;
  }

  // Replace the modal opener with inline editing
function replaceModalOpener(){
  var origShow = window.showEditModal;
  window.showEditModal = function (featureId /*, featureData */) {
    var fid = featureId != null ? String(featureId) : '';
    var pane = qcFindTabPaneForFeatureId(fid);
    if (!pane) {
      pane =
        document.querySelector('#featureContent .tab-pane.active') ||
        document.querySelector('#featureContent .tab-pane') ||
        document.querySelector('#featureContent .popup-content') ||
        document.querySelector('#featureContent');
    }
    if (!pane) {
      pane =
        document.querySelector('#inspectBody .tab-pane.active') ||
        document.querySelector('#inspectBody .tab-pane') ||
        document.querySelector('#inspectBody .popup-content') ||
        document.querySelector('#inspectBody');
    }
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
        var hasGeomEdit = activeEditLayer && typeof activeEditLayer.getLatLng === 'function';
        if (!changed && !hasGeomEdit) { alert('No changes detected'); return; }
        quickSave(featureId, updates, pane);
      };

      var cancel = document.createElement('button');
      cancel.className = 'btn btn-secondary btn-sm flex-fill';
      cancel.innerHTML = 'Cancel';
      cancel.onclick = function(ev){ ev.preventDefault(); resetPointEditState(); restoreReadOnly(pane, original); };

      editBtn.parentNode.replaceChild(wrap, editBtn);
      wrap.appendChild(save); wrap.appendChild(cancel);
    }

    // do NOT open any modal
    return false;
  };
}

/**
 * Stable id for edits / WFS: QGIS GetFeatureInfo sometimes sets feature.id off-by-one vs properties.fid.
 * When fid/FID disagrees with the numeric part of feature.id, prefer layerName.fid from properties.
 */
function qcResolveFeatureEditId(feature) {
  if (!feature) return '';
  var fromId = feature.id != null && feature.id !== '' ? String(feature.id) : '';
  var p = feature.properties || {};
  var rawFid = p.fid != null ? p.fid : (p.FID != null ? p.FID : null);
  if (rawFid === null || rawFid === '') {
    return fromId;
  }

  var layerNm = '';
  if (fromId.indexOf('.') !== -1) {
    layerNm = fromId.slice(0, fromId.indexOf('.'));
  }
  if (!layerNm && p._layerName) {
    layerNm = String(p._layerName);
  }
  if (!layerNm && typeof layerConfigs !== 'undefined' && Array.isArray(layerConfigs) && layerConfigs.length === 1) {
    layerNm = String(layerConfigs[0].name || '');
  }

  var canonical = layerNm ? layerNm + '.' + String(rawFid) : String(rawFid);
  if (!fromId) {
    return canonical;
  }

  var suffix = fromId.indexOf('.') !== -1 ? fromId.split('.').pop() : fromId;
  var a = String(suffix).trim();
  var b = String(rawFid).trim();
  var na = Number(a);
  var nb = Number(b);
  var same = a === b || (!Number.isNaN(na) && !Number.isNaN(nb) && na === nb);
  if (same) {
    return fromId;
  }
  return layerNm ? canonical : fromId;
}

try {
  window.qcResolveFeatureEditId = qcResolveFeatureEditId;
} catch (e) {}

function qcInferLayerNameForFeatureId(featureId) {
  var s = String(featureId || '');
  var dot = s.indexOf('.');
  if (dot > 0) return s.slice(0, dot);
  if (typeof layerConfigs !== 'undefined' && Array.isArray(layerConfigs) && layerConfigs.length === 1) {
    return String(layerConfigs[0].name || '');
  }
  return '';
}

try {
  window.qcInferLayerNameForFeatureId = qcInferLayerNameForFeatureId;
} catch (e) {}

function qcEnsureFeaturePanel() {
  let panel = document.getElementById('featurePanel');
  let content = panel ? panel.querySelector('#featureContent') : null;
  if (panel && content) {
    return { panel, content };
  }
  panel = document.createElement('div');
  panel.id = 'featurePanel';
  panel.className = 'qc-feature-panel collapsed';
  panel.setAttribute('aria-hidden', 'true');
  panel.innerHTML =
    '<div class="qc-feature-header">' +
    '<span>Feature Info</span>' +
    '<button type="button" id="qcFeatureClose" aria-label="Close">&times;</button>' +
    '</div>' +
    '<div id="featureContent"></div>' +
    '<div class="feature-chart-section border-top px-2 pb-2">' +
    '<div id="feature-plotly-chart" style="height:560px;min-height:560px;"></div>' +
    '<div id="feature-plotly-chart-message" class="small text-muted" style="display:none;"></div>' +
    '</div>';
  document.body.appendChild(panel);
  content = document.getElementById('featureContent');
  const btn = document.getElementById('qcFeatureClose');
  if (btn) {
    btn.addEventListener('click', qcCloseFeaturePanel);
  }
  return { panel, content };
}

/** Legacy docked panel (pages with #featurePanel only, no #featurePopup). */
function qcOpenFeaturePanelDocked(html) {
  const { panel, content } = qcEnsureFeaturePanel();
  if (!content || !panel) {
    return;
  }
  if (map && typeof map.closePopup === 'function') {
    map.closePopup();
  }
  content.innerHTML = html;
  content.scrollTop = 0;
  initTabbedNavigation(content);
  qcWireFeaturePanelAttributeEditButtons(content);
  qcWireDockedGeometryEditButton(content);
  panel.classList.remove('collapsed');
  panel.setAttribute('aria-hidden', 'false');
  setTimeout(() => {
    try {
      const plotHost = qcResolveFeaturePlotlyHost();
      if (!plotHost || typeof Plotly === 'undefined' || !Plotly.Plots || typeof Plotly.Plots.resize !== 'function') return;
      Plotly.Plots.resize(plotHost);
    } catch (err) {}
  }, 80);
}

function qcOpenFeaturePanel(html) {
  renderFeatureInfoPanel(html);
}

function qcCloseFeaturePanel() {
  try { endGeometryEditMode(); } catch (e) {}
  const floatPop = document.getElementById('featurePopup');
  if (floatPop) {
    floatPop.classList.add('hidden');
    floatPop.setAttribute('aria-hidden', 'true');
    const minBody = floatPop.querySelector('.qc-popup-body');
    if (minBody) minBody.style.display = '';
  }
  const panel = document.getElementById('featurePanel');
  if (panel) {
    panel.classList.add('collapsed');
    panel.setAttribute('aria-hidden', 'true');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('qcFeatureClose');
  if (btn) {
    btn.addEventListener('click', qcCloseFeaturePanel);
  }
});
