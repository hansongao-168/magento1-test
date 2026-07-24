/**
 * XFE ShippingRule - Condition Builder
 *
 * Prototype.js-based dynamic condition builder with nested group support
 * for Magento 1.x admin interface.
 *
 * Supports:
 * - Multiple condition groups (OR logic between groups)
 * - Nested sub-groups at any depth (AND/OR within groups)
 * - Multiple condition types (country_code, city, zip_code, weight, volume, etc.)
 * - Volume auto-calculation from L x W x H
 */

// Global data
var conditionGroups = {};     // Flat lookup: groupId -> {id, aggregator, items[]}
var rootGroupOrder = [];      // Ordered array of top-level group IDs
var nextGroupId = 0;
var nextConditionId = 0;

// Numeric attributes list (for operator filtering)
var numericAttributes = [
    'user_id', 'package_count', 'package_weight',
    'length', 'width', 'height', 'volume'
];

// Attribute options (populated from PHP)
var attributeOptions = [];
var operatorOptions = {};
var numericOperators = {};
var stringOperators = {};

/**
 * Initialize the condition builder from hidden field data
 */
function initConditionBuilder() {
    // Load options from hidden fields
    var attrEl = $('attr_options_json');
    var opEl = $('op_options_json');
    var numOpEl = $('numeric_op_json');
    var strOpEl = $('string_op_json');

    if (attrEl) {
        try {
            attributeOptions = JSON.parse(attrEl.value);
        } catch(e) { attributeOptions = []; }
    }
    if (opEl) {
        try {
            operatorOptions = JSON.parse(opEl.value);
        } catch(e) { operatorOptions = {}; }
    }
    if (numOpEl) {
        try {
            numericOperators = JSON.parse(numOpEl.value);
        } catch(e) { numericOperators = {}; }
    }
    if (strOpEl) {
        try {
            stringOperators = JSON.parse(strOpEl.value);
        } catch(e) { stringOperators = {}; }
    }

    // Load existing conditions
    var hiddenEl = $('groups_data_hidden');
    if (hiddenEl && hiddenEl.value) {
        try {
            var existingData = JSON.parse(hiddenEl.value);
            if (existingData && existingData.length > 0) {
                for (var i = 0; i < existingData.length; i++) {
                    addConditionGroup(existingData[i]);
                }
                updateHiddenField();
                return;
            }
        } catch(e) {}
    }

    // Default: add one empty group
    addConditionGroup();
}

/**
 * Add a new root-level condition group
 *
 * @param {Object} groupData Optional existing group data
 * @param {Element} containerEl Optional container element (for root, uses condition-groups-container)
 * @param {number} parentGroupId Optional parent group ID (for nested sub-groups)
 * @returns {number} New group ID
 */
function addConditionGroup(groupData, containerEl, parentGroupId) {
    var groupId = nextGroupId++;
    var aggregator = (groupData && groupData.aggregator) ? groupData.aggregator : 'all';
    var conditionsList = (groupData && groupData.conditions) ? groupData.conditions : [];

    conditionGroups[groupId] = {
        id: groupId,
        aggregator: aggregator,
        items: [],
        parentGroupId: parentGroupId !== undefined ? parentGroupId : null
    };

    if (!containerEl) {
        containerEl = $('condition-groups-container');
        rootGroupOrder.push(groupId);
    }

    if (!containerEl) return groupId;

    var groupHtml = buildGroupHtml(groupId, aggregator, parentGroupId !== undefined);
    containerEl.insert({bottom: groupHtml});

    // Add existing items (conditions and sub-groups)
    if (conditionsList.length > 0) {
        for (var c = 0; c < conditionsList.length; c++) {
            addGroupItem(groupId, conditionsList[c]);
        }
    } else {
        // Default: add one empty condition
        addConditionToGroup(groupId, null);
    }

    updateHiddenField();
    return groupId;
}

/**
 * Build HTML for a condition group container
 *
 * @param {number} groupId
 * @param {string} aggregator
 * @param {boolean} isSubGroup
 * @returns {string}
 */
