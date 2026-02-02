let activeKeywords = new Set();
let isInitialLoad = true;
let hasUserInteracted = false;
let searchTimeout = null;

// Map Search Variables
let map = null;
let drawControl = null;
let drawnItems = null;
let currentDrawing = null;

let searchForm = null;

// disable logging to console
console.log = function () {};

// Add keyword tag
function addKeyword(keyword) {
    if (activeKeywords.has(keyword)) return;
    activeKeywords.add(keyword);

    if (!searchForm.keywordTags) {
        console.error('Keyword tags container not found');
        return;
    }

    const tag = document.createElement('span');
    tag.className = 'px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm flex items-center';
    tag.innerHTML = `
        ${keyword}
        <button class="ml-2 text-blue-600 hover:text-blue-800" data-keyword="${keyword}">×</button>
    `;

    tag.querySelector('button').addEventListener('click', function() {
        activeKeywords.delete(keyword);
        tag.remove();
        hasUserInteracted = true;
        performSearch();
    });

    searchForm.keywordTags.appendChild(tag);
    hasUserInteracted = true;
    performSearch();
}

// Handle search input changes
function debounceSearch() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        if (!hasUserInteracted) {
            console.log('Skipping search - no user interaction yet');
            return;
        }
        isInitialLoad = false;
        performSearch();
    }, 300);
}

