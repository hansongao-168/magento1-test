# XFE_Carrier 承运商管理模块设计文档

**日期**: 2026-07-16  
**状态**: 已确认  
**模块**: `XFE_Carrier`

---

## 1. 概述

在 Magento 1.9 后台开发一个承运商管理模块（CRUD），支持列表查看、新增、编辑、删除功能。模块位于 `app/code/community/XFE/Carrier/`，遵循现有 XFE 模块的标准目录约定。

## 2. 功能范围

- 承运商列表（后台 Grid），支持分页、状态筛选、名称/代码模糊搜索
- 新增承运商
- 编辑承运商（含 Logo 替换）
- 删除承运商（级联删除 Logo 文件和记录）
- Logo 上传，自动生成 3 种预设尺寸（small/medium/large）+ 原图
- `shipping_company_id` 可搜索下拉选择框（数据源由 Helper 接口提供）

## 3. 数据模型

### 3.1 承运商主表 `xfe_carrier`

| 字段 | 类型 | 说明 |
|---|---|---|
| `entity_id` | INT UNSIGNED AUTO_INCREMENT PK | 主键 |
| `name` | VARCHAR(255) NOT NULL | 承运商名称 |
| `code` | VARCHAR(64) NOT NULL | 承运商标识/代码（非唯一） |
| `shipping_company_id` | INT UNSIGNED DEFAULT NULL | 线路公司ID（Helper 数据源） |
| `status` | TINYINT(1) NOT NULL DEFAULT 1 | 状态：1启用/0禁用 |
| `sort_order` | INT NOT NULL DEFAULT 0 | 排序（越小越靠前） |
| `note` | TEXT DEFAULT NULL | 备注 |
| `created_at` | DATETIME DEFAULT NULL | 创建时间 |
| `updated_at` | DATETIME DEFAULT NULL | 更新时间 |

索引：`IDX_XFE_CARRIER_SHIPPING_COMPANY`、`IDX_XFE_CARRIER_STATUS`

### 3.2 Logo 子表 `xfe_carrier_logo`

| 字段 | 类型 | 说明 |
|---|---|---|
| `logo_id` | INT UNSIGNED AUTO_INCREMENT PK | 主键 |
| `carrier_id` | INT UNSIGNED NOT NULL | 关联承运商ID |
| `size_type` | VARCHAR(20) NOT NULL DEFAULT 'original' | 尺寸标识：original/small/medium/large |
| `path` | VARCHAR(255) NOT NULL | 文件相对路径（基于 media 目录） |
| `width` | SMALLINT UNSIGNED DEFAULT NULL | 图片宽度 |
| `height` | SMALLINT UNSIGNED DEFAULT NULL | 图片高度 |
| `created_at` | DATETIME DEFAULT NULL | 创建时间 |

唯一约束：`UNIQUE(carrier_id, size_type)`  
索引：`IDX_XFE_CARRIER_LOGO_CARRIER`

Logo 存储路径：`media/xfe/carrier/logo/{carrier_id}/`

### 3.3 Logo 尺寸规格

| size_type | 尺寸 | 策略 |
|---|---|---|
| `original` | 原图 | 保留原始上传文件 |
| `small` | 100×100 | 等比缩放居中裁剪 |
| `medium` | 200×200 | 等比缩放居中裁剪 |
| `large` | 400×400 | 等比缩放居中裁剪 |

## 4. 文件结构

