document.addEventListener('DOMContentLoaded', () => {

  const toggleBtn = document.getElementById('toggleExplore');
  const exploreContainer = document.getElementById('exploreContainer');
  const grid = document.getElementById('catalogGrid');
  const exploreCards = document.getElementById('exploreCards');
  const resultCountEl = document.getElementById('resultCount');

  if (!toggleBtn || !exploreContainer || !grid || !exploreCards) return;

  let exploreActive = false;
  let map, markers = [];
  let exploreSearchToken = 0;
  let hasFittedBounds = false;
  let lastExploreQueryKey = '';

  toggleBtn.addEventListener('click', () => {
    exploreActive = !exploreActive;

    if (exploreActive) {
      hasFittedBounds = false;
      // Hide non-spatial items (SSR uses data-type="map"; search cards use "layer")
      grid.querySelectorAll(':scope > .group').forEach(card => {
        const t = (card.dataset.type || '').toLowerCase();
        if (t !== 'map' && t !== 'layer') {
          card.style.display = 'none';
        }
      });
      exploreContainer.classList.remove('hidden');
      grid.style.display = 'none';

      initMap();
      requestAnimationFrame(() => { map && map.invalidateSize(); });
      filterByMapBounds();

    } else {
      exploreSearchToken += 1;
      exploreContainer.classList.add('hidden');
      grid.querySelectorAll(':scope > .group').forEach(card => {
        card.style.display = '';
      });
      grid.style.display = '';
      if (typeof window.qcartaRefreshCatalog === 'function') {
        window.qcartaRefreshCatalog();
      }
    }
  });

  function initMap() {
    if (map) return;

    map = L.map('exploreMap').setView([20, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
      subdomains: 'abcd',
      maxZoom: 19
    }).addTo(map);

    map.on('moveend', filterByMapBounds);

    setTimeout(() => {
      map.setView([30, 10], 3);
      map.invalidateSize();
    }, 300);
  }

  function moveCardsToExplore() {
    exploreCards.innerHTML = '';

    grid.querySelectorAll(':scope > .group').forEach(card => {
      const clone = card.cloneNode(true);
      clone.classList.remove('h-64');
      exploreCards.appendChild(clone);
    });
  }

  function filterByMapBounds() {
    if (!map) return;
    const bounds = map.getBounds();

    const bbox = [
      bounds.getWest(),
      bounds.getSouth(),
      bounds.getEast(),
      bounds.getNorth()
    ].join(',');

    runMapSearch(bbox);
  }

  async function runMapSearch(bbox) {
    const token = exploreSearchToken + 1;
    exploreSearchToken = token;

    if (typeof window.qcartaMarkSearchInteracted === 'function') {
      window.qcartaMarkSearchInteracted();
    }

    const topicEl = document.getElementById('topic_id');
    const gemetEl = document.getElementById('gemet_id');
    const topic = topicEl ? Array.from(topicEl.selectedOptions).map(o => o.value).filter(Boolean).join(',') : '';
    const gemet = gemetEl ? Array.from(gemetEl.selectedOptions).map(o => o.value).filter(Boolean).join(',') : '';
    const text = document.getElementById('search')?.value || '';
    const queryKey = `${text}|${topic}|${gemet}`;
    if (queryKey !== lastExploreQueryKey) {
      hasFittedBounds = false;
      lastExploreQueryKey = queryKey;
    }

    const params = new URLSearchParams({
      text,
      topic,
      gemet,
      filters: 'layers',
      bbox
    });

    const catalogGrid = document.getElementById('catalogGrid') || document.querySelector('.grid');
    if (catalogGrid) {
      catalogGrid.innerHTML = `<div class="col-span-full text-center py-12 text-gray-500">Searching...</div>`;
    }
    if (resultCountEl) {
      resultCountEl.textContent = '';
    }

    try {
      const resp = await fetch(`admin/action/search_handler.php?${params.toString()}`);
      const data = await resp.json();

      if (token !== exploreSearchToken) return;

      if (data.error) {
        throw new Error(data.details || data.message || 'Search failed');
      }

      if (!exploreActive) return;

      const exploreData = {
        layers: data.layers || [],
        stories: data.stories || [],
        links: data.links || [],
        docs: data.docs || [],
        dashboards: data.dashboards || []
      };

      const totalResults =
        exploreData.layers.length +
        exploreData.stories.length +
        exploreData.links.length +
        exploreData.docs.length +
        exploreData.dashboards.length;

      if (resultCountEl) {
        resultCountEl.textContent = `${totalResults} result(s) in view`;
      }

      if (typeof window.updateResults === 'function') {
        window.updateResults(exploreData);
      }

      moveCardsToExplore();

      updateMarkers(exploreData.layers);

    } catch (err) {
      console.error(err);
    }
  }

  function createMarkerIcon(type) {
    const colors = {
      map: '#3b82f6',
      layer: '#3b82f6',
      dashboard: '#ef4444',
      geostory: '#22c55e',
      story: '#22c55e',
      document: '#a855f7',
      doc: '#a855f7',
      link: '#f59e0b'
    };

    const color = colors[type] || '#3b82f6';

    return L.divIcon({
      className: '',
      html: `<div class="custom-marker" style="background:${color}"></div>`,
      iconSize: [20, 20],
      iconAnchor: [10, 10]
    });
  }

  function updateMarkers(layers) {
    if (!map) return;
    markers.forEach(m => map.removeLayer(m));
    markers = [];

    layers.forEach(layer => {
      if (layer.lat == null || layer.lng == null || layer.lat === '' || layer.lng === '') return;

      const marker = L.marker([layer.lat, layer.lng], {
        icon: createMarkerIcon(layer.type || 'map')
      }).addTo(map);

      marker.bindTooltip(layer.name || '', {
        direction: 'top',
        offset: [0, -8],
        opacity: 0.9
      });

      marker.on('click', function () {
        document.querySelectorAll('.custom-marker').forEach(el => {
          el.classList.remove('marker-selected');
        });
        const root = typeof this.getElement === 'function' ? this.getElement() : null;
        const dot = root && (root.classList.contains('custom-marker')
          ? root
          : root.querySelector('.custom-marker'));
        if (dot) dot.classList.add('marker-selected');
        highlightCard(String(layer.id));
      });

      markers.push(marker);
    });

    if (!hasFittedBounds && markers.length > 1) {
      const group = L.featureGroup(markers);
      const b = group.getBounds();
      if (b.isValid()) {
        map.off('moveend', filterByMapBounds);
        map.fitBounds(b.pad(0.2));
        hasFittedBounds = true;
        map.once('moveend', () => {
          map.on('moveend', filterByMapBounds);
        });
      }
    }
  }

  function highlightCard(id) {
    exploreCards.querySelectorAll('.group').forEach(c => {
      c.classList.remove('ring-2', 'ring-blue-500');
      if (String(c.dataset.id) === String(id)) {
        c.classList.add('ring-2', 'ring-blue-500');
        c.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

});
