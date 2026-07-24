<?php
/**
 * Categories API - scope: basic
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Categories extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'basic';

    /**
     * @param string $method
     * @param array $params
     * @return void
     */
    public function dispatch($method, array $params = array())
    {
        if (!$this->authenticate()) {
            return;
        }

        switch (strtoupper($method)) {
            case 'GET':
                $this->_getCategories();
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/categories - category tree
     */
    protected function _getCategories()
    {
        $rootId = Mage::app()->getStore()->getRootCategoryId();
        $tree = Mage::getResourceModel('catalog/category_tree');

        $tree->load();
        $root = $tree->getNodeById($rootId);

        if (!$root) {
            $this->_success(array());
            return;
        }

        $categories = $this->_buildTree($root);
        $this->_success($categories);
    }

    /**
     * Recursively build category tree
     *
     * @param Varien_Data_Tree_Node $node
     * @return array
     */
    protected function _buildTree($node)
    {
        $data = array(
            'id'          => $node->getId(),
            'name'        => $node->getName(),
            'parent_id'   => $node->getParentId(),
            'is_active'   => (bool)$node->getIsActive(),
            'position'    => (int)$node->getPosition(),
            'level'       => (int)$node->getLevel(),
            'product_count' => (int)$node->getProductCount(),
        );

        $children = $node->getChildren();
        if ($children && $children->count() > 0) {
            $data['children'] = array();
            foreach ($children as $child) {
                $data['children'][] = $this->_buildTree($child);
            }
        }

        return $data;
    }
}
