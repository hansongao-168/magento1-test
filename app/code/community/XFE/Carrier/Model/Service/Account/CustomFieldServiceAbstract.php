<?php
/**
 * 承运商账号自定义字段 Service(L3 Service 抽象)
 *
 * 两个具体类
 *   - XFE_Carrier_Model_Service_Account_CustomFieldService
 *   - XFE_Carrier_Model_Service_FtpAccount_CustomFieldService
 * 都继承本类,只覆盖 _getModelAlias() / _getEntityIdColumn() 即可。
 *
 * 责任:
 *   - 从 raw POST 数据(键值对列表)构造 Domain 集合
 *   - 把集合编码成 JSON 字符串,set 到 Model
 *   - 广播 xfe_carrier_account_custom_field_changed 事件(payload 不含 value)
 *
 * 依赖方向(单向):
 *   CustomFieldService ─▶  Domain (L1)
 *                       ─▶  Model  (L2)
 *                       ─▶  Event  (Magento Core)
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
abstract class XFE_Carrier_Model_Service_Account_CustomFieldServiceAbstract
{
    /**
     * Model 工厂别名(子类覆盖):
     *   - 'xfe_carrier/carrier_account'
     *   - 'xfe_carrier/carrier_ftp_account'
     *
     * @return string
     */
    abstract protected function _getModelAlias();

    /**
     * Model 主键列名(子类覆盖):account_id / ftp_account_id
     *
     * @return string
     */
    abstract protected function _getEntityIdColumn();

    /**
     * 事件名前缀,用于跨实体区分:
     *   - 'account'     → 'xfe_carrier_account_custom_field_changed'
     *   - 'ftp_account' → 'xfe_carrier_account_custom_field_changed'(同事件,entity_type 区分)
     *
     * @return string
     */
    abstract protected function _getEntityType();

    /**
     * 读单个字段值。账号不存在时返回 null。
     *
     * @param int    $entityId
     * @param string $key
     * @return string|int|float|bool|null
     */
    public function getValue($entityId, $key)
    {
        $coll = $this->getCollection((int) $entityId);
        $field = $coll->get($key);
        return $field ? $field->getValue() : null;
    }

    /**
     * 读整个集合。账号不存在时返回空集合(不抛异常)。
     *
     * @param int $entityId
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    public function getCollection($entityId)
    {
        $model = $this->_load((int) $entityId);
        if (!$model->getId()) {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        return XFE_Carrier_Domain_CustomFieldCodec::decode($model->getCustomFieldsJson());
    }

    /**
     * 用 raw POST 数据更新该账号的自定义字段。
     *
     * POST 形态(来自 phtml 模板的键值对编辑器):
     *   custom_fields[key][label]   = string
     *   custom_fields[key][type]    = text|number|select|multiselect|boolean
     *   custom_fields[key][value]   = string|array (multiselect 时是 chips 数组或逗号串)
     *   custom_fields[key][options] = string (逗号分隔,仅 type=select/multiselect)
     * 删除某一行时,前端不提交该 key;后端视为"已删除"。
     *
     * 抛 InvalidArgumentException 当某行 spec 非法时(由 Domain 抛);
     * 调用方应捕获并翻译为 Mage_Core_Exception。
     *
     * @param int   $entityId
     * @param array $post
     * @return XFE_Carrier_Domain_CustomFieldCollection 实际保存的集合
     */
    public function applyFromPost($entityId, array $post)
    {
        $entityId = (int) $entityId;
        $model = $this->_load($entityId);
        if (!$model->getId()) {
            Mage::throwException(
                Mage::helper('xfe_carrier')->__('账号不存在(ID=%d)。', $entityId)
            );
        }

        $oldColl  = XFE_Carrier_Domain_CustomFieldCodec::decode($model->getCustomFieldsJson());
        $newColl  = $this->_buildFromPost($post);
        $oldKeys  = array_flip($oldColl->getKeys());
        $newKeys  = array_flip($newColl->getKeys());

        $json = XFE_Carrier_Domain_CustomFieldCodec::encode($newColl);
        $model->setCustomFieldsJson($json);
        $model->save();

        // 广播事件(payload 不含 value)
        $added   = array_diff_key($newKeys, $oldKeys);
        $removed = array_diff_key($oldKeys, $newKeys);
        $updated = array_intersect_key($newKeys, $oldKeys);
        $this->_dispatchChanges($entityId, $added, $updated, $removed);

        return $newColl;
    }

    /**
     * POST 段 → 集合。无效 spec 静默跳过(import 友好)。
     *
     * @param array $post
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    protected function _buildFromPost(array $post)
    {
        $coll = new XFE_Carrier_Domain_CustomFieldCollection();
        if (!XFE_Carrier_Domain_Constant_CustomField::isPostBagShape($post)) {
            return $coll;
        }
        $bag = $post[XFE_Carrier_Domain_Constant_CustomField::POST_BAG_KEY];
        foreach ($bag as $key => $spec) {
            if (!is_array($spec) || !is_string($key) || $key === '') {
                continue;
            }
            $type    = isset($spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_TYPE])
                ? (string) $spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_TYPE]
                : XFE_Carrier_Domain_CustomField::TYPE_TEXT;
            $label   = isset($spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_LABEL])
                ? (string) $spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_LABEL]
                : $key;
            $value   = isset($spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_VALUE])
                ? $spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_VALUE]
                : null;
            $options = null;
            if (($type === XFE_Carrier_Domain_CustomField::TYPE_SELECT
                    || $type === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT)
                && isset($spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_OPTIONS])
            ) {
                $raw = (string) $spec[XFE_Carrier_Domain_Constant_CustomField::POST_FIELD_OPTIONS];
                $options = array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
            try {
                $coll->add(new XFE_Carrier_Domain_CustomField(
                    $key, $label, $type, $value, $options
                ));
            } catch (InvalidArgumentException $e) {
                // 跳过非法 spec
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

    /**
     * 广播 xfe_carrier_account_custom_field_changed 事件。
     * 每个 key 一条事件,事件 payload 只含 entity_id / key / action,**不含 value**。
     *
     * @param int   $entityId
     * @param array $added   [key => true, ...]
     * @param array $updated [key => true, ...]
     * @param array $removed [key => true, ...]
     * @return void
     */
    protected function _dispatchChanges($entityId, array $added, array $updated, array $removed)
    {
        $type = $this->_getEntityType();
        $emit = function ($key, $action) use ($entityId, $type) {
            Mage::dispatchEvent(
                XFE_Carrier_Domain_Constant_CustomField::EVENT_CHANGED,
                array(
                    'entity_type' => $type,
                    'entity_id'   => (int) $entityId,
                    'key'         => (string) $key,
                    'action'      => $action,
                )
            );
        };
        foreach (array_keys($added) as $k) {
            $emit($k, XFE_Carrier_Domain_Constant_CustomField::ACTION_ADDED);
        }
        foreach (array_keys($updated) as $k) {
            $emit($k, XFE_Carrier_Domain_Constant_CustomField::ACTION_UPDATED);
        }
        foreach (array_keys($removed) as $k) {
            $emit($k, XFE_Carrier_Domain_Constant_CustomField::ACTION_REMOVED);
        }
    }
}
