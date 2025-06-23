
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content and activate button
    document.getElementById('content-' + tabName).classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

function toggleTable(header, tableName) {
    const columnsDiv = document.getElementById('columns-' + tableName);
    const isExpanded = header.classList.contains('expanded');
    
    // Toggle the expanded class
    header.classList.toggle('expanded');
    
    // Toggle the display of columns
    columnsDiv.style.display = isExpanded ? 'none' : 'block';
    
    // Log for debugging
    console.log('Toggling table:', tableName);
    console.log('Is expanded:', !isExpanded);
    console.log('Columns div:', columnsDiv);
    console.log('Columns display:', columnsDiv.style.display);
}

function insertColumnName(tableName, columnName) {
    const sqlInput = document.getElementById('sql');
    const cursorPos = sqlInput.selectionStart;
    const textBefore = sqlInput.value.substring(0, cursorPos);
    const textAfter = sqlInput.value.substring(sqlInput.selectionEnd);
    
    sqlInput.value = textBefore + columnName + textAfter;
    sqlInput.focus();
    sqlInput.setSelectionRange(cursorPos + columnName.length, cursorPos + columnName.length);
}

function filterTables() {
    const searchBox = document.getElementById('tableSearch');
    const filter = searchBox.value.toLowerCase();
    const tableItems = document.querySelectorAll('.table-list-item');
    
    tableItems.forEach(item => {
        const tableName = item.querySelector('.table-header').textContent.toLowerCase();
        const columns = item.querySelectorAll('.column-item');
        let hasMatch = tableName.includes(filter);
        
        columns.forEach(col => {
            const colName = col.textContent.toLowerCase();
            if (colName.includes(filter)) {
                hasMatch = true;
                col.style.display = '';
            } else {
                col.style.display = 'none';
            }
        });
        
        item.style.display = hasMatch ? '' : 'none';
    });
}

// Visual Query Builder logic
let vqbTableCount = 0;
let vqbTables = {};
let vqbColumns = {};
let vqbTableData = {};
let vqbTableNames = [vqbTableName];
let vqbTablesOnCanvas = {};
let vqbJoinTypes = {};

function addVqbTable(e) {
    e.preventDefault();
    // For shapefiles, we only have one table, so add it directly
    if (!vqbTablesOnCanvas[vqbTableName]) {
        actuallyAddVqbTable(vqbTableName);
    }
}

function actuallyAddVqbTable(table) {
    if (!table || !vqbTableData[table] || vqbTablesOnCanvas[table]) return;
    vqbTableCount++;
    let id = 'vqb-table-' + vqbTableCount;
    let tableDiv = document.createElement('div');
    tableDiv.className = 'vqb-table';
    tableDiv.id = id;
    tableDiv.style.left = (40 + vqbTableCount * 30) + 'px';
    tableDiv.style.top = (40 + vqbTableCount * 30) + 'px';
    tableDiv.innerHTML = `<div class='vqb-table-header'>${table}</div><div class='vqb-columns'>${vqbTableData[table].map(col => `<div class='vqb-column' data-col='${col.name}'><label><input type='checkbox' class='vqb-col-check' data-table='${table}' data-col='${col.name}' checked> ${col.name}</label></div>`).join('')}</div>`;
    document.getElementById('vqb-canvas').appendChild(tableDiv);
    jsPlumb.draggable(tableDiv);
    
    // Make columns endpoints
    let cols = tableDiv.querySelectorAll('.vqb-column');
    cols.forEach(function(colDiv) {
        jsPlumb.addEndpoint(colDiv, { anchors: ["Right"] }, { isSource: true, isTarget: true, maxConnections: -1 });
    });
    
    vqbTables[id] = table;
    vqbColumns[id] = vqbTableData[table].map(col => col.name);
    vqbTablesOnCanvas[table] = true;
    
    // Regenerate SQL when fields are checked/unchecked
    tableDiv.querySelectorAll('.vqb-col-check').forEach(function(cb) {
        cb.addEventListener('change', function() { generateVqbSql(); });
    });
}

function generateVqbSql(e) {
    if (e) e.preventDefault();
    
    if (Object.keys(vqbTables).length === 0) {
        document.getElementById('vqb-sql').value = '-- Add tables to generate SQL.';
        return;
    }

    // Get selected columns
    let selectedColumns = [];
    document.querySelectorAll('.vqb-col-check:checked').forEach(function(cb) {
        let col = cb.getAttribute('data-col');
        // If the column is 'geometry', use OGR_GEOM_WKT
        if (col === 'geometry') {
            selectedColumns.push('OGR_GEOM_WKT');
        } else {
            selectedColumns.push(col);
        }
    });

    if (selectedColumns.length === 0) {
        document.getElementById('vqb-sql').value = '-- Select at least one column.';
        return;
    }

    // Generate SQL
    let sql = 'SELECT ' + selectedColumns.join(', ') + ' FROM ' + vqbTableName;
    
    // Add WHERE clause if there are any conditions (to be implemented)
    // Add ORDER BY if specified (to be implemented)
    
    document.getElementById('vqb-sql').value = sql;
}

function copyVqbSql(e) {
    if (e) e.preventDefault();
    const sqlText = document.getElementById('vqb-sql').value;
    if (sqlText && !sqlText.startsWith('--')) {
        document.getElementById('sql').value = sqlText;
        showTab('sql');
    }
}

// Initialize jsPlumb
jsPlumb.ready(function() {
    jsPlumb.setContainer(document.getElementById('vqb-canvas'));
});

// Initialize table states when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    const tableHeaders = document.querySelectorAll('.table-header');
    tableHeaders.forEach(header => {
        const tableName = header.getAttribute('onclick').match(/'([^']+)'/)[1];
        const columnsDiv = document.getElementById('columns-' + tableName);
        console.log('Initializing table:', tableName);
        console.log('Columns div:', columnsDiv);
        
        if (header.classList.contains('expanded')) {
            columnsDiv.style.display = 'block';
            console.log('Setting display to block for:', tableName);
        } else {
            columnsDiv.style.display = 'none';
            console.log('Setting display to none for:', tableName);
        }
    });
});
