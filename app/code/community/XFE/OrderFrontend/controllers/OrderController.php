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
        $lastPage = (int)ceil($total / $limit);

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
                    'last_page'    => $lastPage,
                    'per_page'     => $limit,
                    'total'        => (int)$total,
                ],
            ],
        ];

        $this->_sendJson($result);
    }

    /**
     * 收件账户使用权限 - 开关设置页面
     * URL: /xfe_order/order/recipientPermission
     */
    public function recipientPermissionAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('收件账户使用权限'));
        $this->renderLayout();
    }

    /**
     * 收件账户使用权限 - AJAX: 获取当前状态
     * URL: /xfe_order/order/recipientPermissionStatus
     */
    public function recipientPermissionStatusAction()
    {
        $customerSession = Mage::getSingleton('customer/session');
        if (!$customerSession->isLoggedIn()) {
            return $this->_sendJson([
                'success' => false,
                'message' => $this->__('Please login first.')
            ]);
        }

        $customerId = $customerSession->getCustomerId();
        // 🔧 可编辑：从数据库或配置中读取当前设置
        $enabled = Mage::getStoreConfig('xfe/recipient_permission/enabled_' . $customerId);
        // 默认为关闭
        $enabled = ($enabled === null) ? false : (bool)$enabled;

        $this->_sendJson([
            'success' => true,
            'data'    => ['enabled' => $enabled],
        ]);
    }

    /**
     * 收件账户使用权限 - AJAX: 保存设置
     * URL: /xfe_order/order/recipientPermissionSave
     */
    public function recipientPermissionSaveAction()
    {
        $customerSession = Mage::getSingleton('customer/session');
        if (!$customerSession->isLoggedIn()) {
            return $this->_sendJson([
                'success' => false,
                'message' => $this->__('Please login first.')
            ]);
        }

        $customerId = $customerSession->getCustomerId();
        $enabled = (bool)$this->getRequest()->getPost('enabled', false);

        // 🔧 可编辑：保存设置到数据库或配置
        // 示例保存到配置（需根据实际数据结构调整）
        Mage::getConfig()->saveConfig('xfe/recipient_permission/enabled_' . $customerId, $enabled ? '1' : '0');
        Mage::getConfig()->cleanCache();

        $this->_sendJson([
            'success' => true,
            'data'    => ['enabled' => $enabled],
        ]);
    }

    /**
     * 获取订单统计数据（今日/本周/本月/今年订单数）
     *
     * @param int $customerId
     * @return array
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
        $dayOfWeek = (int)date('w', $now);
        $weekStart = date('Y-m-d 00:00:00', $now - 86400 * ($dayOfWeek === 0 ? 6 : $dayOfWeek - 1));
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
     *
     * @param array $data
     */
    private function _sendJson($data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
