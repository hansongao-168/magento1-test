# Checkout Error Handler Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Catch MySQL errors during checkout save steps (saveBilling/saveShipping/saveShippingMethod/savePayment/saveOrder), log them to DB + file, and return JSON error with reference ID to frontend — without modifying any Magento core files.

**Architecture:** New `XFE_CheckoutErrors` module overrides the checkout controller's dispatch method to wrap all 5 AJAX save actions in try-catch. Errors are saved to `xfe_checkout_error` DB table and written to `var/log/checkout_error.log`. A JSON response with error reference ID is returned instead of an error page. Admin Grid displays records from DB.

**Tech Stack:** Magento 1.9.3.7, MySQL InnoDB, Magento Admin Grid

---

### Task 1: Module Skeleton — config.xml

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/etc/config.xml`

- [ ] **Create config.xml**

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_CheckoutErrors>
            <version>0.1.0</version>
        </XFE_CheckoutErrors>
    </modules>
    <global>
        <models>
            <xfe_checkouterrors>
                <class>XFE_CheckoutErrors_Model</class>
                <resourceModel>xfe_checkouterrors_resource</resourceModel>
            </xfe_checkouterrors>
            <xfe_checkouterrors_resource>
                <class>XFE_CheckoutErrors_Model_Resource</class>
                <entities>
                    <checkout_error>
                        <table>xfe_checkout_error</table>
                    </checkout_error>
                </entities>
            </xfe_checkouterrors_resource>
        </models>
        <resources>
            <CheckoutErrors_setup>
                <setup>
                    <module>XFE_CheckoutErrors</module>
                    <class>Mage_Sales_Model_Mysql4_Setup</class>
                </setup>
            </CheckoutErrors_setup>
        </resources>
        <blocks>
            <xfe_checkouterrors_adminhtml>
                <class>XFE_CheckoutErrors_Block_Adminhtml</class>
            </xfe_checkouterrors_adminhtml>
        </blocks>
        <helpers>
            <xfe_checkouterrors>
                <class>XFE_CheckoutErrors_Helper</class>
            </xfe_checkouterrors>
        </helpers>
    </global>
    <admin>
        <routers>
            <adminhtml>
                <args>
                    <modules>
                        <XFE_CheckoutErrors before="Mage_Adminhtml">XFE_CheckoutErrors_Adminhtml</XFE_CheckoutErrors>
                    </modules>
                </args>
            </adminhtml>
        </routers>
    </admin>
    <adminhtml>
        <menu>
            <sales>
                <children>
                    <xfe_checkout_errors translate="title" module="xfe_checkouterrors">
                        <title>Checkout 错误记录</title>
                        <action>adminhtml/xfe_checkoutError</action>
                        <sort_order>100</sort_order>
                    </xfe_checkout_errors>
                </children>
            </sales>
        </menu>
        <acl>
            <resources>
                <admin>
                    <children>
                        <sales>
                            <children>
                                <xfe_checkout_errors>
                                    <title>Checkout 错误记录</title>
                                </xfe_checkout_errors>
                            </children>
                        </sales>
                    </children>
                </admin>
            </resources>
        </acl>
    </adminhtml>
    <frontend>
        <routers>
            <checkout>
                <args>
                    <modules>
                        <XFE_CheckoutErrors before="Mage_Checkout">XFE_CheckoutErrors</XFE_CheckoutErrors>
                    </modules>
                </args>
            </checkout>
        </routers>
    </frontend>
</config>
```

- [ ] **Create module declaration file**

Create `app/etc/modules/XFE_CheckoutErrors.xml`:

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_CheckoutErrors>
            <active>true</active>
            <codePool>community</codePool>
            <depends>
                <Mage_Checkout />
                <Mage_Adminhtml />
            </depends>
        </XFE_CheckoutErrors>
    </modules>
</config>
```

---

### Task 2: Database Installation Script

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/sql/CheckoutErrors_setup/install-0.1.0.php`

- [ ] **Create install script**

```php
<?php
/* @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_checkouterrors/checkout_error'))
    ->addColumn('error_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Error ID')
    ->addColumn('reference_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
    ], 'Reference ID (ERR-xxx)')
    ->addColumn('step', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
    ], 'Checkout step')
    ->addColumn('error_message', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Error message')
    ->addColumn('error_trace', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Exception stack trace')
    ->addColumn('request_data', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Serialized request data')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Customer ID')
    ->addColumn('customer_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, [
        'nullable'  => true,
    ], 'Customer email')
    ->addColumn('quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Quote ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Store ID')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable'  => false,
    ], 'Created at')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'reference_id'),
        'reference_id')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'step'),
        'step')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'created_at'),
        'created_at')
    ->setComment('XFE Checkout Error Log');

$installer->getConnection()->createTable($table);
$installer->endSetup();
```

