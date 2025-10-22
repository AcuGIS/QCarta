// Initialize tables when page loads
$(document).ready(function() {
  // Load data for the first tab
  loadLayerData(layers[0], 0);
  
  // Handle tab changes
  $('#layerTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    const layer = $(e.target).data('layer');
    const index = layers.indexOf(layer);
    if (!dataTables[layer]) {
      loadLayerData(layer, index);
    }
  });
});

function loadLayerData(layer, index) {
  const loadingDiv = $(`#loading-${index}`);
  const errorDiv = $(`#error-${index}`);
  const table = $(`#dataTable-${index}`);
  
  // Show loading
  loadingDiv.show();
  errorDiv.hide();
  
  // Build WFS request URL
  const wfsUrl = `${WMS_SVC_URL}&SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&LAYERS=${layer}&TYPENAME=${layer}&OUTPUTFORMAT=application/json`;
  
  $.ajax({
    url: wfsUrl,
    method: 'GET',
    dataType: 'json',
    timeout: 30000,
    success: function(data) {
      loadingDiv.hide();
      
      if (data.features && data.features.length > 0) {
        // Store data for pivot
        layerData[layer] = data.features;
        
        // Extract properties from first feature to get column headers
        const firstFeature = data.features[0];
        const properties = firstFeature.properties;
        const columns = Object.keys(properties);
        
        // Populate field list for pivot
        populateFieldList(index, columns);
        
        // Populate chart column selectors
        populateChartColumns(index, columns);
        
        // Create table headers
        let headerHtml = '<tr>';
        columns.forEach(col => {
          headerHtml += `<th>${col}</th>`;
        });
        headerHtml += '</tr>';
        table.find('thead').html(headerHtml);
        
        // Create table rows
        let bodyHtml = '';
        data.features.forEach(feature => {
          bodyHtml += '<tr>';
          columns.forEach(col => {
            const value = feature.properties[col];
            bodyHtml += `<td>${value !== null && value !== undefined ? htmlEscape(value.toString()) : ''}</td>`;
          });
          bodyHtml += '</tr>';
        });
        table.find('tbody').html(bodyHtml);
        
        // Initialize DataTable
        if (dataTables[layer]) {
          dataTables[layer].destroy();
        }
        
        dataTables[layer] = table.DataTable({
          pageLength: 25,
          responsive: true,
          dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
          buttons: [
            {
              extend: 'copy',
              text: '<i class="fas fa-copy"></i> Copy',
              className: 'btn btn-secondary btn-sm'
            },
            {
              extend: 'csv',
              text: '<i class="fas fa-file-csv"></i> CSV',
              className: 'btn btn-success btn-sm',
              title: layer + '_data'
            },
            {
              extend: 'excel',
              text: '<i class="fas fa-file-excel"></i> Excel',
              className: 'btn btn-info btn-sm',
              title: layer + '_data'
            },
            {
              extend: 'pdf',
              text: '<i class="fas fa-file-pdf"></i> PDF',
              className: 'btn btn-danger btn-sm',
              title: layer + '_data'
            },
            {
              extend: 'print',
              text: '<i class="fas fa-print"></i> Print',
              className: 'btn btn-warning btn-sm'
            }
          ],
          language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries per page",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
          }
        });
        
      } else {
        errorDiv.find('#error-text-' + index).text('No data found for this layer');
        errorDiv.show();
      }
    },
    error: function(xhr, status, error) {
      loadingDiv.hide();
      errorDiv.find('#error-text-' + index).text(`Error loading data: ${error}`);
      errorDiv.show();
      console.error('Error loading layer data:', error);
    }
  });
}

      function populateFieldList(index, columns) {
    const fieldList = $(`#field-list-${index}`);
    fieldList.empty();
    
    columns.forEach(col => {
      const fieldItem = $(`<div class="field-item" data-field="${col}" draggable="true">${col}</div>`);
      
      // Click to add field
      fieldItem.click(function() {
        addFieldToPivot(index, col);
      });
      
      // Drag functionality
      fieldItem.on('dragstart', function(e) {
        e.originalEvent.dataTransfer.setData('text/plain', col);
        e.originalEvent.dataTransfer.setData('application/json', JSON.stringify({field: col, index: index}));
        $(this).addClass('dragging');
      });
      
      fieldItem.on('dragend', function() {
        $(this).removeClass('dragging');
      });
      
      fieldList.append(fieldItem);
    });
    
    // Setup drop zones for this index
    setupDropZones(index);
  }

      function addFieldToPivot(index, field, targetType = null) {
    if (!pivotConfig[index]) {
      pivotConfig[index] = { rows: [], cols: [], vals: [] };
    }
    
    // If target type is specified (from drag and drop), use it
    if (targetType && !pivotConfig[index][targetType].includes(field)) {
      pivotConfig[index][targetType].push(field);
      updateDropZone(index, targetType, pivotConfig[index][targetType]);
      return;
    }
    
    // Otherwise, use simple logic: add to rows first, then cols, then vals
    // Allow more row fields when subtotals are enabled
    const maxRows = $(`#subtotals-${index}`).is(':checked') ? 3 : 2;
    if (pivotConfig[index].rows.length < maxRows && !pivotConfig[index].rows.includes(field)) {
      pivotConfig[index].rows.push(field);
      updateDropZone(index, 'rows', pivotConfig[index].rows);
    } else if (pivotConfig[index].cols.length < 2 && !pivotConfig[index].cols.includes(field)) {
      pivotConfig[index].cols.push(field);
      updateDropZone(index, 'cols', pivotConfig[index].cols);
    } else if (pivotConfig[index].vals.length < 1 && !pivotConfig[index].vals.includes(field)) {
      pivotConfig[index].vals.push(field);
      updateDropZone(index, 'vals', pivotConfig[index].vals);
    }
  }

      function setupDropZones(index) {
    const dropZones = ['rows', 'cols', 'vals'];
    
    dropZones.forEach(type => {
      const zone = $(`#${type}-zone-${index}`);
      
      // Prevent default drag behaviors
      zone.on('dragover', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('active');
      });
      
      zone.on('dragleave', function(e) {
        if (!$(this).has(e.relatedTarget).length) {
          $(this).removeClass('active');
        }
      });
      
      zone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('active');
        
        try {
          const data = JSON.parse(e.originalEvent.dataTransfer.getData('application/json'));
          if (data.index === index) {
            addFieldToPivot(index, data.field, type);
          }
        } catch (err) {
          // Fallback to text data
          const field = e.originalEvent.dataTransfer.getData('text/plain');
          if (field) {
            addFieldToPivot(index, field, type);
          }
        }
      });
    });
  }
  
  function updateDropZone(index, type, fields) {
    const zone = $(`#${type}-zone-${index}`);
    zone.empty();
    
    if (fields.length === 0) {
      zone.html('<small class="text-muted">Drag fields here for ' + type + '</small>');
    } else {
      fields.forEach(field => {
        const fieldItem = $(`<div class="field-item">${field}</div>`);
        fieldItem.click(function() {
          removeFieldFromPivot(index, type, field);
        });
        zone.append(fieldItem);
      });
    }
  }

