/**
 * Plotly Chart Default Configuration Handler
 * This file manages initial values for Plotly chart selections
 */

// Function to set initial values for Plotly chart selections
function initializePlotlyDefaults() {
    console.log('=== INITIALIZING PLOTLY DEFAULTS ===');
    console.log('Available plotlyDefaults:', plotlyDefaults);
    
    if (!plotlyDefaults || !plotlyDefaults.defaults) {
        console.log('No Plotly defaults configured');
        return;
    }

    const defaults = plotlyDefaults.defaults;
    console.log('Setting Plotly defaults:', defaults);
    
    // Set default chart type immediately
    if (defaults.chartType) {
        const chartTypeSelect = document.getElementById('plotlyChartType');
        if (chartTypeSelect) {
            chartTypeSelect.value = defaults.chartType;
            console.log('✓ Set chart type to:', defaults.chartType);
        } else {
            console.log('✗ Chart type select not found');
        }
    }

    // Set default chart configuration
    if (defaults.chartConfig) {
        const configSelect = document.getElementById('plotlyConfig');
        if (configSelect) {
            const options = Array.from(configSelect.options);
            const defaultOption = options.find(option => option.value === defaults.chartConfig);
            if (defaultOption) {
                configSelect.value = defaultOption.value;
                console.log('✓ Set chart config to:', defaults.chartConfig);
            } else {
                console.log('✗ Chart config option not found:', defaults.chartConfig);
            }
        } else {
            console.log('✗ Chart config select not found');
        }
    }

    // Set default layer and trigger field population
    if (defaults.layer) {
        const layerSelect = document.getElementById('plotlyLayerSelect');
        if (layerSelect) {
            console.log('Layer select found, options:', layerSelect.options.length);
            // Wait for options to be populated
            setTimeout(() => {
                const options = Array.from(layerSelect.options);
                console.log('Available layer options:', options.map(opt => opt.text));
                const defaultOption = options.find(option => option.text === defaults.layer);
                if (defaultOption) {
                    layerSelect.value = defaultOption.value;
                    console.log('✓ Set layer to:', defaults.layer);
                    
                    // Trigger change event to populate other fields
                    layerSelect.dispatchEvent(new Event('change'));
                    
                    // Set X and Y fields after a delay to allow field population
                    setTimeout(() => {
                        setFieldDefaults(defaults.xField, defaults.yField);
                    }, 1000);
                } else {
                    console.log('✗ Layer option not found:', defaults.layer);
                    // Try to set fields anyway
                    setTimeout(() => {
                        setFieldDefaults(defaults.xField, defaults.yField);
                    }, 1000);
                }
            }, 500);
        } else {
            console.log('✗ Layer select not found');
            // Try to set fields anyway
            setTimeout(() => {
                setFieldDefaults(defaults.xField, defaults.yField);
            }, 1000);
        }
    } else {
        // If no layer specified, just set the fields after a delay
        setTimeout(() => {
            setFieldDefaults(defaults.xField, defaults.yField);
        }, 1000);
    }
}