function buildGroupHtml(groupId, aggregator, isSubGroup) {
    var label = isSubGroup ? 'Nested Group' : ('Condition Group #' + (rootGroupOrder.length));
    var marginLeft = isSubGroup ? '20px' : '0';

    var html = '<div class="condition-group" id="condition-group-' + groupId + '" style="border:1px solid #ccc;padding:12px;margin-bottom:12px;margin-left:' + marginLeft + ';background:#fafafa;">';

    // Group header
    html += '<div class="condition-group-header" style="margin-bottom:8px;font-weight:bold;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">';
    html += '<span>' + label + '</span>';
    html += '<div>';

    // Aggregator selector
    html += '<label>Logic: </label>';
    html += '<select id="aggregator-' + groupId + '" onchange="changeAggregator(' + groupId + ', this.value)" style="margin-right:8px;">';
    html += '<option value="all"' + (aggregator === 'all' ? ' selected' : '') + '>ALL (AND)</option>';
    html += '<option value="any"' + (aggregator === 'any' ? ' selected' : '') + '>ANY (OR)</option>';
    html += '</select>';

    // Remove group button
    html += '<button type="button" class="scalable delete" onclick="removeConditionGroup(' + groupId + ')" style="color:red;">X Remove Group</button>';
    html += '</div>';
    html += '</div>';

    // Items container
    html += '<div class="condition-items" id="conditions-' + groupId + '">';
    html += '</div>';

    // Action buttons
    html += '<div class="condition-group-actions" style="margin-top:8px;">';
    html += '<button type="button" class="scalable add" onclick="addConditionToGroup(' + groupId + ', null)" style="margin-right:6px;">+ Add Condition</button>';
    html += '<button type="button" class="scalable add" onclick="addSubGroup(' + groupId + ')">+ Add Sub-Group</button>';
    html += '</div>';

    html += '</div>';
    return html;
}

/**
 * Add an item (condition or sub-group) to a group
 *
 * @param {number} groupId
 * @param {Object} itemData
 */
function addGroupItem(groupId, itemData) {
    if (itemData && itemData.type === 'group') {
        // It's a nested sub-group
        var itemsContainer = $('conditions-' + groupId);
        var subGroupId = addConditionGroup(itemData, itemsContainer, groupId);
        conditionGroups[groupId].items.push({
            type: 'group',
            groupId: subGroupId
        });
    } else {
        // It's a simple condition
        addConditionToGroup(groupId, itemData);
    }
}

/**
 * Add a sub-group to an existing group
 *
 * @param {number} parentGroupId
 */
function addSubGroup(parentGroupId) {
    var itemsContainer = $('conditions-' + parentGroupId);
    if (!itemsContainer) return;

    var subGroupId = addConditionGroup(null, itemsContainer, parentGroupId);
    conditionGroups[parentGroupId].items.push({
        type: 'group',
        groupId: subGroupId
    });

    updateHiddenField();
}

/**
 * Add a condition row to a group
 *
 * @param {number} groupId
 * @param {Object|null} conditionData
 */
function addConditionToGroup(groupId, conditionData) {
    var container = $('conditions-' + groupId);
    if (!container) return;

    var conditionId = nextConditionId++;
    var attr = (conditionData && conditionData.attribute) ? conditionData.attribute : '';
    var op = (conditionData && conditionData.operator) ? conditionData.operator : '==';
    var val = (conditionData && conditionData.value) ? conditionData.value : '';

    var html = buildConditionHtml(groupId, conditionId, attr, op, val);
    container.insert({bottom: html});

    // Track in data structure
    conditionGroups[groupId].items.push({
        type: 'condition',
        conditionId: conditionId,
        attribute: attr,
        operator: op,
        value: val
    });

    updateHiddenField();
}

/**
 * Build HTML for a single condition row
 *
 * @param {number} groupId
 * @param {number} conditionId
 * @param {string} attr
 * @param {string} op
 * @param {string} val
 * @returns {string}
 */