function removeFieldFromPivot(index, type, field) {
  const config = pivotConfig[index];
  const fieldIndex = config[type].indexOf(field);
  if (fieldIndex > -1) {
    config[type].splice(fieldIndex, 1);
    updateDropZone(index, type, config[type]);
  }
}

function generatePivot(index) {
  const layer = layers[index];
  const config = pivotConfig[index];
  const data = layerData[layer];
  
  if (!config || !data) {
    alert('Please select fields and ensure data is loaded');
    return;
  }
  
  if (config.rows.length === 0 && config.cols.length === 0) {
    alert('Please select at least one field for rows or columns');
    return;
  }
  
  // Check if subtotals are enabled
  const showSubtotals = $(`#subtotals-${index}`).is(':checked');
  
  // Transform data for pivot
  const pivotData = transformDataForPivot(data);
  
  // Generate pivot table
  const pivotTable = createPivotTable(pivotData, config, showSubtotals);
  
  // Display result
  const output = $(`#pivot-output-${index}`);
  output.html(pivotTable);
  output.show();
}

function transformDataForPivot(features) {
  return features.map(feature => {
    const row = {};
    Object.keys(feature.properties).forEach(key => {
      const value = feature.properties[key];
      row[key] = value !== null && value !== undefined ? value : '';
    });
    return row;
  });
}

