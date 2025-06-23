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

// Visual Query Builder logic
let vqbTableCount = 0;
let vqbTables = {};
let vqbColumns = {};
let vqbTablesOnCanvas = {};
let vqbJoinTypes = {};

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
    console.log('Connections:', conns);

    if (Object.keys(vqbTables).length === 0) {
        document.getElementById('vqb-sql').value = '-- Add tables and create joins to generate SQL.';
        return;
    }

    // Debug log for tables and join types
    console.log('Tables:', vqbTables);
    console.log('Join Types:', vqbJoinTypes);

    // Use exact table names as in the database
    let tables = Object.values(vqbTables);
    // Map lower/upper-case table names to their exact case from the database
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
        
        if (!sourceTableDiv || !targetTableDiv) {
            console.log('Could not find table divs for connection');
            return;
        }

        let sourceHeader = sourceTableDiv.querySelector('.vqb-table-header');
        let targetHeader = targetTableDiv.querySelector('.vqb-table-header');
        
        console.log('Connection headers:', { 
            source: sourceHeader?.textContent, 
            target: targetHeader?.textContent,
            sourceTableDiv: sourceTableDiv.id,
            targetTableDiv: targetTableDiv.id
        });
        
        if (!sourceHeader || !targetHeader) {
            console.log('Missing header for connection');
            return;
        }
        
        let sourceTable = tableNameMap[sourceHeader.textContent.toLowerCase()] || sourceHeader.textContent;
        let targetTable = tableNameMap[targetHeader.textContent.toLowerCase()] || targetHeader.textContent;
        let sourceCol = conn.source.getAttribute('data-col');
        let targetCol = conn.target.getAttribute('data-col');
        let joinId = conn.id || (conn.sourceId + '-' + conn.targetId);
        let joinType = vqbJoinTypes[joinId] || 'INNER';
        
        console.log('Join info:', {
            sourceTable,
            targetTable,
            sourceCol,
            targetCol,
            joinId,
            joinType
        });
        
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
    
    console.log('Join Map:', Array.from(joinMap.entries()));
    
    // If no LEFT JOIN found, use the first table as base
    if (!baseTable) {
        baseTable = tableNameMap[tables[0].toLowerCase()] || tables[0];
    }
    console.log('Base table:', baseTable);

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
                    console.log('Adding join:', joinClause);
                    joins.push(joinClause);
                    currentTable = joinInfo.targetTable;
                    processedJoins.add(joinId);
                    foundJoin = true;
                    break;
                }
            }
            if (!foundJoin) {
                console.log('No more joins found');
                break;
            }
        }
    } else {
        console.log('No joins in joinMap');
    }

    console.log('Final joins:', joins);

    let sql = `SELECT ${selectCols.join(', ')} FROM ${baseTable}`;
    if (joins.length) sql += '\n  ' + joins.join('\n  ');
    console.log('Final SQL:', sql);
    document.getElementById('vqb-sql').value = sql;
}

function copyVqbSql(e) {
    if (e) e.preventDefault();
    let sql = document.getElementById('vqb-sql').value;
    document.getElementById('sql').value = sql;
    showTab('sql');
}

function toggleTable(element, tableName) {
    element.classList.toggle('expanded');
    const columnList = document.getElementById('columns-' + tableName);
    columnList.classList.toggle('show');
    // Also toggle expanded class on the parent item for background
    const parent = document.getElementById('item-' + tableName);
    if (parent) parent.classList.toggle('expanded');
}

function insertTableName(tableName) {
    const textarea = document.querySelector('textarea[name="sql"]');
    const currentValue = textarea.value;
    const cursorPos = textarea.selectionStart;
    
    textarea.value = currentValue.substring(0, cursorPos) + 
                    tableName + 
                    currentValue.substring(textarea.selectionEnd);
    textarea.focus();
    textarea.selectionStart = cursorPos + tableName.length;
    textarea.selectionEnd = cursorPos + tableName.length;
}

function insertColumnName(tableName, columnName) {
    const textarea = document.querySelector('textarea[name="sql"]');
    const currentValue = textarea.value;
    const cursorPos = textarea.selectionStart;
    
    textarea.value = currentValue.substring(0, cursorPos) + 
                    tableName + '.' + columnName + 
                    currentValue.substring(textarea.selectionEnd);
    textarea.focus();
    textarea.selectionStart = cursorPos + tableName.length + columnName.length + 1;
    textarea.selectionEnd = cursorPos + tableName.length + columnName.length + 1;
}

function filterTables() {
    var input = document.getElementById('tableSearch');
    var filter = input.value.toLowerCase();
    var items = document.querySelectorAll('.table-list-item');
    items.forEach(function(item) {
        var text = item.querySelector('.table-header').textContent.toLowerCase();
        item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
}

// Set jsPlumb container to the canvas
jsPlumb.setContainer('vqb-canvas');

// Listen for new connections to set join type
jsPlumb.bind('connection', function(info) {
    console.log('New connection created:', info); // Debug log
    let conn = info.connection;
    let joinId = conn.id || (conn.sourceId + '-' + conn.targetId);
    vqbJoinTypes[joinId] = 'INNER'; // default
    console.log('Setting join type:', { joinId, type: vqbJoinTypes[joinId] }); // Debug log
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
    // Remove any existing selector
    let old = document.getElementById('join-type-' + joinId);
    if (old) old.remove();
    // Create selector
    let selector = document.createElement('select');
    selector.id = 'join-type-' + joinId;
    selector.style.position = 'absolute';
    selector.style.zIndex = 1000;
    selector.style.fontSize = '0.95em';
    selector.style.background = '#fff';
    selector.style.border = '1px solid #ccc';
    selector.style.borderRadius = '4px';
    selector.style.padding = '2px 6px';
    ['INNER', 'LEFT', 'RIGHT', 'FULL'].forEach(function(type) {
        let opt = document.createElement('option');
        opt.value = type;
        opt.textContent = type + ' JOIN';
        selector.appendChild(opt);
    });
    selector.value = vqbJoinTypes[joinId] || 'INNER';
    selector.onchange = function() {
        console.log('Join type changed:', { joinId, newType: selector.value }); // Debug log
        vqbJoinTypes[joinId] = selector.value;
        generateVqbSql();
    };
    // Position selector at midpoint of connection
    let canvas = document.getElementById('vqb-canvas');
    let srcRect = conn.source.getBoundingClientRect();
    let tgtRect = conn.target.getBoundingClientRect();
    let cRect = canvas.getBoundingClientRect();
    let x = (srcRect.right + tgtRect.right) / 2 - cRect.left;
    let y = (srcRect.top + tgtRect.top) / 2 - cRect.top;
    selector.style.left = x + 'px';
    selector.style.top = y + 'px';
    canvas.appendChild(selector);
}
