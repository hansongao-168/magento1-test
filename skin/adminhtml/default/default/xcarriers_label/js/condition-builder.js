/**
 * XFE LogisticsCarriersLabel - Condition Builder
 *
 * Prototype.js-based dynamic condition group/condition builder
 * for Magento 1.x admin interface.
 */

// Global data
var conditionGroups = [];
var nextGroupId = 0;
var nextConditionId = 0;

// Numeric attributes list (for operator filtering)
var numericAttributes = ['weight', 'order_total', 'package_count'];

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
 * Add a new condition group
 *
 * @param {Object} groupData Optional existing group data
 */
function addConditionGroup(groupData) {
    var groupId = nextGroupId++;
    var aggregator = (groupData && groupData.aggregator) ? groupData.aggregator : 'all';
    var conditionsList = (groupData && groupData.conditions) ? groupData.conditions : [];

    conditionGroups[groupId] = {
        id: groupId,
        aggregator: aggregator,
        conditions: []
    };

    var container = $('condition-groups-container');
    if (!container) return;

    var groupHtml = '<div class="condition-group" id="condition-group-' + groupId + '" style="border:1px solid #ccc;padding:12px;margin-bottom:12px;background:#fafafa;">';
    groupHtml += '<div style="margin-bottom:8px;font-weight:bold;display:flex;justify-content:space-between;align-items:center;">';
    groupHtml += '<span>Condition Group #' + (groupId + 1) + '</span>';
    groupHtml += '<div>';
    groupHtml += '<label>Group Logic: </label>';
    groupHtml += '<select id="aggregator-' + groupId + '" onchange="changeAggregator(' + groupId + ', this.value)">';
    groupHtml += '<option value="all"' + (aggregator === 'all' ? ' selected' : '') + '>ALL (AND)</option>';
    groupHtml += '<option value="any"' + (aggregator === 'any' ? ' selected' : '') + '>ANY (OR)</option>';
    groupHtml += '</select>';
    groupHtml += '&nbsp;&nbsp;<button type="button" class="scalable delete" onclick="removeConditionGroup(' + groupId + ')" style="color:red;">X Remove Group</button>';
    groupHtml += '</div>';
    groupHtml += '</div>';
    groupHtml += '<div class="condition-items" id="conditions-' + groupId + '">';

    // Add existing conditions or one empty condition
    if (conditionsList.length > 0) {
        for (var c = 0; c < conditionsList.length; c++) {
            groupHtml += buildConditionHtml(groupId, conditionsList[c]);
            conditionGroups[groupId].conditions.push({
                conditionId: nextConditionId - 1,
                attribute: conditionsList[c].attribute,
                operator: conditionsList[c].operator,
                value: conditionsList[c].value
            });
        }
    } else {
        groupHtml += buildConditionHtml(groupId, null);
        conditionGroups[groupId].conditions.push({
            conditionId: nextConditionId - 1,
            attribute: '',
            operator: '==',
            value: ''
        });
    }

    groupHtml += '</div>'; // end condition-items
    groupHtml += '<div style="margin-top:6px;">';
    groupHtml += '<button type="button" class="scalable add" onclick="addCondition(' + groupId + ')">+ Add Condition</button>';
    groupHtml += '</div>';
    groupHtml += '</div>'; // end condition-group

    container.insert({bottom: groupHtml});

    updateHiddenField();
}

/**
 * Build HTML for a single condition row
 *
 * @param {number} groupId
 * @param {Object|null} conditionData
 * @returns {string}
 */
