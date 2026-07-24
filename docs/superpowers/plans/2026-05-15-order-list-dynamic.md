# 前台订单列表动态显示 — 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将前台"我的订单"列表改为 Bootstrap 5.0 + Alpine.js 驱动的 AJAX 动态页面，增加订单数量统计卡片

**Architecture:** 新建 XFE_OrderFrontend 社区模块（控制器+Block+Helper）+ 新建 exp5/default 前端主题，通过 AJAX JSON 端点 `/xfe_order/order/list` 获取数据，Alpine.js 前端渲染

**Tech Stack:** Magento 1.x, Bootstrap 5.0 (CDN), Alpine.js 3.x (CDN), PHP, MySQL

---

### Task 1: 模块声明文件

**Files:**
- Create: `app/etc/modules/XFE_OrderFrontend.xml`
- Create: `app/code/community/XFE/OrderFrontend/etc/config.xml`

- [ ] **Step 1: 创建模块声明文件**

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_OrderFrontend>
            <active>true</active>
            <codePool>community</codePool>
            <depends>
                <Mage_Core/>
                <Mage_Customer/>
                <Mage_Sales/>
            </depends>
        </XFE_OrderFrontend>
    </modules>
</config>
```

写入 `app/etc/modules/XFE_OrderFrontend.xml`

- [ ] **Step 2: 创建模块配置 config.xml**

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_OrderFrontend>
            <version>1.0.0</version>
        </XFE_OrderFrontend>
    </modules>
    <frontend>
        <routers>
            <xfe_orderfrontend>
                <use>standard</use>
                <args>
                    <module>XFE_OrderFrontend</module>
                    <frontName>xfe_order</frontName>
                </args>
            </xfe_orderfrontend>
        </routers>
        <layout>
            <updates>
                <xfe_orderfrontend>
                    <file>xfe_order.xml</file>
                </xfe_orderfrontend>
            </updates>
        </layout>
    </frontend>
    <global>
        <blocks>
            <xfe_orderfrontend>
                <class>XFE_OrderFrontend_Block</class>
            </xfe_orderfrontend>
        </blocks>
        <helpers>
            <xfe_orderfrontend>
                <class>XFE_OrderFrontend_Helper</class>
            </xfe_orderfrontend>
        </helpers>
    </global>
</config>
```

写入 `app/code/community/XFE/OrderFrontend/etc/config.xml`

- [ ] **Step 3: 创建 Helper 基类**

```php
<?php
class XFE_OrderFrontend_Helper_Data extends Mage_Core_Helper_Abstract
{
}
```

写入 `app/code/community/XFE/OrderFrontend/Helper/Data.php`

---

### Task 2: Block 类

**Files:**
- Create: `app/code/community/XFE/OrderFrontend/Block/Order/History.php`

- [ ] **Step 1: 创建 Block 类**

```php
<?php
class XFE_OrderFrontend_Block_Order_History extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xfe_order/order/history.phtml');
    }

    /**
     * 获取 AJAX JSON 端点 URL
     */
    public function getListUrl()
    {
        return $this->getUrl('xfe_order/order/list');
    }

    /**
     * 获取订单状态下拉选项
     */
    public function getStatusOptions()
    {
        return Mage::getSingleton('sales/order_config')->getStatuses();
    }
}
```

---

### Task 3: 控制器（初始页面 + AJAX 端点）

**Files:**
- Create: `app/code/community/XFE/OrderFrontend/controllers/OrderController.php`

- [ ] **Step 1: 创建控制器，实现 indexAction（服务端渲染初始页面）**

```php
<?php
class XFE_OrderFrontend_OrderController extends Mage_Core_Controller_Front_Action
{
    /**
     * 检查登录，未登录跳转
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->setFlag('', 'no-dispatch', true);
            $this->_redirect('customer/account/login');
            return;
        }
    }

    /**
     * 初始页面 - 服务端渲染 Alpine.js 挂载点
     * URL: /xfe_order/order/index
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('My Orders'));
        $this->renderLayout();
    }
}
```

- [ ] **Step 2: 在控制器中添加 listAction（AJAX JSON 端点）**

