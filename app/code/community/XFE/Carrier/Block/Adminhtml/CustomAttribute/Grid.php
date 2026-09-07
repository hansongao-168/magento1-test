<?php

/**
 * 自定义属性 Grid
 *
 * 列: ID / 分类 / Key / 展示名 / 类型 / 候选项 / 必填 / 启用 / 排序 / 操作
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xfe_carrier_custom_attribute_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(false);
    }

    /**
     * 准备 collection。支持 ?entity_type=carrier 过滤。
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $entityType = $this->getRequest()->getParam('entity_type');
        $service = XFE_Carrier_Model_Service_Registry::customAttributeService();

        if ($entityType !== null
            && in_array($entityType, XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES, true)
        ) {
            $coll = $service->getAllDefs($entityType);
        } else {
            // 全部 4 分类
            $coll = new XFE_Carrier_Domain_CustomAttributeCollection();
            foreach (XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES as $t) {
                foreach ($service->getAllDefs($t) as $def) {
                    $coll->add($def);
                }
            }
        }

        // Domain Collection → Varien_Data_Collection(Mage Grid 需要)
        $varien = new Varien_Data_Collection();
        foreach ($coll as $def) {
            $row = new Varien_Object(array(
                'id'           => $def->getId(),
                'entity_type'  => $def->getEntityType(),
                'field_key'    => $def->getFieldKey(),
                'label'        => $def->getLabel(),
                'field_type'   => $def->getFieldType(),
                'options_csv'  => $def->getOptions() === null
                    ? ''
                    : implode(',', $def->getOptions()),
                'is_required'  => $def->isRequired() ? 1 : 0,
                'is_active'    => $def->isActive()   ? 1 : 0,
                'sort_order'   => $def->getSortOrder(),
            ));
            $varien->addItem($row);
        }
        $this->setCollection($varien);
        return parent::_prepareCollection();
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfe_carrier');

        $this->addColumn('id', array(
            'header' => $helper->__('ID'),
            'index'  => 'id',
            'type'   => 'number',
            'width'  => '50px',
        ));

        $this->addColumn('entity_type', array(
            'header'  => $helper->__('分类'),
            'index'   => 'entity_type',
            'type'    => 'options',
            'width'   => '100px',
            'options' => array(
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_CARRIER     => $helper->__('承运商信息'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT     => $helper->__('账号'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_FTP_ACCOUNT => $helper->__('FTP'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_LOGO        => $helper->__('LOGO'),
            ),
        ));

        $this->addColumn('field_key', array(
            'header' => $helper->__('Key'),
            'index'  => 'field_key',
        ));

        $this->addColumn('label', array(
            'header' => $helper->__('展示名'),
            'index'  => 'label',
        ));

        $this->addColumn('field_type', array(
            'header'  => $helper->__('类型'),
            'index'   => 'field_type',
            'type'    => 'options',
            'width'   => '100px',
            'options' => array(
                'text'        => $helper->__('文本'),
                'number'      => $helper->__('数字'),
                'select'      => $helper->__('下拉'),
                'multiselect' => $helper->__('多选'),
                'boolean'     => $helper->__('布尔'),
            ),
        ));

        $this->addColumn('is_required', array(
            'header'  => $helper->__('必填'),
            'index'   => 'is_required',
            'type'    => 'options',
            'width'   => '60px',
            'options' => array(0 => $helper->__('否'), 1 => $helper->__('是')),
        ));

        $this->addColumn('is_active', array(
            'header'  => $helper->__('启用'),
            'index'   => 'is_active',
            'type'    => 'options',
            'width'   => '60px',
            'options' => array(0 => $helper->__('停用'), 1 => $helper->__('启用')),
        ));

        $this->addColumn('sort_order', array(
            'header' => $helper->__('排序'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('action', array(
            'header'  => $helper->__('操作'),
            'width'   => '180px',
            'type'    => 'action',
            'getter'  => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('编辑'),
                    'url'     => array('base' => '*/carrier_customAttribute/edit'),
                    'field'   => 'id',
                ),
                array(
                    'caption' => $helper->__('停用'),
                    'url'     => array('base' => '*/carrier_customAttribute/delete'),
                    'field'   => 'id',
                    'confirm' => $helper->__('确定要停用该属性吗?'),
                ),
                array(
                    'caption' => $helper->__('启用'),
                    'url'     => array('base' => '*/carrier_customAttribute/activate'),
                    'field'   => 'id',
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }
}