function buildConditionHtml(groupId, conditionData) {
    var conditionId = nextConditionId++;
    var attr = (conditionData && conditionData.attribute) ? conditionData.attribute : '';
    var op = (conditionData && conditionData.operator) ? conditionData.operator : '==';
    var val = (conditionData && conditionData.value) ? conditionData.value : '';
    var isCustom = false;

    // Check if attribute is custom (not in preset list keys)
    if (attr && !attributeOptions[attr]) {
        isCustom = true;
    }

    var html = '<div class="condition-row" id="condition-row-' + groupId + '-' + conditionId + '" style="margin-bottom:6px;padding:6px;background:#fff;border:1px solid #e0e0e0;">';
    html += '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';

    // Attribute dropdown (or custom input)
    html += '<select id="condition-attr-' + groupId + '-' + conditionId + '" onchange="onAttributeChange(' + groupId + ', ' + conditionId + ', this.value)" style="width:160px;">';

    // Preset options from PHP
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
    html += '<input type="text" id="condition-attr-custom-' + groupId + '-' + conditionId + '" value="' + (isCustom ? attr : '') + '" placeholder="Custom attribute..." style="width:140px;' + customDisplay + '" onchange="onCustomAttributeChange(' + groupId + ', ' + conditionId + ', this.value)" />';

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
    html += '<input type="text" id="condition-val-' + groupId + '-' + conditionId + '" value="' + val.replace(/"/g, '&quot;') + '" placeholder="Value" style="width:120px;" onchange="onValueChange(' + groupId + ', ' + conditionId + ', this.value)" />';

    // Remove button
    html += '<button type="button" class="scalable delete" onclick="removeCondition(' + groupId + ', ' + conditionId + ')" style="color:red;padding:0 6px;" title="Remove condition">X</button>';

    html += '</div>';
    html += '</div>';

    return html;
}

/**
 * Add a condition to a group
 */
function addCondition(groupId) {
    var container = $('conditions-' + groupId);
    if (!container) return;

    var html = buildConditionHtml(groupId, null);
    container.insert({bottom: html});

    conditionGroups[groupId].conditions.push({
        conditionId: nextConditionId - 1,
        attribute: '',
        operator: '==',
        value: ''
    });

    updateHiddenField();
}

/**
 * Remove a condition group
 */
function removeConditionGroup(groupId) {
    var el = $('condition-group-' + groupId);
    if (el) {
        el.remove();
    }
    delete conditionGroups[groupId];
    updateHiddenField();
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
        conditionGroups[groupId].conditions = conditionGroups[groupId].conditions.filter(function(c) {
            return c.conditionId !== conditionId;
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
        customInput.show();
        var customVal = customInput.value;
        updateConditionData(groupId, conditionId, 'attribute', customVal);
    } else {
        customInput.hide();
        updateConditionData(groupId, conditionId, 'attribute', value);

        // Update operator options based on attribute type
        var isNumeric = numericAttributes.indexOf(value) >= 0;
        updateOperatorOptions(opSelect, isNumeric);
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

    var conditions = conditionGroups[groupId].conditions;
    for (var i = 0; i < conditions.length; i++) {
        if (conditions[i].conditionId === conditionId) {
            conditions[i][key] = value;
            break;
        }
    }
}

/**
 * Update the hidden field with JSON representation of all condition groups
 */
function updateHiddenField() {
    var result = [];
    var groupContainer = $('condition-groups-container');
    if (!groupContainer) return;

    for (var gId in conditionGroups) {
        if (!conditionGroups.hasOwnProperty(gId)) continue;

        var group = conditionGroups[gId];
        if (!group) continue;

        // Get live values from DOM
        var aggregatorEl = $('aggregator-' + gId);
        if (aggregatorEl) {
            group.aggregator = aggregatorEl.value;
        }

        var conditions = [];
        var groupConditions = group.conditions || [];
        for (var c = 0; c < groupConditions.length; c++) {
            var cond = groupConditions[c];
            var attrEl = $('condition-attr-' + gId + '-' + cond.conditionId);
            var customEl = $('condition-attr-custom-' + gId + '-' + cond.conditionId);
            var opEl = $('condition-op-' + gId + '-' + cond.conditionId);
            var valEl = $('condition-val-' + gId + '-' + cond.conditionId);

            var attribute = '';
            if (attrEl) {
                if (attrEl.value === '__custom__' && customEl) {
                    attribute = customEl.value;
                } else {
                    attribute = attrEl.value;
                }
            }
            var operator = opEl ? opEl.value : '==';
            var value = valEl ? valEl.value : '';

            conditions.push({
                attribute: attribute,
                operator: operator,
                value: value
            });
        }

        result.push({
            aggregator: group.aggregator,
            conditions: conditions
        });
    }

    var hiddenEl = $('groups_data_hidden');
    if (hiddenEl) {
        hiddenEl.value = JSON.stringify(result);
    }
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