```php
    /**
     * AJAX JSON 端点 - 返回订单列表 + 统计数据
     * URL: /xfe_order/order/list
     * 参数: page, limit, search, status, date_from, date_to
     */
    public function listAction()
    {
        $customerSession = Mage::getSingleton('customer/session');
        if (!$customerSession->isLoggedIn()) {
            return $this->_sendJson([
                'success' => false,
                'message' => $this->__('Please login first.')
            ]);
        }

        $customerId = $customerSession->getCustomerId();
        $page     = max(1, (int)$this->getRequest()->getParam('page', 1));
        $limit    = min(100, max(1, (int)$this->getRequest()->getParam('limit', 10)));
        $search   = trim($this->getRequest()->getParam('search', ''));
        $status   = trim($this->getRequest()->getParam('status', ''));
        $dateFrom = trim($this->getRequest()->getParam('date_from', ''));
        $dateTo   = trim($this->getRequest()->getParam('date_to', ''));

        // 构建订单集合查询
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('state', ['in' => Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates()])
            ->setOrder('created_at', 'desc');

        if ($search !== '') {
            $orders->addFieldToFilter('increment_id', ['like' => "%{$search}%"]);
        }
        if ($status !== '') {
            $orders->addFieldToFilter('status', $status);
        }
        if ($dateFrom !== '') {
            $orders->addFieldToFilter('created_at', ['gte' => $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $orders->addFieldToFilter('created_at', ['lte' => $dateTo . ' 23:59:59']);
        }

        $total = $orders->getSize();
        $orders->setPageSize($limit)->setCurPage($page);
        $lastPage = ceil($total / $limit);

        // 格式化订单数据
        $orderData = [];
        foreach ($orders as $order) {
            $shippingName = '';
            if ($order->getShippingAddress()) {
                $shippingName = $order->getShippingAddress()->getName();
            }
            $orderData[] = [
                'entity_id'     => (int)$order->getId(),
                'increment_id'  => $order->getIncrementId(),
                'created_at'    => $order->getCreatedAt(),
                'status'        => $order->getStatus(),
                'status_label'  => $order->getStatusLabel(),
                'grand_total'   => number_format($order->getGrandTotal(), 2),
                'currency'      => $order->getOrderCurrencyCode(),
                'shipping_name' => $shippingName,
                'can_reorder'   => $order->canReorder(),
            ];
        }

        $result = [
            'success' => true,
            'data'    => [
                'stats'      => $this->_getOrderStats($customerId),
                'orders'     => $orderData,
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => (int)$lastPage,
                    'per_page'     => $limit,
                    'total'        => (int)$total,
                ],
            ],
        ];

        $this->_sendJson($result);
    }
```

- [ ] **Step 3: 添加统计方法 `_getOrderStats()` 和 JSON 辅助方法 `_sendJson()`**

```php
    /**
     * 获取订单统计数据（今日/本周/本月/今年订单数）
     */
    private function _getOrderStats($customerId)
    {
        $visibleStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
        $now = Mage::getModel('core/date')->gmtTimestamp();

        $baseQuery = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('state', ['in' => $visibleStates]);

        // 今日
        $todayStart = date('Y-m-d 00:00:00', $now);
        $today = (clone $baseQuery)
            ->addFieldToFilter('created_at', ['gte' => $todayStart])
            ->getSize();

        // 本周（周一为一周起始）
        $weekStart = date('Y-m-d 00:00:00', $now - 86400 * date('w', $now) + 86400);
        $week = (clone $baseQuery)
            ->addFieldToFilter('created_at', ['gte' => $weekStart])
            ->getSize();

        // 本月
        $monthStart = date('Y-m-01 00:00:00', $now);
        $month = (clone $baseQuery)
            ->addFieldToFilter('created_at', ['gte' => $monthStart])
            ->getSize();

        // 今年
        $yearStart = date('Y-01-01 00:00:00', $now);
        $year = (clone $baseQuery)
            ->addFieldToFilter('created_at', ['gte' => $yearStart])
            ->getSize();

        return [
            'today' => (int)$today,
            'week'  => (int)$week,
            'month' => (int)$month,
            'year'  => (int)$year,
        ];
    }

    /**
     * 发送 JSON 响应
     */
    private function _sendJson($data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }
```

---

### Task 4: exp5/default 主题配置文件

**Files:**
- Create: `app/design/frontend/exp5/default/etc/theme.xml`
- Create: `app/design/frontend/exp5/default/layout/page.xml`
- Create: `app/design/frontend/exp5/default/layout/xfe_order.xml`
- Create: `app/design/frontend/exp5/default/layout/customer_account.xml`

- [ ] **Step 1: 创建 theme.xml（主题声明，parent: base/default）**

```xml
<?xml version="1.0"?>
<theme>
    <package>exp5</package>
    <title>exp5 Frontend</title>
    <parent>base/default</parent>
</theme>
```

