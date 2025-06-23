// Move all JavaScript into a DOMContentLoaded event handler
document.addEventListener('DOMContentLoaded', function() {
    // Initialize - expand first layer by default
    const firstLayer = document.querySelector('.table-list-item');
    if (firstLayer) {
        const header = firstLayer.querySelector('.table-header');
        const columns = firstLayer.querySelector('.column-list');
        if (header && columns) {
            header.classList.add('expanded');
            columns.classList.add('show');
            firstLayer.classList.add('expanded');
        }
    }

    // Add SQL form handling if the form exists
    const sqlForm = document.getElementById('sqlForm');
    if (sqlForm) {
        sqlForm.addEventListener('submit', function(e) {
            const sqlInput = document.getElementById('sql');
            if (!sqlInput.value.trim()) {
                e.preventDefault();
                alert('Please enter a SQL query');
                return;
            }
            // Keep the form data when submitting
            const formData = new FormData(this);
            const query = formData.get('sql');
            if (query && !query.toLowerCase().trim().startsWith('select')) {
                e.preventDefault();
                alert('Only SELECT queries are allowed');
                return;
            }
        });
    }
});

// Keep these as global functions since they're called from HTML
function toggleTable(element, tableName) {
    element.classList.toggle('expanded');
    const columnList = document.getElementById('columns-' + tableName);
    columnList.classList.toggle('show');
    // Also toggle expanded class on the parent item for background
    const parent = document.getElementById('item-' + tableName);
    if (parent) parent.classList.toggle('expanded');
}

function insertColumnName(layerName, columnName) {
    const textarea = document.querySelector('textarea[name="sql"]');
    if (textarea) {
        const currentValue = textarea.value;
        const cursorPos = textarea.selectionStart;
        textarea.value = currentValue.substring(0, cursorPos) + layerName + '.' + columnName + currentValue.substring(textarea.selectionEnd);
        textarea.focus();
        textarea.selectionStart = cursorPos + layerName.length + columnName.length + 1;
        textarea.selectionEnd = cursorPos + layerName.length + columnName.length + 1;
    }
}

function filterTables() {
    const input = document.getElementById('tableSearch');
    if (input) {
        const filter = input.value.toLowerCase();
        const items = document.querySelectorAll('.table-list-item');
        items.forEach(function(item) {
            const text = item.querySelector('.table-header').textContent.toLowerCase();
            item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
        });
    }
}

// Visual Query Builder logic
let vqbTableCount = 0;
let vqbTables = {};
let vqbColumns = {};
let vqbTablesOnCanvas = {};
let vqbJoinTypes = {};

// Tab switching
function showTab(tab) {
    document.getElementById('tab-sql').classList.remove('active');
    document.getElementById('tab-vqb').classList.remove('active');
    document.getElementById('content-sql').classList.remove('active');
    document.getElementById('content-vqb').classList.remove('active');
    if (tab === 'sql') {
        document.getElementById('tab-sql').classList.add('active');
        document.getElementById('content-sql').classList.add('active');
    } else {
        document.getElementById('tab-vqb').classList.add('active');
        document.getElementById('content-vqb').classList.add('active');
    }
}