// Function to set X and Y field defaults
function setFieldDefaults(xField, yField) {
    console.log('=== SETTING FIELD DEFAULTS ===');
    console.log('X field to set:', xField);
    console.log('Y field to set:', yField);
    
    if (xField) {
        const xFieldSelect = document.getElementById('plotlyXField');
        if (xFieldSelect) {
            console.log('X field select found, options:', xFieldSelect.options.length);
            if (xFieldSelect.options.length > 0) {
                // Try to find exact match first
                let found = false;
                for (let i = 0; i < xFieldSelect.options.length; i++) {
                    const option = xFieldSelect.options[i];
                    console.log('Checking option:', option.text, 'vs', xField);
                    if (option.text === xField || option.value === xField) {
                        xFieldSelect.selectedIndex = i;
                        console.log('✓ Set X field to:', xField);
                        found = true;
                        break;
                    }
                }
                // If not found, set to first option
                if (!found && xFieldSelect.options.length > 0) {
                    xFieldSelect.selectedIndex = 0;
                    console.log('✗ X field not found, set to first option:', xFieldSelect.options[0].text);
                }
            } else {
                console.log('✗ X field select has no options');
            }
        } else {
            console.log('✗ X field select not found');
        }
    }

    if (yField) {
        const yFieldSelect = document.getElementById('plotlyYField');
        if (yFieldSelect) {
            console.log('Y field select found, options:', yFieldSelect.options.length);
            if (yFieldSelect.options.length > 0) {
                // Try to find exact match first
                let found = false;
                for (let i = 0; i < yFieldSelect.options.length; i++) {
                    const option = yFieldSelect.options[i];
                    console.log('Checking option:', option.text, 'vs', yField);
                    if (option.text === yField || option.value === yField) {
                        yFieldSelect.selectedIndex = i;
                        console.log('✓ Set Y field to:', yField);
                        found = true;
                        break;
                    }
                }
                // If not found, set to first option
                if (!found && yFieldSelect.options.length > 0) {
                    yFieldSelect.selectedIndex = 0;
                    console.log('✗ Y field not found, set to first option:', yFieldSelect.options[0].text);
                }
            } else {
                console.log('✗ Y field select has no options');
            }
        } else {
            console.log('✗ Y field select not found');
        }
    }
    
    // Try to trigger chart rendering with a single, clean approach
    setTimeout(() => {
        if (typeof map !== 'undefined' && map) {
            console.log('✓ Triggering chart render via map interaction');
            // Trigger a small map movement to force chart update
            const currentCenter = map.getCenter();
            map.panBy([1, 1]); // Move 1 pixel
            setTimeout(() => {
                map.panTo(currentCenter); // Move back
            }, 100);
        }
    }, 1000);
}

// Function to trigger chart update using existing system (simplified)
function triggerChartUpdate() {
    console.log('=== TRIGGERING CHART UPDATE ===');
    
    // Simple approach - just trigger map events
    if (typeof map !== 'undefined' && map) {
        console.log('✓ Triggering map events for chart update');
        map.fire('moveend');
    }
}

// Function to set layer-specific defaults when layer changes
function setLayerDefaults(selectedLayer) {
    console.log('=== SETTING LAYER DEFAULTS ===');
    console.log('Selected layer:', selectedLayer);
    
    if (!plotlyDefaults || !plotlyDefaults.layerDefaults || !plotlyDefaults.layerDefaults[selectedLayer]) {
        console.log('No layer defaults found for:', selectedLayer);
        return;
    }

    const layerDefault = plotlyDefaults.layerDefaults[selectedLayer];
    console.log('Layer defaults:', layerDefault);
    
    // Set chart type for this layer
    if (layerDefault.chartType) {
        const chartTypeSelect = document.getElementById('plotlyChartType');
        if (chartTypeSelect) {
            chartTypeSelect.value = layerDefault.chartType;
            console.log('✓ Set layer chart type to:', layerDefault.chartType);
        }
    }

    // Set chart configuration for this layer
    if (layerDefault.chartConfig) {
        const configSelect = document.getElementById('plotlyConfig');
        if (configSelect) {
            const options = Array.from(configSelect.options);
            const defaultOption = options.find(option => option.value === layerDefault.chartConfig);
            if (defaultOption) {
                configSelect.value = defaultOption.value;
                console.log('✓ Set layer chart config to:', layerDefault.chartConfig);
            }
        }
    }

    // Set X and Y field defaults after fields are populated
    if (layerDefault.xField || layerDefault.yField) {
        setTimeout(() => {
            setFieldDefaults(layerDefault.xField, layerDefault.yField);
        }, 500);
    }
}

// Function to filter chart types based on selected layer
function filterChartTypes(selectedLayer) {
    if (!plotlyDefaults || !plotlyDefaults.chartTypes || !plotlyDefaults.chartTypes[selectedLayer]) {
        return;
    }

    const chartTypeSelect = document.getElementById('plotlyChartType');
    if (!chartTypeSelect) return;

    const allowedTypes = plotlyDefaults.chartTypes[selectedLayer];
    const currentValue = chartTypeSelect.value;

    // Clear current options
    chartTypeSelect.innerHTML = '';

    // Add allowed chart types
    allowedTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        chartTypeSelect.appendChild(option);
    });

    // Set to first available type if current value is not allowed
    if (!allowedTypes.includes(currentValue) && allowedTypes.length > 0) {
        chartTypeSelect.value = allowedTypes[0];
    }
}