写入 `app/design/frontend/exp5/default/etc/theme.xml`

- [ ] **Step 2: 创建 page.xml（全局引入 Bootstrap 5 CSS）**

```xml
<?xml version="1.0"?>
<layout version="0.1.0">
    <default>
        <reference name="head">
            <block type="core/text" name="xfe.bootstrap5.css">
                <action method="setText">
                    <text><![CDATA[<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" crossorigin="anonymous">]]></text>
                </action>
            </block>
        </reference>
    </default>
</layout>
```

写入 `app/design/frontend/exp5/default/layout/page.xml`

- [ ] **Step 3: 创建 xfe_order.xml（订单页面布局）**

```xml
<?xml version="1.0"?>
<layout version="0.1.0">
    <xfe_order_order_index translate="label">
        <label>Customer My Account Order History (Dynamic)</label>
        <update handle="customer_account"/>
        <reference name="my.account.wrapper">
            <block type="xfe_orderfrontend/order_history" name="xfe.order.history"/>
        </reference>
        <reference name="head">
            <action method="addItem">
                <type>js</type>
                <name>https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js</name>
            </action>
        </reference>
    </xfe_order_order_index>
</layout>
```

写入 `app/design/frontend/exp5/default/layout/xfe_order.xml`

- [ ] **Step 4: 创建 customer_account.xml（替换导航链接到新地址）**

```xml
<?xml version="1.0"?>
<layout version="0.1.0">
    <customer_account>
        <reference name="customer_account_navigation">
            <action method="removeLink">
                <name>orders</name>
            </action>
            <action method="addLink" translate="label" module="xfe_orderfrontend">
                <name>xfe_orders</name>
                <path>xfe_order/order/index</path>
                <label>My Orders</label>
            </action>
        </reference>
    </customer_account>
</layout>
```

写入 `app/design/frontend/exp5/default/layout/customer_account.xml`

---

### Task 5: 前端模板 history.phtml + Alpine.js 组件

**Files:**
- Create: `app/design/frontend/exp5/default/template/xfe_order/order/history.phtml`

- [ ] **Step 1: 创建模板目录**

```bash
mkdir -p app/design/frontend/exp5/default/template/xfe_order/order
```

- [ ] **Step 2: 创建 history.phtml**