```
app/code/community/XFE/Carrier/
├── Block/
│   └── Adminhtml/
│       ├── Carrier.php                                    # Grid 容器 Block
│       ├── Carrier/Grid.php                               # 列表 Grid
│       └── Carrier/Edit/
│           ├── Form.php                                   # 编辑表单
│           └── Form/Element/
│               └── ShippingCompany.php                    # 可搜索选择框 Element
├── Helper/
│   └── Data.php                                          # 数据 Helper（线路数据源接口）
├── Model/
│   ├── Carrier.php                                       # 承运商 Model
│   ├── Logo.php                                          # Logo Model
│   ├── Resource/
│   │   ├── Carrier.php                                   # Resource Model
│   │   ├── Carrier/Collection.php                        # Collection
│   │   ├── Logo.php                                      # Logo Resource
│   │   └── Logo/Collection.php                           # Logo Collection
│   └── Source/
│       └── Status.php                                    # 状态下拉选项源
├── controllers/
│   └── Adminhtml/
│       └── CarrierController.php                         # 后台控制器
├── etc/
│   ├── config.xml                                        # 模块配置、路由、安装脚本
│   └── adminhtml.xml                                     # 后台菜单 + ACL
└── sql/
    └── xfe_carrier_setup/
        └── install-1.0.0.php                             # 建表脚本
```

```
app/etc/modules/
└── XFE_Carrier.xml                                       # 模块激活声明
```

## 5. 核心功能设计

### 5.1 Logo 上传与多尺寸处理

1. 表单使用 Magento 原生 `file` 类型 Element，限制 MIME 类型（JPG/PNG/GIF/SVG）
2. Carrier Model `_afterSave()` 中处理上传：
   - 移动到 `media/xfe/carrier/logo/{carrier_id}/`
   - 保存原图到 `xfe_carrier_logo`（size_type=original）
   - GD 库裁剪生成 small/medium/large，分别入库
   - 替换 Logo 时先删除旧文件和旧记录
3. Logo Model 提供便捷方法：`$carrier->getLogoUrl('medium')`

### 5.2 shipping_company_id 可搜索选择框

1. 自定义 Form Element `ShippingCompany`，继承 `Varien_Data_Form_Element_Abstract`
2. AJAX 请求 `CarrierController::shippingCompanyAction()`，参数 `q`
3. Controller 调用 `Mage::helper('xfe_carrier')->getShippingCompanyOptions($q)` 返回 JSON `[{id, name}]`
4. 前端使用 jQuery + Select2 实现可搜索下拉

### 5.3 Helper 数据源接口

`XFE_Carrier_Helper_Data`：

```php
public function getShippingCompanyOptions($searchKeyword = null)
```

默认实现返回空数组（占位），实际数据由对接模块覆盖或通过事件注入。

### 5.4 后台菜单与权限

- 菜单：`System → XFE → 承运商管理`
- ACL 资源：`admin/xfe/carrier`
- 仅具备权限的管理员角色可见

### 5.5 Grid 列表

- 列：名称、标识代码、线路公司名称、状态（启用/禁用）、排序、操作（编辑/删除）
- 默认排序：`sort_order ASC, entity_id DESC`
- 支持状态筛选、名称/代码模糊搜索
- Logo 仅在编辑表单中展示，Grid 中不显示

## 6. 错误处理与边缘场景

| 场景 | 处理方式 |
|---|---|
| Logo 上传失败 | `Mage::logException()` + 用户提示 "Logo 上传失败，请重试" |
| Logo 格式不正确 | 表单 MIME 限制 + GD 二次校验 |
| Logo 裁剪失败 | 降级：保留原图，其他尺寸留空，不阻塞保存 |
| 删除承运商 | 级联删除 Logo 子表 + `unlink()` 文件（失败仅日志记录） |
| shipping_company_id 数据源为空 | 下拉框正常显示空状态，不阻塞提交 |
| 删除已无文件的 Logo | `file_exists()` 检查，不抛异常 |
| Logo 目录不存在 | 自动创建 `media/xfe/carrier/logo/{id}/` |

## 7. 技术栈与依赖

- **平台**: Magento 1.9（Community Edition）
- **语言**: PHP 5.4+
- **图像处理**: GD Library
- **依赖模块**: `Mage_Core`、`Mage_Adminhtml`
- **Code Pool**: `community`
- **命名空间**: `XFE`