---

### Task 3: Model / Resource / Collection

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/Model/CheckoutError.php`
- Create: `app/code/community/XFE/CheckoutErrors/Model/Resource/CheckoutError.php`
- Create: `app/code/community/XFE/CheckoutErrors/Model/Resource/CheckoutError/Collection.php`

- [ ] **Create Model**

```php
<?php
class XFE_CheckoutErrors_Model_CheckoutError extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkoutError');
    }

    /**
     * Set created_at before saving
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->gmtDate());
        }
        return $this;
    }
}
```

Note: `_beforeSave` auto-sets `created_at` if not already set.

- [ ] **Create Resource**

```php
<?php
class XFE_CheckoutErrors_Model_Resource_CheckoutError extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkout_error', 'error_id');
    }
}
```

- [ ] **Create Collection**

```php
<?php
class XFE_CheckoutErrors_Model_Resource_CheckoutError_Collection
    extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkoutError');
    }
}
```

---

### Task 4: Helper — File Logger

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/Helper/Data.php`

- [ ] **Create Helper**

```php
<?php
class XFE_CheckoutErrors_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Log checkout error to var/log/checkout_error.log
     *
     * @param string     $refId
     * @param string     $step
     * @param Exception  $e
     * @param Mage_Core_Controller_Request_Http $request
     */
    public function logError($refId, $step, Exception $e, $request)
    {
        $log = sprintf(
            "[%s] [%s] [%s] %s\nTrace:\n%s\nRequest: %s\n%s\n",
            date('Y-m-d H:i:s'),
            $refId,
            $step,
            $e->getMessage(),
            $e->getTraceAsString(),
            json_encode($request->getParams()),
            str_repeat('-', 80)
        );
        Mage::log($log, Zend_Log::ERR, 'checkout_error.log', true);
    }
}
```

---

### Task 5: OnepageController — Checkout Error Handler

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/controllers/OnepageController.php`

- [ ] **Create OnepageController**

```php
<?php
require_once 'Mage/Checkout/controllers/OnepageController.php';

class XFE_CheckoutErrors_OnepageController extends Mage_Checkout_OnepageController
{
    /**
     * Protected checkout AJAX actions
     */
    protected $_protectedActions = [
        'saveBilling',
        'saveShipping',
        'saveShippingMethod',
        'savePayment',
        'saveOrder',
    ];

    /**
     * Override dispatch to wrap protected actions in try-catch
     */
    public function dispatch($action)
    {
        $actionName = strtolower($this->getRequest()->getActionName());

        // Normalize: Magento passes "saveBilling" but we check lowercase
        $normalizedActions = array_map('strtolower', $this->_protectedActions);

        if (in_array($actionName, $normalizedActions)) {
            try {
                return parent::dispatch($action);
            } catch (Exception $e) {
                return $this->_handleCheckoutError($e);
            }
        }
        return parent::dispatch($action);
    }

    /**
     * Unified error handler: log + return JSON
     *
     * @param Exception $e
     * @return $this
     */
    protected function _handleCheckoutError(Exception $e)
    {
        $refId = 'ERR-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
        $request = $this->getRequest();
        $actionName = $request->getActionName();

        // Determine customer info
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerId = $customer && $customer->getId() ? $customer->getId() : 0;
        $customerEmail = $customer ? $customer->getEmail() : '';

        // Also try to get email from billing data if guest
        if (!$customerEmail) {
            $billingData = $request->getPost('billing', []);
            $customerEmail = isset($billingData['email']) ? $billingData['email'] : '';
        }

        // 1. Save to database
        try {
            Mage::getModel('xfe_checkouterrors/checkoutError')
                ->setReferenceId($refId)
                ->setStep($actionName)
                ->setErrorMessage($e->getMessage())
                ->setErrorTrace($e->getTraceAsString())
                ->setRequestData(serialize($request->getParams()))
                ->setCustomerId($customerId)
                ->setCustomerEmail($customerEmail)
                ->setQuoteId((int)Mage::getSingleton('checkout/session')->getQuoteId())
                ->setStoreId((int)Mage::app()->getStore()->getId())
                ->save();
        } catch (Exception $dbE) {
            // If DB save also fails, at least log to file
            Mage::log(
                'Failed to save checkout error to DB: ' . $dbE->getMessage(),
                Zend_Log::ERR, 'checkout_error.log', true
            );
        }

        // 2. Write file log
        try {
            Mage::helper('xfe_checkouterrors')->logError($refId, $actionName, $e, $request);
        } catch (Exception $logE) {
            // Silently ignore file logging failure
        }

        // 3. Return JSON response
        $msg = $this->__('下单失败，请联系客服，参考号：%s', $refId);
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode([
                'error'   => true,
                'message' => $msg,
                'ref_id'  => $refId,
            ])
        );

        return $this;
    }
}
```

