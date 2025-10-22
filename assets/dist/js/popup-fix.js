/**
 * QGIS Custom Tabbed Popup Scroll Bar Fix
 * Fixes the nested scroll bar issue in custom tabbed popups with Bootstrap tabs
 */

(function() {
    'use strict';

    // Fields/aliases that must not be edited
    const READ_ONLY_FIELDS = new Set(['fid','uuid','geom','geometry','Infected','Position locked?']);

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the fix
        initPopupScrollFix();
    });

    // Also run when Leaflet map is ready (in case it loads after DOM)
    if (typeof L !== 'undefined') {
        L.Map.addInitHook(function() {
            setTimeout(initPopupScrollFix, 100);
        });
    }

    function initPopupScrollFix() {
        // Fix existing popups
        fixExistingPopups();
        
        // Set up observer for new popups
        setupPopupObserver();
        
        // Override the custom tabbedPopup function if it exists
        overrideTabbedPopup();
    }

    function fixExistingPopups() {
        // Find all existing popups (both Leaflet and custom)
        const leafletPopups = document.querySelectorAll('.leaflet-popup-content-wrapper, .leaflet-popup-content');
        const customPopups = document.querySelectorAll('.custom-popup');
        
        leafletPopups.forEach(fixPopupScroll);
        customPopups.forEach(fixCustomPopup);
    }

    function setupPopupObserver() {
        // Create a mutation observer to watch for new popups
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if this is a popup or contains popups
                        if (node.classList && (
                            node.classList.contains('leaflet-popup-content-wrapper') ||
                            node.classList.contains('leaflet-popup-content') ||
                            node.classList.contains('custom-popup')
                        )) {
                            fixPopupScroll(node);
                            fixCustomPopup(node);
                        } else if (node.querySelectorAll) {
                            const popups = node.querySelectorAll('.leaflet-popup-content-wrapper, .leaflet-popup-content, .custom-popup');
                            popups.forEach(popup => {
                                fixPopupScroll(popup);
                                fixCustomPopup(popup);
                            });
                        }
                    }
                });
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function overrideTabbedPopup() {
        // Check if the tabbedPopup function exists in the global scope
        if (typeof window.tabbedPopup === 'function') {
            const originalTabbedPopup = window.tabbedPopup;
            
            window.tabbedPopup = function(e) {
                const result = originalTabbedPopup.call(this, e);
                
                // Fix the popup after it's created
                setTimeout(() => {
                    const popups = document.querySelectorAll('.custom-popup, .leaflet-popup-content-wrapper');
                    popups.forEach(popup => {
                        fixPopupScroll(popup);
                        fixCustomPopup(popup);
                    });
                }, 100);
                
                return result;
            };
        }
    }

    function fixPopupScroll(popupElement) {
        if (!popupElement) return;

        // Find the popup content wrapper and content
        const wrapper = popupElement.classList.contains('leaflet-popup-content-wrapper') 
            ? popupElement 
            : popupElement.querySelector('.leaflet-popup-content-wrapper');
        
        const content = popupElement.classList.contains('leaflet-popup-content') 
            ? popupElement 
            : popupElement.querySelector('.leaflet-popup-content');

        if (!wrapper || !content) return;

        // Apply CSS fixes
        applyPopupStyles(wrapper, content);

        // Check content height and adjust scroll behavior
        adjustScrollBehavior(wrapper, content);

        // Remove any duplicate scroll bars
        removeDuplicateScrollbars(wrapper, content);

        // Try to locate the inner popup content; fall back to Leaflet content node
        let popupContent = null;
        // common containers used by QCarta custom popups
        popupContent = content.querySelector('.popup-content') || content.querySelector('.custom-popup') || content;
        
        // Add edit buttons (if not already present) and fix tab content
        try {
            addEditButtonsToPopup(popupContent);
            fixBootstrapTabs(popupContent);
        } catch (e) {
            console.warn('addEditButtonsToPopup/fixBootstrapTabs failed:', e);
        }
        
        // Fix any nested scrollable elements (avoid nested scrollbars)
        const nestedElements = popupContent.querySelectorAll('*');
        nestedElements.forEach(element => {
            if (element.style && (element.style.overflow || element.style.overflowY || element.style.overflowX)) {
                element.style.overflow = 'visible';
                element.style.overflowY = 'visible';
                element.style.overflowX = 'visible';
            }
        });

}

    function fixCustomPopup(popupElement) {
        if (!popupElement) return;

        // Find the custom popup content
        const popupContent = popupElement.querySelector('.popup-content');
        if (!popupContent) return;

        // Add edit buttons to popup if they don't exist
        addEditButtonsToPopup(popupContent);

        // Fix Bootstrap tab content
        fixBootstrapTabs(popupContent);

        // Fix any nested scrollable elements
        const nestedElements = popupContent.querySelectorAll('*');
        nestedElements.forEach(element => {
            if (element.style && (element.style.overflow || element.style.overflowY || element.style.overflowX)) {
                element.style.overflow = 'visible';
                element.style.overflowY = 'visible';
                element.style.overflowX = 'visible';
            }
        });
    }

    function addEditButtonsToPopup(popupContent) {
        // We'll add one button per tab; don't bail out if another tab has one.
        // (We skip only if this specific tab already has a button.)

        // Find all tab panes (each represents a feature)
        const tabPanes = popupContent.querySelectorAll('.tab-pane');
        
        tabPanes.forEach((tabPane, index) => {
            // Get the feature ID from the tab pane ID
            const featureId = tabPane.id.replace('popup-', '').replace('-tab', '');
            
            // Find the popup body to add the edit button (fallbacks)
            const popupBody = tabPane.querySelector('.popup-body') || tabPane.querySelector('.modal-body') || tabPane;
            // Skip if this pane already has a button
            if (tabPane.querySelector('.edit-button')) return;

            // Create edit button
            const editButton = document.createElement('button');
            editButton.className = 'btn btn-primary btn-sm edit-button mt-2';
            editButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
            editButton.style.width = '100%';
            
            // Add click handler
            editButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openEditModal(featureId, tabPane);
            });

            // Add the button to the popup body
            popupBody.appendChild(editButton);
        });
    }

    function openEditModal(featureId, tabPane) {
        // Extract feature data from the popup
        const featureData = extractFeatureData(tabPane);
        
        // Create and show edit modal
        showEditModal(featureId, featureData);
    }