function createPivotTable(data, config, showSubtotals = false) {
  const rows = config.rows || [];
  const cols = config.cols || [];
  const vals = config.vals || [];
  
  if (rows.length === 0 && cols.length === 0) {
    return '<p>No fields selected for pivot</p>';
  }
  
  // Get unique values for rows and columns
  const rowValues = rows.length > 0 ? [...new Set(data.map(d => d[rows[0]]))] : [];
  const colValues = cols.length > 0 ? [...new Set(data.map(d => d[cols[0]]))] : [];
  
  // Create table HTML
  let tableHtml = '<table class="table table-bordered table-sm">';
  
  // Header row
  tableHtml += '<thead><tr>';
  if (rows.length > 0) {
    tableHtml += '<th>' + rows[0] + '</th>';
  }
  colValues.forEach(colVal => {
    tableHtml += '<th>' + colVal + '</th>';
  });
  tableHtml += '<th>Total</th></tr></thead>';
  
  // Data rows
  tableHtml += '<tbody>';
  
  if (showSubtotals && rows.length > 1) {
    // Generate subtotals for multiple row fields
    tableHtml += generateSubtotalsTable(data, rows, cols, vals, colValues);
  } else {
    // Simple table without subtotals
    tableHtml += generateSimpleTable(data, rows, cols, vals, rowValues, colValues);
  }
  
  // Grand total row
  tableHtml += '<tr class="table-dark"><td><strong>Grand Total</strong></td>';
  let grandTotal = 0;
  
  if (colValues.length > 0) {
    colValues.forEach(colVal => {
      const colData = data.filter(d => d[cols[0]] === colVal);
      
      let colTotal = 0;
      if (vals.length > 0) {
        // If we have value fields, aggregate them
        const valField = vals[0];
        colTotal = colData.reduce((sum, d) => {
          const val = parseFloat(d[valField]) || 0;
          return sum + val;
        }, 0);
        tableHtml += '<td><strong>' + colTotal.toFixed(2) + '</strong></td>';
      } else {
        // Otherwise, just count the records
        colTotal = colData.length;
        tableHtml += '<td><strong>' + colTotal + '</strong></td>';
      }
      grandTotal += colTotal;
    });
  } else {
    // When no columns are defined, calculate grand total
    if (vals.length > 0) {
      // If we have value fields, aggregate them
      const valField = vals[0];
      grandTotal = data.reduce((sum, d) => {
        const val = parseFloat(d[valField]) || 0;
        return sum + val;
      }, 0);
      tableHtml += '<td><strong>' + grandTotal.toFixed(2) + '</strong></td>';
    } else {
      // Otherwise, just count the records
      grandTotal = data.length;
      tableHtml += '<td><strong>' + grandTotal + '</strong></td>';
    }
  }
  
  tableHtml += '<td><strong>' + grandTotal + '</strong></td></tr>';
  
  tableHtml += '</tbody></table>';
  
  return tableHtml;
}