function addVqbTable(e) {
    e.preventDefault();
    // Build dropdown if not present
    let selectId = 'vqb-table-select';
    let select = document.getElementById(selectId);
    if (!select) {
        select = document.createElement('select');
        select.id = selectId;
        select.style.marginRight = '10px';
        select.style.padding = '6px 12px';
        select.style.fontSize = '1em';
        select.style.borderRadius = '5px';
        select.style.border = '1.5px solid #e3e6ea';
        let option = document.createElement('option');
        option.value = '';
        option.textContent = '-- Select table --';
        select.appendChild(option);
        vqbTableNames.forEach(function(t) {
            if (!vqbTablesOnCanvas[t]) {
                let opt = document.createElement('option');
                opt.value = t;
                opt.textContent = t;
                select.appendChild(opt);
            }
        });
        select.onchange = function() {
            if (select.value) {
                actuallyAddVqbTable(select.value);
                select.remove();
            }
        };
        document.querySelector('#content-vqb > div').prepend(select);
        select.focus();
        return;
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
        // Ensure the column div has the data-col attribute
        if (!colDiv.hasAttribute('data-col')) {
            let colName = colDiv.querySelector('label').textContent.trim();
            colDiv.setAttribute('data-col', colName);
        }
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
    // Get all connections
    let conns = jsPlumb.getAllConnections();

    if (Object.keys(vqbTables).length === 0) {
        document.getElementById('vqb-sql').value = '-- Add tables and create joins to generate SQL.';
        return;
    }

    // Use exact table names as in the database
    let tables = Object.values(vqbTables);
    // Map lower/upper-case table names to their exact case from the database
    let tableNameMap = {};
    let tableNameMap = Object.fromEntries(vqbTableNames.map(x => [x.toLowerCase(), x]));

    // Build a map of column name -> count of tables it appears in
    let colTableCount = {};
    tables.forEach(function(t) {
        (vqbTableData[t] || []).forEach(function(col) {
            colTableCount[col.name] = (colTableCount[col.name] || 0) + 1;
        });
    });

    // Collect selected columns (add table prefix if ambiguous)
    let selectCols = [];
    tables.forEach(function(t) {
        let checks = document.querySelectorAll(`.vqb-col-check[data-table='${t}']:checked`);
        let exactTable = tableNameMap[t.toLowerCase()] || t;
        checks.forEach(function(cb) {
            let colName = cb.getAttribute('data-col');
            // Always prefix columns with table name to avoid ambiguity
            selectCols.push(exactTable + '.' + colName);
        });
    });

    // Determine the base table and joins
    let baseTable = null;
    let joins = [];
    let joinPairs = new Set();
    
    // First pass: find the base table and build join map
    let joinMap = new Map(); // Map to store join information
    conns.forEach(function(conn) {
        // Find the table divs by traversing up from the column elements
        let sourceTableDiv = conn.source.closest('.vqb-table');
        let targetTableDiv = conn.target.closest('.vqb-table');
        
        if (!sourceTableDiv || !targetTableDiv) return;

        let sourceHeader = sourceTableDiv.querySelector('.vqb-table-header');
        let targetHeader = targetTableDiv.querySelector('.vqb-table-header');
        
        if (!sourceHeader || !targetHeader) return;
        
        let sourceTable = tableNameMap[sourceHeader.textContent.toLowerCase()] || sourceHeader.textContent;
        let targetTable = tableNameMap[targetHeader.textContent.toLowerCase()] || targetHeader.textContent;
        let sourceCol = conn.source.getAttribute('data-col');
        let targetCol = conn.target.getAttribute('data-col');
        let joinId = conn.id || (conn.sourceId + '-' + conn.targetId);
        let joinType = vqbJoinTypes[joinId] || 'INNER';
        
        // Store join information
        joinMap.set(joinId, {
            sourceTable: sourceTable,
            targetTable: targetTable,
            sourceCol: sourceCol,
            targetCol: targetCol,
            joinType: joinType
        });
        
        // For LEFT JOIN, the source table should be the base table
        if (joinType === 'LEFT') {
            baseTable = sourceTable;
        }
    });
    
    // If no LEFT JOIN found, use the first table as base
    if (!baseTable) {
        baseTable = tableNameMap[tables[0].toLowerCase()] || tables[0];
    }

    // Second pass: build joins in correct order
    if (joinMap.size > 0) {
        // Find the join that starts from the base table
        let currentTable = baseTable;
        let processedJoins = new Set();
        
        while (processedJoins.size < joinMap.size) {
            let foundJoin = false;
            for (let [joinId, joinInfo] of joinMap) {
                if (processedJoins.has(joinId)) continue;
                
                if (joinInfo.sourceTable === currentTable) {
                    let joinClause = `${joinInfo.joinType} JOIN ${joinInfo.targetTable} ON ${joinInfo.sourceTable}.${joinInfo.sourceCol} = ${joinInfo.targetTable}.${joinInfo.targetCol}`;
                    joins.push(joinClause);
                    currentTable = joinInfo.targetTable;
                    processedJoins.add(joinId);
                    foundJoin = true;
                    break;
                }
            }
            if (!foundJoin) break;
        }
    }

    let sql = `SELECT ${selectCols.join(', ')} FROM ${baseTable}`;
    if (joins.length) sql += '\n  ' + joins.join('\n  ');
    document.getElementById('vqb-sql').value = sql;
}

function copyVqbSql(e) {
    if (e) e.preventDefault();
    let sql = document.getElementById('vqb-sql').value;
    document.getElementById('sql').value = sql;
    showTab('sql');
}

// Set jsPlumb container to the canvas
jsPlumb.setContainer('vqb-canvas');

// Listen for new connections to set join type
jsPlumb.bind('connection', function(info) {
    let conn = info.connection;
    let joinId = conn.id || (conn.sourceId + '-' + conn.targetId);
    vqbJoinTypes[joinId] = 'INNER'; // default
    showJoinTypeSelector(conn, joinId);
    generateVqbSql();
});

// Listen for connection detach to remove join type
jsPlumb.bind('connectionDetached', function(info) {
    let conn = info.connection;
    let joinId = conn.id || (conn.sourceId + '-' + conn.targetId);
    delete vqbJoinTypes[joinId];
    generateVqbSql();
});

function showJoinTypeSelector(conn, joinId) {
    // Create a popup for join type selection
    let popup = document.createElement('div');
    popup.style.position = 'absolute';
    popup.style.left = (conn.endpoints[0].canvas.x + conn.endpoints[1].canvas.x) / 2 + 'px';
    popup.style.top = (conn.endpoints[0].canvas.y + conn.endpoints[1].canvas.y) / 2 + 'px';
    popup.style.background = '#fff';
    popup.style.padding = '8px';
    popup.style.borderRadius = '4px';
    popup.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
    popup.style.zIndex = '1000';
    
    let select = document.createElement('select');
    select.style.padding = '4px';
    select.style.marginRight = '4px';
    ['INNER', 'LEFT'].forEach(type => {
        let option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        if (type === vqbJoinTypes[joinId]) option.selected = true;
        select.appendChild(option);
    });
    
    select.onchange = function() {
        vqbJoinTypes[joinId] = this.value;
        generateVqbSql();
        popup.remove();
    };
    
    popup.appendChild(select);
    document.getElementById('vqb-canvas').appendChild(popup);
    
    // Remove popup when clicking outside
    setTimeout(() => {
        document.addEventListener('click', function removePopup(e) {
            if (!popup.contains(e.target)) {
                popup.remove();
                document.removeEventListener('click', removePopup);
            }
        });
    }, 0);
}