// Canonicalize keys and coerce types before sending to server (keep OUTSIDE extractFeatureData)
function canonicalizeAndCoerce(updates) {
  // Do NOT remap aliases here; backend maps aliases using the QGIS project.
  const typeHints = {
    // add safe numeric hints if you want, e.g. 'area_id': 'integer',
  };
  const out = {};
  for (const [k, v0] of Object.entries(updates)) {
    let v = v0;
    const hint = typeHints[k];
    if (hint === 'integer') {
      if (v === '' || v === null) v = null;
      else { const n = parseInt(v, 10); if (!Number.isNaN(n)) v = n; }
    } else if (hint === 'number') {
      if (v === '' || v === null) v = null;
      else { const n = parseFloat(v); if (!Number.isNaN(n)) v = n; }
    } else {
      if (v === '') v = null;
    }
    out[k] = v;
  }
  return out;
}
// expose for inline onclick handlers, just in case
try { window.canonicalizeAndCoerce = canonicalizeAndCoerce; } catch(e) {}

function extractFeatureData(tabPane) {
  const data = {};

  const popupRows = tabPane.querySelectorAll('.popup-row');
  console.log('Extracting data from popup rows:', popupRows.length);

  // Field name mapping from display names to database field names
  const fieldMapping = {
    'Beekeeper': 'beekeeper',
    'Number of Boxes': 'nbr_of_boxes',
    'Species of Bees': 'bee_species',
    'Amount of Bees': 'bee_amount',
    'Photo': 'picture',
    'Kind of Disease': 'kind_of_disease',
    'Yearly Harvest (kg)': 'average_harvest',
    'Area mostly used': 'area_id',
    'ID': 'uuid',
    'Source': 'source',
    'Quality': 'quality',
    'X': 'x',
    'Y': 'y',
    'Z': 'z',
    'Horizontal accuracy': 'horizontal_accuracy',
    'Nb. of satellites': 'nr_used_satellites',
    'Fix status': 'fix_status_descr',
  };

  popupRows.forEach(row => {
    const label = row.querySelector('.popup-label');
    const value = row.querySelector('.popup-value');

    if (label && value) {
      const displayKey = label.textContent.replace(':', '').trim();
      // Map display name to database field name
      const dbKey = fieldMapping[displayKey] || displayKey.toLowerCase();
      // Remove any HTML from the value (like image tags)
      const textValue = value.textContent || value.innerText || '';
      data[dbKey] = textValue.trim();
      console.log('Extracted field:', displayKey, '->', dbKey, '=', textValue.trim());
    }
  });

  console.log('Final extracted data:', data);
  return data;
}


