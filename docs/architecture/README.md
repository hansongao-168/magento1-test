# docs/architecture/ 索引

> 本目录承载各业务模块的架构文档，遵循根目录 [`AGENTS.md`](../../AGENTS.md) 的总章程。
> 命名规则：`<module>-architecture.md`、`<module>-api.md`、`<module>-events.md`。
> 不可逆决策必须落在 `decisions/NNNN-<slug>.md`（ADR）。

---

## 当前文档

| 文件 | 模块 | 类型 | 说明 |
|------|------|------|------|
| `carrier-facade.md` | `XFE_Carrier` | architecture | Carrier 模块 Facade 设计与调用关系(已废弃,见 carrier-facade-deprecation.md) |
| `carrier-facade-deprecation.md` | `XFE_Carrier` | architecture | Carrier Facade 废弃通知 + 迁移到 XML 注入的指南 |
| `carrier-observer-events.md` | `XFE_Carrier` | events | Carrier 模块领域事件契约清单 |
| `carrier-line-examples.md` | `XFE_Carrier` | examples | 线路（line）使用示例 |
| `carrier-rule-conditions.md` | `XFE_Carrier` | architecture | 承运商规则条件树持久化与加载 |
| `carrier-rule-import-export.md` | `XFE_Carrier` | architecture | 承运商规则批量导入导出 |
| `injection-architecture.md` | `XFE_Injection` | architecture | 公共模块：基于 XML 注入的跨模块调用机制 |
| `injection-api.md` | `XFE_Injection` | api | 公共模块对外契约（Runner / Registry / Domain） |
| `injection-examples.md` | `XFE_Injection` | examples | 使用示例（Carrier↔Logistic 解耦 / 教学 Demo） |
| `injection-onboarding.md` | `XFE_Injection` | examples | 新模块接入指南（Provider / Consumer 角色 + 5 步流程 + 完整 A→B 示例 + 反模式清单） |
| `injection-carrier-logistic-integration.md` | `XFE_Carrier` / `XFE_Logistic` | examples | Carrier↔Logistic 集成开发指南（最小化方案 + 验证清单 + 回滚方案） |
| `documentupload-injection-integration.md` | `XFE_DocumentUpload` / `XFE_Carrier` | examples | DocumentUpload 通过 XML 注入接入 Carrier（消除 accounts_json 独立小作坊） |
| `ci-php-integration.md` | `XFE_Injection` | examples | XFE_Injection PHP 测试 CI 集成开发指南（GitLab CI + GitHub Actions） |
| `decouple-podservice-main-path.md` | `XFE_Logistic` / `XFE_Carrier` | examples | PodService 主流路径接入 XML 注入（凭据单一来源） |
| `migrate-gls-api-credentials.md` | `XFE_Logistic` / `XFE_Carrier` | examples | GLS API 凭据迁移脚本（system config → xfe_carrier_account） |
| `finalize-podservice-injection.md` | `XFE_Logistic` / `XFE_Carrier` | examples | PodService 注入最终态（删除 fallback + Helper 空壳 + system config 下线） |
| `carrier-account-import-export.md` | `XFE_Carrier` | architecture | 承运商账号与 FTP账号批量导入导出 |
| `logistic-architecture.md` | `XFE_Logistic` | architecture | GLS 物流集成模块定位与分层 |
| `logistic-gls-api.md` | `XFE_Logistic` | api | GLS Web API 接口契约（Collect POD） |
| `logistic-gls-pod-diagnostics.md` | `XFE_Logistic` | diagnostics | Collect POD「无签收图片」排查报告（实测响应取证 `404 + Error: NO_POD_IMAGE_FOUND`，剩余 GLS 确认清单，暂无代码改动） |
| `oauth2-architecture.md` | `XFE_OAuth2` | architecture | OAuth2 Server 模块分层 + bshaffer 库 autoloader 契约 + 4 处 storage 类顶部 require_once 父接口铁律 |
| `oauth2-customer-navigation.md` | `XFE_OAuth2` | architecture | 客户账户页 layout handle 名纠正（`oauth2_*` → `xfeoauth2_*`） |

> 历史文件命名（`carrier-facade.md` 等）已稳定，新文档请统一使用 `<module>-architecture.md` 风格命名。
> 如需新增其他模块，请先在 `app/code/community/XFE/<Module>/` 下创建前，先在本目录添加：

```
docs/architecture/<module>-architecture.md   # 必填：模块定位、依赖、目录结构
docs/architecture/<module>-api.md            # 选填：对外 API 契约
docs/architecture/<module>-events.md         # 选填：dispatchEvent / observer 契约
```

并在下方表格中补一行。

---

## 待办（模块文档缺失）

| 模块（待补） | 应有文档 | 优先级 |
|-------------|---------|--------|
| （暂无） | | |

AI 助手在改动任何模块时，若发现该模块在 `app/code/community/XFE/` 下存在但本目录无对应文档，**必须**先补文档再改代码。