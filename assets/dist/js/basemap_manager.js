// Simple Basemap Manager for QGIS Map Viewer
class BasemapManager {
    constructor() {
        this.basemaps = [];
        this.currentBasemap = null;
        this.map = null;
        this.baseLayer = null; // Single basemap layer like the working system
        this.init();
    }

    async init() {
        try {
            console.log('BasemapManager: Starting initialization...');
            await this.loadBasemaps();
            console.log('BasemapManager: Basemaps loaded, populating list...');
            this.populateBasemapList();
        
            // Try to build the base layer if the map is already available
            console.log('BasemapManager: Ensuring base layer...');
            this.ensureBaseLayer();
            console.log('BasemapManager: Initialization complete');
} catch (error) {
            console.error('Failed to initialize basemap manager:', error);
        }
    }

    async loadBasemaps() {
        try {
            const response = await fetch('../../admin/action/basemap.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            if (data.success && data.basemaps && data.basemaps.length > 0) {
                this.basemaps = data.basemaps;
                console.log('BasemapManager: Loaded basemaps from server:', this.basemaps);
            } else {
                this.basemaps = this.getDefaultBasemaps();
                console.log('BasemapManager: Using default basemaps:', this.basemaps);
            }
        } catch (error) {
            this.basemaps = this.getDefaultBasemaps();
            console.log('BasemapManager: Error loading basemaps, using defaults:', error);
        }
    }

    getDefaultBasemaps() {
        return [
            {
                id: 'carto',
                name: 'Carto Light',
                description: 'Clean, light basemap from CartoDB',
                url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                type: 'xyz',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                min_zoom: 0,
                max_zoom: 18
            },
            {
                id: 'osm',
                name: 'OpenStreetMap',
                description: 'Standard OpenStreetMap tiles',
                url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                type: 'xyz',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                min_zoom: 0,
                max_zoom: 18
            },
            {
                id: 'esri',
                name: 'ESRI Satellite',
                description: 'High-resolution satellite imagery from ESRI',
                url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                type: 'xyz',
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                min_zoom: 0,
                max_zoom: 18
            }
        ];
    }

