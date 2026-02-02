// Map Search functionality (styled to match new cards)
document.addEventListener('DOMContentLoaded', function () {
  let map;
  let currentRectangle;

  // Modal elements
  const modal = document.getElementById('mapSearchModal');
  const openButton = document.getElementById('openMapSearch');
  const closeButton = document.getElementById('closeMapSearch');
  const clearButton = document.getElementById('clearSelection');
  const searchButton = document.getElementById('searchInArea');

  if (!openButton || !closeButton || !clearButton || !searchButton) {
    console.error('Required modal elements not found');
    return;
  }

  // Initialize map when modal is opened
  openButton.addEventListener('click', function () {
    modal.classList.remove('hidden');
    if (!map) {
      initMap();
    } else {
      setTimeout(() => map.invalidateSize(), 100);
    }
  });

  // Close modal
  closeButton.addEventListener('click', function () {
    modal.classList.add('hidden');
    if (currentRectangle) {
      map.removeLayer(currentRectangle);
      currentRectangle = null;
    }
  });

  // Clear selection
  clearButton.addEventListener('click', function () {
    if (currentRectangle) {
      map.removeLayer(currentRectangle);
      currentRectangle = null;
    }
  });

  // Initialize the map
  function initMap() {
    if (map) {
      map.remove();
      map = null;
    }
    map = L.map('searchMap').setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Leaflet Draw
    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);
    const drawControl = new L.Control.Draw({
      draw: {
        polygon: false,
        polyline: false,
        circle: false,
        marker: false,
        circlemarker: false,
        rectangle: {
          shapeOptions: { color: '#3388ff', weight: 2, fillOpacity: 0.2 }
        }
      },
      edit: { featureGroup: drawnItems, remove: true }
    });
    map.addControl(drawControl);

    // Rectangle created
    map.on(L.Draw.Event.CREATED, function (e) {
      if (currentRectangle) drawnItems.removeLayer(currentRectangle);
      const layer = e.layer;
      drawnItems.addLayer(layer);
      currentRectangle = layer;
    });

    // Rectangle deleted
    map.on('draw:deleted', function () {
      currentRectangle = null;
    });
  }

  // Search in selected area
  searchButton.addEventListener('click', async function () {
    if (!currentRectangle) {
      alert('Please select an area on the map first');
      return;
    }

    const b = currentRectangle.getBounds();
    const minLng = b.getSouthWest().lng, minLat = b.getSouthWest().lat;
    const maxLng = b.getNorthEast().lng, maxLat = b.getNorthEast().lat;
    if (minLng === maxLng || minLat === maxLat) {
      alert('Please draw a rectangle (not just a point) on the map.');
      return;
    }
    const bbox = [minLng, minLat, maxLng, maxLat].join(',');

    try {
      // Build search parameters (layers only + bbox)
      const params = new URLSearchParams({
        text: '',
        topic: '',
        gemet: '',
        keywords: '',
        filters: 'layers',
        bbox
      });

      // Loading state
      const grid = document.querySelector('.grid');
      if (grid) {
        grid.innerHTML = `
          <div class="col-span-full text-center py-12 text-gray-500">Searching...</div>
        `;
      }

      const resp = await fetch(`admin/action/search_handler.php?${params.toString()}`);
      if (!resp.ok) throw new Error('Failed to fetch search results');
      const data = await resp.json();
      if (data.error) throw new Error(data.details || data.message || 'Search failed');

      handleSearchResults(data);
    } catch (err) {
      const grid = document.querySelector('.grid');
      if (grid) {
        grid.innerHTML = `
          <div class="col-span-full text-center py-12 text-red-500">Error: ${err.message}</div>
        `;
      }
    }
  });

  // Handle search results reuse global updateResults if present; else render locally
  function handleSearchResults(data) {
    // Close the modal
    modal.classList.add('hidden');

    // Prefer the page's updateResults override (keeps styles 100% consistent)
    if (typeof window.updateResults === 'function') {
      // Your global search script expects this shape
      window.hasUserInteracted = true;
      window.isInitialLoad = false;
      window.updateResults({ layers: data.layers || [] });
      return;
    }

    // Fallback: local render using the same card skeleton as the static grid
    const grid = document.querySelector('.grid');
    if (!grid) {
      console.error('Results grid not found');
      return;
    }

    const layers = data.layers || [];
    grid.innerHTML = '';

    if (layers.length === 0) {
      grid.innerHTML = `
        <div class="col-span-full text-center py-12 text-gray-500">
          No layers found in the selected area
        </div>`;
      return;
    }

    layers.forEach(layer => {
      const img = `assets/layers/${layer.id}.png`;
      const name = (layer.name || '').replace(/_/g, ' ');
      const desc = (layer.description && layer.description.trim()) ? layer.description : 'View details';
      const url = `layers/${layer.id}/index.php`;

      const wrapper = document.createElement('div');
      wrapper.className = 'group h-64 relative';
      wrapper.innerHTML = `
        <a href="${url}" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col"
           target="_blank" rel="noopener" aria-label="Open Map: ${name}">
          <div class="thumb-wrap relative">
            <img loading="lazy" src="${img}" alt="${name} thumbnail"
                 onerror="this.src='assets/layers/default.png'"
                 class="w-full h-32 object-cover hover-zoom">
            <div class="quick-actions">
              <span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Map</span>
            </div>
          </div>
          <div class="p-3 flex flex-col flex-1 justify-between">
            <div>
              <h3 class="text-base font-semibold text-gray-900 mb-1">${name}</h3>
              <p class="text-sm text-gray-500 line-clamp-2 mb-3">${desc}</p>
            </div>
          </div>
          <div class="card-foot px-3 py-2 flex items-center justify-between">
            <span class="badge badge-map">Map</span>
            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
          </div>
        </a>
      `;
      grid.appendChild(wrapper);
    });
  }
});
