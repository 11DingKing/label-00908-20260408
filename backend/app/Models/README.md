# App\Models 别名类说明

## 架构设计

本项目采用模块化架构，核心业务逻辑位于 `App\Modules` 目录下：

- `App\Modules\Billing` - 计费模块
- `App\Modules\Payment` - 支付模块  
- `App\Modules\Subscription` - 订阅模块

## 为什么保留 App\Models？

`App\Models` 目录下的类是**别名类**（Alias Classes），它们继承自对应的模块类。保留这些别名类的原因：

### 1. Laravel Factory 系统兼容性
Laravel 的 Factory 系统默认在 `Database\Factories` 命名空间下查找工厂类，并期望模型位于 `App\Models` 命名空间。别名类确保 Factory 能正常工作。

### 2. IDE 自动发现
许多 IDE 和代码分析工具默认扫描 `App\Models` 目录，别名类提供更好的开发体验。

### 3. 向后兼容
如果未来需要将模块拆分为独立包，别名类可以作为兼容层，减少迁移成本。

## 使用规范

- **新代码**：建议直接使用模块内的类（如 `App\Modules\Billing\Models\Bill`）
- **测试代码**：可以使用别名类（如 `App\Models\Bill`）以简化 Factory 调用
- **路由/控制器**：已配置使用模块内的控制器

## 类映射关系

| 别名类 | 实际实现 |
|--------|----------|
| `App\Models\Bill` | `App\Modules\Billing\Models\Bill` |
| `App\Models\Payment` | `App\Modules\Payment\Models\Payment` |
| `App\Models\Subscription` | `App\Modules\Subscription\Models\Subscription` |
| ... | ... |

## 注意事项

- 别名类**不应包含任何业务逻辑**，仅作为继承桥梁
- 所有业务逻辑修改应在 `App\Modules` 下的原始类中进行
