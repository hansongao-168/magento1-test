<?php
/**
 * Adjustment Orders Tab
 *
 * Displays associated orders in a table format with add/remove capability.
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Tab_Orders extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Prepare form
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Tab_Orders
     */
    protected function _prepareForm()
    {
        $model  = Mage::registry('current_adjustment');
        $helper = Mage::helper('invoiceadjustment');

        $form = new Varien_Data_Form();
        $this->setForm($form);

        $fieldset = $form->addFieldset('orders_fieldset', array(
            'legend' => $helper->__('Associated Orders'),
        ));

        // Build existing orders table HTML
        $ordersHtml = $this->_buildOrdersTable($model);

        $fieldset->addField('orders_table', 'note', array(
            'label' => $helper->__('Linked Orders (%s)', $model->getOrders()->count()),
            'text'  => $ordersHtml,
        ));

        // Add order section
        $fieldset->addField('add_order_note', 'note', array(
            'text' => '<hr/><h4>' . $helper->__('Add Order') . '</h4>'
                . $helper->__('Enter an order number to load its original amounts and add to this adjustment.'),
        ));

        $fieldset->addField('new_order_number', 'text', array(
            'label' => $helper->__('Order Number'),
            'name'  => 'new_order_number',
            'after_element_html' =>
                '<button type="button" class="scalable add" onclick="invoiceAdjustmentLoadOrder()">'
                . '<span>' . $helper->__('Load Order') . '</span></button>'
                . '<span id="load_order_status" style="margin-left:10px;color:#666;"></span>',
        ));

        // Hidden fields container for orders_data
        $fieldset->addField('orders_data_container', 'note', array(
            'text' => '<div id="orders_data_container">' . $this->_buildHiddenFields($model) . '</div>'
                . '<div id="new_orders_container"></div>',
        ));

        // Add JS for AJAX load and dynamic row management
        $fieldset->addField('orders_js', 'note', array(
            'text' => $this->_getJavaScript($model),
        ));

        return parent::_prepareForm();
    }

    /**
     * Build existing orders table HTML
     *
     * @param XFE_InvoiceAdjustment_Model_Adjustment $model
     * @return string
     */
    protected function _buildOrdersTable($model)
    {
        $helper = Mage::helper('invoiceadjustment');
        $orders = $model->getOrders();

        if ($orders->count() == 0) {
            return '<p style="color:#666;">' . $helper->__('No orders linked to this adjustment.') . '</p>';
        }

        $html = '<table class="data-table" style="width:100%;" id="linked_orders_table">'
            . '<thead><tr>'
            . '<th>' . $helper->__('Order #') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Original HT') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Original TVA') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Original TTC') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Adjusted HT') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Adjusted TVA') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Adjusted TTC') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Customer Pay') . '</th>'
            . '<th style="text-align:right;">' . $helper->__('Customer Refund') . '</th>'
            . '<th>' . $helper->__('Action') . '</th>'
            . '</tr></thead><tbody>';

        $rowIdx = 0;
        foreach ($orders as $order) {
            $payClass = $order->getCustomerPay() > 0 ? 'color:#D9534F;' : '';
            $refundClass = $order->getCustomerRefund() > 0 ? 'color:#5CB85C;' : '';

            $html .= '<tr id="order_row_' . $rowIdx . '">'
                . '<td><strong>' . $order->getOrderNumber() . '</strong></td>'
                . '<td style="text-align:right;">' . number_format((float)$order->getOriginalHt(), 2) . '</td>'
                . '<td style="text-align:right;">' . number_format((float)$order->getOriginalTva(), 2) . '</td>'
                . '<td style="text-align:right;">' . number_format((float)$order->getOriginalTtc(), 2) . '</td>'
                . '<td style="text-align:right;">' . number_format((float)$order->getAdjustedHt(), 2) . '</td>'
                . '<td style="text-align:right;">' . number_format((float)$order->getAdjustedTva(), 2) . '</td>'
                . '<td style="text-align:right;font-weight:bold;">' . number_format((float)$order->getAdjustedTtc(), 2) . '</td>'
                . '<td style="text-align:right;' . $payClass . '">' . number_format((float)$order->getCustomerPay(), 2) . '</td>'
                . '<td style="text-align:right;' . $refundClass . '">' . number_format((float)$order->getCustomerRefund(), 2) . '</td>'
                . '<td><button type="button" class="scalable delete" onclick="invoiceAdjustmentRemoveOrder(' . $rowIdx . ')">'
                . '<span>' . $helper->__('Remove') . '</span></button></td>'
                . '</tr>';
            $rowIdx++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Build hidden form fields for existing orders
     *
     * @param XFE_InvoiceAdjustment_Model_Adjustment $model
     * @return string
     */
    protected function _buildHiddenFields($model)
    {
        $orders = $model->getOrders();
        $html = '';
        $idx = 0;

        foreach ($orders as $order) {
            $prefix = 'orders_data[' . $idx . ']';
            $html .= '<input type="hidden" name="' . $prefix . '[order_id]" value="' . $order->getOrderId() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[order_number]" value="' . $order->getOrderNumber() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[original_ht]" value="' . $order->getOriginalHt() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[original_tva]" value="' . $order->getOriginalTva() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[original_ttc]" value="' . $order->getOriginalTtc() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[adjusted_ht]" value="' . $order->getAdjustedHt() . '" class="order-hidden" data-idx="' . $idx . '" />'
                . '<input type="hidden" name="' . $prefix . '[adjusted_tva]" value="' . $order->getAdjustedTva() . '" class="order-hidden" data-idx="' . $idx . '" />';
            $idx++;
        }

        return $html;
    }

    /**
     * Get JavaScript for AJAX order loading and row management
     *
     * @param XFE_InvoiceAdjustment_Model_Adjustment $model
     * @return string
     */
    protected function _getJavaScript($model)
    {
        $helper = Mage::helper('invoiceadjustment');
        $loadUrl = $this->getUrl('*/*/loadOrder');
        $adjustmentId = $model ? $model->getId() : 0;

        return <<<JS
<script type="text/javascript">
//<![CDATA[
var orderIndex = {$this->_getNextOrderIndex($model)};

function invoiceAdjustmentLoadOrder() {
    var orderNumber = $('new_order_number').value;
    var statusEl = $('load_order_status');

    if (!orderNumber) {
        statusEl.update('{$helper->__('Please enter an order number')}');
        statusEl.setStyle({color: '#D9534F'});
        return;
    }

    statusEl.update('{$helper->__('Loading...')}');
    statusEl.setStyle({color: '#666'});

    new Ajax.Request('{$loadUrl}', {
        method: 'post',
        parameters: {
            order_number: orderNumber,
            adjustment_id: {$adjustmentId}
        },
        onSuccess: function(transport) {
            var response = transport.responseText.evalJSON();
            if (response.error) {
                statusEl.update(response.error);
                statusEl.setStyle({color: '#D9534F'});
            } else {
                statusEl.update('');
                invoiceAdjustmentAddOrderRow(response);
                $('new_order_number').value = '';
            }
        },
        onFailure: function() {
            statusEl.update('{$helper->__('Request failed')}');
            statusEl.setStyle({color: '#D9534F'});
        }
    });
}

function invoiceAdjustmentAddOrderRow(data) {
    var idx = orderIndex;
    var prefix = 'orders_data[' + idx + ']';

    // Add hidden fields
    var container = $('new_orders_container');
    var fields = '';
    fields += '<input type="hidden" name="' + prefix + '[order_id]" value="' + data.order_id + '" class="order-hidden" data-idx="' + idx + '" />';
    fields += '<input type="hidden" name="' + prefix + '[order_number]" value="' + data.order_number + '" class="order-hidden" data-idx="' + idx + '" />';
    fields += '<input type="hidden" name="' + prefix + '[original_ht]" value="' + data.original_ht + '" class="order-hidden" data-idx="' + idx + '" />';
    fields += '<input type="hidden" name="' + prefix + '[original_tva]" value="' + data.original_tva + '" class="order-hidden" data-idx="' + idx + '" />';
    fields += '<input type="hidden" name="' + prefix + '[original_ttc]" value="' + data.original_ttc + '" class="order-hidden" data-idx="' + idx + '" />';
    container.insert(fields);

    // Add table row
    var table = $('linked_orders_table');
    if (!table) {
        // Create table if not exists
        var tableHtml = '<table class="data-table" style="width:100%;" id="linked_orders_table">'
            + '<thead><tr>'
            + '<th>{$helper->__('Order #')}</th>'
            + '<th style="text-align:right;">{$helper->__('Original HT')}</th>'
            + '<th style="text-align:right;">{$helper->__('Original TVA')}</th>'
            + '<th style="text-align:right;">{$helper->__('Original TTC')}</th>'
            + '<th style="text-align:right;">{$helper->__('Adjusted HT')}</th>'
            + '<th style="text-align:right;">{$helper->__('Adjusted TVA')}</th>'
            + '<th style="text-align:right;">{$helper->__('Adjusted TTC')}</th>'
            + '<th style="text-align:right;">{$helper->__('Customer Pay')}</th>'
            + '<th style="text-align:right;">{$helper->__('Customer Refund')}</th>'
            + '<th>{$helper->__('Action')}</th>'
            + '</tr></thead><tbody></tbody></table>';
        $('orders_table').up().insert({after: tableHtml});
        table = $('linked_orders_table');
    }

    // Default adjusted amounts = original amounts
    var adjHt  = parseFloat(data.original_ht);
    var adjTva = parseFloat(data.original_tva);
    var adjTtc = adjHt + adjTva;
    var origTtc = parseFloat(data.original_ttc);
    var pay = adjTtc > origTtc ? (adjTtc - origTtc).toFixed(2) : '0.00';
    var refund = adjTtc < origTtc ? (origTtc - adjTtc).toFixed(2) : '0.00';

    // Build row with editable adjusted HT/TVA inputs
    var row = '<tr id="order_row_' + idx + '">'
        + '<td><strong>' + data.order_number + '</strong></td>'
        + '<td style="text-align:right;">' + parseFloat(data.original_ht).toFixed(2) + '</td>'
        + '<td style="text-align:right;">' + parseFloat(data.original_tva).toFixed(2) + '</td>'
        + '<td style="text-align:right;">' + parseFloat(data.original_ttc).toFixed(2) + '</td>'
        + '<td style="text-align:right;"><input type="text" name="' + prefix + '[adjusted_ht]" value="' + adjHt.toFixed(2) + '" class="input-text validate-number" style="width:80px;text-align:right;" onchange="invoiceAdjustmentRecalc(this, ' + idx + ')" /></td>'
        + '<td style="text-align:right;"><input type="text" name="' + prefix + '[adjusted_tva]" value="' + adjTva.toFixed(2) + '" class="input-text validate-number" style="width:80px;text-align:right;" onchange="invoiceAdjustmentRecalc(this, ' + idx + ')" /></td>'
        + '<td style="text-align:right;font-weight:bold;" id="adj_ttc_' + idx + '">' + adjTtc.toFixed(2) + '</td>'
        + '<td style="text-align:right;" id="cust_pay_' + idx + '">' + pay + '</td>'
        + '<td style="text-align:right;" id="cust_refund_' + idx + '">' + refund + '</td>'
        + '<td><button type="button" class="scalable delete" onclick="invoiceAdjustmentRemoveOrder(' + idx + ')">'
        + '<span>{$helper->__('Remove')}</span></button></td>'
        + '</tr>';

    var tbody = table.down('tbody');
    if (tbody) {
        tbody.insert(row);
    }

    orderIndex++;
}

function invoiceAdjustmentRemoveOrder(idx) {
    // Remove table row
    var row = $('order_row_' + idx);
    if (row) row.remove();

    // Remove hidden fields
    var fields = $$('input.order-hidden[data-idx="' + idx + '"]');
    fields.each(function(el) { el.remove(); });

    // Also remove visible inputs in the row (already removed with row)
}

function invoiceAdjustmentRecalc(el, idx) {
    // Find the adjusted HT/TVA inputs for this order
    var prefix = 'orders_data[' + idx + ']';
    var adjHtInput = $$('input[name="' + prefix + '[adjusted_ht]"]')[0];
    var adjTvaInput = $$('input[name="' + prefix + '[adjusted_tva]"]')[0];

    if (!adjHtInput || !adjTvaInput) return;

    var adjHt  = parseFloat(adjHtInput.value) || 0;
    var adjTva = parseFloat(adjTvaInput.value) || 0;
    var adjTtc = adjHt + adjTva;

    // Get original TTC from hidden field
    var origTtcInput = $$('input[name="' + prefix + '[original_ttc]"]')[0];
    var origTtc = origTtcInput ? parseFloat(origTtcInput.value) || 0 : 0;

    var pay = adjTtc > origTtc ? (adjTtc - origTtc) : 0;
    var refund = adjTtc < origTtc ? (origTtc - adjTtc) : 0;

    // Update display cells
    var ttcCell = $('adj_ttc_' + idx);
    var payCell = $('cust_pay_' + idx);
    var refundCell = $('cust_refund_' + idx);

    if (ttcCell) ttcCell.update(adjTtc.toFixed(2));
    if (payCell) payCell.update(pay.toFixed(2));
    if (refundCell) refundCell.update(refund.toFixed(2));
}
//]]>
</script>
JS;
    }

    /**
     * Get next order index for JS
     *
     * @param XFE_InvoiceAdjustment_Model_Adjustment $model
     * @return int
     */
    protected function _getNextOrderIndex($model)
    {
        return $model->getOrders()->count();
    }

    /**
     * Get tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('invoiceadjustment')->__('Orders');
    }

    /**
     * Get tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('invoiceadjustment')->__('Orders');
    }

    /**
     * Can show tab
     *
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Is tab hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }
}