// Event listener for when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM LOADED ===');
    console.log('Plotly defaults available:', plotlyDefaults);
    
    // Initialize defaults when Plotly tab is shown
    const plotlyTab = document.getElementById('plotly-tab');
    if (plotlyTab) {
        console.log('Plotly tab found, adding click listener');
        plotlyTab.addEventListener('click', function() {
            console.log('=== PLOTLY TAB CLICKED ===');
            // Check for URL parameters first, then fall back to JSON defaults
            const urlDefaults = getUrlDefaults();
            if (urlDefaults.layer || urlDefaults.chartType || urlDefaults.xField || urlDefaults.yField || urlDefaults.chartConfig) {
                console.log('Using URL defaults');
                setTimeout(setUrlDefaults, 100);
            } else {
                console.log('Using JSON defaults');
                setTimeout(initializePlotlyDefaults, 100);
            }
        });
    } else {
        console.log('✗ Plotly tab not found');
    }

    // Set up event listeners for layer selection changes
    const layerSelect = document.getElementById('plotlyLayerSelect');
    if (layerSelect) {
        console.log('Layer select found, adding change listener');
        layerSelect.addEventListener('change', function() {
            const selectedLayer = this.options[this.selectedIndex].text;
            console.log('=== LAYER CHANGED ===');
            console.log('New layer:', selectedLayer);
            setLayerDefaults(selectedLayer);
        });
    } else {
        console.log('✗ Layer select not found');
    }
    
    // Also try to initialize immediately if Plotly tab is already active
    setTimeout(() => {
        const plotlyTabPane = document.getElementById('plotly');
        if (plotlyTabPane && plotlyTabPane.classList.contains('show')) {
            console.log('=== PLOTLY TAB ALREADY ACTIVE ===');
            initializePlotlyDefaults();
        }
    }, 2000);
});

// Function to get URL parameters for initial values
function getUrlDefaults() {
    const urlParams = new URLSearchParams(window.location.search);
    return {
        layer: urlParams.get('plotly_layer'),
        chartType: urlParams.get('plotly_chart_type'),
        xField: urlParams.get('plotly_x_field'),
        yField: urlParams.get('plotly_y_field'),
        chartConfig: urlParams.get('plotly_config')
    };
}

// Function to set defaults from URL parameters (overrides JSON defaults)
function setUrlDefaults() {
    const urlDefaults = getUrlDefaults();
    console.log('Setting URL defaults:', urlDefaults);
    
    if (urlDefaults.layer) {
        const layerSelect = document.getElementById('plotlyLayerSelect');
        if (layerSelect) {
            setTimeout(() => {
                const options = Array.from(layerSelect.options);
                const defaultOption = options.find(option => option.text === urlDefaults.layer);
                if (defaultOption) {
                    layerSelect.value = defaultOption.value;
                    layerSelect.dispatchEvent(new Event('change'));
                }
            }, 100);
        }
    }

    if (urlDefaults.chartType) {
        const chartTypeSelect = document.getElementById('plotlyChartType');
        if (chartTypeSelect) {
            chartTypeSelect.value = urlDefaults.chartType;
        }
    }

    if (urlDefaults.chartConfig) {
        const configSelect = document.getElementById('plotlyConfig');
        if (configSelect) {
            configSelect.value = urlDefaults.chartConfig;
        }
    }

    if (urlDefaults.xField || urlDefaults.yField) {
        setTimeout(() => {
            setFieldDefaults(urlDefaults.xField, urlDefaults.yField);
        }, 500);
    }
}

// Export functions for use in other scripts
window.PlotlyDefaults = {
    initialize: initializePlotlyDefaults,
    setLayerDefaults: setLayerDefaults,
    setFieldDefaults: setFieldDefaults,
    setUrlDefaults: setUrlDefaults,
    getUrlDefaults: getUrlDefaults,
    triggerChartUpdate: triggerChartUpdate
};