Key defensive design notes:
- `require_once` ensures the parent class is loaded even if not autoloaded
- Action names compared case-insensitively
- Nested try-catch for DB save failure prevents cascading errors
- File logging also wrapped in try-catch

---

### Task 6: Admin Grid — Controller + Blocks

**Files:**
- Create: `app/code/community/XFE/CheckoutErrors/controllers/Adminhtml/Xfe/CheckoutErrorController.php`
- Create: `app/code/community/XFE/CheckoutErrors/Block/Adminhtml/CheckoutError.php`
- Create: `app/code/community/XFE/CheckoutErrors/Block/Adminhtml/CheckoutError/Grid.php`

- [ ] **Create Admin Controller**

```php
<?php
class XFE_CheckoutErrors_Adminhtml_Xfe_CheckoutErrorController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_title($this->__('Checkout 错误记录'));
        $this->loadLayout();
        $this->_setActiveMenu('sales/xfe_checkout_errors');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/xfe_checkout_errors');
    }
}
```

- [ ] **Create Grid Container Block**

```php
<?php
class XFE_CheckoutErrors_Block_Adminhtml_CheckoutError extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_checkoutError';
        $this->_blockGroup = 'xfe_checkouterrors_adminhtml';
        $this->_headerText = $this->__('Checkout 错误记录');
        parent::__construct();
        $this->_removeButton('add');
    }
}
```

- [ ] **Create Grid Block**

```php
<?php
class XFE_CheckoutErrors_Block_Adminhtml_CheckoutError_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('checkoutErrorGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfe_checkouterrors/checkoutError')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('reference_id', [
            'header' => $this->__('参考号'),
            'index'  => 'reference_id',
            'width'  => '180px',
        ]);

        $this->addColumn('step', [
            'header'  => $this->__('步骤'),
            'index'   => 'step',
            'type'    => 'options',
            'options' => [
                'saveBilling'        => $this->__('保存账单地址'),
                'saveShipping'       => $this->__('保存配送地址'),
                'saveShippingMethod' => $this->__('选择配送方式'),
                'savePayment'        => $this->__('选择支付方式'),
                'saveOrder'          => $this->__('提交订单'),
            ],
            'width'   => '120px',
        ]);

        $this->addColumn('error_message', [
            'header'   => $this->__('错误信息'),
            'index'    => 'error_message',
            'renderer' => 'adminhtml/widget_grid_column_renderer_longtext',
        ]);

        $this->addColumn('customer_email', [
            'header' => $this->__('客户邮箱'),
            'index'  => 'customer_email',
            'width'  => '180px',
        ]);

        $this->addColumn('created_at', [
            'header' => $this->__('时间'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ]);

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return false;
    }
}
```

---

### Task 7: Verify Module Installation

- [ ] **Run Magento compilation check**

Run: `php -l app/code/community/XFE/CheckoutErrors/etc/config.xml`
Run: `php -l app/code/community/XFE/CheckoutErrors/controllers/OnepageController.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/controllers/Adminhtml/Xfe/CheckoutErrorController.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Model/CheckoutError.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Model/Resource/CheckoutError.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Model/Resource/CheckoutError/Collection.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Helper/Data.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Block/Adminhtml/CheckoutError.php`
Run: `php -l app/code/community/XFE/CheckoutErrors/Block/Adminhtml/CheckoutError/Grid.php`
Expected: All files return "No syntax errors detected"

- [ ] **Verify module is active in Magento**

Run: `php -r "require 'app/Mage.php'; Mage::app('admin'); var_dump(Mage::getConfig()->getModuleConfig('XFE_CheckoutErrors')->is('active', 'true'));"`

Expected: bool(true)

- [ ] **Verify table was created**

Run: `php -r "require 'app/Mage.php'; Mage::app(); $conn = Mage::getSingleton('core/resource')->getConnection('core_write'); $tables = $conn->listTables(); var_dump(in_array('xfe_checkout_error', $tables));"`

Expected: bool(true)

- [ ] **Verify admin menu appears**

Open browser → Login to admin → Go to Sales menu → Verify "Checkout 错误记录" appears in the Sales dropdown

- [ ] **Verify Grid renders**

Navigate to Sales > Checkout 错误记录. Expected: Grid page displays (may be empty).