// Perform search
function performSearch() {
    // Skip if this is the initial load and we haven't had user interaction
    if (isInitialLoad && !hasUserInteracted) {
        console.log('Skipping search - initial load with no user interaction');
        return;
    }

    console.log('Performing search...', { isInitialLoad, hasUserInteracted });
    
    // Get selected filters
    const selectedFilters = Array.from(searchForm.filters)
        .filter(f => f.checked)
        .map(f => {
            return f.value;
        });
    // Get selected topics
    const selectedTopics = searchForm.topic ? 
        Array.from(searchForm.topic.selectedOptions).map(option => option.value) : 
        [];
    // Get selected GEMETs
    const selectedGemets = searchForm.gemet ? 
        Array.from(searchForm.gemet.selectedOptions).map(option => option.value) : 
        [];
    console.log('Selected filters after mapping:', selectedFilters);

    const searchParams = new URLSearchParams({
        text: searchForm.text ? searchForm.text.value : '',
        topic: selectedTopics.join(','),
        gemet: selectedGemets.join(','),
        keywords: Array.from(activeKeywords).join(','),
        filters: selectedFilters.join(',')
    });

    // Log the exact URL being requested
    const searchUrl = `admin/action/search_handler.php?${searchParams.toString()}`;
    console.log('Making search request to:', searchUrl);
    console.log('Search parameters:', {
        text: searchParams.get('text'),
        topic: searchParams.get('topic'),
        gemet: searchParams.get('gemet'),
        keywords: searchParams.get('keywords'),
        filters: searchParams.get('filters'),
        rawFilters: selectedFilters,
        url: searchUrl
    });

    fetch(searchUrl)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.details || err.message || `HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.details || data.message || 'Unknown error occurred');
            }
            console.log('Search results:', data);
            updateResults(data);
        })
        .catch(error => {
            console.error('Search error:', error);
            const grid = document.querySelector('.grid');
            if (grid) {
                // Only show error if we've had user interaction
                if (hasUserInteracted) {
                    grid.innerHTML = `
                        <div class="col-span-full text-center py-12">
                            <p class="text-red-500 mb-2">Error performing search:</p>
                            <p class="text-gray-600 text-sm">${error.message}</p>
                            <p class="text-gray-500 text-xs mt-2">Please try again or contact support if the problem persists.</p>
                        </div>
                    `;
                }
            }
        });
}

// --- helper: per-type UI meta (badge + quick action text) ---
const TYPE_META = {
  layer:     { badge: 'badge-map',  quick: 'Open Map',        label: 'Map' },
  dashboard: { badge: 'badge-dash', quick: 'Open Dashboard',  label: 'Dashboard' },
  story:     { badge: 'badge-pres', quick: 'Open Story',      label: 'Presentation' },
  link:      { badge: 'badge-link', quick: 'Open Link',       label: 'Link' },
  doc:       { badge: 'badge-doc',  quick: 'Open Document',   label: 'Document' }
};

// Update results in the grid matches new card style
function updateResults(data) {
  const grid = document.querySelector('.grid');
  if (!grid) {
    console.error('Results grid not found');
    return;
  }

  // Merge results and annotate type meta
  const allResults = [
    ...(data.layers || []).map(x => ({ ...x, type: 'layer' })),
    ...(data.stories || []).map(x => ({ ...x, type: 'story' })),
    ...(data.links || []).map(x => ({ ...x, type: 'link' })),
    ...(data.docs || []).map(x => ({ ...x, type: 'doc' })),
    ...(data.dashboards || []).map(x => ({ ...x, type: 'dashboard' }))
  ].map(item => {
    const meta = TYPE_META[item.type] || {};
    return {
      ...item,
      _badge: meta.badge || '',
      _quick: meta.quick || 'Open',
      _label: meta.label || (item.label || ''),
      _desc: item.description && item.description.trim() ? item.description : 'View details',
      _img: item.image || 'assets/layers/default.png',
      _url: item.url,
      _name: item.name
    };
  });

  // Only update if we have results or we've had user interaction
  if (allResults.length > 0 || hasUserInteracted) {
    grid.innerHTML = '';

    allResults.forEach(item => {
      const div = document.createElement('div');
      div.className = 'group h-64 relative';

      div.innerHTML = `
        <a href="${item._url}" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" rel="noopener" aria-label="${item._quick}: ${item._name}">
          <div class="thumb-wrap relative">
            <img loading="lazy" src="${item._img}" alt="${item._name} thumbnail" class="w-full h-32 object-cover hover-zoom">
            <div class="quick-actions">
              <span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">${item._quick}</span>
            </div>
          </div>
          <div class="p-3 flex flex-col flex-1 justify-between">
            <div>
              <h3 class="text-base font-semibold text-gray-900 mb-1">${item._name}</h3>
              <p class="text-sm text-gray-500 line-clamp-2 mb-3">${item._desc}</p>
            </div>
          </div>
          <div class="card-foot px-3 py-2 flex items-center justify-between">
            <span class="badge ${item._badge}">${item._label}</span>
            ${item.is_public === false ? '' : '<span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>'}
          </div>
        </a>
      `;

      grid.appendChild(div);
    });

    if (allResults.length === 0 && hasUserInteracted) {
      grid.innerHTML = `
        <div class="col-span-full text-center py-12">
          <p class="text-gray-500">No results found. Try adjusting your search criteria.</p>
        </div>
      `;
    }
  }

  isInitialLoad = false;
}

document.addEventListener('DOMContentLoaded', function() {
    
    searchForm = {
        text: document.querySelector('input[placeholder="Search anything..."]'),
        topic: document.getElementById('topic_id'),
        gemet: document.getElementById('gemet_id'),
        keywordInput: document.querySelector('input[placeholder="Add keywords..."]'),
        filters: document.querySelectorAll('input[type="checkbox"]'),
        keywordTags: document.getElementById('keywordTags'),
        mapButton: document.querySelector('button.bg-blue-500')
    };

    // Handle keyword input
    if (searchForm.keywordInput) {
        searchForm.keywordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                e.preventDefault();
                hasUserInteracted = true;
                addKeyword(this.value.trim());
                this.value = '';
            }
        });
    }

    // Only add event listeners if elements exist
    if (searchForm.text) {
        searchForm.text.addEventListener('input', function() {
            hasUserInteracted = true;
            debounceSearch();
        });
    }
    if (searchForm.topic) {
        searchForm.topic.addEventListener('change', function() {
            hasUserInteracted = true;
            isInitialLoad = false;
            performSearch();
        });
    }
    if (searchForm.gemet) {
        searchForm.gemet.addEventListener('change', function() {
            hasUserInteracted = true;
            isInitialLoad = false;
            performSearch();
        });
    }
    searchForm.filters.forEach(filter => {
        filter.addEventListener('change', function() {
            hasUserInteracted = true;
            isInitialLoad = false;
            performSearch();
        });
    });

    // Handle map search button
    document.getElementById('openMapSearch').addEventListener('click', function() {
        // Open the map search modal
        document.getElementById('mapSearchModal').classList.remove('hidden');
    });
    
    //addKeyword('Geology');
    //addKeyword('Maps');
});