window.showEditModal = function showEditModal(featureId, featureData) {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="editFeatureModal" tabindex="-1" aria-labelledby="editFeatureModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editFeatureModalLabel">Edit Feature: ${featureId}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editFeatureForm">
                                ${Object.entries(featureData)
                                    .filter(([key]) => !READ_ONLY_FIELDS.has(key) && !['fid','uuid','geom','geometry'].includes(key.toLowerCase()))
                                    .map(([key, value]) => `
                                        <div class="mb-3">
                                            <label for="edit_${key}" class="form-label">${key}</label>
                                            <input type="text" class="form-control" id="edit_${key}" name="${key}" value="${value}">
                                        </div>
                                    `).join('')}
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveFeatureChanges('${featureId}')">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('editFeatureModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editFeatureModal'));
        modal.show();

        // Store feature data for saving
        window.currentEditData = {
            featureId: featureId,
            originalData: featureData
        };
    }

    // Add global function for saving changes
    window.saveFeatureChanges = function(featureId) {
        console.log('saveFeatureChanges called with featureId:', featureId);
        
        const form = document.getElementById('editFeatureForm');
        const formData = new FormData(form);
        const updates = {};
        
        console.log('Original data:', window.currentEditData.originalData);
        
        // Collect form data
        for (let [key, value] of formData.entries()) {
            console.log('Form field:', key, '=', value);
            if (value !== window.currentEditData.originalData[key]) {
                updates[key] = value;
                console.log('Field changed:', key, 'from', window.currentEditData.originalData[key], 'to', value);
            }
        }

        console.log('Updates to send:', updates);

        if (Object.keys(updates).length === 0) {
            alert('No changes detected');
            return;
        }

        // Determine collection name from feature ID
        const collection = 'auto';
	const layerHint = featureId.includes('.') ? featureId.split('.')[0] : null;
        console.log('Collection name:', collection);

        const _canonFn = (window.canonicalizeAndCoerce || (typeof canonicalizeAndCoerce==='function' ? canonicalizeAndCoerce : (u)=>u));
        const coercedUpdates = _canonFn(updates);
        if (!Object.keys(coercedUpdates).length) {
            alert('No valid editable fields in your changes.');
            return;
        }
        const requestBody = {
            collection: collection,
            id: featureId,
            layer_id: layerId,  // constant in map_index.php
            updates: coercedUpdates,
            layerHint
        };
        
        console.log('Sending request to ../../admin/action/oapif_update.php with body:', requestBody);

        // Send update request
        fetch('../../admin/action/oapif_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody)
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text();
        })
        .then(data => {
            console.log('Raw response data:', data);
            try {
                const result = JSON.parse(data);
                console.log('Parsed JSON result:', result);
                
                // Check if this is a GeoJSON feature response (successful update)
                if (result.type === 'Feature' && result.properties) {
                    // verify each value actually changed on server
                    const intended = coercedUpdates;
                    const matchAll = Object.entries(intended).every(([k,v]) => {
                        const sv = result.properties[k];
                        if (typeof v === 'number') return Number(sv) === Number(v);
                        if (typeof v === 'boolean') return (sv === v) || (String(sv).toLowerCase() === String(v));
                        return String(sv ?? '') === String(v ?? '');
                    });
                    if (!matchAll) throw new Error('Server response did not reflect requested changes');
                    console.log('Update successful! Feature returned:', result);
                    // success: no alert
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeatureModal'));
                    modal.hide();
                    // Refresh the map or popup
                    if (window.map) {
                        window.map.closePopup();
                        // Cache-bust WMS/tiles so the map reflects the update
                        if (window.ol && typeof map.getLayers === 'function') {
                            map.getLayers().forEach(layer => {
                                const src = layer.getSource && layer.getSource();
                                if (src?.updateParams) {
                                    const p = src.getParams ? src.getParams() : {};
                                    p._ts = Date.now(); src.updateParams(p);
                                } else if (src?.refresh) src.refresh();
                            });
                        } else if (window.L && typeof map.eachLayer === 'function') {
                            map.eachLayer(layer => {
                                if (typeof layer.setParams === 'function') layer.setParams({ _ts: Date.now() });
                                else if (typeof layer.redraw === 'function') layer.redraw();
                                else if (typeof layer.refresh === 'function') layer.refresh();
                            });
                        }
                    }

                } else if (typeof data === 'string' && /<TransactionResponse/i.test(data) && /<totalUpdated>\s*[1-9]/i.test(data)) {
                    console.log('WFS-T update reported success'); 
			applyUpdatesToOpenPopup(coercedUpdates);
                    // success: no alert
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeatureModal'));
                    if (modal) modal.hide();
                    if (window.map) {
                        // cache-bust WMS/tiles
                        if (window.ol && typeof map.getLayers === 'function') {
                            map.getLayers().forEach(layer => {
                                const src = layer.getSource && layer.getSource();
                                if (src?.updateParams) {
                                    const p = src.getParams ? src.getParams() : {};
                                    p._ts = Date.now(); src.updateParams(p);
                                } else if (src?.refresh) src.refresh();
                            });
                        } else if (window.L && typeof map.eachLayer === 'function') {
                            map.eachLayer(layer => {
                                if (typeof layer.setParams === 'function') layer.setParams({ _ts: Date.now() });
                                else if (typeof layer.redraw === 'function') layer.redraw();
                                else if (typeof layer.refresh === 'function') layer.refresh();
                            });
                        }
                    }
                    return;
                } else if (result.error) {
                    alert('Error: ' + result.error);
                } else {
                    // Close modal on successful update
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeatureModal'));
                    if (modal) modal.hide();
                    alert('Update completed. Response: ' + JSON.stringify(result).substring(0, 100));
                }
            } catch (e) {
                console.log('Response is not JSON, treating as XML/text');
                // If response is not JSON, it might be XML (WFS response)
                if (data.includes('SUCCESS') || data.includes('success')) {
                    // success: no alert
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeatureModal'));
                    modal.hide();
                } else {
                    // Close modal on successful update
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeatureModal'));
                    if (modal) modal.hide();
                    alert('Update completed. Response: ' + data.substring(0, 100));
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error updating feature: ' + error.message);
        });
    };

    function fixBootstrapTabs(popupContent) {
        // Find all tab panes
        const tabPanes = popupContent.querySelectorAll('.tab-pane');
        tabPanes.forEach(tabPane => {
            // Ensure tab panes don't create scroll contexts
            tabPane.style.overflow = 'visible';
            tabPane.style.overflowY = 'visible';
            tabPane.style.overflowX = 'visible';
            tabPane.style.maxHeight = 'none';
        });

        // Find popup sections and bodies
        const popupSections = popupContent.querySelectorAll('.popup-section, .popup-body');
        popupSections.forEach(section => {
            section.style.overflow = 'visible';
            section.style.overflowY = 'visible';
            section.style.overflowX = 'visible';
            section.style.maxHeight = 'none';
        });

        // Fix popup rows
        const popupRows = popupContent.querySelectorAll('.popup-row');
        popupRows.forEach(row => {
            row.style.overflow = 'visible';
            row.style.overflowY = 'visible';
            row.style.overflowX = 'visible';
        });
    }

    function applyPopupStyles(wrapper, content) {
        // Ensure wrapper has proper overflow settings
        wrapper.style.overflow = 'hidden';
        wrapper.style.overflowY = 'auto';
        wrapper.style.overflowX = 'hidden';

        // Ensure content doesn't create its own scroll context
        content.style.overflow = 'visible';
        content.style.overflowY = 'visible';
        content.style.overflowX = 'visible';
        content.style.maxHeight = 'none';

        // Add custom CSS classes for better control
        wrapper.classList.add('popup-scroll-fixed');
        content.classList.add('popup-content-fixed');
    }

    function adjustScrollBehavior(wrapper, content) {
        const wrapperHeight = wrapper.offsetHeight;
        const contentHeight = content.scrollHeight;
        const maxHeight = Math.min(400, Math.max(200, contentHeight)); // Reasonable popup height

        if (contentHeight > maxHeight) {
            wrapper.style.maxHeight = maxHeight + 'px';
            wrapper.style.overflowY = 'auto';
            
            // Ensure smooth scrolling
            wrapper.style.scrollBehavior = 'smooth';
            
            // Custom scrollbar styling
            wrapper.style.scrollbarWidth = 'thin';
            wrapper.style.scrollbarColor = '#888 #f1f1f1';
        } else {
            wrapper.style.maxHeight = 'none';
            wrapper.style.overflowY = 'visible';
        }
    }

    function removeDuplicateScrollbars(wrapper, content) {
        // Remove any inline styles that might cause scroll conflicts
        const elementsToCheck = [wrapper, content];
        
        elementsToCheck.forEach(element => {
            if (element) {
                // Remove conflicting overflow styles
                element.style.removeProperty('overflow-x');
                element.style.removeProperty('overflow-y');
                
                // Ensure only the wrapper handles scrolling
                if (element === wrapper) {
                    element.style.overflow = 'hidden';
                    element.style.overflowY = 'auto';
                } else {
                    element.style.overflow = 'visible';
                }
            }
        });

        // Check for nested scrollable elements and fix them
        const nestedScrollables = content.querySelectorAll('[style*="overflow"], [style*="scroll"]');
        nestedScrollables.forEach(element => {
            const computedStyle = window.getComputedStyle(element);
            if (computedStyle.overflow === 'auto' || computedStyle.overflow === 'scroll') {
                element.style.overflow = 'visible';
                element.style.overflowY = 'visible';
                element.style.overflowX = 'visible';
            }
        });
    }

    // Add custom CSS to prevent scroll bar conflicts
    function addCustomCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .popup-scroll-fixed {
                scrollbar-width: thin !important;
                scrollbar-color: #888 #f1f1f1 !important;
            }
            
            .popup-scroll-fixed::-webkit-scrollbar {
                width: 8px !important;
            }
            
            .popup-scroll-fixed::-webkit-scrollbar-track {
                background: #f1f1f1 !important;
                border-radius: 4px !important;
            }
            
            .popup-scroll-fixed::-webkit-scrollbar-thumb {
                background: #888 !important;
                border-radius: 4px !important;
            }
            
            .popup-scroll-fixed::-webkit-scrollbar-thumb:hover {
                background: #555 !important;
            }
            
            .popup-content-fixed {
                overflow: visible !important;
                max-height: none !important;
            }
            
            /* Prevent double scroll bars */
            .leaflet-popup-content-wrapper .leaflet-popup-content {
                overflow: visible !important;
                max-height: none !important;
            }
            
            /* Ensure only the wrapper scrolls */
            .leaflet-popup-content-wrapper {
                overflow: hidden !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
            }
            
            /* Fix for custom tabbed popups */
            .custom-popup .popup-content {
                overflow: visible !important;
                max-height: none !important;
            }
            
            .custom-popup .tab-content {
                overflow: visible !important;
                max-height: none !important;
            }
            
            .custom-popup .tab-pane {
                overflow: visible !important;
                max-height: none !important;
            }
            
            .custom-popup .popup-section,
            .custom-popup .popup-body {
                overflow: visible !important;
                max-height: none !important;
            }
            
            .custom-popup .popup-row {
                overflow: visible !important;
                max-height: none !important;
            }
            
            /* Ensure Bootstrap tabs don't create scroll contexts */
            .custom-popup .nav-tabs {
                overflow: visible !important;
            }
            
            .custom-popup .tab-content {
                overflow: visible !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Add the custom CSS
    addCustomCSS();

    // Export functions for manual use if needed
    window.PopupScrollFix = {
        fixAll: fixExistingPopups,
        fixPopup: fixPopupScroll,
        fixCustomPopup: fixCustomPopup,
        init: initPopupScrollFix
    };

    console.log('QGIS Custom Tabbed Popup Scroll Fix initialized');
})();

