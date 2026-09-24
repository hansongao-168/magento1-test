# 0027. 自定义属性 options 升级为 JSON + 多 locale map(方案 C)

- 状态：Proposed
- 日期：2026-09-18
- 决策者：AI 助手 + 用户确认
- 关联 ADR：0022(行编辑器)/ 0023(boolean 自定义 label)/ 0026(JS 自启动)
- 关联模块：`XFE_Carrier` 自定义属性全局定义(`xfe_carrier_custom_attribute`)

## 背景

截至 ADR 0026,自定义属性的 `options_csv` 字段采用 `key|label` 形式(单 label,
小改 K 后结构化为 `[{key,label}]`)。这种格式在两种诉求下失效:

1. **后台编辑 boolean/select/multiselect 时**,label 只有一个,无法按语言分别输入
   (中/英同时填)
2. **实体编辑页(承运商/账号/FTP/LOGO)展示时**,boolean radio / select 选项只能显示
   一个固定 label 字符串,不能按当前 store view 自动切换

用户 2026-09-18 提的"多语言如何切换编辑"直指这两点,确认走完整方案 C。

## 决策

把 `options_csv`(单 label)升级为 **`options_json`**(多 locale map),向后兼容
旧的 `options_csv` 字段,迁移期两者并存。

### 1. 存储格式

新增字段 `xfe_carrier_custom_attribute.options_json TEXT NULL`,
JSON 结构(UTF-8):

```json
[
  {
    "key": "0",
    "labels": {
      "default": "否",
      "zh_CN":   "否",
      "en_US":   "No"
    }
  },
  {
    "key": "1",
    "labels": {
      "default": "是",
      "zh_CN":   "是",
      "en_US":   "Yes"
    }
  }
]
```

`labels.default` 是 fallback;渲染时按 `Mage::app()->getStore()->getLocaleCode()`
查找对应 locale,缺失再回退 `default`。

### 2. locale 列表(白名单,小改 P1)

```php
class XFE_Carrier_Domain_CustomAttribute {
    const ALLOWED_LOCALES = array(
        'default',  // 兜底(必须)
        'zh_CN',    // 简体中文
        'en_US',    // 英文
    );
}
```

新增语言在常量里追加 + 行编辑器多一个 tab,不需要改 schema。

### 3. 渲染按当前 store locale 自动切

`XFE_Carrier_Domain_CustomAttribute::getLocalizedOptions(string $locale)`
返回结构化数组,每个 entry 含 `key` 和 `labels`。消费者(`strict_editor.phtml`)
传入当前 store locale 取 label。

### 4. 编辑器 UI

行编辑器每个 row 增加 locale tab 切换(默认 / zh_CN / en_US),
当前 tab 下显示对应 locale 的 label 输入框。tab 标题从
`window.XfeCaEditorLabels.locales` 注入(小改 P1 配套)。

## 备选方案

| 方案 | 描述 | 放弃理由 |
|---|---|---|
| A. i18n key 模式 | label 是 i18n key,渲染 `__()` | 不能完全自定义,受翻译表约束 |
| B. 双语 inline 列 | `key\|label_zh\|label_en`,CSV 三列 | 改格式时扩展第三种语言要再改 |
| **C. JSON + 多 locale map**(本方案) | `options_json` 字段,`{key,labels:{locale:str}}` | 实施量最大、最灵活 |

## 后果

### 正面
- 任意扩展 locale(在常量里加,改编辑器文案即可)
- 编辑 boolean/select/multiselect 时多语言可同时输入
- 实体编辑页按当前 store locale 自动切换显示
- 旧 `options_csv` 保留,迁移期读取优先 `options_json`,缺失回退 CSV

### 负面
- 改动跨 6 个文件(`Domain` / `Model` / `Service` / JS / 2 个模板)
- 数据库新增列,需 `upgrade-1.0.19-1.0.20.php` 升级脚本
- 行编辑器 UI 复杂度提升(从 2 列到 5 列:key + default + zh_CN + en_US + del)
- `options_csv` 字段不再主动写入,但保留读路径(向后兼容)

### 兼容
- 老 `options_csv` 数据:自动迁移到 `options_json`(`label` 写入 `labels.default`)
- 老 `default_value` 字段无变化
- 旧 strict_editor.phtml 不消费 `options_json` 的,保留 fallback `options_csv`

## 实施范围

| 文件 | 改动 |
|---|---|
| `app/code/community/XFE/Carrier/Domain/CustomAttribute.php` | + ALLOWED_LOCALES / + optionsJson 字段 / + getLocalizedOptions / + getLocalizedBooleanLabels / + getLocalizedLabelFor |
| `app/code/community/XFE/Carrier/Model/CustomAttribute.php` | getDomain() 优先 options_json,回退 options_csv |
| `app/code/community/XFE/Carrier/Model/Service/CustomAttributeService.php` | _detectBooleanMigration 兼容 options_json;持久化写 options_json |
| `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/upgrade-1.0.19-1.0.20.php` | 新增 options_json 列 |
| `app/design/.../custom_attribute/edit/form/type_switcher.phtml` | 注入 XfeCaEditorLabels.locales;行编辑器对接新结构 |
| `app/design/.../custom_attribute/strict_editor.phtml` | 注入当前 store locale;传 localizedOptions 给 renderValueCell |
| `js/xfe_carrier/custom-attribute-form-switcher.js` | + mountOptionsEditor 改写:row 多 locale tab / + serializeOptionsJson / + parseOptionsJson / + JSON 隐藏字段 |
| `etc/config.xml` | version 1.0.19 -> 1.0.20 |
| `tests/js/run-tests.js` | + locale tab 序列化/反序列化测试 + JS 行编辑器 mount |
| `tests/php/run-tests.php` | + Domain::getLocalizedOptions 单测 |
| `tests/browser/_ca-form-test.html` | + locale 切换测试页 |

## 验证基线

预期:`tests/js/run-tests.js` 166 → 175+ PASS,`tests/php/run-tests.php` 452 →
465+ PASS,真浏览器 `tests/browser/diagnose.html` 新增 locale 切换 PASS。