    // Resolve a thumbnail path to a browser-usable URL.
    // If 'thumbnail' is absolute (starts with http or '/'), use it as-is.
    // Otherwise, assume it's a filename under /assets/images (web root at /var/www/html).
    resolveThumbnailUrl(basemap) {
        const t = basemap && basemap.thumbnail ? String(basemap.thumbnail) : '';
        if (!t) return null;
        if (/^https?:\/\//i.test(t) || t.startsWith('/')) return t;
        return `/assets/images/${t}`;
    }

    populateBasemapList() {
        const basemapList = document.getElementById('basemapList');
        if (!basemapList) return;

        basemapList.innerHTML = '';
        
        const gridContainer = document.createElement('div');
        gridContainer.className = 'row g-2';
        
        if (!this.basemaps || this.basemaps.length === 0) {
            basemapList.innerHTML = '<div class="alert alert-warning">No basemaps available</div>';
            return;
        }
        
        this.basemaps.forEach((basemap) => {
            const isCurrent = this.currentBasemap && this.currentBasemap.id === basemap.id;
            
            const col = document.createElement('div');
            col.className = 'col-6';
            
            const card = document.createElement('div');
            card.className = `basemap-card card h-100 ${isCurrent ? 'border-primary' : ''}`;
            card.style.cursor = 'pointer';
            card.style.transition = 'all 0.2s ease';
            
            if (isCurrent) {
                card.classList.add('border-primary', 'shadow-sm');
            }
            
            const thumbnailColor = this.getThumbnailColor(basemap);
            
            
card.innerHTML = `
  <div class=\"basemap-thumbnail\">
    ${ (function(){ const _u = this.resolveThumbnailUrl(basemap); return _u ? 
       `<img src=\"${_u}\" alt=\"${basemap.name}\" style=\"width:100%;height:100%;object-fit:cover;border-radius:4px;\">` :
       `<div style=\\"width:100%;height:100%;background:${this.getThumbnailColor(basemap)};border-radius:4px;\\"></div>`; }).call(this) }
  </div>
  <div class=\"basemap-info\">
    <h6>${basemap.name}</h6>
  </div>
`;
card.addEventListener('click', () => {
                this.switchBasemap(basemap.id);
            });

            card.addEventListener('mouseenter', () => {
                if (!isCurrent) {
                    card.style.transform = 'translateY(-2px)';
                    card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                }
            });

            card.addEventListener('mouseleave', () => {
                if (!isCurrent) {
                    card.style.transform = 'translateY(0)';
                    card.style.boxShadow = 'none';
                }
            });

            col.appendChild(card);
            gridContainer.appendChild(col);
        });
        
        basemapList.appendChild(gridContainer);
    }

    switchBasemap(basemapId) {
        const basemap = this.basemaps.find(b => b.id == basemapId);
        if (!basemap || !this.map) {
            console.log('Cannot switch basemap:', { basemap, map: this.map });
            return;
        }

        console.log('Switching to basemap:', basemap.name);
        console.log('Current baseLayer:', this.baseLayer);

        // Use the exact same approach as the working backup system
        if (this.baseLayer) {
            // Just change the URL - this preserves all other layers
            console.log('Changing basemap URL to:', basemap.url);
            this.baseLayer.setUrl(basemap.url);
        } else {
            console.warn('baseLayer was null; creating via ensureBaseLayer()...');
            this.ensureBaseLayer();
            if (this.baseLayer) {
                this.baseLayer.setUrl(basemap.url);
            } else {
                console.error('baseLayer is still null - basemap manager not properly connected to map');
                return;
            }
        }

        // Update current basemap
        this.currentBasemap = basemap;

        // Update UI
        this.updateUI();
        this.updateAttribution();
    }

    updateUI() {
        this.populateBasemapList();
    }

    updateAttribution() {
        const attributionDiv = document.getElementById('basemapAttribution');
        const attributionText = document.getElementById('attributionText');
        
        if (attributionDiv && attributionText && this.currentBasemap) {
            if (this.currentBasemap.attribution) {
                attributionText.innerHTML = this.currentBasemap.attribution;
                attributionDiv.style.display = 'block';
            } else {
                attributionDiv.style.display = 'none';
            }
        }
    }

    // Ensure the baseLayer exists once both map and basemaps are available
    ensureBaseLayer() {
        try {
            if (!this.map) return;
            if (this.baseLayer) return;
            if (!Array.isArray(this.basemaps) || this.basemaps.length === 0) return;
            const firstBasemap = this.getPreferredDefaultBasemap();
            this.baseLayer = L.tileLayer(firstBasemap.url, {
                attribution: firstBasemap.attribution,
                minZoom: firstBasemap.min_zoom,
                maxZoom: firstBasemap.max_zoom,
                zIndex: 0
            });
            this.baseLayer.addTo(this.map);
            this.currentBasemap = firstBasemap;
            this.updateAttribution();
            console.log('BasemapManager: ensureBaseLayer() created initial basemap');
        } catch (e) {
            console.error('BasemapManager.ensureBaseLayer() failed:', e);
        }
    }

    // Choose the preferred default basemap based on layer configuration or fallback to Carto Light
    getPreferredDefaultBasemap() {
        if (!Array.isArray(this.basemaps)) return null;
        
        // First priority: Use the default basemap from layer configuration
        console.log('Checking for default basemap:', defaultBasemap);
        console.log('Available basemaps:', this.basemaps);
        if (typeof defaultBasemap !== 'undefined' && defaultBasemap && defaultBasemap.id) {
            const configuredBasemap = this.basemaps.find(b => b.id == defaultBasemap.id);
            console.log('Found configured basemap:', configuredBasemap);
            if (configuredBasemap) {
                console.log('Using configured default basemap:', configuredBasemap.name);
                return configuredBasemap;
            } else {
                console.log('Configured basemap not found in available basemaps');
            }
        } else {
            console.log('No default basemap configured or invalid data');
        }
        
        // Fallback: Choose Carto Light as the preferred default, regardless of array order
        return (
            this.basemaps.find(b => b.id === 'carto') ||                                     // explicit id
            this.basemaps.find(b => /carto/i.test(b.name || '') && /light/i.test(b.name || '')) || // name says Carto Light
            this.basemaps.find(b => /light_all/.test(b.url || '')) ||                        // url includes light tiles
            this.basemaps[0] || null
        );
    }

    setMap(map) {
        if (!map) {
            console.error('BasemapManager: No map provided to setMap');
            return;
        }
        
        this.map = map;
        console.log('BasemapManager: Map set successfully');
        
        
        // Build base layer if basemaps are already loaded
        this.ensureBaseLayer();
// Create the basemap layer FIRST, before any WMS layers are added
        // This matches the working backup system exactly
        if (this.basemaps.length > 0) {
            const firstBasemap = this.getPreferredDefaultBasemap();
            this.baseLayer = L.tileLayer(firstBasemap.url, {
                attribution: firstBasemap.attribution,
                minZoom: firstBasemap.min_zoom,
                maxZoom: firstBasemap.max_zoom
            });
            this.baseLayer.addTo(map);
            this.currentBasemap = firstBasemap;
            console.log('Basemap layer created and added to map FIRST');
        }
    }

    refreshBasemapList() {
        this.populateBasemapList();
    }
    
    getThumbnailColor(basemap) {
        const name = basemap.name.toLowerCase();
        
        if (name.includes('satellite') || name.includes('imagery')) {
            return 'linear-gradient(135deg, #8B4513, #A0522D)';
        } else if (name.includes('dark') || name.includes('night')) {
            return 'linear-gradient(135deg, #2F2F2F, #404040)';
        } else if (name.includes('carto')) {
            return 'linear-gradient(135deg, #87CEEB, #4682B4)';
        } else if (name.includes('openstreetmap') || name.includes('osm')) {
            return 'linear-gradient(135deg, #90EE90, #32CD32)';
        } else if (name.includes('esri')) {
            return 'linear-gradient(135deg, #FF6B6B, #FF8E8E)';
        } else {
            return 'linear-gradient(135deg, #87CEEB, #4682B4)';
        }
    }
}

// Initialize basemap manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const basemapList = document.getElementById('basemapList');
    if (basemapList) {
        window.basemapManager = new BasemapManager();
    }
});

// Initialize when the basemap tab is clicked
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'basemaps-tab') {
        setTimeout(() => {
            if (window.basemapManager) {
                window.basemapManager.refreshBasemapList();
            }
        }, 100);
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BasemapManager;
}