function generateSimpleTable(data, rows, cols, vals, rowValues, colValues) {
  let html = '';
  
  rowValues.forEach(rowVal => {
    html += '<tr>';
    html += '<td><strong>' + rowVal + '</strong></td>';
    
    let rowTotal = 0;
    
    if (colValues.length > 0) {
      // When columns are defined, calculate values for each column
      colValues.forEach(colVal => {
        const cellData = data.filter(d => 
          d[rows[0]] === rowVal && d[cols[0]] === colVal
        );
        
        let cellValue = 0;
        if (vals.length > 0) {
          // If we have value fields, aggregate them
          const valField = vals[0];
          cellValue = cellData.reduce((sum, d) => {
            const val = parseFloat(d[valField]) || 0;
            return sum + val;
          }, 0);
          html += '<td>' + cellValue.toFixed(2) + '</td>';
        } else {
          // Otherwise, just count the records
          cellValue = cellData.length;
          html += '<td>' + cellValue + '</td>';
        }
        rowTotal += cellValue;
      });
    } else {
      // When no columns are defined, calculate aggregate for the row
      const rowData = data.filter(d => d[rows[0]] === rowVal);
      
      if (vals.length > 0) {
        // If we have value fields, aggregate them
        const valField = vals[0];
        rowTotal = rowData.reduce((sum, d) => {
          const val = parseFloat(d[valField]) || 0;
          return sum + val;
        }, 0);
        html += '<td>' + rowTotal.toFixed(2) + '</td>';
      } else {
        // Otherwise, just count the records
        rowTotal = rowData.length;
        html += '<td>' + rowTotal + '</td>';
      }
    }
    
    html += '<td><strong>' + rowTotal + '</strong></td>';
    html += '</tr>';
  });
  
  return html;
}

function generateSubtotalsTable(data, rows, cols, vals, colValues) {
  let html = '';
  
  // Group data by the first row field
  const groupedData = {};
  data.forEach(d => {
    const key = d[rows[0]];
    if (!groupedData[key]) {
      groupedData[key] = [];
    }
    groupedData[key].push(d);
  });
  
  // Process each group
  Object.keys(groupedData).sort().forEach(groupKey => {
    const groupData = groupedData[groupKey];
    
    // If we have more than one row field, create subtotals
    if (rows.length > 1) {
      // Group by second row field within this group
      const subGroupedData = {};
      groupData.forEach(d => {
        const subKey = d[rows[1]];
        if (!subGroupedData[subKey]) {
          subGroupedData[subKey] = [];
        }
        subGroupedData[subKey].push(d);
      });
      
      // Add rows for each subgroup
      Object.keys(subGroupedData).sort().forEach(subKey => {
        html += '<tr>';
        html += '<td style="padding-left: 20px;">' + subKey + '</td>';
        
        let subTotal = 0;
        
        if (colValues.length > 0) {
          colValues.forEach(colVal => {
            const cellData = subGroupedData[subKey].filter(d => d[cols[0]] === colVal);
            
            let cellValue = 0;
            if (vals.length > 0) {
              // If we have value fields, aggregate them
              const valField = vals[0];
              cellValue = cellData.reduce((sum, d) => {
                const val = parseFloat(d[valField]) || 0;
                return sum + val;
              }, 0);
              html += '<td>' + cellValue.toFixed(2) + '</td>';
            } else {
              // Otherwise, just count the records
              cellValue = cellData.length;
              html += '<td>' + cellValue + '</td>';
            }
            subTotal += cellValue;
          });
        } else {
          if (vals.length > 0) {
            // If we have value fields, aggregate them
            const valField = vals[0];
            subTotal = subGroupedData[subKey].reduce((sum, d) => {
              const val = parseFloat(d[valField]) || 0;
              return sum + val;
            }, 0);
            html += '<td>' + subTotal.toFixed(2) + '</td>';
          } else {
            subTotal = subGroupedData[subKey].length;
            html += '<td>' + subTotal + '</td>';
          }
        }
        
        html += '<td><strong>' + subTotal + '</strong></td>';
        html += '</tr>';
      });
      
      // Add subtotal row for this group
      html += '<tr class="table-info">';
      html += '<td><strong>' + groupKey + ' Subtotal</strong></td>';
      
      let groupTotal = 0;
      
      if (colValues.length > 0) {
        colValues.forEach(colVal => {
          const cellData = groupData.filter(d => d[cols[0]] === colVal);
          
          let cellValue = 0;
          if (vals.length > 0) {
            // If we have value fields, aggregate them
            const valField = vals[0];
            cellValue = cellData.reduce((sum, d) => {
              const val = parseFloat(d[valField]) || 0;
              return sum + val;
            }, 0);
            html += '<td><strong>' + cellValue.toFixed(2) + '</strong></td>';
          } else {
            // Otherwise, just count the records
            cellValue = cellData.length;
            html += '<td><strong>' + cellValue + '</strong></td>';
          }
          groupTotal += cellValue;
        });
      } else {
        if (vals.length > 0) {
          // If we have value fields, aggregate them
          const valField = vals[0];
          groupTotal = groupData.reduce((sum, d) => {
            const val = parseFloat(d[valField]) || 0;
            return sum + val;
          }, 0);
          html += '<td><strong>' + groupTotal.toFixed(2) + '</strong></td>';
        } else {
          groupTotal = groupData.length;
          html += '<td><strong>' + groupTotal + '</strong></td>';
        }
      }
      
      html += '<td><strong>' + groupTotal + '</strong></td>';
      html += '</tr>';
    } else {
      // Single row field - just add the row
      html += '<tr>';
      html += '<td><strong>' + groupKey + '</strong></td>';
      
      let rowTotal = 0;
      
      if (colValues.length > 0) {
        colValues.forEach(colVal => {
          const cellData = groupData.filter(d => d[cols[0]] === colVal);
          
          let cellValue = 0;
          if (vals.length > 0) {
            // If we have value fields, aggregate them
            const valField = vals[0];
            cellValue = cellData.reduce((sum, d) => {
              const val = parseFloat(d[valField]) || 0;
              return sum + val;
            }, 0);
            html += '<td>' + cellValue.toFixed(2) + '</td>';
          } else {
            // Otherwise, just count the records
            cellValue = cellData.length;
            html += '<td>' + cellValue + '</td>';
          }
          rowTotal += cellValue;
        });
      } else {
        if (vals.length > 0) {
          // If we have value fields, aggregate them
          const valField = vals[0];
          rowTotal = groupData.reduce((sum, d) => {
            const val = parseFloat(d[valField]) || 0;
            return sum + val;
          }, 0);
          html += '<td>' + rowTotal.toFixed(2) + '</td>';
        } else {
          rowTotal = groupData.length;
          html += '<td>' + rowTotal + '</td>';
        }
      }
      
      html += '<td><strong>' + rowTotal + '</strong></td>';
      html += '</tr>';
    }
  });
  
  return html;
}