function buildConditionHtml(groupId, conditionId, attr, op, val) {
    var isCustom = false;

    // Check if attribute is custom (not in preset list keys)
    if (attr && attributeOptions[attr] === undefined) {
        isCustom = true;
    }

    var html = '<div class="condition-row" id="condition-row-' + groupId + '-' + conditionId + '" style="margin-bottom:6px;padding:6px;background:#fff;border:1px solid #e0e0e0;">';
    html += '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';

    // Attribute dropdown (or custom input)
    html += '<select id="condition-attr-' + groupId + '-' + conditionId + '" onchange="onAttributeChange(' + groupId + ', ' + conditionId + ', this.value)" style="width:160px;">';

    // Preset options from PHP (skip 'custom' key as it has special handling)
    for (var key in attributeOptions) {
        if (attributeOptions.hasOwnProperty(key) && key !== 'custom') {
            html += '<option value="' + key + '"' + (attr === key ? ' selected' : '') + '>' + attributeOptions[key] + '</option>';
        }
    }

    // Custom option
    html += '<option value="__custom__"' + (isCustom ? ' selected' : '') + '>' + (attributeOptions['custom'] || 'Custom...') + '</option>';
    html += '</select>';

    // Custom input (hidden unless custom selected)
    var customDisplay = (isCustom || attr === '__custom__') ? '' : 'display:none;';
    html += '<input type="text" id="condition-attr-custom-' + groupId + '-' + conditionId + '" value="' + (isCustom ? escapeHtml(attr) : '') + '" placeholder="Custom attribute..." style="width:140px;' + customDisplay + '" onchange="onCustomAttributeChange(' + groupId + ', ' + conditionId + ', this.value)" />';

    // Operator dropdown
    html += '<select id="condition-op-' + groupId + '-' + conditionId + '" onchange="onOperatorChange(' + groupId + ', ' + conditionId + ', this.value)" style="width:140px;">';

    // Determine which operators to show
    var isNumeric = numericAttributes.indexOf(attr) >= 0;
    var operators = isNumeric ? numericOperators : stringOperators;
    for (var opKey in operators) {
        if (operators.hasOwnProperty(opKey)) {
            html += '<option value="' + opKey + '"' + (op === opKey ? ' selected' : '') + '>' + operators[opKey] + '</option>';
        }
    }
    html += '</select>';

    // Value input
    html += '<input type="text" id="condition-val-' + groupId + '-' + conditionId + '" value="' + escapeHtml(val) + '" placeholder="Value" style="width:120px;" onchange="onValueChange(' + groupId + ', ' + conditionId + ', this.value)" />';

    // Remove button
    html += '<button type="button" class="scalable delete" onclick="removeCondition(' + groupId + ', ' + conditionId + ')" style="color:red;padding:0 6px;" title="Remove condition">X</button>';

    html += '</div>';
    html += '</div>';

    return html;
}

/**
 * Escape HTML entities for safe use in attribute values
 */
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/**
 * Remove a condition group (or sub-group)
 */
function removeConditionGroup(groupId) {
    var el = $('condition-group-' + groupId);
    if (el) {
        el.remove();
    }

    // Remove from parent's items list
    var group = conditionGroups[groupId];
    if (group && group.parentGroupId !== null) {
        var parentGroup = conditionGroups[group.parentGroupId];
        if (parentGroup) {
            parentGroup.items = parentGroup.items.filter(function(item) {
                return !(item.type === 'group' && item.groupId === groupId);
            });
        }
    }

    // Remove from root group order
    var idx = rootGroupOrder.indexOf(groupId);
    if (idx >= 0) {
        rootGroupOrder.splice(idx, 1);
    }

    // Also clean up sub-groups recursively
    cleanupSubGroups(groupId);

    delete conditionGroups[groupId];
    updateHiddenField();
}

/**
 * Recursively clean up sub-group references when removing a parent group
 */
function cleanupSubGroups(groupId) {
    var group = conditionGroups[groupId];
    if (!group) return;

    for (var i = 0; i < group.items.length; i++) {
        var item = group.items[i];
        if (item.type === 'group') {
            cleanupSubGroups(item.groupId);
            delete conditionGroups[item.groupId];
        }
    }
}

/**
 * Remove a condition from a group
 */
function removeCondition(groupId, conditionId) {
    var el = $('condition-row-' + groupId + '-' + conditionId);
    if (el) {
        el.remove();
    }

    if (conditionGroups[groupId]) {
        conditionGroups[groupId].items = conditionGroups[groupId].items.filter(function(item) {
            return !(item.type === 'condition' && item.conditionId === conditionId);
        });
    }

    updateHiddenField();
}

/**
 * Handle attribute dropdown change
 */
function onAttributeChange(groupId, conditionId, value) {
    var customInput = $('condition-attr-custom-' + groupId + '-' + conditionId);
    var opSelect = $('condition-op-' + groupId + '-' + conditionId);

    if (value === '__custom__') {
        if (customInput) customInput.show();
        var customVal = customInput ? customInput.value : '';
        updateConditionData(groupId, conditionId, 'attribute', customVal);
    } else {
        if (customInput) customInput.hide();
        updateConditionData(groupId, conditionId, 'attribute', value);

        // Update operator options based on attribute type
        if (opSelect) {
            var isNumeric = numericAttributes.indexOf(value) >= 0;
            updateOperatorOptions(opSelect, isNumeric);
        }
    }

    updateHiddenField();
}

/**
 * Handle custom attribute input change
 */