```php
<?php
/**
 * @var $this XFE_OrderFrontend_Block_Order_History
 */
$listUrl = $this->getListUrl();
$statusOptions = $this->getStatusOptions();
?>
<div class="xfe-order-history container py-4" x-data="orderList('<?php echo $listUrl ?>')" x-init="init()">

    <!-- ======== 统计卡片 ======== -->
    <div class="row g-3 mb-4" x-show="stats">
        <template x-for="(val, key, idx) in stats" :key="key">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center p-3 h-100"
                     :style="'background: linear-gradient(135deg, ' + gradientColor(key) + ')'">
                    <div class="text-white small text-uppercase mb-1 opacity-75"
                         x-text="labelMap(key)"></div>
                    <div class="text-white fs-3 fw-bold" x-text="val"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- ======== 搜索/筛选栏 ======== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted"><?php echo $this->__('Order #') ?></label>
                    <input type="text" class="form-control" x-model="search"
                           placeholder="<?php echo $this->__('Search by order number...') ?>"
                           @keyup.enter="searchOrders()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted"><?php echo $this->__('Status') ?></label>
                    <select class="form-select" x-model="status" @change="searchOrders()">
                        <option value=""><?php echo $this->__('All Statuses') ?></option>
                        <?php foreach ($statusOptions as $val => $label): ?>
                        <option value="<?php echo $this->escapeHtml($val) ?>">
                            <?php echo $this->escapeHtml($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold text-muted"><?php echo $this->__('From') ?></label>
                    <input type="date" class="form-control" x-model="dateFrom">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold text-muted"><?php echo $this->__('To') ?></label>
                    <input type="date" class="form-control" x-model="dateTo">
                </div>
                <div class="col-md-1">
                    <label class="form-label small">&nbsp;</label>
                    <button class="btn btn-primary w-100" @click="searchOrders()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ======== 统计信息行 ======== -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="text-muted small" x-show="!loading && total > 0"
                  x-text="'<?php echo $this->__('Total') ?>: ' + total + ' <?php echo $this->__('orders') ?>'"></span>
            <span class="text-muted small" x-show="error" x-text="error"></span>
        </div>
    </div>

    <!-- ======== 订单表格 ======== -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo $this->__('Order #') ?></th>
                        <th><?php echo $this->__('Date') ?></th>
                        <th><?php echo $this->__('Ship To') ?></th>
                        <th class="text-end"><?php echo $this->__('Order Total') ?></th>
                        <th><?php echo $this->__('Status') ?></th>
                        <th><?php echo $this->__('Action') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 加载中 -->
                    <tr x-show="loading && !orders.length">
                        <td colspan="6" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2 text-muted small"><?php echo $this->__('Loading...') ?></div>
                        </td>
                    </tr>
                    <!-- 无数据 -->
                    <tr x-show="!loading && !orders.length">
                        <td colspan="6" class="text-center py-5 text-muted">
                            <?php echo $this->__('You have placed no orders.') ?>
                        </td>
                    </tr>
                    <!-- 数据行 -->
                    <template x-for="order in orders" :key="order.entity_id">
                        <tr>
                            <td>
                                <a :href="'<?php echo $this->getUrl('sales/order/view') ?>order_id/' + order.entity_id"
                                   class="fw-bold text-decoration-none" x-text="order.increment_id"></a>
                            </td>
                            <td x-text="formatDate(order.created_at)"></td>
                            <td x-text="order.shipping_name || '-'"></td>
                            <td class="text-end fw-semibold" x-text="order.currency + ' ' + order.grand_total"></td>
                            <td>
                                <span class="badge rounded-pill"
                                      :class="statusBadge(order.status)"
                                      x-text="order.status_label"></span>
                            </td>
                            <td>
                                <a :href="'<?php echo $this->getUrl('sales/order/view') ?>order_id/' + order.entity_id"
                                   class="btn btn-sm btn-outline-primary me-1"><?php echo $this->__('View Order') ?></a>
                                <a :href="'<?php echo $this->getUrl('sales/order/reorder') ?>order_id/' + order.entity_id"
                                   x-show="order.can_reorder"
                                   class="btn btn-sm btn-outline-secondary"><?php echo $this->__('Reorder') ?></a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======== 分页 ======== -->
    <nav class="mt-4" x-show="lastPage > 1">
        <ul class="pagination justify-content-center">
            <li class="page-item" :class="{ disabled: page <= 1 }">
                <a class="page-link" href="#" @click.prevent="loadPage(page - 1)"
                   x-text="'<?php echo $this->__('Previous') ?>'"></a>
            </li>
            <template x-for="p in paginationRange()" :key="p">
                <li class="page-item" :class="{ active: p === page, disabled: p === '...' }">
                    <a class="page-link" href="#" @click.prevent="loadPage(p)" x-text="p"></a>
                </li>
            </template>
            <li class="page-item" :class="{ disabled: page >= lastPage }">
                <a class="page-link" href="#" @click.prevent="loadPage(page + 1)"
                   x-text="'<?php echo $this->__('Next') ?>'"></a>
            </li>
        </ul>
    </nav>
</div>
```

- [ ] **Step 3: 追加 Alpine.js 组件脚本（放在模板底部）**

