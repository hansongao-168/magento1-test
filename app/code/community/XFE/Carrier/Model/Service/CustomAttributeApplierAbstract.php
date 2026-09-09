<?php
/**
 * 自定义属性 Applier 抽象基类(L3 Service)
 *
 * 4 个实体(承运商 / 账号 / FTP / LOGO)各有一个具体子类,继承本类,只覆盖
 *   - _getModelAlias()       (Model 工厂别名)
 *   - _getEntityIdColumn()   (主键列名)
 *   - _getEntityType()       (CustomAttribute 分类: carrier / account / ftp_account / logo)
 *
 * 1.0.15 严格模式:
 *   - POST 中每个 key 必须已在全局属性表 (xfe_carrier_custom_attribute) 登记
 *   - is_required=true 字段 value 不能为空
 *   - value 必须在 options 内 (select / multiselect 固定模式)
 *   - label / type / options **以全局表最新值为准**,不采纳 POST 自带的(POST 只决定 key + value)
 *
 * 兼容:
 *   - 旧 per-row JSON 里"未登记"的 key: getCollection() / getValue() 仍能读
 *     (CustomFieldCodec 不感知全局表),但 applyFromPostStrict() 拒绝写入
 *
 * 依赖方向(单向):
 *   CustomAttributeApplier ─▶  Domain (L1)
 *                            ─▶  Codec (L1)
 *                            ─▶  Model  (L2)
 *                            ─▶  CustomAttributeService (L3, 同层互调)
 *                            ─▶  Event  (Magento Core)
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 *           docs/architecture/decisions/0006-custom-attribute-management.md
 */
abstract class XFE_Carrier_Model_Service_CustomAttributeApplierAbstract
{
    /** @var XFE_Carrier_Model_Service_CustomAttributeService|null */
    protected static $_defService = null;

    /**
     * 注入 defService(测试钩子;默认走 Registry 单例)。
     *
     * @param XFE_Carrier_Model_Service_CustomAttributeService|null $svc
     */
    public static function setDefService($svc)
    {
        self::$_defService = $svc;
    }

    /**
     * @return XFE_Carrier_Model_Service_CustomAttributeService
     */
    protected function _defService()
    {
        if (self::$_defService !== null) {
            return self::$_defService;
        }
        return XFE_Carrier_Model_Service_Registry::customAttributeService();
    }

    /**
     * Model 工厂别名(子类覆盖):
     *   - 'xfe_carrier/carrier'              (承运商)
     *   - 'xfe_carrier/carrier_account'      (账号)
     *   - 'xfe_carrier/carrier_ftp_account'  (FTP 账号)
     *   - 'xfe_carrier/carrier_logo'         (LOGO)
     *
     * @return string
     */
    abstract protected function _getModelAlias();

    /**
     * Model 主键列名(子类覆盖):
     *   - carrier_id / account_id / ftp_account_id / logo_id
     *
     * @return string
     */
    abstract protected function _getEntityIdColumn();

    /**
     * CustomAttribute 分类(子类覆盖):
     *   - carrier / account / ftp_account / logo
     *
     * @return string
     */
    abstract protected function _getEntityType();

    /**
     * 读整个集合(读时不强制校验,即使 key 未登记也返回——向后兼容)。
     *
     * @param int $entityId
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    public function getCollection($entityId)
    {
        $entityId = (int) $entityId;
        $model = Mage::getModel($this->_getModelAlias())->load($entityId);
        if (!$model->getId()) {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        return XFE_Carrier_Domain_CustomFieldCodec::decode($model->getCustomFieldsJson());
    }

    /**
     * 读单个字段值。实体不存在或 key 不存在时返回 null。
     *
     * @param int    $entityId
     * @param string $key
     * @return string|int|float|bool|string[]|null
     */
    public function getValue($entityId, $key)
    {
        $coll = $this->getCollection((int) $entityId);
        $field = $coll->get($key);
        return $field ? $field->getValue() : null;
    }