function clearPivot(index) {
  pivotConfig[index] = { rows: [], cols: [], vals: [] };
  updateDropZone(index, 'rows', []);
  updateDropZone(index, 'cols', []);
  updateDropZone(index, 'vals', []);
  $(`#pivot-output-${index}`).hide();
}

function showTableView(index) {
  $(`#table-view-${index}`).show();
  $(`#chart-view-${index}`).hide();
  $(`#pivot-view-${index}`).hide();
  $(`#tab-${index}`).parent().find('.btn-primary').removeClass('btn-primary').addClass('btn-outline-primary');
  $(`#tab-${index}`).parent().find('.btn-outline-primary').first().removeClass('btn-outline-primary').addClass('btn-primary');
}

function showPivotView(index) {
  $(`#table-view-${index}`).hide();
  $(`#chart-view-${index}`).hide();
  $(`#pivot-view-${index}`).show();
  $(`#tab-${index}`).parent().find('.btn-primary').removeClass('btn-primary').addClass('btn-outline-primary');
  $(`#tab-${index}`).parent().find('.btn-outline-primary').eq(2).removeClass('btn-outline-primary').addClass('btn-primary');
}

function refreshData(layer) {
  const index = layers.indexOf(layer);
  if (dataTables[layer]) {
    dataTables[layer].destroy();
    delete dataTables[layer];
  }
  loadLayerData(layer, index);
}