```php
<script>
function orderList(url) {
    return {
        // ---------- 状态 ----------
        orders: [],
        stats: null,
        loading: true,
        error: null,

        search: '',
        status: '',
        dateFrom: '',
        dateTo: '',

        page: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,

        // ---------- 生命周期 ----------
        init() {
            this.loadOrders();
        },

        // ---------- 数据加载 ----------
        async loadOrders() {
            this.loading = true;
            this.error = null;
            try {
                const params = new URLSearchParams({
                    page: this.page,
                    limit: this.perPage,
                    search: this.search,
                    status: this.status,
                    date_from: this.dateFrom,
                    date_to: this.dateTo
                });
                const resp = await fetch(url + '?' + params.toString());
                const json = await resp.json();
                if (json.success) {
                    this.orders = json.data.orders;
                    this.stats = json.data.stats;
                    this.page = json.data.pagination.current_page;
                    this.lastPage = json.data.pagination.last_page;
                    this.perPage = json.data.pagination.per_page;
                    this.total = json.data.pagination.total;
                } else {
                    this.error = json.message || '<?php echo $this->__('Load failed') ?>';
                }
            } catch (e) {
                this.error = '<?php echo $this->__('Network error') ?>';
            } finally {
                this.loading = false;
            }
        },

        // ---------- 搜索/筛选 ----------
        searchOrders() {
            this.page = 1;
            this.loadOrders();
        },

        // ---------- 分页 ----------
        loadPage(p) {
            if (p === '...') return;
            if (p < 1 || p > this.lastPage) return;
            this.page = p;
            this.loadOrders();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        paginationRange() {
            const range = [];
            const delta = 2;
            const left = Math.max(2, this.page - delta);
            const right = Math.min(this.lastPage - 1, this.page + delta);
            range.push(1);
            if (left > 2) range.push('...');
            for (let i = left; i <= right; i++) range.push(i);
            if (right < this.lastPage - 1) range.push('...');
            if (this.lastPage > 1) range.push(this.lastPage);
            return range;
        },

        // ---------- 显示辅助 ----------
        statusBadge(status) {
            const map = {
                pending: 'bg-warning text-dark',
                processing: 'bg-info text-dark',
                complete: 'bg-success',
                closed: 'bg-secondary',
                canceled: 'bg-danger',
                holded: 'bg-dark'
            };
            return map[status] || 'bg-secondary';
        },

        gradientColor(key) {
            const map = {
                today: '#667eea,#764ba2',
                week: '#f093fb,#f5576c',
                month: '#4facfe,#00f2fe',
                year: '#43e97b,#38f9d7'
            };
            return map[key] || '#667eea,#764ba2';
        },

        labelMap(key) {
            const map = {
                today: '<?php echo $this->__('Today') ?>',
                week: '<?php echo $this->__('This Week') ?>',
                month: '<?php echo $this->__('This Month') ?>',
                year: '<?php echo $this->__('This Year') ?>'
            };
            return map[key] || key;
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr.replace(' ', 'T'));
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return m + '/' + day;
        }
    };
}
</script>
```

- [ ] **Step 4: 合并 Step 2 和 Step 3 写入最终的 history.phtml**

将 Step 2 的 HTML 模板 + Step 3 的 Alpine.js 脚本合并写入：
`app/design/frontend/exp5/default/template/xfe_order/order/history.phtml`

---

### Task 6: 验证模块安装

**Files:** 无（仅执行命令）

- [ ] **Step 1: 验证模块已启用**

```bash
php -r "echo (Mage::getConfig()->getModuleConfig('XFE_OrderFrontend')->is('active', 'true') ? 'ACTIVE' : 'INACTIVE') . PHP_EOL;"
```

Expected: `ACTIVE`

- [ ] **Step 2: 验证前端路由**

```bash
php -r "
\$url = Mage::getUrl('xfe_order/order/index');
echo 'Index URL: ' . \$url . PHP_EOL;
\$url = Mage::getUrl('xfe_order/order/list');
echo 'List URL: ' . \$url . PHP_EOL;
"
```

Expected: 输出两个可用的 URL

- [ ] **Step 3: 验证主题可识别**

```bash
php -r "
Mage::getDesign()->setPackageName('exp5')->setTheme('default');
echo 'Theme area: ' . Mage::getDesign()->getArea() . PHP_EOL;
echo 'Theme package: ' . Mage::getDesign()->getPackageName() . PHP_EOL;
echo 'Theme theme: ' . Mage::getDesign()->getTheme('layout') . PHP_EOL;
"
```

Expected: exp5 / default 主题可正常加载

---

### Task 7: 功能验证

**Files:** 无（仅测试）

- [ ] **Step 1: 访问前端页面检查是否有 JS 错误**

访问 `/xfe_order/order/index`，用浏览器开发者工具检查：
- 页面加载成功（无 404/500）
- Alpine.js 已加载（控制台可输入 `Alpine.version` 查看版本）
- 无 JavaScript 错误

- [ ] **Step 2: 检查 AJAX 请求和响应**

打开浏览器 Network 面板，确认：
- 页面加载后自动发起 `GET /xfe_order/order/list?page=1&limit=10&search=&status=&date_from=&date_to=`
- 返回 JSON 包含 `stats`、`orders`、`pagination` 三个字段
- 订单数据正确显示在表格中

- [ ] **Step 3: 测试搜索/筛选功能**

- 在搜索框输入订单号，点击搜索按钮 → 列表刷新，只显示匹配订单
- 切换状态下拉 → 自动刷新列表
- 选择日期范围 → 点击搜索 → 正确过滤

- [ ] **Step 4: 测试分页**

- 翻到第 2 页 → 列表刷新，URL 参数中 page=2
- 页脚显示正确页码和高亮当前页

- [ ] **Step 5: 测试统计卡片**

确认顶部 4 张卡片显示正确的订单数，且与当前筛选条件下的数据对应
