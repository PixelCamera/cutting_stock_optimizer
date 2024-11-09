# Cutting Stock Optimizer

材料切割优化工具，用于计算最优切割方案并生成可视化图纸。

---
## 项目说明

### 主要功能
- 多规格产品混合切割优化
- 自动计算最佳切割顺序和位置
- 考虑孔位、边距等实际加工要求
- 生成直观的切割方案图纸
- 提供材料利用率分析

### 技术栈
- PHP 8.0+
- GD 图形库
- Composer 依赖管理
- PHPUnit 测试框架

### 核心算法
1. 评分机制：
   - 材料利用率 (20分)：当前材料的总体利用率
   - 切割连续性 (8分/次)：与已切割区域的衔接程度
   - 边缘优化 (5分/次)：是否紧贴材料边缘
   - 孔位密度 (10分系数)：单位长度内的孔位数量

2. 优化策略：
   - 产品长度 (length * 2.5)：优先切割较长的产品
   - 孔位密度 (holesDensity * 15)：优先切割孔位密度大的产品
   - 边距比 ((1 - marginRatio) * 8)：优先切割边距较小的产品
   - 剩余数量 (quantity * 4)：优先切割数量多的产品
   - 材料利用率 ((length / STOCK_LENGTH) * 10)：优先切割更符合材料长度的产品

---

## 环境��署

### 系统要求
- PHP >= 8.0
- GD 扩展
- Composer
- 中文字体支持

### 安装步骤
1. 配置 PHP 环境（确保 GD 扩展已启用）
2. 执行依赖安装：
   ```bash
   composer install
   ```
3. 确认字体文件存在：
   - resources/fonts/SourceHanSansHWSC-Bold.otf
   - resources/fonts/SourceHanSansHWSC-Regular.otf

---

## 项目结构

```
project/
├── src/
│   ├── Config.php              # 配置类
│   ├── CuttingStockOptimizer.php   # 主优化器类
│   ├── Material.php            # 材料类
│   └── Product.php             # 产品类
├── test/
│   └── CuttingStockTest.php    # 测试用例
├── resources/
│   └── fonts/                  # 字体文件
└── vendor/                     # 依赖包
```

---

## 配置说明

### 基础配置 (Config.php)
```php
class Config {
    /** @var float 材料总长度 */
    public const float STOCK_LENGTH = 96.0;
    
    /** @var float 孔位间隔 */
    public const float HOLE_SPACING = 8.0;
    
    /** @var array<int> 可用孔位坐标列表 */
    public const array HOLES = [4, 12, 20, 28, 36, 44, 52, 60, 68, 76, 84, 92];
}
```

### 材料类说明 (Material.php)
```php
class Material {
    /** @var float 材料总长度 */
    protected float $length;

    /** @var array 已使用的切割区间 [[start, end, productId], ...] */
    protected array $usedSections;

    // 主要方法：
    public function canCut(float $start, float $end): bool;  // 检查指定区间是否可切割
    public function cut(float $start, float $end, int $productId): void;  // 执行切割操作
    public function getUsedSections(): array;  // 获取已使用的切割区间
    public function getUtilizationRate(): float;  // 计算材料利用率(0-1)
    public function getMaxRemainingSpace(): float;  // 获取最大剩余空间长度
    public function getUsedLength(): float;  // 获取已使用长度
    public function getMaxContinuousSpace(): float;  // 获取最大连续可用空间
    
    // 切割区间格式：
    // - start: 切割起始位置
    // - end: 切割结束位置
    // - productId: 产品ID
}
```

### 产品参数说明
```php
// 产品数组格式：[孔数, 左边距, 右边距, 数量]
$products = [
    [1, 2, 2, 15],    // 产品1: 1个孔, 左右边距各2, 需求15个
    [2, 3, 3, 25],    // 产品2: 2个孔, 左右边距各3, 需求25个
    [3, 4, 4, 10],    // 产品3: 3个孔, 左右边距各4, 需求10个
    // ...
];

// 参数说明：
// - 孔数：产品需要的孔位数量
// - 左边距：最左侧孔位到产品左端的距离
// - 右边距：最右侧孔位到产品右端的距离
// - 数量：该产品的需求数量
```

### 输出结果说明
```php
// 分析结果格式
$analysis = [
    'theoretical_min_materials' => float,  // 理论最小材料数量
    'actual_materials' => int,             // 实际使用材料数量
    'utilization_rate' => float            // 材料利用率(%)
];
```

---
## 使用示例

### 基本使用
```php
// 创建优化器实例
$optimizer = new CuttingStockOptimizer($products);

// 运行优化并显示过程
$optimizer->optimizeWithVisualization();

// 生成可视化图纸
$optimizer->visualizeCuttingPlan('cutting_plan.png');

// 获取分析结果
$analysis = $optimizer->analyzeResult();
```

### 测试用例
运行测试：`php test/CuttingStockTest.php`