function populateChartColumns(index, columns) {
  const xSelect = $(`#x-column-${index}`);
  const ySelect = $(`#y-column-${index}`);
  const colorSelect = $(`#color-column-${index}`);
  
  // Clear existing options
  xSelect.empty().append('<option value="">Select X Column</option>');
  ySelect.empty().append('<option value="">Select Y Column</option>');
  colorSelect.empty().append('<option value="">Select Color Column</option>');
  
  // Add columns to all selectors
  columns.forEach(col => {
    xSelect.append(`<option value="${col}">${col}</option>`);
    ySelect.append(`<option value="${col}">${col}</option>`);
    colorSelect.append(`<option value="${col}">${col}</option>`);
  });
}

function showChartView(index) {
  $(`#table-view-${index}`).hide();
  $(`#pivot-view-${index}`).hide();
  $(`#chart-view-${index}`).show();
  
  // Update button states
  $(`#tab-${index}`).parent().find('.btn-primary').removeClass('btn-primary').addClass('btn-outline-primary');
  $(`#tab-${index}`).parent().find('.btn-outline-primary').eq(1).removeClass('btn-outline-primary').addClass('btn-primary');
}

function generateChart(index) {
  const layer = layers[index];
  const data = layerData[layer];
  
  if (!data || data.length === 0) {
    alert('No data available for chart generation');
    return;
  }
  
  const chartType = $(`#chart-type-${index}`).val();
  const xColumn = $(`#x-column-${index}`).val();
  const yColumn = $(`#y-column-${index}`).val();
  const colorColumn = $(`#color-column-${index}`).val();
  
  if (!xColumn) {
    alert('Please select an X-axis column');
    return;
  }
  
  // Destroy existing chart
  if (charts[index]) {
    charts[index].destroy();
    charts[index] = null;
  }
  
  // Hide placeholder
  $(`#chart-placeholder-${index}`).hide();
  
  const ctx = document.getElementById(`chart-${index}`).getContext('2d');
  
  // Prepare data based on chart type
  let chartData, chartOptions;
  
  switch (chartType) {
    case 'bar':
      chartData = prepareBarChartData(data, xColumn, yColumn, colorColumn);
      chartOptions = getBarChartOptions();
      break;
    case 'line':
      chartData = prepareLineChartData(data, xColumn, yColumn, colorColumn);
      chartOptions = getLineChartOptions();
      break;
    case 'pie':
      chartData = preparePieChartData(data, xColumn, yColumn);
      chartOptions = getPieChartOptions();
      break;
    case 'scatter':
      chartData = prepareScatterChartData(data, xColumn, yColumn, colorColumn);
      chartOptions = getScatterChartOptions();
      break;
    case 'histogram':
      chartData = prepareHistogramData(data, xColumn);
      chartOptions = getHistogramOptions();
      break;
    case 'box':
      chartData = prepareBoxPlotData(data, xColumn, yColumn);
      chartOptions = getBoxPlotOptions();
      break;
    default:
      alert('Invalid chart type');
      return;
  }
  
  charts[index] = new Chart(ctx, {
    type: chartType === 'scatter' ? 'scatter' : chartType,
    data: chartData,
    options: chartOptions
  });
}

function prepareBarChartData(data, xColumn, yColumn, colorColumn) {
  const groupedData = {};
  
  data.forEach(item => {
    const xValue = item.properties[xColumn];
    const yValue = yColumn ? parseFloat(item.properties[yColumn]) || 0 : 1;
    const colorValue = colorColumn ? item.properties[colorColumn] : 'default';
    
    if (!groupedData[xValue]) {
      groupedData[xValue] = {};
    }
    
    if (!groupedData[xValue][colorValue]) {
      groupedData[xValue][colorValue] = 0;
    }
    
    groupedData[xValue][colorValue] += yValue;
  });
  
  const labels = Object.keys(groupedData);
  const datasets = [];
  
  if (colorColumn) {
    const colorValues = [...new Set(data.map(d => d.properties[colorColumn]))];
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
    
    colorValues.forEach((colorValue, index) => {
      datasets.push({
        label: colorValue,
        data: labels.map(label => groupedData[label][colorValue] || 0),
        backgroundColor: colors[index % colors.length],
        borderColor: colors[index % colors.length],
        borderWidth: 1
      });
    });
  } else {
    datasets.push({
      label: yColumn || 'Count',
      data: labels.map(label => Object.values(groupedData[label]).reduce((a, b) => a + b, 0)),
      backgroundColor: '#36A2EB',
      borderColor: '#36A2EB',
      borderWidth: 1
    });
  }
  
  return { labels, datasets };
}

