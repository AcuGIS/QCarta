// Map Search functionality
document.addEventListener('DOMContentLoaded', function() {
    let map;
    let rectangle;
    let drawingMode = false;
    let startPoint;
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
    openButton.addEventListener('click', function() {
        modal.classList.remove('hidden');
        if (!map) {
            initMap();
        } else {
            setTimeout(() => { map.invalidateSize(); }, 100);
        }
    });

    // Close modal
    closeButton.addEventListener('click', function() {
        modal.classList.add('hidden');
        if (currentRectangle) {
            map.removeLayer(currentRectangle);
            currentRectangle = null;
        }
        drawingMode = false;
    });

    // Initialize the map
    function initMap() {
        if (map) {
            map.remove();
            map = null;
        }
        map = L.map('searchMap').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add Leaflet Draw control
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
                    shapeOptions: {
                        color: '#3388ff',
                        weight: 2,
                        fillOpacity: 0.2
                    }
                }
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });
        map.addControl(drawControl);

        // Listen for rectangle draw event
        map.on(L.Draw.Event.CREATED, function (e) {
            if (currentRectangle) {
                drawnItems.removeLayer(currentRectangle);
            }
            const layer = e.layer;
            drawnItems.addLayer(layer);
            currentRectangle = layer;
        });

        // Listen for rectangle delete event
        map.on('draw:deleted', function () {
            currentRectangle = null;
        });
    }

    // Clear selection
    clearButton.addEventListener('click', function() {
        if (currentRectangle) {
            map.removeLayer(currentRectangle);
            currentRectangle = null;
        }
        drawingMode = false;
    });

    // Search in selected area
    searchButton.addEventListener('click', async function() {
        if (!currentRectangle) {
            alert('Please select an area on the map first');
            return;
        }

        const bounds = currentRectangle.getBounds();
        const minLng = bounds.getSouthWest().lng;
        const minLat = bounds.getSouthWest().lat;
        const maxLng = bounds.getNorthEast().lng;
        const maxLat = bounds.getNorthEast().lat;
        if (minLng === maxLng || minLat === maxLat) {
            alert('Please draw a rectangle (not just a point) on the map.');
            return;
        }
        const bbox = [minLng, minLat, maxLng, maxLat].join(',');

        try {
            // Build search parameters
            const searchParams = new URLSearchParams({
                text: '',  // Empty text search
                topic: '', // No topic filter
                gemet: '',
                keywords: '', // No keywords
                filters: 'layers', // Only search layers
                bbox: bbox // Add the bounding box
            });

            // Show loading state
            const mainContent = document.querySelector('.grid');
            mainContent.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Searching...</div>';

            // Make the search request
            const response = await fetch(`admin/action/search_handler.php?${searchParams.toString()}`);
            if (!response.ok) {
                throw new Error('Failed to fetch search results');
            }
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.details || data.message || 'Search failed');
            }
            
            handleSearchResults(data);
        } catch (error) {

            const mainContent = document.querySelector('.grid');
            mainContent.innerHTML = `<div class="col-span-full text-center py-8 text-red-500">Error: ${error.message}</div>`;
        }
    });

    // Handle search results
    function handleSearchResults(data) {
        // Close the modal
        modal.classList.add('hidden');
        
        // Clear any existing results
        const mainContent = document.querySelector('.grid');
        mainContent.innerHTML = '';
        
        // Display the results
        if (data.layers && data.layers.length > 0) {
            data.layers.forEach(layer => {
                const image = `assets/layers/${layer.id}.png`;
                const card = createLayerCard(layer, image);
                mainContent.appendChild(card);
            });
        } else {
            mainContent.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">No layers found in the selected area</div>';
        }
    }

    // Create a layer card element
    function createLayerCard(layer, image) {
        const div = document.createElement('div');
        div.className = 'group';
        div.innerHTML = `
            <a href="layers/${layer.id}/index.php" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                <div class="relative">
                    <img src="${image}" alt="${layer.name.replace(/_/g, ' ')}" 
                         class="w-full h-48 object-cover"
                         onerror="this.src='assets/layers/default.png'">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                </div>
                <div class="p-4">
                    <h3 class="text-base font-medium text-gray-900 mb-1">
                        ${layer.name.replace(/_/g, ' ')}
                    </h3>
                    <p class="text-sm text-gray-500 line-clamp-2">${layer.description}</p>
<p>&nbsp;</p>
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                        </svg>
                        Map
                    </div>
                    <div class="mt-auto flex items-center justify-between pt-4">
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Last updated: ${layer.last_updated}
                        </div>
                    </div>
                </div>
            </a>
        `;
        return div;
    }
});