    /**
     * 严格模式 apply:仅当 entity_type 的全局属性表非空时启用校验。
     * 严格校验规则:
     *   1. POST 中每个 key 必须已在 xfe_carrier_custom_attribute 登记
     *      (is_active=1,entity_type 匹配)
     *   2. is_required=true 字段 value 不能为空
     *   3. value 必须在 options 内 (select / multiselect 固定模式)
     *   4. label / type / options 以全局表最新值为准
     *
     * 兼容模式:若全局表为空(getActiveDefs() 返回 0 条),退化为旧逻辑
     *   (POST 自带 label/type/options,任意 key 接受)——首次部署无全局表时
     *   不会破坏现有流程。
     *
     * @param int   $entityId
     * @param array $post
     * @return XFE_Carrier_Domain_CustomFieldCollection 实际保存的集合
     * @throws Mage_Core_Exception 校验失败 / 实体不存在
     */
    public function applyFromPost($entityId, array $post)
    {
        $entityId = (int) $entityId;
        $model = $this->_load($entityId);
        if (!$model->getId()) {
            Mage::throwException(
                Mage::helper('xfe_carrier')->__('实体不存在(ID=%d)。', $entityId)
            );
        }

        $oldColl = XFE_Carrier_Domain_CustomFieldCodec::decode($model->getCustomFieldsJson());
        $defs    = $this->_defService()->getActiveDefs($this->_getEntityType());

        $newColl = $this->_buildFromPost($post, $defs, $oldColl);

        $json = XFE_Carrier_Domain_CustomFieldCodec::encode($newColl);
        $model->setCustomFieldsJson($json);
        $model->save();

        return $newColl;
    }

    /**
     * POST → 集合(严格模式)。
     *
     * @param array $post
     * @param XFE_Carrier_Domain_CustomAttributeCollection $defs
     * @param XFE_Carrier_Domain_CustomFieldCollection $oldColl
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    protected function _buildFromPost(array $post, $defs, $oldColl)
    {
        $coll = new XFE_Carrier_Domain_CustomFieldCollection();
        $bag  = isset($post['custom_fields']) && is_array($post['custom_fields'])
            ? $post['custom_fields']
            : array();
        $strict = $defs->count() > 0;   // 全局表为空 → 兼容模式

        foreach ($bag as $key => $spec) {
            if (!is_array($spec) || !is_string($key) || $key === '') {
                continue;
            }
            $def = $defs->get($key);

            if ($strict) {
                if ($def === null) {
                    Mage::throwException(Mage::helper('xfe_carrier')->__(
                        '未登记的自定义属性: %s。请先到 "系统 → 承运商管理 → 自定义属性" 登记。',
                        $key
                    ));
                }
                if ($def->isRequired()) {
                    $val = isset($spec['value']) ? $spec['value'] : null;
                    $isEmpty = ($val === null || $val === '' || $val === array());
                    if ($isEmpty) {
                        Mage::throwException(Mage::helper('xfe_carrier')->__(
                            '必填字段未填写: %s (%s)。',
                            $key,
                            $def->getLabel()
                        ));
                    }
                }
            }

            // value 由 POST 决定;label/type/options 由全局表覆盖(若有)
            $type    = $def !== null ? $def->getFieldType() : 'text';
            $label   = $def !== null ? $def->getLabel()     : (isset($spec['label']) ? $spec['label'] : $key);
            $options = $def !== null ? $def->getOptions()   : null;
            $value   = isset($spec['value']) ? $spec['value'] : null;

            // multiselect 接受 array / 逗号分隔字符串
            if ($type === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT
                && is_string($value)
                && $value !== ''
            ) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            try {
                $coll->add(new XFE_Carrier_Domain_CustomField(
                    $key, $label, $type, $value, $options
                ));
            } catch (InvalidArgumentException $e) {
                if ($strict) {
                    Mage::throwException(Mage::helper('xfe_carrier')->__(
                        '字段值非法: %s — %s',
                        $key,
                        $e->getMessage()
                    ));
                }
                // 兼容模式:跳过非法 spec
                continue;
            }
        }
        return $coll;
    }

    /**
     * @param int $entityId
     * @return Mage_Core_Model_Abstract
     */
    protected function _load($entityId)
    {
        return Mage::getModel($this->_getModelAlias())->load($entityId);
    }
}
