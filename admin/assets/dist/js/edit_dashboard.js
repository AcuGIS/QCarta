(() => {

  function dbg(msg, warn=false){
    const el=document.getElementById('dbg');
    if(!el){
      return;
    }
    el.style.display='block';
    el.style.background = warn ? '#fdecea' : '#fff3cd';
    el.style.color = warn ? '#b71c1c' : '#856404';
    el.innerHTML = msg;
  }

  // ------- simple grid -------
  function metrics(){ const r=canvas.getBoundingClientRect(), cols=12, g=8; return {r, cols, g, colW:(r.width-(cols-1)*g)/cols, rowH:120}; }
  function applyPos(el){ const m=metrics(); const x=+el.dataset.x,y=+el.dataset.y,w=+el.dataset.w,h=+el.dataset.h;
    el.style.left=(x*(m.colW+m.g))+'px'; el.style.top=(y*(m.rowH+m.g))+'px';
    el.style.width=(w*m.colW+(w-1)*m.g)+'px'; el.style.height=(h*m.rowH+(h-1)*m.g)+'px'; }

  let uid=0;
  function addItem(kind,x=0,y=0,w=3,h=2){
    const id=`w_${Date.now().toString(36)}_${++uid}`;
    const el=document.createElement('div'); el.className='item'; Object.assign(el.dataset,{id,x,y,w,h});
    const body = kind==='map' ? `<div id="${id}_map" style="height:100%;width:100%"></div>` :
                 kind==='chart' ? `<div class="pad"><div id="${id}_chart" style="height:100%;min-height:280px" data-cfg='{"source":{"kind":"wfs"}}'></div></div>` :
                 kind==='table' ? `<div class="pad"><div id="${id}_table" style="height:100%;min-height:200px" data-cfg='{"source":{"kind":"wfs"}}'></div></div>` :
                 kind==='legend' ? `<div class="pad"><div id="${id}_legend" style="height:100%;min-height:200px" data-cfg='{"source":{"kind":"wms"}}'></div></div>` :
                 kind==='counter' ? `<div class="pad"><div id="${id}_counter" style="height:100%;min-height:120px;display:flex;align-items:center;justify-content:center;flex-direction:column" data-cfg='{"source":{"kind":"wfs"}}'></div></div>` :
                 `<div class="pad" contenteditable="true"><h4>Notes</h4><p class="help">Click to typeï¿½</p></div>`;
    const tools = (DASHBOARD_EDITOR) ? `
      <button class="tbtn" data-act="cfg" title="Configure">âš™</button>
      <button class="tbtn" data-act="refresh" title="Refresh">â†»</button>
      <button class="tbtn" data-act="dup" title="Duplicate">â§‰</button>
      <button class="tbtn" data-act="del" title="Delete">Ã—</button>` : '';

    el.innerHTML = `<div class="card-header">
      <div class="title" contenteditable="true" title="Double-click to edit title">${kind[0].toUpperCase()+kind.slice(1)}</div>
      <div class="tools">${tools}</div></div>
      <div class="body">${body}</div><div class="resize" title="Resize"></div>`;
    // toolbar
    el.querySelector('.tools').addEventListener('click', (e)=>{
      const b=e.target.closest('button'); if(!b) return; const act=b.dataset.act;
      // Determine widget type dynamically
      const widgetKind = (el.querySelector('[id$="_map"]')&&'map')||(el.querySelector('[id$="_chart"]')&&'chart')||(el.querySelector('[id$="_table"]')&&'table')||(el.querySelector('[id$="_legend"]')&&'legend')||(el.querySelector('[id$="_counter"]')&&'counter')||'text';
      if (act==='del'){ el.remove(); return; }
      else if (act==='dup'){ addItem(widgetKind,+el.dataset.x+1,+el.dataset.y+1,+el.dataset.w,+el.dataset.h); }
      else if (act==='refresh'){
        let elid = 0;
        switch(widgetKind){
          case 'map':
            el._leafletMap?.invalidateSize(); break;
          case 'chart':
            elid=el.querySelector('[id$="_chart"]').id; 
            // Clear cached data to force fresh fetch
            chartData.delete(elid);
            renderChart(elid, getCfg(el)); 
            break;
          case 'table':
            elid=el.querySelector('[id$="_table"]').id; 
            // Clear cached data to force fresh fetch
            tableData.delete(elid);
            renderTable(elid, getCfg(el)); 
            break;
          case 'legend':
            elid=el.querySelector('[id$="_legend"]').id; 
            renderLegend(elid, getCfg(el)); 
            break;
          case 'counter':
            elid=el.querySelector('[id$="_counter"]').id; 
            // Clear cached data to force fresh fetch
            counterData.delete(elid);
            renderCounter(elid, getCfg(el)); 
            break;
          default:
            break;
        }
      }
      else if (act==='cfg') {
        switch(widgetKind){
          case 'chart':   openChartCfg(el); break;
          case 'map':     openMapCfg(el);   break;
          case 'table':   openTableCfg(el); break;
          case 'legend':  openLegendCfg(el);break;
          case 'counter': openCounterCfg(el);break;
          case 'text':    openTextCfg(el);  break;
          default:                          break;
        }
      }
    });
    // drag/resize
    const head=el.querySelector('.card-header'); let drag=null;
    const onMove=(ev)=>{ const m=metrics(); if(!drag) return;
      if (drag.type==='move'){ const dx=ev.clientX-drag.mx, dy=ev.clientY-drag.my;
        const px=drag.x*(m.colW+m.g)+dx, py=drag.y*(m.rowH+m.g)+dy;
        el.dataset.x=Math.max(0,Math.round(px/(m.colW+m.g))); el.dataset.y=Math.max(0,Math.round(py/(m.rowH+m.g)));
      } else { const dx=ev.clientX-drag.mx, dy=ev.clientY-drag.my;
        const baseW=drag.w*m.colW+(drag.w-1)*m.g, baseH=drag.h*m.rowH+(drag.h-1)*m.g;
        el.dataset.w=Math.min(Math.max(1,Math.round((baseW+dx+m.g)/(m.colW+m.g))), m.cols-drag.x);
        el.dataset.h=Math.max(1,Math.round((baseH+dy+m.g)/(m.rowH+m.g)));
      } applyPos(el); el._leafletMap?.invalidateSize(); };
    const onUp=()=>{ el.classList.remove('ghost'); window.removeEventListener('mousemove',onMove); drag=null; };
    head.addEventListener('mousedown', ev=>{ ev.preventDefault(); drag={type:'move',mx:ev.clientX,my:ev.clientY,x:+el.dataset.x,y:+el.dataset.y,w:+el.dataset.w,h:+el.dataset.h}; el.classList.add('ghost'); window.addEventListener('mousemove',onMove); window.addEventListener('mouseup',onUp,{once:true}); });
    el.querySelector('.resize').addEventListener('mousedown', ev=>{ ev.preventDefault(); drag={type:'resize',mx:ev.clientX,my:ev.clientY,x:+el.dataset.x,y:+el.dataset.y,w:+el.dataset.w,h:+el.dataset.h}; el.classList.add('ghost'); window.addEventListener('mousemove',onMove); window.addEventListener('mouseup',onUp,{once:true}); });

    canvas.appendChild(el); applyPos(el);
    let elid = 0;
    switch(kind){  
      case 'map':   buildMap(el.querySelector('[id$="_map"]').id, el); break;
      case 'chart': elid  = el.querySelector('[id$="_chart"]').id;  renderChart(elid, getCfg(el)); break;
      case 'table': elid  = el.querySelector('[id$="_table"]').id;  renderTable(elid, getCfg(el)); break;
      case 'legend': elid = el.querySelector('[id$="_legend"]').id; renderLegend(elid, getCfg(el)); break;
      case 'counter': elid = el.querySelector('[id$="_counter"]').id; renderCounter(elid, getCfg(el)); break;
    }
  }

  // ------- WMS via proxy_qgis.php -------
  async function buildMap(divId, el){
    // Check if map already exists and remove it
    if (el._leafletMap) {
      el._leafletMap.remove();
      el._leafletMap = null;
    }
    
    // Clear any existing Leaflet state from the DOM element
    const mapDiv = document.getElementById(divId);
    if (mapDiv._leaflet_id) {
      delete mapDiv._leaflet_id;
    }
    
    const map = L.map(divId, { zoomControl:true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'ï¿½ OSM'}).addTo(map);

    // Store map instance globally for chart updates
    window.currentMap = map;
    // Store highlighted feature layer for zoom functionality
    window.highlightedFeatureLayer = null;

    // Add event listeners for map movement to update charts
    map.on('moveend', updateAllCharts);
    map.on('zoomend', updateAllCharts);

    // Get popup configuration
    const cfg = getCfg(el);
    const popupConfig = cfg?.popups;

        // Add popup click handler if popups are enabled (or enable by default)
        if (popupConfig?.enabled !== false) {
          // Create default popup config if none exists
          const defaultPopupConfig = popupConfig || {
            enabled: true,
            limit: 10,
            radius: 1
          };
          map.on('click', async (e) => {
            try {
              const clickPoint = e.latlng;
              
              // First, try to get feature info from WMS GetFeatureInfo to identify the specific layer
              const wmsLayers = await wfsGetLayers();
              let clickedFeature = null;
              let clickedLayer = null;
              
              // Try GetFeatureInfo for each layer to find which one was clicked
              for (const layerName of wmsLayers) {
                try {
                  const featureInfo = await getFeatureInfo(map, clickPoint, layerName);
                  if (featureInfo && featureInfo.features && featureInfo.features.length > 0) {
                    clickedFeature = featureInfo.features[0];
                    clickedLayer = layerName;
                    break;
                  }
                } catch (err) {
                  // Continue to next layer if this one fails
                  continue;
                }
              }
              
              if (clickedFeature && clickedLayer) {
                // Show popup for the specific clicked feature
                const featureData = clickedFeature.properties || clickedFeature;
                
                // If GetFeatureInfo returns generic data, try to get actual feature data via WFS
                if (!featureData || Object.keys(featureData).length === 0 || 
                    (Object.keys(featureData).length === 1 && featureData.id)) {
                  const radiusKm = defaultPopupConfig.radius || 1;
                  const buffer = radiusKm / 111;
                  const clickBounds = L.latLngBounds(
                    [clickPoint.lat - buffer, clickPoint.lng - buffer],
                    [clickPoint.lat + buffer, clickPoint.lng + buffer]
                  );

                  const features = await wfsFetchRows({
                    typeName: clickedLayer,
                    limit: 1, // Only get the closest feature
                    bounds: clickBounds
                  });

                  if (features.length > 0) {
                    const popupContent = createPopupContent([features[0]], defaultPopupConfig);
                    L.popup()
                      .setLatLng(clickPoint)
                      .setContent(popupContent)
                      .openOn(map);
                  }
                  // Don't show popup if no features found
                } else {
                  // Use the GetFeatureInfo data if it looks valid
                  const popupContent = createPopupContent([featureData], defaultPopupConfig);
                  L.popup()
                    .setLatLng(clickPoint)
                    .setContent(popupContent)
                    .openOn(map);
                }
              } else {
                // Fallback: use the configured layer with bounding box search
                const radiusKm = defaultPopupConfig.radius || 1;
                const buffer = radiusKm / 111;
                const clickBounds = L.latLngBounds(
                  [clickPoint.lat - buffer, clickPoint.lng - buffer],
                  [clickPoint.lat + buffer, clickPoint.lng + buffer]
                );

                const features = await wfsFetchRows({
                  typeName: clickedLayer || defaultPopupConfig.layer,
                  limit: defaultPopupConfig.limit || 10,
                  bounds: clickBounds
                });

                if (features.length > 0) {
                  const popupContent = createPopupContent(features, defaultPopupConfig);
                  L.popup()
                    .setLatLng(clickPoint)
                    .setContent(popupContent)
                    .openOn(map);
                }
                // Don't show popup if no features found
              }
            } catch(error) {
              console.error('Popup error:', error);
              L.popup()
                .setLatLng(e.latlng)
                .setContent('<div style="padding: 8px; color: #dc2626;">Error loading popup data. Check console for details.</div>')
                .openOn(map);
            }
          });
        }

    if (!WMS_BASE) { dbg('Add ?store_id=6 to the URL for WMS.', true); }
    const capUrl = WMS_BASE
      ? `${WMS_BASE}${WMS_BASE.includes('?')?'&':'?'}SERVICE=WMS&REQUEST=GetCapabilities`
      : `${BASE}${BASE.includes('?')?'&':'?'}SERVICE=WMS&REQUEST=GetCapabilities`; // fallback
    dbg(`WMS Capabilities: <code>${capUrl}</code>`);
    const forced = (WMS_FORCE||'').trim();
    try{
      const txt = await fetch(capUrl, {credentials:'same-origin'}).then(r=>r.text());
      
      const xml = new DOMParser().parseFromString(txt,'text/xml');
      const layers = Array.from(xml.getElementsByTagName('Layer'))
        .map(n => ({ node:n, name:(n.getElementsByTagName('Name')[0]?.textContent||'').trim()}))
        .filter(o => o.name);
      const chosen = forced
        ? layers.find(o => o.name===forced)
        : (layers.find(o => o.node.getElementsByTagName('EX_GeographicBoundingBox')[0] || o.node.getElementsByTagName('LatLonBoundingBox')[0]) || layers[0]);
      const lname = chosen?.name;
      if (!lname) { dbg('WMS: no layers found.', true); map.setView([31.5,34.8],7); el._leafletMap=map; return; }
      L.tileLayer.wms(WMS_BASE || BASE, { layers: lname, transparent:true, format:'image/png', version:'1.3.0' })
        .on('tileerror', e => dbg(`WMS tile error<br><small>${e.tile?.src||''}</small>`, true))
        .addTo(map);

      // fit to bounds
      const ex=chosen.node.getElementsByTagName('EX_GeographicBoundingBox')[0];
      if (ex){ const w=+ex.getElementsByTagName('westBoundLongitude')[0].textContent;
               const s=+ex.getElementsByTagName('southBoundLatitude')[0].textContent;
               const e=+ex.getElementsByTagName('eastBoundLongitude')[0].textContent;
               const n=+ex.getElementsByTagName('northBoundLatitude')[0].textContent;
               map.fitBounds([[s,w],[n,e]]); } else {
        const ll=chosen.node.getElementsByTagName('LatLonBoundingBox')[0];
        if (ll){ const w=+ll.getAttribute('minx'), s=+ll.getAttribute('miny'), e=+ll.getAttribute('maxx'), n=+ll.getAttribute('maxy'); map.fitBounds([[s,w],[n,e]]); }
        else map.setView([31.5,34.8],7);
      }
    }catch(e){
      dbg('WMS GetCapabilities failed.', true);
      map.setView([31.5,34.8],7);
    }
    el._leafletMap=map; setTimeout(()=>map.invalidateSize(),60);
  }

  // ------- Popup content creation -------
  function createPopupContent(features, popupConfig) {
    if (!features || features.length === 0) {
      return '<div style="padding: 8px; color: #6b7280;">No features found</div>';
    }

    // Get all available fields from the feature
    const allFields = Object.keys(features[0] || {});
    
    // Use configured fields if available, otherwise use all fields
    const fields = popupConfig.fields && popupConfig.fields.length > 0 
      ? popupConfig.fields 
      : allFields;
    
    // Find a good title field - prefer configured one, then look for common name fields
    let titleField = popupConfig.titleField;
    if (!titleField) {
      // Look for common name fields
      const nameFields = ['name', 'Name', 'NAME', 'title', 'Title', 'TITLE', 'label', 'Label', 'LABEL'];
      titleField = nameFields.find(field => allFields.includes(field)) || allFields[0];
    }
    
    let content = '<div style="max-width: 300px; max-height: 400px; overflow-y: auto;">';
    
    features.forEach((feature, index) => {
      // Get title - use the title field value, or first non-empty field, or fallback
      let title = feature[titleField];
      if (!title || title === '' || title === null || title === undefined) {
        // Find first non-empty field value
        for (const field of allFields) {
          if (feature[field] && feature[field] !== '' && feature[field] !== null && feature[field] !== undefined) {
            title = `${field}: ${feature[field]}`;
            break;
          }
        }
        if (!title) {
          title = `Feature ${index + 1}`;
        }
      }
      
      content += `<div style="border-bottom: 1px solid #e5e7eb; padding: 8px 0; ${index > 0 ? 'margin-top: 8px;' : ''}">`;
      content += `<div style="font-weight: 600; margin-bottom: 4px; color: #1f2937;">${title}</div>`;
      
      fields.forEach(field => {
        if (field !== titleField && feature[field] !== undefined && feature[field] !== null && feature[field] !== '') {
          const value = String(feature[field]).length > 50 
            ? String(feature[field]).substring(0, 50) + '...' 
            : String(feature[field]);
          content += `<div style="font-size: 12px; color: #6b7280; margin: 2px 0;">`;
          content += `<strong>${field}:</strong> ${value}`;
          content += `</div>`;
        }
      });
      
      content += '</div>';
    });
    
    if (features.length > 1) {
      content += `<div style="font-size: 11px; color: #9ca3af; text-align: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e5e7eb;">`;
      content += `Showing ${features.length} features`;
      content += `</div>`;
    }
    
    content += '</div>';
    return content;
  }

  // ------- WMS GetFeatureInfo helper -------
  async function getFeatureInfo(map, latlng, layerName) {
    const size = map.getSize();
    const bounds = map.getBounds();
    const sw = bounds.getSouthWest();
    const ne = bounds.getNorthEast();
    
    const params = {
      SERVICE: 'WMS',
      VERSION: '1.1.1',
      REQUEST: 'GetFeatureInfo',
      LAYERS: layerName,
      QUERY_LAYERS: layerName,
      INFO_FORMAT: 'application/json',
      FEATURE_COUNT: 1,
      X: Math.round((latlng.lng - sw.lng) / (ne.lng - sw.lng) * size.x),
      Y: Math.round((ne.lat - latlng.lat) / (ne.lat - sw.lat) * size.y),
      SRS: 'EPSG:4326',
      WIDTH: size.x,
      HEIGHT: size.y,
      BBOX: `${sw.lng},${sw.lat},${ne.lng},${ne.lat}`
    };
    
    const baseUrl = WMS_SVC_URL + (WMS_SVC_URL.includes('?') ? '&' : '?');
    const url = baseUrl + Object.keys(params)
      .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
      .join('&');
    
    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      const data = await response.json();
      return data;
    } catch (error) {
      console.error(`GetFeatureInfo failed for layer ${layerName}:`, error);
      throw error;
    }
  }

  // ------- WFS helpers -------
  async function wfsGetLayers(){
    // Use store-based endpoints for GetCapabilities (these work)
    const wfsUrl = `${WFS_BASE}${WFS_BASE.includes('?')?'&':'?'}REQUEST=GetCapabilities`;
    const wmsUrl = `${WMS_BASE}${WMS_BASE.includes('?')?'&':'?'}REQUEST=GetCapabilities`;
    
    // Try WFS first
    try {
      const txt = await fetch(wfsUrl, {credentials:'same-origin'}).then(r=>r.text());
      
      // Check if we got redirected to login page
      if (txt.includes('Sign in to QCarta') || txt.includes('login')) {
        throw new Error('Authentication required');
      }
      
      const xml = new DOMParser().parseFromString(txt,'text/xml');
      const layers = Array.from(xml.getElementsByTagName('FeatureType'))
        .map(ft => (ft.getElementsByTagName('Name')[0]?.textContent||'').trim())
        .filter(Boolean);
      
      if (layers.length > 0) {
        return layers;
      }
    } catch(e) {
    }
    
    // Fallback to WMS layers if WFS fails
    try {
      const txt = await fetch(wmsUrl, {credentials:'same-origin'}).then(r=>r.text());
      
      // Check if we got redirected to login page
      if (txt.includes('Sign in to QCarta') || txt.includes('login')) {
        throw new Error('Authentication required');
      }
      
      const xml = new DOMParser().parseFromString(txt,'text/xml');
      const layers = Array.from(xml.getElementsByTagName('Layer'))
        .map(layer => (layer.getElementsByTagName('Name')[0]?.textContent||'').trim())
        .filter(Boolean);
      
      return layers;
    } catch(e) {
      return [];
    }
  }
  async function wfsDescribe(typeName){
    // Use the same approach as analysis.js - load actual data and extract field names
    const baseUrl = WMS_SVC_URL + (WMS_SVC_URL.includes('?') ? '&' : '?');
    const url = `${baseUrl}SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&LAYERS=${encodeURIComponent(typeName)}&TYPENAME=${encodeURIComponent(typeName)}&OUTPUTFORMAT=application/json&MAXFEATURES=1`;
    
    try {
      const response = await fetch(url, {credentials:'same-origin'});
      
      // Check if response is ok
      if (!response.ok) {
        console.error(`WFS describe request failed: ${response.status} ${response.statusText}`);
        return [];
      }
      
      // Check content type
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        console.error('WFS describe response is not JSON:', contentType);
        return [];
      }
      
      // Get response text first
      const responseText = await response.text();
      
      // Check if response is empty or contains error
      if (!responseText || responseText.trim() === '') {
        console.error('WFS describe response is empty');
        return [];
      }
      
      // Check for common error patterns
      if (responseText.includes('Exception') || responseText.includes('Error') || responseText.includes('error')) {
        console.error('WFS describe returned error:', responseText.substring(0, 200));
        return [];
      }
      
      // Try to parse as JSON
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        console.error('Failed to parse WFS describe response as JSON:', parseError);
        console.error('Response text:', responseText.substring(0, 500));
        return [];
      }
      
      if (data.features && data.features.length > 0) {
        // Extract field names from first feature properties (same as analysis.js)
        const firstFeature = data.features[0];
        const properties = firstFeature.properties;
        return Object.keys(properties);
      }
      return [];
    } catch(e) {
      console.error('Failed to get fields via GetFeature:', e);
      return [];
    }
  }
  async function wfsFetchRows({typeName, limit=2000, cql='', bounds=null}){
    const baseUrl = WMS_SVC_URL + (WMS_SVC_URL.includes('?') ? '&' : '?');
    let url = `${baseUrl}SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&LAYERS=${encodeURIComponent(typeName)}&TYPENAME=${encodeURIComponent(typeName)}&OUTPUTFORMAT=application/json`;
    if (limit && limit < 2000) url += `&MAXFEATURES=${encodeURIComponent(limit)}`;
    if (cql) url += `&CQL_FILTER=${encodeURIComponent(cql)}`;
    
    // Add bounding box filter if provided
    if (bounds) {
      const bbox = `${bounds.getWest()},${bounds.getSouth()},${bounds.getEast()},${bounds.getNorth()}`;
      url += `&BBOX=${encodeURIComponent(bbox)}&SRSNAME=EPSG:4326`;
    }
    
    try {
      const response = await fetch(url, {credentials:'same-origin'});
      
      // Check if response is ok
      if (!response.ok) {
        console.error(`WFS request failed: ${response.status} ${response.statusText}`);
        return [];
      }
      
      // Check content type
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        console.error('WFS response is not JSON:', contentType);
        return [];
      }
      
      // Get response text first to check if it's valid
      const responseText = await response.text();
      
      // Check if response is empty or contains error
      if (!responseText || responseText.trim() === '') {
        console.error('WFS response is empty');
        return [];
      }
      
      // Check for common error patterns
      if (responseText.includes('Exception') || responseText.includes('Error') || responseText.includes('error')) {
        console.error('WFS returned error:', responseText.substring(0, 200));
        return [];
      }
      
      // Try to parse as JSON
      let geojson;
      try {
        geojson = JSON.parse(responseText);
      } catch (parseError) {
        console.error('Failed to parse WFS response as JSON:', parseError);
        console.error('Response text:', responseText.substring(0, 500));
        return [];
      }
      
      return Array.isArray(geojson?.features) ? geojson.features.map(f => f.properties || {}) : [];
    } catch (error) {
      console.error('WFS fetch error:', error);
      return [];
    }
  }

  // Fetch full GeoJSON features (with geometry) for zoom functionality
  async function wfsFetchFullFeatures({typeName, limit=2000, cql='', bounds=null}){
    const baseUrl = WMS_SVC_URL + (WMS_SVC_URL.includes('?') ? '&' : '?');
    let url = `${baseUrl}SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&LAYERS=${encodeURIComponent(typeName)}&TYPENAME=${encodeURIComponent(typeName)}&OUTPUTFORMAT=application/json`;
    if (limit && limit < 2000) url += `&MAXFEATURES=${encodeURIComponent(limit)}`;
    if (cql) url += `&CQL_FILTER=${encodeURIComponent(cql)}`;
    
    // Add bounding box filter if provided
    if (bounds) {
      const bbox = `${bounds.getWest()},${bounds.getSouth()},${bounds.getEast()},${bounds.getNorth()}`;
      url += `&BBOX=${encodeURIComponent(bbox)}&SRSNAME=EPSG:4326`;
    }
    
    try {
      const response = await fetch(url, {credentials:'same-origin'});
      
      if (!response.ok) {
        console.error(`WFS request failed: ${response.status} ${response.statusText}`);
        return [];
      }
      
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        console.error('WFS response is not JSON:', contentType);
        return [];
      }
      
      const responseText = await response.text();
      
      if (!responseText || responseText.trim() === '') {
        console.error('WFS response is empty');
        return [];
      }
      
      if (responseText.includes('Exception') || responseText.includes('Error') || responseText.includes('error')) {
        console.error('WFS returned error:', responseText.substring(0, 200));
        return [];
      }
      
      let geojson;
      try {
        geojson = JSON.parse(responseText);
      } catch (parseError) {
        console.error('Failed to parse WFS response as JSON:', parseError);
        return [];
      }
      
      return Array.isArray(geojson?.features) ? geojson.features : [];
    } catch (error) {
      console.error('WFS fetch error:', error);
      return [];
    }
  }

  // ------- Chart cfg + render -------
  function getCfg(cardEl){ 
    const d=cardEl.querySelector('[id$="_chart"]') || cardEl.querySelector('[id$="_table"]') || cardEl.querySelector('[id$="_legend"]') || cardEl.querySelector('[id$="_counter"]'); 
    try{ return d?.dataset?.cfg ? JSON.parse(d.dataset.cfg) : {}; }catch{return {};} 
  }
  function setCfg(cardEl, cfg){ 
    const d=cardEl.querySelector('[id$="_chart"]') || cardEl.querySelector('[id$="_table"]') || cardEl.querySelector('[id$="_legend"]') || cardEl.querySelector('[id$="_counter"]'); 
    if (d) d.dataset.cfg = JSON.stringify(cfg||{}); 
  }

  // Store chart instances and their data for updates
  const chartInstances = new Map();
  const chartData = new Map();
  
  // Store table instances and their data for updates
  const tableInstances = new Map();
  const tableData = new Map();
  // Store full GeoJSON features with geometry for zoom functionality
  const tableFeatures = new Map();
  
  // Store counter instances and their data for updates
  const counterInstances = new Map();
  const counterData = new Map();

  async function renderChart(divId, cfg, filterByBounds = false){
    const el = document.getElementById(divId); if (!el) return;
    if (!cfg || !cfg.type){
      const x = Array.from({length:8}, (_,i)=>`Item ${i+1}`), y = x.map(()=>Math.round(Math.random()*100));
      Plotly.newPlot(el, [{type:'bar', x, y}], {margin:{t:32,l:40,r:16,b:32}, title:'Chart (âš™ to configure)'}, {responsive:true, displaylogo:false});
      return;
    }
    
    let rows = [];
    try{
      // If we have cached data and not filtering by bounds, use cached data
      if (!filterByBounds && chartData.has(divId)) {
        rows = chartData.get(divId);
      } else {
        // Fetch fresh data with bounds if filtering
        const bounds = filterByBounds && window.currentMap ? window.currentMap.getBounds() : null;
        rows = await wfsFetchRows({ 
          typeName: cfg.wfs?.typeName, 
          limit: cfg.wfs?.limit||2000, 
          cql: cfg.wfs?.cql||'',
          bounds: bounds
        });
        // Cache the data only if not filtering by bounds
        if (!filterByBounds) {
          chartData.set(divId, rows);
        }
      }
    }catch(e){
      Plotly.newPlot(el, [], {title:'WFS error', margin:{t:32}}, {responsive:true, displaylogo:false});
      return;
    }
    if (!rows.length){ Plotly.newPlot(el, [], {title:'No rows', margin:{t:32}}, {responsive:true, displaylogo:false}); return; }

    const xField = cfg.xField, yField = cfg.yField, agg=cfg.agg||'sum';
    if (!xField || (!yField && cfg.type!=='pie')){ Plotly.newPlot(el, [], {title:'Pick fields in âš™', margin:{t:32}}, {responsive:true, displaylogo:false}); return; }

    const groups=new Map();
    for (const r of rows){ const k=String(r[xField]); const v=parseFloat(String(r[yField]??'').replace(/[, ]/g,'')); if(!groups.has(k)) groups.set(k,[]); if(isFinite(v)) groups.get(k).push(v); }
    const labels=[...groups.keys()];
    const values=labels.map(k=>{ const a=groups.get(k); if(!a.length) return 0; if(agg==='count') return a.length; if(agg==='avg') return a.reduce((s,v)=>s+v,0)/a.length; return a.reduce((s,v)=>s+v,0); });

    // Update title to show if data is filtered by map bounds
    const title = filterByBounds ? 
      `${cfg.title || ''} (${rows.length} features in view)` : 
      (cfg.title || '');

    if (cfg.type==='pie'){
      Plotly.newPlot(el,[{type:'pie',labels,values}],{margin:{t:32,l:16,r:16,b:16},title:title},{responsive:true,displaylogo:false});
    } else {
      const t=(cfg.type==='line')?'scatter':'bar', mode=(cfg.type==='line')?'lines+markers':undefined;
      Plotly.newPlot(el,[{type:t,mode,x:labels,y:values}],{margin:{t:32,l:40,r:16,b:32},title:title,xaxis:{title:xField},yaxis:{title:yField||agg}},{responsive:true,displaylogo:false});
    }
    
    // Store chart instance for updates
    chartInstances.set(divId, { element: el, config: cfg });
  }

  // ------- Table render -------
  async function renderTable(divId, cfg, filterByBounds = false){
    const el = document.getElementById(divId); if (!el) return;
    
    // Show default table if no configuration
    if (!cfg || !cfg.wfs || !cfg.wfs.typeName){
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;"><h4>Table (âš™ to configure)</h4><p>Configure the table to display WFS data</p></div>';
      return;
    }
    
    let rows = [];
    let fullFeatures = [];
    try{
      // If we have cached data and not filtering by bounds, use cached data
      if (!filterByBounds && tableData.has(divId)) {
        rows = tableData.get(divId);
        fullFeatures = tableFeatures.get(divId) || [];
      } else {
        // Fetch fresh data with bounds if filtering
        const bounds = filterByBounds && window.currentMap ? window.currentMap.getBounds() : null;
        rows = await wfsFetchRows({ 
          typeName: cfg.wfs.typeName, 
          limit: cfg.wfs.limit||2000, 
          cql: cfg.wfs.cql||'',
          bounds: bounds
        });
        // Fetch full features with geometry for zoom functionality
        fullFeatures = await wfsFetchFullFeatures({
          typeName: cfg.wfs.typeName,
          limit: cfg.wfs.limit||2000,
          cql: cfg.wfs.cql||'',
          bounds: bounds
        });
        // Cache the data only if not filtering by bounds
        if (!filterByBounds) {
          tableData.set(divId, rows);
          tableFeatures.set(divId, fullFeatures);
        }
      }
    }catch(e){
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#dc2626;"><h4>WFS Error</h4><p>Failed to fetch data</p></div>';
      return;
    }
    
    if (!rows.length){ 
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;"><h4>No Data</h4><p>No features found</p></div>'; 
      return; 
    }

    // Get all unique field names from the data
    const allFields = [...new Set(rows.flatMap(row => Object.keys(row)))];
    
    // Use configured fields if available, otherwise use all fields
    let displayFields = cfg.fields && cfg.fields.length > 0 ? cfg.fields : allFields.slice(0, 10); // Limit to 10 fields by default
    
    // Apply column ordering if specified
    if (cfg.columnOrder && cfg.columnOrder.length > 0) {
      // Reorder fields according to columnOrder, but only include fields that are actually selected
      const orderedFields = cfg.columnOrder.filter(field => displayFields.includes(field));
      // Add any remaining fields that weren't in the order (newly selected fields)
      const remainingFields = displayFields.filter(field => !cfg.columnOrder.includes(field));
      displayFields = [...orderedFields, ...remainingFields];
    }
    
    // Update title to show if data is filtered by map bounds
    const title = filterByBounds ? 
      `${cfg.title || ''} (${rows.length} features in view)` : 
      (cfg.title || '');
    
    // SVG icon for Zoom To button
    const zoomIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    
    // Build table HTML
    let tableHtml = '<div style="height:100%;overflow:auto;">';
    if (title) tableHtml += `<h4 style="margin:0 0 10px 0;padding:0 8px;">${title}</h4>`;
    tableHtml += '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    
    // Header
    tableHtml += '<thead><tr style="background:#f8fafc;border-bottom:2px solid #e5e7eb;">';
    // Add Zoom To column header first
    tableHtml += `<th style="padding:8px;text-align:center;border-right:1px solid #e5e7eb;width:60px;">${zoomIconSvg}</th>`;
    displayFields.forEach(field => {
      tableHtml += `<th style="padding:8px;text-align:left;border-right:1px solid #e5e7eb;">${field}</th>`;
    });
    tableHtml += '</tr></thead>';
    
    // Body
    tableHtml += '<tbody>';
    const displayedRows = rows.slice(0, cfg.limit || 100);
    displayedRows.forEach((row, idx) => { // Limit displayed rows
      tableHtml += `<tr style="border-bottom:1px solid #f3f4f6;${idx % 2 === 0 ? 'background:#fff' : 'background:#f9fafb'}">`;
      // Add Zoom To button cell first
      const feature = fullFeatures[idx];
      const hasGeometry = feature && feature.geometry;
      if (hasGeometry) {
        tableHtml += `<td style="padding:6px 8px;border-right:1px solid #f3f4f6;text-align:center;"><button class="zoom-to-btn" data-feature-index="${idx}" style="background:none;border:none;cursor:pointer;padding:4px;display:inline-flex;align-items:center;justify-content:center;opacity:0.7;transition:opacity 0.2s;" title="Zoom to feature" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">${zoomIconSvg}</button></td>`;
      } else {
        tableHtml += `<td style="padding:6px 8px;border-right:1px solid #f3f4f6;text-align:center;"></td>`;
      }
      // Add data fields
      displayFields.forEach(field => {
        const value = row[field];
        const displayValue = value !== null && value !== undefined ? String(value) : '';
        tableHtml += `<td style="padding:6px 8px;border-right:1px solid #f3f4f6;">${displayValue}</td>`;
      });
      tableHtml += '</tr>';
    });
    tableHtml += '</tbody></table>';
    
    if (rows.length > (cfg.limit || 100)) {
      tableHtml += `<div style="padding:8px;text-align:center;color:#6b7280;font-size:11px;">Showing ${cfg.limit || 100} of ${rows.length} rows</div>`;
    }
    
    tableHtml += '</div>';
    el.innerHTML = tableHtml;
    
    // Add click handlers for zoom buttons
    const zoomButtons = el.querySelectorAll('.zoom-to-btn');
    zoomButtons.forEach((btn) => {
      btn.addEventListener('click', function() {
        const featureIndex = parseInt(this.getAttribute('data-feature-index'));
        const feature = fullFeatures[featureIndex];
        if (feature && feature.geometry && window.currentMap) {
          try {
            // Remove previous highlight if it exists
            if (window.highlightedFeatureLayer) {
              window.currentMap.removeLayer(window.highlightedFeatureLayer);
              window.highlightedFeatureLayer = null;
            }
            
            // Create a highlighted GeoJSON layer with distinctive styling
            window.highlightedFeatureLayer = L.geoJSON(feature, {
              style: function(f) {
                return {
                  color: '#ff0000',        // Red border
                  weight: 4,               // Thick border
                  opacity: 0.9,
                  fillColor: '#ffff00',    // Yellow fill
                  fillOpacity: 0.3
                };
              },
              pointToLayer: function(feature, latlng) {
                return L.circleMarker(latlng, {
                  radius: 10,
                  color: '#ff0000',
                  weight: 4,
                  fillColor: '#ffff00',
                  fillOpacity: 0.7
                });
              }
            }).addTo(window.currentMap);
            
            // Create a Leaflet GeoJSON layer to get bounds
            const geoJsonLayer = L.geoJSON(feature);
            const bounds = geoJsonLayer.getBounds();
            if (bounds.isValid()) {
              // Expand bounds to zoom out more (less close)
              const sw = bounds.getSouthWest();
              const ne = bounds.getNorthEast();
              const latDiff = ne.lat - sw.lat;
              const lngDiff = ne.lng - sw.lng;
              const expandedBounds = L.latLngBounds(
                [sw.lat - latDiff * 0.3, sw.lng - lngDiff * 0.3],
                [ne.lat + latDiff * 0.3, ne.lng + lngDiff * 0.3]
              );
              window.currentMap.fitBounds(expandedBounds, { padding: [100, 100], maxZoom: 16 });
            }
          } catch (error) {
            console.error('Error zooming to feature:', error);
          }
        }
      });
    });
    
    // Store table instance for updates
    tableInstances.set(divId, { element: el, config: cfg });
  }

  // Function to update all charts and tables when map bounds change
  function updateAllCharts() {
    chartInstances.forEach((chartInfo, divId) => {
      renderChart(divId, chartInfo.config, true);
    });
    tableInstances.forEach((tableInfo, divId) => {
      renderTable(divId, tableInfo.config, true);
    });
    counterInstances.forEach((counterInfo, divId) => {
      renderCounter(divId, counterInfo.config, true);
    });
  }

  // ------- Legend render -------
  function renderLegend(divId, cfg){
    const el = document.getElementById(divId); if (!el) return;
    
    // Show default legend if no configuration
    if (!cfg || !cfg.layers || cfg.layers.length === 0){
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;"><h4>Legend (âš™ to configure)</h4><p>Configure the legend to display WMS layer legends</p></div>';
      return;
    }
    
    // Build legend HTML
    let legendHtml = '<div style="height:100%;overflow:auto;">';
    if (cfg.title) legendHtml += `<h4 style="margin:0 0 10px 0;padding:0 8px;">${cfg.title}</h4>`;
    
    cfg.layers.forEach(layerName => {
      const legendUrl = `${WMS_SVC_URL}${WMS_SVC_URL.includes('?') ? '&' : '?'}SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=${encodeURIComponent(layerName)}&FORMAT=image/png&EXCEPTIONS=application/vnd.ogc.se_xml`;
      legendHtml += `
        <div style="margin-bottom:10px;">
          <div style="font-weight:600;margin-bottom:5px;font-size:12px;">${layerName}</div>
          <img src="${legendUrl}" alt="Legend for ${layerName}" style="max-width:100%;height:auto;" onerror="this.parentNode.innerHTML='<div style=&quot;color:#dc2626;font-size:11px;&quot;>Error loading legend for ${layerName}</div>'">
        </div>
      `;
    });
    
    legendHtml += '</div>';
    el.innerHTML = legendHtml;
  }

  // ------- Counter render -------
  async function renderCounter(divId, cfg, filterByBounds = false){
    const el = document.getElementById(divId); if (!el) return;
    
    // Show default counter if no configuration
    if (!cfg || !cfg.wfs || !cfg.wfs.typeName || !cfg.field){
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;"><h4>Counter (âš™ to configure)</h4><p>Configure the counter to display aggregated data</p></div>';
      return;
    }
    
    let rows = [];
    try{
      // If we have cached data and not filtering by bounds, use cached data
      if (!filterByBounds && counterData.has(divId)) {
        rows = counterData.get(divId);
      } else {
        // Fetch fresh data with bounds if filtering
        const bounds = filterByBounds && window.currentMap ? window.currentMap.getBounds() : null;
        rows = await wfsFetchRows({ 
          typeName: cfg.wfs.typeName, 
          limit: cfg.wfs.limit||2000, 
          cql: cfg.wfs.cql||'',
          bounds: bounds
        });
        // Cache the data only if not filtering by bounds
        if (!filterByBounds) {
          counterData.set(divId, rows);
        }
      }
    }catch(e){
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#dc2626;"><h4>WFS Error</h4><p>Failed to fetch data</p></div>';
      return;
    }
    
    if (!rows.length){ 
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;"><h4>No Data</h4><p>No features found</p></div>'; 
      return; 
    }

    // Calculate the aggregated value based on the operation type
    let value = 0;
    const field = cfg.field;
    const operation = cfg.operation || 'count';
    
    if (operation === 'count') {
      value = rows.length;
    } else if (operation === 'sum') {
      value = rows.reduce((sum, row) => {
        const val = parseFloat(String(row[field] ?? '').replace(/[, ]/g, ''));
        return sum + (isFinite(val) ? val : 0);
      }, 0);
    } else if (operation === 'avg') {
      const validValues = rows.map(row => parseFloat(String(row[field] ?? '').replace(/[, ]/g, ''))).filter(val => isFinite(val));
      value = validValues.length > 0 ? validValues.reduce((sum, val) => sum + val, 0) / validValues.length : 0;
    }
    
    // Format the value
    let formattedValue = value;
    if (operation === 'avg') {
      formattedValue = value.toFixed(2);
    } else if (operation === 'sum' && Math.abs(value) >= 1000) {
      formattedValue = value.toLocaleString();
    }
    
    // Update title to show if data is filtered by map bounds
    const title = filterByBounds ? 
      `${cfg.title || ''} (${rows.length} features in view)` : 
      (cfg.title || '');
    
    // Build counter HTML
    let counterHtml = '<div style="height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;">';
    
    if (title) {
      counterHtml += `<h4 style="margin:0 0 10px 0;font-size:14px;color:#6b7280;text-align:center;">${title}</h4>`;
    }
    
    counterHtml += `<div style="font-size:48px;font-weight:bold;color:#2563eb;text-align:center;line-height:1;">${formattedValue}</div>`;
    counterHtml += `<div style="font-size:12px;color:#9ca3af;text-align:center;margin-top:5px;text-transform:uppercase;">${operation} of ${field}</div>`;
    
    if (filterByBounds) {
      counterHtml += `<div style="font-size:10px;color:#6b7280;text-align:center;margin-top:5px;">(${rows.length} features in view)</div>`;
    }
    
    counterHtml += '</div>';
    el.innerHTML = counterHtml;
    
    // Store counter instance for updates
    counterInstances.set(divId, { element: el, config: cfg });
  }

  function openChartCfg(cardEl){
    console.log('Opening chart config modal');
    const cfg = getCfg(cardEl) || {};
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(800px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Chart</div>
      <div class="mb">
        <div class="row"><label>Title</label><input id="chartTitle" placeholder="Enter chart title" value="${cfg?.title||''}"></div>
        <div class="row"><label>WFS endpoint</label><input value="${BASE}" readonly></div>
        <div class="row"><label>Layer (typeName)</label><select id="wfsLayer"><option>Loading...ï¿½</option></select></div>
        <div class="row"><label>X field</label><select id="xField"><option value="">ï¿½â† choose layerï¿½</option></select></div>
        <div class="row" id="rowY"><label>Y field</label><select id="yField"><option value="">ï¿½â† choose layerï¿½</option></select></div>
        <div class="row" id="rowAgg"><label>Aggregate</label>
          <select id="agg"><option value="sum"${cfg.agg==='sum'?' selected':''}>sum</option>
                          <option value="avg"${cfg.agg==='avg'?' selected':''}>avg</option>
                          <option value="count"${cfg.agg==='count'?' selected':''}>count</option></select></div>
        <div class="row"><label>CQL filter</label><input id="cql" placeholder="population > 10000" value="${cfg?.wfs?.cql||''}"></div>
        <div class="row"><label>Limit</label><input id="limit" type="number" min="1" max="50000" value="${cfg?.wfs?.limit||2000}"></div>
        <div class="row"><label>Chart type</label>
          <select id="ctype"><option value="bar"${cfg.type==='bar'?' selected':''}>Bar</option>
                             <option value="line"${cfg.type==='line'?' selected':''}>Line</option>
                             <option value="pie"${cfg.type==='pie'?' selected':''}>Pie</option></select></div>
        <div class="help" id="preview"></div>
      </div>
      <div class="mf"><button class="btn" id="loadBtn2">Load</button><button class="btn btn-primary" id="applyBtn">Apply</button><button class="btn" id="cancelBtn">Cancel</button></div></div>`;
    document.body.appendChild(bd);
    console.log('Chart modal appended to body, backdrop element:', bd);

    const layerSel=bd.querySelector('#wfsLayer'), xSel=bd.querySelector('#xField'), ySel=bd.querySelector('#yField');
    const preview=bd.querySelector('#preview'), ctype=bd.querySelector('#ctype'), rowAgg=bd.querySelector('#rowAgg');
    const toggleAgg=()=> rowAgg.style.display = (ctype.value==='pie') ? 'none':'grid';
    ctype.addEventListener('change', toggleAgg); toggleAgg();

    (async ()=>{ try{
      const ls = await wfsGetLayers();
      
      if (!ls.length){ 
        layerSel.innerHTML='<option value="">(none)</option>'; 
        if (!accessKey) {
          preview.textContent='No layers found. Authentication may be required. Add ?access_key=YOUR_KEY to the URL.';
        } else {
          preview.textContent='No layers found. Check if WFS/WMS services are available.';
        }
        return; 
      }
      layerSel.innerHTML = ls.map(n=>`<option value="${n}">${n}</option>`).join('');
      if (cfg?.wfs?.typeName && ls.includes(cfg.wfs.typeName)) layerSel.value = cfg.wfs.typeName;
      preview.textContent = `Found ${ls.length} layer(s). ${ls.length > 0 ? 'Select a layer to configure fields.' : ''}`;
      await fillFields();
    }catch(e){ 
      console.error('Layer loading error:', e);
      if (e.message === 'Authentication required') {
        preview.textContent='Authentication required. Add ?access_key=YOUR_KEY to the URL.';
      } else {
        preview.textContent='Failed to load layers. Check console for details.'; 
      }
    } })();

    async function fillFields(){
      const tn = layerSel.value;
      if (!tn) {
        preview.textContent = 'Please select a layer first.';
        return;
      }
      
      try{
        const fields = await wfsDescribe(tn);
        if (fields.length > 0) {
          const opts = fields.map(n=>`<option value="${n}">${n}</option>`).join('');
          xSel.innerHTML = opts; ySel.innerHTML = opts;
          if (cfg.xField) xSel.value = cfg.xField;
          if (cfg.yField) ySel.value = cfg.yField;
          preview.innerHTML = `Layer <code>${tn}</code> fields: <code>${fields.join(', ')}</code>`;
        } else {
          preview.textContent = `No fields found for layer ${tn}. This might be a WMS-only layer.`;
          xSel.innerHTML = '<option value="">No fields available</option>';
          ySel.innerHTML = '<option value="">No fields available</option>';
        }
      }catch(e){ 
        console.error('DescribeFeatureType failed:', e);
        preview.textContent = `Failed to get fields for layer ${tn}. This might be a WMS-only layer or WFS is not available.`;
        xSel.innerHTML = '<option value="">Fields unavailable</option>';
        ySel.innerHTML = '<option value="">Fields unavailable</option>';
      }
    }
    layerSel.addEventListener('change', fillFields);

    bd.querySelector('#loadBtn2').onclick = async ()=>{
      try{
        const tn=layerSel.value; const rows=await wfsFetchRows({typeName:tn,limit:+bd.querySelector('#limit').value||2000,cql:bd.querySelector('#cql').value||''});
        preview.innerHTML = `Loaded <b>${rows.length}</b> features from <code>${tn}</code>.`;
      }catch{ preview.textContent='GetFeature failed.'; }
    };
    bd.querySelector('#cancelBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyBtn').onclick = ()=>{
      const title = bd.querySelector('#chartTitle').value.trim();
      const newCfg = { type: ctype.value, source:{kind:'wfs'}, title: title,
        xField: xSel.value || '', yField: ySel.value || '', agg: bd.querySelector('#agg').value,
        wfs:{ typeName: layerSel.value, cql: bd.querySelector('#cql').value.trim(), limit:+bd.querySelector('#limit').value||2000 } };
      setCfg(cardEl, newCfg); 
      // Update the card title
      cardEl.querySelector('.title').innerText = title || 'Chart';
      const cid=cardEl.querySelector('[id$="_chart"]').id; renderChart(cid, newCfg); bd.remove();
    };
  }

  function openMapCfg(cardEl){
    console.log('Opening map config modal');
    const currentTitle = cardEl.querySelector('.title').innerText;
    const cfg = getCfg(cardEl) || {};
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(800px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Map</div>
      <div class="mb" style="padding:2rem;background-color:#fff;max-height:70vh;overflow-y:auto">
        <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Title</label><input id="mapTitle" placeholder="Enter map title" value="${currentTitle}" style="width:100%;background:#fff;color:#495057;border:2px solid #e9ecef;border-radius:8px;padding:0.75rem 1rem;transition:all 0.3s ease;font-size:0.95rem"></div>
        <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Enable Popups</label><input type="checkbox" id="enablePopups" ${cfg.popups?.enabled !== false ? 'checked' : ''} style="width:1.2rem;height:1.2rem;margin-top:0.1rem;border:2px solid #dee2e6;border-radius:4px;transition:all 0.3s ease"></div>
        <div id="popupConfig" style="display:${cfg.popups?.enabled !== false ? 'block' : 'none'};">
          <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Popup Fields</label><select id="popupFields" multiple style="height:100px;width:100%;background:#fff;color:#495057;border:2px solid #e9ecef;border-radius:8px;padding:0.75rem 1rem;transition:all 0.3s ease;font-size:0.95rem"><option>All fields will be shown by default</option></select></div>
          <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Popup Title Field</label><select id="popupTitleField" style="width:100%;background:#fff;color:#495057;border:2px solid #e9ecef;border-radius:8px;padding:0.75rem 1rem;transition:all 0.3s ease;font-size:0.95rem"><option value="">Auto (first field)</option></select></div>
          <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Max Features</label><input id="popupLimit" type="number" min="1" max="100" value="${cfg.popups?.limit || 10}" style="width:100%;background:#fff;color:#495057;border:2px solid #e9ecef;border-radius:8px;padding:0.75rem 1rem;transition:all 0.3s ease;font-size:0.95rem"></div>
          <div class="row" style="display:grid;grid-template-columns:200px 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem"><label style="color:#495057;font-weight:600;margin-bottom:0.5rem">Search Radius (km)</label><input id="popupRadius" type="number" min="0.1" max="50" step="0.1" value="${cfg.popups?.radius || 1}" style="width:100%;background:#fff;color:#495057;border:2px solid #e9ecef;border-radius:8px;padding:0.75rem 1rem;transition:all 0.3s ease;font-size:0.95rem"></div>
        </div>
        <div class="help" id="mapPreview" style="font-size:0.85rem;color:#6c757d;margin-top:0.25rem">Map configuration with popup support. Popups will show features from all layers automatically.</div>
      </div>
      <div class="mf" style="display:flex;justify-content:flex-end;align-items:center;background-color:#f8f9fa;border-top:1px solid #e9ecef;padding:1.5rem 2rem;border-radius:0 0 16px 16px;gap:0.5rem"><button class="btn" id="loadMapBtn" style="padding:0.75rem 1.5rem;border-radius:8px;font-weight:600;font-size:0.95rem;transition:all 0.3s ease;border:2px solid #6c757d;color:#6c757d;background:transparent">Test Popup</button><button class="btn btn-primary" id="applyMapBtn" style="padding:0.75rem 1.5rem;border-radius:8px;font-weight:600;font-size:0.95rem;transition:all 0.3s ease;background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-color:#0d6efd;color:#fff">Apply</button><button class="btn" id="cancelMapBtn" style="padding:0.75rem 1.5rem;border-radius:8px;font-weight:600;font-size:0.95rem;transition:all 0.3s ease;border:2px solid #6c757d;color:#6c757d;background:transparent">Cancel</button></div></div>`;
    document.body.appendChild(bd);
    console.log('Modal appended to body, backdrop element:', bd);

    const popupToggle = bd.querySelector('#enablePopups');
    const popupConfig = bd.querySelector('#popupConfig');
    const fieldsSel = bd.querySelector('#popupFields');
    const titleFieldSel = bd.querySelector('#popupTitleField');
    const preview = bd.querySelector('#mapPreview');

    // Toggle popup configuration visibility
    popupToggle.addEventListener('change', () => {
      popupConfig.style.display = popupToggle.checked ? 'block' : 'none';
    });

    // Load available fields for popup configuration
    (async () => {
      try {
        const ls = await wfsGetLayers();
        if (!ls.length) {
          preview.textContent = 'No WFS layers available for popups.';
          return;
        }
        await fillPopupFields();
      } catch(e) {
        preview.textContent = 'Failed to load layers for popup configuration.';
      }
    })();

    async function fillPopupFields() {
      try {
        const ls = await wfsGetLayers();
        if (ls.length > 0) {
          // Get fields from the first layer as a sample
          const fields = await wfsDescribe(ls[0]);
          if (fields.length > 0) {
            const opts = fields.map(n => `<option value="${n}">${n}</option>`).join('');
            fieldsSel.innerHTML = opts;
            titleFieldSel.innerHTML = '<option value="">Auto (first field)</option>' + opts;
            
            // Pre-select configured fields
            if (cfg.popups?.fields) {
              cfg.popups.fields.forEach(field => {
                const option = fieldsSel.querySelector(`option[value="${field}"]`);
                if (option) option.selected = true;
              });
            }
            
            if (cfg.popups?.titleField) {
              titleFieldSel.value = cfg.popups.titleField;
            }
            
            preview.innerHTML = `Available fields: <code>${fields.join(', ')}</code><br><small>Hold Ctrl/Cmd to select multiple fields for popup content. Popups will work with all layers.</small>`;
          } else {
            preview.textContent = 'No fields found in available layers.';
            fieldsSel.innerHTML = '<option value="">No fields available</option>';
            titleFieldSel.innerHTML = '<option value="">No fields available</option>';
          }
        } else {
          preview.textContent = 'No layers available.';
        }
      } catch(e) {
        preview.textContent = 'Failed to get fields from available layers.';
        fieldsSel.innerHTML = '<option value="">Fields unavailable</option>';
        titleFieldSel.innerHTML = '<option value="">Fields unavailable</option>';
      }
    }

    bd.querySelector('#loadMapBtn').onclick = async () => {
      if (!popupToggle.checked) {
        preview.textContent = 'Enable popups first to test.';
        return;
      }
      try {
        const ls = await wfsGetLayers();
        if (ls.length > 0) {
          const rows = await wfsFetchRows({typeName: ls[0], limit: 5});
          preview.innerHTML = `Test successful! Found <b>${rows.length}</b> features in <code>${ls[0]}</code>. Popups will work with all layers.`;
        } else {
          preview.textContent = 'No layers available for testing.';
        }
      } catch(e) {
        preview.textContent = 'Popup test failed. Check layer configuration.';
      }
    };

    bd.querySelector('#cancelMapBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyMapBtn').onclick = ()=>{
      const title = bd.querySelector('#mapTitle').value.trim();
      const selectedFields = Array.from(fieldsSel.selectedOptions).map(opt => opt.value);
      const newCfg = {
        title: title,
        popups: popupToggle.checked ? {
          enabled: true,
          fields: selectedFields,
          titleField: titleFieldSel.value || '',
          limit: +bd.querySelector('#popupLimit').value || 10,
          radius: +bd.querySelector('#popupRadius').value || 1
        } : { enabled: false }
      };
      setCfg(cardEl, newCfg);
      cardEl.querySelector('.title').innerText = title || 'Map';
      
      // Update existing map with new popup configuration
      const existingMap = cardEl._leafletMap;
      if (existingMap) {
        // Remove existing click handlers
        existingMap.off('click');
        
        // Add new popup click handler if popups are enabled (or enable by default)
        if (newCfg.popups?.enabled !== false) {
          existingMap.on('click', async (e) => {
            try {
              const clickPoint = e.latlng;
              
              // First, try to get feature info from WMS GetFeatureInfo to identify the specific layer
              const wmsLayers = await wfsGetLayers();
              let clickedFeature = null;
              let clickedLayer = null;
              
              // Try GetFeatureInfo for each layer to find which one was clicked
              for (const layerName of wmsLayers) {
                try {
                  const featureInfo = await getFeatureInfo(existingMap, clickPoint, layerName);
                  if (featureInfo && featureInfo.features && featureInfo.features.length > 0) {
                    clickedFeature = featureInfo.features[0];
                    clickedLayer = layerName;
                    break;
                  }
                } catch (err) {
                  // Continue to next layer if this one fails
                  continue;
                }
              }
              
              if (clickedFeature && clickedLayer) {
                // Show popup for the specific clicked feature
                const featureData = clickedFeature.properties || clickedFeature;
                
                // If GetFeatureInfo returns generic data, try to get actual feature data via WFS
                if (!featureData || Object.keys(featureData).length === 0 || 
                    (Object.keys(featureData).length === 1 && featureData.id)) {
                  const radiusKm = newCfg.popups.radius || 1;
                  const buffer = radiusKm / 111;
                  const clickBounds = L.latLngBounds(
                    [clickPoint.lat - buffer, clickPoint.lng - buffer],
                    [clickPoint.lat + buffer, clickPoint.lng + buffer]
                  );

                  const features = await wfsFetchRows({
                    typeName: clickedLayer,
                    limit: 1, // Only get the closest feature
                    bounds: clickBounds
                  });

                  if (features.length > 0) {
                    const popupContent = createPopupContent([features[0]], newCfg.popups);
                    L.popup()
                      .setLatLng(clickPoint)
                      .setContent(popupContent)
                      .openOn(existingMap);
                  }
                  // Don't show popup if no features found
                } else {
                  // Use the GetFeatureInfo data if it looks valid
                  const popupContent = createPopupContent([featureData], newCfg.popups);
                  L.popup()
                    .setLatLng(clickPoint)
                    .setContent(popupContent)
                    .openOn(existingMap);
                }
              } else {
                // Fallback: use the configured layer with bounding box search
                const radiusKm = newCfg.popups.radius || 1;
                const buffer = radiusKm / 111;
                const clickBounds = L.latLngBounds(
                  [clickPoint.lat - buffer, clickPoint.lng - buffer],
                  [clickPoint.lat + buffer, clickPoint.lng + buffer]
                );

                  const features = await wfsFetchRows({
                    typeName: clickedLayer || newCfg.popups.layer,
                    limit: newCfg.popups.limit || 10,
                    bounds: clickBounds
                  });

                if (features.length > 0) {
                  const popupContent = createPopupContent(features, newCfg.popups);
                  L.popup()
                    .setLatLng(clickPoint)
                    .setContent(popupContent)
                    .openOn(existingMap);
                }
                // Don't show popup if no features found
              }
            } catch(error) {
              console.error('Popup error:', error);
              L.popup()
                .setLatLng(e.latlng)
                .setContent('<div style="padding: 8px; color: #dc2626;">Error loading popup data. Check console for details.</div>')
                .openOn(existingMap);
            }
          });
        }
      }
      
      bd.remove();
    };
  }

  function openTextCfg(cardEl){
    const currentTitle = cardEl.querySelector('.title').innerText;
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(600px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Text</div>
      <div class="mb">
        <div class="row"><label>Title</label><input id="textTitle" placeholder="Enter text title" value="${currentTitle}"></div>
        <div class="help">Text configuration options will be added here in future updates.</div>
      </div>
      <div class="mf"><button class="btn btn-primary" id="applyTextBtn">Apply</button><button class="btn" id="cancelTextBtn">Cancel</button></div></div>`;
    document.body.appendChild(bd);

    bd.querySelector('#cancelTextBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyTextBtn').onclick = ()=>{
      const title = bd.querySelector('#textTitle').value.trim();
      cardEl.querySelector('.title').innerText = title || 'Text';
      bd.remove();
    };
  }

  function openLegendCfg(cardEl){
    const cfg = getCfg(cardEl) || {};
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(800px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Legend</div>
      <div class="mb">
        <div class="row"><label>Title</label><input id="legendTitle" placeholder="Enter legend title" value="${cfg?.title||''}"></div>
        <div class="row"><label>WMS endpoint</label><input value="${WMS_SVC_URL}" readonly></div>
        <div class="row"><label>Layers for legend</label><select id="legendLayers" multiple style="height:100px;"><option>Loading...</option></select></div>
        <div class="help" id="legendPreview">Loading available WMS layers...</div>
      </div>
      <div class="mf"><button class="btn" id="loadLegendBtn">Test Legend</button><button class="btn btn-primary" id="applyLegendBtn">Apply</button><button class="btn" id="cancelLegendBtn">Cancel</button></div></div>`;
    document.body.appendChild(bd);

    const layerSel=bd.querySelector('#legendLayers');
    const preview=bd.querySelector('#legendPreview');

    (async ()=>{ try{
      const ls = await wfsGetLayers();
      
      if (!ls.length){ 
        layerSel.innerHTML='<option value="">(none)</option>'; 
        if (!accessKey) {
          preview.textContent='No layers found. Authentication may be required. Add ?access_key=YOUR_KEY to the URL.';
        } else {
          preview.textContent='No layers found. Check if WMS services are available.';
        }
        return; 
      }
      layerSel.innerHTML = ls.map(n=>`<option value="${n}">${n}</option>`).join('');
      
      // Pre-select configured layers
      if (cfg.layers) {
        cfg.layers.forEach(layerName => {
          const option = layerSel.querySelector(`option[value="${layerName}"]`);
          if (option) option.selected = true;
        });
      }
      
      preview.innerHTML = `Found ${ls.length} layer(s). <br><small>Hold Ctrl/Cmd to select multiple layers for legend display</small>`;
    }catch(e){ 
      console.error('Layer loading error:', e);
      if (e.message === 'Authentication required') {
        preview.textContent='Authentication required. Add ?access_key=YOUR_KEY to the URL.';
      } else {
        preview.textContent='Failed to load layers. Check console for details.'; 
      }
    } })();

    bd.querySelector('#loadLegendBtn').onclick = async ()=>{
      const selectedLayers = Array.from(layerSel.selectedOptions).map(opt => opt.value);
      if (selectedLayers.length === 0) {
        preview.textContent = 'Please select at least one layer to test.';
        return;
      }
      try{
        preview.innerHTML = `Testing legend for layers: <b>${selectedLayers.join(', ')}</b>...`;
        
        // Test each layer's legend URL
        const testResults = [];
        for (const layer of selectedLayers) {
          const testUrl = `${WMS_SVC_URL}${WMS_SVC_URL.includes('?') ? '&' : '?'}SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=${encodeURIComponent(layer)}&FORMAT=image/png`;
          console.log(`Testing legend URL for ${layer}:`, testUrl);
          
          try {
            const response = await fetch(testUrl, {credentials:'same-origin'});
            if (response.ok) {
              testResults.push(`âœ… ${layer}: OK`);
            } else {
              testResults.push(`âŒ ${layer}: HTTP ${response.status}`);
            }
          } catch (error) {
            testResults.push(`âŒ ${layer}: ${error.message}`);
          }
        }
        
        preview.innerHTML = `Legend test results:<br>${testResults.join('<br>')}`;
      }catch(error){ 
        preview.textContent='Legend test failed: ' + error.message; 
      }
    };
    bd.querySelector('#cancelLegendBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyLegendBtn').onclick = ()=>{
      const title = bd.querySelector('#legendTitle').value.trim();
      const selectedLayers = Array.from(layerSel.selectedOptions).map(opt => opt.value);
      const newCfg = { 
        title: title,
        source:{kind:'wms'}, 
        layers: selectedLayers
      };
      setCfg(cardEl, newCfg); 
      // Update the card title
      cardEl.querySelector('.title').innerText = title || 'Legend';
      const lid=cardEl.querySelector('[id$="_legend"]').id; renderLegend(lid, newCfg); bd.remove();
    };
  }

  function openTableCfg(cardEl){
    const cfg = getCfg(cardEl) || {};
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(900px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Table</div>
      <div class="mb">
        <div class="row"><label>Title</label><input id="tableTitle" placeholder="Enter table title" value="${cfg?.title||''}"></div>
        <div class="row"><label>WFS endpoint</label><input value="${BASE}" readonly></div>
        <div class="row"><label>Layer (typeName)</label><select id="tableLayer"><option>Loading...</option></select></div>
        <div class="row"><label>Fields to display</label><select id="tableFields" multiple style="height:100px;"><option>Select layer first...</option></select></div>
        <div class="row"><label>Column order</label>
          <div id="columnOrderContainer" style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#f9fafb;min-height:60px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:8px;">Drag to reorder columns (select fields first)</div>
            <div id="columnOrderList" style="min-height:40px;"></div>
          </div>
        </div>
        <div class="row"><label>CQL filter</label><input id="tableCql" placeholder="population > 10000" value="${cfg?.wfs?.cql||''}"></div>
        <div class="row"><label>Max rows to fetch</label><input id="tableLimit" type="number" min="1" max="50000" value="${cfg?.wfs?.limit||2000}"></div>
        <div class="row"><label>Max rows to display</label><input id="tableDisplayLimit" type="number" min="1" max="1000" value="${cfg?.displayLimit||100}"></div>
        <div class="help" id="tablePreview"></div>
      </div>
      <div class="mf"><button class="btn" id="loadTableBtn">Load</button><button class="btn btn-primary" id="applyTableBtn">Apply</button><button class="btn" id="cancelTableBtn">Cancel</button></div></div>`;
    document.body.appendChild(bd);

    const layerSel=bd.querySelector('#tableLayer'), fieldsSel=bd.querySelector('#tableFields');
    const preview=bd.querySelector('#tablePreview');
    const columnOrderList=bd.querySelector('#columnOrderList');
    
    // Column ordering functionality
    let columnOrder = cfg.columnOrder || [];
    
    function updateColumnOrder() {
      columnOrderList.innerHTML = '';
      columnOrder.forEach((field, index) => {
        const item = document.createElement('div');
        item.className = 'column-item';
        item.draggable = true;
        item.dataset.field = field;
        item.innerHTML = `
          <span class="drag-handle">â‹®â‹®</span>
          <span class="field-name">${field}</span>
          <span class="remove-btn" title="Remove column">Ã—</span>
        `;
        
        // Drag and drop functionality
        item.addEventListener('dragstart', (e) => {
          item.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/html', item.outerHTML);
          e.dataTransfer.setData('text/plain', index.toString());
        });
        
        item.addEventListener('dragend', () => {
          item.classList.remove('dragging');
        });
        
        item.addEventListener('dragover', (e) => {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
        });
        
        item.addEventListener('drop', (e) => {
          e.preventDefault();
          const dragIndex = parseInt(e.dataTransfer.getData('text/plain'));
          const dropIndex = index;
          
          if (dragIndex !== dropIndex) {
            const draggedField = columnOrder[dragIndex];
            columnOrder.splice(dragIndex, 1);
            columnOrder.splice(dropIndex, 0, draggedField);
            updateColumnOrder();
          }
        });
        
        // Remove column functionality
        item.querySelector('.remove-btn').addEventListener('click', () => {
          columnOrder = columnOrder.filter(f => f !== field);
          updateColumnOrder();
          // Also remove from fields selection
          const option = fieldsSel.querySelector(`option[value="${field}"]`);
          if (option) option.selected = false;
        });
        
        columnOrderList.appendChild(item);
      });
    }
    
    // Update column order when fields are selected/deselected
    fieldsSel.addEventListener('change', () => {
      const selectedFields = Array.from(fieldsSel.selectedOptions).map(opt => opt.value);
      
      // Add new fields to the end of the order
      selectedFields.forEach(field => {
        if (!columnOrder.includes(field)) {
          columnOrder.push(field);
        }
      });
      
      // Remove fields that are no longer selected
      columnOrder = columnOrder.filter(field => selectedFields.includes(field));
      
      updateColumnOrder();
    });
    
    // Initialize column order if we have existing configuration
    if (cfg.fields && cfg.fields.length > 0) {
      columnOrder = cfg.columnOrder || cfg.fields;
      updateColumnOrder();
    }

    (async ()=>{ try{
      const ls = await wfsGetLayers();
      
      if (!ls.length){ 
        layerSel.innerHTML='<option value="">(none)</option>'; 
        if (!accessKey) {
          preview.textContent='No layers found. Authentication may be required. Add ?access_key=YOUR_KEY to the URL.';
        } else {
          preview.textContent='No layers found. Check if WFS/WMS services are available.';
        }
        return; 
      }
      layerSel.innerHTML = ls.map(n=>`<option value="${n}">${n}</option>`).join('');
      if (cfg?.wfs?.typeName && ls.includes(cfg.wfs.typeName)) layerSel.value = cfg.wfs.typeName;
      preview.textContent = `Found ${ls.length} layer(s). Select a layer to configure fields.`;
      await fillTableFields();
    }catch(e){ 
      console.error('Layer loading error:', e);
      if (e.message === 'Authentication required') {
        preview.textContent='Authentication required. Add ?access_key=YOUR_KEY to the URL.';
      } else {
        preview.textContent='Failed to load layers. Check console for details.'; 
      }
    } })();

    async function fillTableFields(){
      const tn = layerSel.value;
      if (!tn) {
        preview.textContent = 'Please select a layer first.';
        return;
      }
      
      try{
        const fields = await wfsDescribe(tn);
        if (fields.length > 0) {
          const opts = fields.map(n=>`<option value="${n}">${n}</option>`).join('');
          fieldsSel.innerHTML = opts;
          
          // Pre-select configured fields
          if (cfg.fields) {
            cfg.fields.forEach(field => {
              const option = fieldsSel.querySelector(`option[value="${field}"]`);
              if (option) option.selected = true;
            });
          }
          
          preview.innerHTML = `Layer <code>${tn}</code> fields: <code>${fields.join(', ')}</code><br><small>Hold Ctrl/Cmd to select multiple fields</small>`;
        } else {
          preview.textContent = `No fields found for layer ${tn}. This might be a WMS-only layer.`;
          fieldsSel.innerHTML = '<option value="">No fields available</option>';
        }
      }catch(e){ 
        console.error('DescribeFeatureType failed:', e);
        preview.textContent = `Failed to get fields for layer ${tn}. This might be a WMS-only layer or WFS is not available.`;
        fieldsSel.innerHTML = '<option value="">Fields unavailable</option>';
      }
    }
    layerSel.addEventListener('change', fillTableFields);

    bd.querySelector('#loadTableBtn').onclick = async ()=>{
      try{
        const tn=layerSel.value; const rows=await wfsFetchRows({typeName:tn,limit:+bd.querySelector('#tableLimit').value||2000,cql:bd.querySelector('#tableCql').value||''});
        preview.innerHTML = `Loaded <b>${rows.length}</b> features from <code>${tn}</code>.`;
      }catch{ preview.textContent='GetFeature failed.'; }
    };
    bd.querySelector('#cancelTableBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyTableBtn').onclick = ()=>{
      const title = bd.querySelector('#tableTitle').value.trim();
      const selectedFields = Array.from(fieldsSel.selectedOptions).map(opt => opt.value);
      const newCfg = { 
        title: title,
        source:{kind:'wfs'}, 
        fields: selectedFields,
        columnOrder: columnOrder, // Save the column order
        displayLimit: +bd.querySelector('#tableDisplayLimit').value || 100,
        wfs:{ 
          typeName: layerSel.value, 
          cql: bd.querySelector('#tableCql').value.trim(), 
          limit:+bd.querySelector('#tableLimit').value||2000 
        } 
      };
      setCfg(cardEl, newCfg); 
      // Update the card title
      cardEl.querySelector('.title').innerText = title || 'Table';
      const tid=cardEl.querySelector('[id$="_table"]').id; renderTable(tid, newCfg); bd.remove();
    };
  }

  function openCounterCfg(cardEl){
    const cfg = getCfg(cardEl) || {};
    const bd = document.createElement('div'); bd.className='backdrop';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;visibility:visible;opacity:1;backdrop-filter:blur(2px)';
    bd.innerHTML = `<div class="modal" style="width:min(800px,calc(100% - 2rem));background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;position:relative;z-index:100000;visibility:visible;opacity:1;display:block;margin:2rem auto"><div class="mh" style="background:linear-gradient(135deg,#0d6efd 0%,#0b5ed7 100%);border-bottom:none;padding:1.5rem 2rem;border-radius:16px 16px 0 0;color:#fff;font-size:1.4rem;font-weight:600;margin:0">Configure Counter</div>
      <div class="mb">
        <div class="row"><label>Title</label><input id="counterTitle" placeholder="Enter counter title" value="${cfg?.title||''}"></div>
        <div class="row"><label>WFS endpoint</label><input value="${BASE}" readonly></div>
        <div class="row"><label>Layer (typeName)</label><select id="counterLayer"><option>Loading...</option></select></div>
        <div class="row"><label>Field to aggregate</label><select id="counterField"><option value="">â† choose layer first</option></select></div>
        <div class="row"><label>Operation</label>
          <select id="counterOperation">
            <option value="count"${cfg.operation==='count'?' selected':''}>Count (number of records)</option>
            <option value="sum"${cfg.operation==='sum'?' selected':''}>Sum (total value)</option>
            <option value="avg"${cfg.operation==='avg'?' selected':''}>Average (mean value)</option>
          </select></div>
        <div class="row"><label>CQL filter</label><input id="counterCql" placeholder="population > 10000" value="${cfg?.wfs?.cql||''}"></div>
        <div class="row"><label>Limit</label><input id="counterLimit" type="number" min="1" max="50000" value="${cfg?.wfs?.limit||2000}"></div>
        <div class="help" id="counterPreview"></div>
      </div>
      <div class="mf"><button class="btn" id="loadCounterBtn">Load</button><button class="btn btn-primary" id="applyCounterBtn">Apply</button><button class="btn" id="cancelCounterBtn">Cancel</button></div></div>`;
    document.body.appendChild(bd);

    const layerSel=bd.querySelector('#counterLayer'), fieldSel=bd.querySelector('#counterField');
    const preview=bd.querySelector('#counterPreview');

    (async ()=>{ try{
      const ls = await wfsGetLayers();
      
      if (!ls.length){ 
        layerSel.innerHTML='<option value="">(none)</option>'; 
        if (!accessKey) {
          preview.textContent='No layers found. Authentication may be required. Add ?access_key=YOUR_KEY to the URL.';
        } else {
          preview.textContent='No layers found. Check if WFS/WMS services are available.';
        }
        return; 
      }
      layerSel.innerHTML = ls.map(n=>`<option value="${n}">${n}</option>`).join('');
      if (cfg?.wfs?.typeName && ls.includes(cfg.wfs.typeName)) layerSel.value = cfg.wfs.typeName;
      preview.textContent = `Found ${ls.length} layer(s). Select a layer to configure fields.`;
      await fillCounterFields();
    }catch(e){ 
      console.error('Layer loading error:', e);
      if (e.message === 'Authentication required') {
        preview.textContent='Authentication required. Add ?access_key=YOUR_KEY to the URL.';
      } else {
        preview.textContent='Failed to load layers. Check console for details.'; 
      }
    } })();

    async function fillCounterFields(){
      const tn = layerSel.value;
      if (!tn) {
        preview.textContent = 'Please select a layer first.';
        return;
      }
      
      try{
        const fields = await wfsDescribe(tn);
        if (fields.length > 0) {
          const opts = fields.map(n=>`<option value="${n}">${n}</option>`).join('');
          fieldSel.innerHTML = opts;
          if (cfg.field) fieldSel.value = cfg.field;
          preview.innerHTML = `Layer <code>${tn}</code> fields: <code>${fields.join(', ')}</code>`;
        } else {
          preview.textContent = `No fields found for layer ${tn}. This might be a WMS-only layer.`;
          fieldSel.innerHTML = '<option value="">No fields available</option>';
        }
      }catch(e){ 
        console.error('DescribeFeatureType failed:', e);
        preview.textContent = `Failed to get fields for layer ${tn}. This might be a WMS-only layer or WFS is not available.`;
        fieldSel.innerHTML = '<option value="">Fields unavailable</option>';
      }
    }
    layerSel.addEventListener('change', fillCounterFields);

    bd.querySelector('#loadCounterBtn').onclick = async ()=>{
      try{
        const tn=layerSel.value; 
        const field=fieldSel.value;
        const operation=bd.querySelector('#counterOperation').value;
        
        if (!tn || !field) {
          preview.textContent = 'Please select both layer and field.';
          return;
        }
        
        const rows=await wfsFetchRows({typeName:tn,limit:+bd.querySelector('#counterLimit').value||2000,cql:bd.querySelector('#counterCql').value||''});
        
        // Calculate preview value
        let previewValue = 0;
        if (operation === 'count') {
          previewValue = rows.length;
        } else if (operation === 'sum') {
          previewValue = rows.reduce((sum, row) => {
            const val = parseFloat(String(row[field] ?? '').replace(/[, ]/g, ''));
            return sum + (isFinite(val) ? val : 0);
          }, 0);
        } else if (operation === 'avg') {
          const validValues = rows.map(row => parseFloat(String(row[field] ?? '').replace(/[, ]/g, ''))).filter(val => isFinite(val));
          previewValue = validValues.length > 0 ? validValues.reduce((sum, val) => sum + val, 0) / validValues.length : 0;
        }
        
        let formattedValue = previewValue;
        if (operation === 'avg') {
          formattedValue = previewValue.toFixed(2);
        } else if (operation === 'sum' && Math.abs(previewValue) >= 1000) {
          formattedValue = previewValue.toLocaleString();
        }
        
        preview.innerHTML = `Loaded <b>${rows.length}</b> features from <code>${tn}</code>.<br>Preview: <strong>${formattedValue}</strong> (${operation} of ${field})`;
      }catch{ preview.textContent='GetFeature failed.'; }
    };
    bd.querySelector('#cancelCounterBtn').onclick = ()=> bd.remove();
    bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
    bd.querySelector('#applyCounterBtn').onclick = ()=>{
      const title = bd.querySelector('#counterTitle').value.trim();
      const newCfg = { 
        title: title,
        source:{kind:'wfs'}, 
        field: fieldSel.value,
        operation: bd.querySelector('#counterOperation').value,
        wfs:{ 
          typeName: layerSel.value, 
          cql: bd.querySelector('#counterCql').value.trim(), 
          limit:+bd.querySelector('#counterLimit').value||2000 
        } 
      };
      setCfg(cardEl, newCfg); 
      // Update the card title
      cardEl.querySelector('.title').innerText = title || 'Counter';
      const cid=cardEl.querySelector('[id$="_counter"]').id; renderCounter(cid, newCfg); bd.remove();
    };
  }

  // ------- sidebar + persistence -------
  document.querySelectorAll('.picker').forEach(p=>p.addEventListener('click', ()=>{
    const k=p.dataset.kind; addItem(k,0,0, k==='map'?6:k==='chart'?6:k==='table'?6:k==='legend'?3:k==='counter'?3:3, k==='map'?5:k==='chart'?3:k==='table'?4:k==='legend'?3:k==='counter'?2:2);
  }));
  
  const exportLayout=()=>[...canvas.querySelectorAll('.item')].map(el=>{
    const kind = (el.querySelector('[id$="_map"]')&&'map')||(el.querySelector('[id$="_chart"]')&&'chart')||(el.querySelector('[id$="_table"]')&&'table')||(el.querySelector('[id$="_legend"]')&&'legend')||(el.querySelector('[id$="_counter"]')&&'counter')||'text';
    const config = getCfg(el);
    return {
      id: el.dataset.id,
      x: +el.dataset.x,
      y: +el.dataset.y,
      w: +el.dataset.w,
      h: +el.dataset.h,
      title: el.querySelector('.title')?.innerText||'',
      kind: kind,
      config: config,
      body: el.querySelector('.body').innerHTML
    };
  });

  function importLayout(items){ 
    canvas.innerHTML=''; 
    items.forEach(it=>{ 
      addItem(it.kind,it.x,it.y,it.w,it.h); 
      const el=canvas.lastElementChild; 
      el.querySelector('.title').innerText=it.title||it.kind; 
      
      // Restore configuration if available
      if(it.config) {
        setCfg(el, it.config);
      }
      
      let elid = 0;
      switch(it.kind){
      // Restore body content for text widgets
      case 'text':
        el.querySelector('.body').innerHTML=it.body;
        break;
      
      // Initialize widgets with their configurations
      case 'map':
        /*elid=[...el.querySelectorAll('[id$="_map"]')].pop()?.id; 
        if(id) {
          // Clear any existing map instance to prevent reinitialization error
          if(el._leafletMap) {
            el._leafletMap.remove();
            el._leafletMap = null;
          }
          buildMap(id,el);
        }*/
        break;
      case 'chart':
        elid=[...el.querySelectorAll('[id$="_chart"]')].pop()?.id; 
        if(elid) renderChart(elid,getCfg(el));
        break;
      case 'table':
        elid=[...el.querySelectorAll('[id$="_table"]')].pop()?.id; 
        if(elid) renderTable(elid,getCfg(el));
        break;
      
      case 'legend':
        elid=[...el.querySelectorAll('[id$="_legend"]')].pop()?.id; 
        if(elid) renderLegend(elid,getCfg(el));
        break;
      case 'counter':
        elid=[...el.querySelectorAll('[id$="_counter"]')].pop()?.id; 
        if(elid) renderCounter(elid,getCfg(el));
        break;
      default:
        break;
      }

      applyPos(el); 
    }); 
  }
  // Enhanced save functionality - export as JSON file
  let saveBtn = document.getElementById('saveBtn');
  if(saveBtn){
  saveBtn.onclick=()=>{ 
    const layout = exportLayout();
    const config = {
      version: '1.0',
      timestamp: new Date().toISOString(),
      dashboard: {
        title: 'QCarta Grid Dashboard',
        description: 'Dashboard configuration exported from QCarta Grid',
        layout: layout
      }
    };

    // Create and download JSON file
    /*const blob = new Blob([JSON.stringify(config, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `qcarta-dashboard-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);*/
    
    // Also save to localStorage as backup    
    var data = {'action': 'save_config', 'id': DASHBOARD_ID, 'config': config}
		$.ajax({
				type: "POST",
				url: 'action/dashboard.php',
				data: data,
				dataType:"json",
				success: function(response){
				  alert(response.message);
				}
		});
  }
  };
  
  // Enhanced load functionality - import from JSON file
  let loadBtn = document.getElementById('loadBtn');
  if(loadBtn){
  loadBtn.onclick=()=>{ 
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const config = JSON.parse(e.target.result);
          let layout;
          
          // Handle both old format (array) and new format (object with dashboard.layout)
          if (Array.isArray(config)) {
            layout = config;
          } else if (config.dashboard && config.dashboard.layout) {
            layout = config.dashboard.layout;
          } else if (config.layout) {
            layout = config.layout;
          } else {
            throw new Error('Invalid configuration format');
          }
          
          importLayout(layout);
          alert('Dashboard configuration loaded successfully.');
        } catch (error) {
          alert('Error loading configuration: ' + error.message);
        }
      };
      reader.readAsText(file);
    };
    input.click();
  };
  }
  
  let clearBtn = document.getElementById('clearBtn');
  if(clearBtn){
  clearBtn.onclick=()=>{ 
    if (confirm('Are you sure you want to clear the dashboard? This will remove all widgets.')) {
      canvas.innerHTML=''; 
      alert('Dashboard cleared.');
    }
  };
  }

  // ------- start -------
  if(dashboard_config){
    importLayout(dashboard_config.dashboard.layout);
  }else{
    addItem('map',0,0,6,5); addItem('chart',6,0,6,3); addItem('table',0,5,6,4); addItem('legend',6,3,3,3); addItem('text',9,3,3,4);
  }
  window.addEventListener('resize', ()=>canvas.querySelectorAll('.item').forEach(applyPos));
})();