// --- silence only the verbose success alert ---
(function () {
  const orig = window.alert;
  window.alert = function (msg) {
    const s = String(msg || '');
    if (s.startsWith('Update completed. Response:')) {
      console.log('[silenced success]', s.slice(0, 120) + '�');
      return;
    }
    return orig.apply(this, arguments);
  };
})();

// Update the currently-open popup with the values we just saved
function applyUpdatesToOpenPopup(updates) {
  // Map canonical DB names -> labels used in the popup UI
  const canonicalToAlias = {
    beekeeper: 'Beekeeper',
    nbr_of_boxes: 'Number of Boxes',
    bee_species: 'Species of Bees',
    bee_amount: 'Amount of Bees',
    picture: 'Photo',
    kind_of_disease: 'Kind of Disease',
    average_harvest: 'Yearly Harvest (kg)',
    area_id: 'Area mostly used',
    uuid: 'ID',
    source: 'Source',
    quality: 'Quality',
    x: 'X', y: 'Y', z: 'Z',
    horizontal_accuracy: 'Horizontal accuracy',
    nr_used_satellites: 'Nb. of satellites',
    fix_status_descr: 'Fix status'
  };

  // Find the content node of the *currently open* popup
  const popup =
    document.querySelector('.leaflet-popup-content') ||
    document.querySelector('.popup-content') ||
    document.querySelector('.custom-popup');
  if (!popup) return;

  // Preferred layout in your app: rows with .popup-row/.popup-label/.popup-value
  const rows = Array.from(popup.querySelectorAll('.popup-row'));

  const setRowValue = (labelText, val) => {
    const row = rows.find(r => {
      const lab = r.querySelector('.popup-label');
      return lab && lab.textContent.replace(/:\s*$/, '').trim() === labelText;
    });
    if (!row) return false;
    const vEl =
      row.querySelector('.popup-value') ||
      row.querySelector('.value') ||
      row.querySelector('td:nth-child(2)') ||
      row.querySelector('dd');
    if (vEl) vEl.textContent = (val == null ? '' : String(val));
    return true;
  };

  // Fallbacks for simple tables (no special classes)
  const genericRows = Array.from(popup.querySelectorAll('tr'));

  for (const [canon, val] of Object.entries(updates)) {
    const label = canonicalToAlias[canon] || canon; // fallback to canonical
    let done = setRowValue(label, val);
    if (!done) {
      const tr = genericRows.find(tr => {
        const th = tr.querySelector('th, td:first-child, .popup-label');
        return th && th.textContent.replace(/:\s*$/, '').trim() === label;
      });
      if (tr) {
        const cell = tr.querySelector('td:nth-child(2), .popup-value') || tr.children[1];
        if (cell) cell.textContent = (val == null ? '' : String(val));
      }
    }
  }
}


