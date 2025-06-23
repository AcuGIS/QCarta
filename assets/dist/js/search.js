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

// Update results in the grid
function updateResults(data) {
    const grid = document.querySelector('.grid');
    if (!grid) {
        console.error('Results grid not found');
        return;
    }

    // Combine all results
    const allResults = [
        ...(data.layers || []).map(item => ({...item, type: 'layer', label:'Map', icon_path:'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />'})),
        ...(data.stories || []).map(item => ({...item, type: 'story', label:'Presentation', icon_path:'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'})),
        ...(data.links || []).map(item => ({...item, type: 'link', label:'Link', icon_path:'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"></path>'})),
        ...(data.docs || []).map(item => ({...item, type: 'doc', label:'Document', icon_path:'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"></path>'}))
    ];

    console.log('Total results:', allResults.length);

    // Only update if we have results or we've had user interaction
    if (allResults.length > 0 || hasUserInteracted) {
        grid.innerHTML = '';

        // Create grid items
        allResults.forEach(item => {
            const div = document.createElement('div');
            div.className = 'group';
            div.innerHTML = `
                <a href="${item.url}" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                    <div class="relative">
                        <img src="${item.image}" alt="${item.name}" class="w-full h-36 object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                    </div>
                    <div class="p-4">
                        <h3 class="text-base font-medium text-gray-900 mb-1">${item.name}</h3>
              <p class="text-sm text-gray-500 line-clamp-2">${item.description}</p>
<p>&nbsp;</p>
              <div class="flex items-center text-sm text-gray-600">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${item.icon_path}
                  </svg>
                  ${item.label}
              </div>
              <div class="mt-auto flex items-center justify-between pt-4">
                  <div class="flex items-center text-sm text-gray-500">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                      Last updated: ${item.last_updated}
                  </div>
              </div>
              </div>
            </a>`;
            grid.appendChild(div);
        });

        // Show message if no results and we've had user interaction
        if (allResults.length === 0 && hasUserInteracted) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-500">No results found. Try adjusting your search criteria.</p>
                </div>
            `;
        }
    }

    // Set initial load to false after first search
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