function prepareLineChartData(data, xColumn, yColumn, colorColumn) {
  const sortedData = data.sort((a, b) => {
    const aVal = a.properties[xColumn];
    const bVal = b.properties[xColumn];
    return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
  });
  
  const labels = sortedData.map(d => d.properties[xColumn]);
  const datasets = [];
  
  if (colorColumn) {
    const colorValues = [...new Set(data.map(d => d.properties[colorColumn]))];
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
    
    colorValues.forEach((colorValue, index) => {
      const filteredData = sortedData.filter(d => d.properties[colorColumn] === colorValue);
      datasets.push({
        label: colorValue,
        data: filteredData.map(d => parseFloat(d.properties[yColumn]) || 0),
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length] + '20',
        fill: false,
        tension: 0.1
      });
    });
  } else {
    datasets.push({
      label: yColumn || 'Value',
      data: sortedData.map(d => parseFloat(d.properties[yColumn]) || 0),
      borderColor: '#36A2EB',
      backgroundColor: '#36A2EB20',
      fill: false,
      tension: 0.1
    });
  }
  
  return { labels, datasets };
}

function preparePieChartData(data, xColumn, yColumn) {
  const groupedData = {};
  
  data.forEach(item => {
    const xValue = item.properties[xColumn];
    const yValue = yColumn ? parseFloat(item.properties[yColumn]) || 0 : 1;
    
    if (!groupedData[xValue]) {
      groupedData[xValue] = 0;
    }
    groupedData[xValue] += yValue;
  });
  
  const labels = Object.keys(groupedData);
  const values = Object.values(groupedData);
  const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'];
  
  return {
    labels: labels,
    datasets: [{
      data: values,
      backgroundColor: colors.slice(0, labels.length),
      borderColor: colors.slice(0, labels.length),
      borderWidth: 1
    }]
  };
}

function prepareScatterChartData(data, xColumn, yColumn, colorColumn) {
  const datasets = [];
  
  if (colorColumn) {
    const colorValues = [...new Set(data.map(d => d.properties[colorColumn]))];
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
    
    colorValues.forEach((colorValue, index) => {
      const filteredData = data.filter(d => d.properties[colorColumn] === colorValue);
      datasets.push({
        label: colorValue,
        data: filteredData.map(d => ({
          x: parseFloat(d.properties[xColumn]) || 0,
          y: parseFloat(d.properties[yColumn]) || 0
        })),
        backgroundColor: colors[index % colors.length],
        borderColor: colors[index % colors.length]
      });
    });
  } else {
    datasets.push({
      label: 'Data Points',
      data: data.map(d => ({
        x: parseFloat(d.properties[xColumn]) || 0,
        y: parseFloat(d.properties[yColumn]) || 0
      })),
      backgroundColor: '#36A2EB',
      borderColor: '#36A2EB'
    });
  }
  
  return { datasets };
}

function prepareHistogramData(data, xColumn) {
  const values = data.map(d => parseFloat(d.properties[xColumn])).filter(v => !isNaN(v));
  const bins = 10;
  const min = Math.min(...values);
  const max = Math.max(...values);
  const binSize = (max - min) / bins;
  
  const histogram = new Array(bins).fill(0);
  const labels = [];
  
  for (let i = 0; i < bins; i++) {
    const binStart = min + i * binSize;
    const binEnd = min + (i + 1) * binSize;
    labels.push(`${binStart.toFixed(1)}-${binEnd.toFixed(1)}`);
    
    values.forEach(value => {
      if (value >= binStart && value < binEnd) {
        histogram[i]++;
      }
    });
  }
  
  return {
    labels: labels,
    datasets: [{
      label: 'Frequency',
      data: histogram,
      backgroundColor: '#36A2EB',
      borderColor: '#36A2EB',
      borderWidth: 1
    }]
  };
}