function _inferLayerFrom(tabPane, featureId) {
  // 1) from featureId "Layer.fid"
  if (featureId && featureId.indexOf('.') !== -1) return featureId.split('.')[0];

  // 2) data-* on the popup DOM
  var el = tabPane, name = null;
  while (el && el.getAttribute) {
    if (el.dataset) {
      name = el.dataset.layer || el.dataset.layername || el.dataset.layerId;
      if (name) return name;
    }
    el = el.parentNode;
  }

  // 3) from tabPane id "popup-Layer.fid-tab"
  if (tabPane && tabPane.id) {
    var inner = tabPane.id.replace(/^popup-/, '').replace(/-tab$/, '');
    if (inner.indexOf('.') !== -1) return inner.split('.')[0];
  }

  // 4) last resort: a global default you can set, e.g. window.DEFAULT_LAYER_NAME = 'Apiary'
  return window.DEFAULT_LAYER_NAME || null;
}


// Called by your inline Save flow: saveInlineChanges(...) -> saveFeatureChangesInline(...)
window.saveFeatureChangesInline = function (featureId, updates, tabPane) {
  // Qualify id as "LayerName.fid" (backend expects this)
  var layer = _inferLayerFrom(tabPane, featureId);
  var id = (featureId && featureId.indexOf('.') !== -1) ? featureId : (layer ? (layer + '.' + featureId) : null);
  if (!id) { alert('Cannot determine layer for this feature. Set window.DEFAULT_LAYER_NAME = "YourLayerName".'); return; }

  // Use your existing canonicalizer if present (same as your modal code)
  var canon = (window.canonicalizeAndCoerce || (typeof canonicalizeAndCoerce==='function' ? canonicalizeAndCoerce : function(u){ return u; }));
  var changes = canon(updates || {});
  if (!changes || !Object.keys(changes).length) { alert('No changes detected'); return; }

  var body = { collection: 'auto', id: id, updates: changes, layerHint: layer };

  // Same relative URL your modal uses (works under /layers/6/)
  fetch('../../admin/action/oapif_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  })
  .then(function (res) {
    return res.text().then(function (txt) {
      return { ok: res.ok, status: res.status, ct: res.headers.get('content-type') || '', text: txt };
    });
  })
  .then(function (r) {
    if (!r.ok) { console.error('Inline update failed', r.status, r.text.slice(0,400)); alert('Save failed (HTTP ' + r.status + '). See console.'); return; }

    // JSON Feature success
    if (r.ct.indexOf('application/json') !== -1) {
      var data; try { data = JSON.parse(r.text); } catch (e) { data = null; }
      if (data && data.type === 'Feature' && data.properties) {
        if (window.applyUpdatesToPopup) applyUpdatesToPopup(tabPane, changes);
        if (window.disableInlineEditing) disableInlineEditing(tabPane);
        return;
      }
      if (data && data.error) { alert('Error: ' + data.error); return; }
    }

    // WFS-T XML success
    if (/<TransactionResponse/i.test(r.text) && /<totalUpdated>\s*[1-9]/i.test(r.text)) {
      if (window.applyUpdatesToPopup) applyUpdatesToPopup(tabPane, changes);
      if (window.disableInlineEditing) disableInlineEditing(tabPane);
      return;
    }

    console.warn('Unexpected response:', r.text.slice(0,400));
    alert('Save might not have applied. See console.');
  })
  .catch(function (err) {
    console.error('Inline fetch error', err);
    alert('Save failed: ' + err.message);
  });
};