function onCustomAttributeChange(groupId, conditionId, value) {
    updateConditionData(groupId, conditionId, 'attribute', value);
    updateHiddenField();
}

/**
 * Handle operator change
 */
function onOperatorChange(groupId, conditionId, value) {
    updateConditionData(groupId, conditionId, 'operator', value);
    updateHiddenField();
}

/**
 * Handle value change
 */
function onValueChange(groupId, conditionId, value) {
    updateConditionData(groupId, conditionId, 'value', value);
    updateHiddenField();
}

/**
 * Handle aggregator change
 */
function changeAggregator(groupId, value) {
    if (conditionGroups[groupId]) {
        conditionGroups[groupId].aggregator = value;
    }
    updateHiddenField();
}

/**
 * Update operator dropdown options
 */
function updateOperatorOptions(selectEl, isNumeric) {
    if (!selectEl) return;

    var selectedValue = selectEl.value;
    selectEl.innerHTML = '';

    var operators = isNumeric ? numericOperators : stringOperators;
    for (var key in operators) {
        if (operators.hasOwnProperty(key)) {
            var option = document.createElement('option');
            option.value = key;
            option.text = operators[key];
            if (key === selectedValue) {
                option.selected = true;
            }
            selectEl.appendChild(option);
        }
    }
}

/**
 * Update a condition's data in the groups array
 */
function updateConditionData(groupId, conditionId, key, value) {
    if (!conditionGroups[groupId]) return;

    var items = conditionGroups[groupId].items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type === 'condition' && items[i].conditionId === conditionId) {
            items[i][key] = value;
            break;
        }
    }
}

/**
 * Update the hidden field with JSON representation of all condition groups
 */
function updateHiddenField() {
    var result = [];

    for (var r = 0; r < rootGroupOrder.length; r++) {
        var gId = rootGroupOrder[r];
        if (!conditionGroups[gId]) continue;

        var groupJson = buildGroupJson(gId, false);
        if (groupJson) {
            result.push(groupJson);
        }
    }

    var hiddenEl = $('groups_data_hidden');
    if (hiddenEl) {
        hiddenEl.value = JSON.stringify(result);
    }
}

/**
 * Recursively build JSON for a group
 *
 * @param {number} groupId
 * @param {boolean} isSubGroup - Whether this group is a sub-group (adds 'type' field)
 * @returns {Object|null}
 */
function buildGroupJson(groupId, isSubGroup) {
    var group = conditionGroups[groupId];
    if (!group) return null;

    // Read live aggregator from DOM
    var aggregatorEl = $('aggregator-' + groupId);
    var aggregator = aggregatorEl ? aggregatorEl.value : group.aggregator;

    var items = [];
    for (var i = 0; i < group.items.length; i++) {
        var itemData = group.items[i];
        if (itemData.type === 'condition') {
            // Read live values from DOM
            var conditionItem = readConditionFromDom(groupId, itemData.conditionId);
            if (conditionItem) {
                items.push(conditionItem);
            }
        } else if (itemData.type === 'group') {
            var subGroupJson = buildGroupJson(itemData.groupId, true);
            if (subGroupJson) {
                items.push(subGroupJson);
            }
        }
    }

    var json = {
        aggregator: aggregator,
        conditions: items
    };

    if (isSubGroup) {
        json.type = 'group';
    }

    return json;
}

/**
 * Read a condition's live values from DOM
 *
 * @param {number} groupId
 * @param {number} conditionId
 * @returns {Object|null}
 */
function readConditionFromDom(groupId, conditionId) {
    var attrEl = $('condition-attr-' + groupId + '-' + conditionId);
    var customEl = $('condition-attr-custom-' + groupId + '-' + conditionId);
    var opEl = $('condition-op-' + groupId + '-' + conditionId);
    var valEl = $('condition-val-' + groupId + '-' + conditionId);

    if (!attrEl || !opEl || !valEl) return null;

    var attribute = '';
    if (attrEl.value === '__custom__' && customEl) {
        attribute = customEl.value;
    } else {
        attribute = attrEl.value;
    }

    return {
        attribute: attribute,
        operator: opEl.value,
        value: valEl.value
    };
}

/**
 * Attach event handlers to form submit
 */
document.observe('dom:loaded', function() {
    // Delay init to make sure all fields are rendered
    setTimeout(initConditionBuilder, 100);

    // Update hidden field before form submit
    var editForm = $('edit_form');
    if (editForm) {
        editForm.observe('submit', function() {
            updateHiddenField();
        });
    }
});