function prepareBoxPlotData(data, xColumn, yColumn) {
  const groupedData = {};
  
  data.forEach(item => {
    const xValue = item.properties[xColumn];
    const yValue = parseFloat(item.properties[yColumn]) || 0;
    
    if (!groupedData[xValue]) {
      groupedData[xValue] = [];
    }
    groupedData[xValue].push(yValue);
  });
  
  const labels = Object.keys(groupedData);
  const datasets = [{
    label: yColumn || 'Value',
    data: labels.map(label => {
      const values = groupedData[label].sort((a, b) => a - b);
      const q1 = values[Math.floor(values.length * 0.25)];
      const median = values[Math.floor(values.length * 0.5)];
      const q3 = values[Math.floor(values.length * 0.75)];
      const min = Math.min(...values);
      const max = Math.max(...values);
      
      return { min, q1, median, q3, max };
    }),
    backgroundColor: '#36A2EB',
    borderColor: '#36A2EB'
  }];
  
  return { labels, datasets };
}

function getBarChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true
      }
    },
    plugins: {
      legend: {
        position: 'top'
      }
    }
  };
}

function getLineChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true
      }
    },
    plugins: {
      legend: {
        position: 'top'
      }
    }
  };
}

function getPieChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right'
      }
    }
  };
}

function getScatterChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        type: 'linear',
        position: 'bottom'
      }
    },
    plugins: {
      legend: {
        position: 'top'
      }
    }
  };
}

function getHistogramOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true
      }
    },
    plugins: {
      legend: {
        display: false
      }
    }
  };
}

function getBoxPlotOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true
      }
    },
    plugins: {
      legend: {
        position: 'top'
      }
    }
  };
}

function exportChart(index) {
  if (!charts[index]) {
    alert('No chart to export');
    return;
  }
  
  const canvas = document.getElementById(`chart-${index}`);
  const url = canvas.toDataURL('image/png');
  
  const link = document.createElement('a');
  link.download = `chart-${layers[index]}-${new Date().toISOString().split('T')[0]}.png`;
  link.href = url;
  link.click();
}

function showChartData(index) {
  const layer = layers[index];
  const data = layerData[layer];
  
  if (!data || data.length === 0) {
    alert('No data available');
    return;
  }
  
  // Create a simple data table
  const firstFeature = data[0];
  const columns = Object.keys(firstFeature.properties);
  
  let tableHtml = '<div class="table-responsive"><table class="table table-striped table-sm table-bordered"><thead><tr>';
  columns.forEach(col => {
    tableHtml += `<th>${col}</th>`;
  });
  tableHtml += '</tr></thead><tbody>';
  
  data.slice(0, 100).forEach(feature => { // Limit to first 100 rows
    tableHtml += '<tr>';
    columns.forEach(col => {
      const value = feature.properties[col];
      tableHtml += `<td>${value !== null && value !== undefined ? htmlEscape(value.toString()) : ''}</td>`;
    });
    tableHtml += '</tr>';
  });
  
  tableHtml += '</tbody></table></div>';
  
  if (data.length > 100) {
    tableHtml += `<p class="text-muted">Showing first 100 of ${data.length} records</p>`;
  }
  
  // Show in modal or alert
  const modal = $(`
    <div class="modal fade" tabindex="-1">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Chart Data - ${layer}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" style="overflow-x: auto;">
            ${tableHtml}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  `);
  
  $('body').append(modal);
  modal.modal('show');
  
  modal.on('hidden.bs.modal', function() {
    modal.remove();
  });
}

function htmlEscape(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
