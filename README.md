# 切割库存优化系统

一个专业的材料切割优化工具，用于计算最优切割方案并生成可视化图纸。支持多种切割工具（普通刀片和线切割），自动优化材料利用率。

## 功能特点

- 支持多种切割工具（普通刀片3mm和线切割0.3mm）
- 多规格产品混合切割优化
- 自动计算最佳切割顺序和位置
- 考虑孔位、边距等实际加工要求
- 生成直观的切割方案图纸
- 提供详细的材料利用率分析
- 支持批量生产优化

## 系统要求

- PHP >= 8.0
- GD 扩展
- Composer
- 中文字体支持

## 快速开始

### 1. 安装

```bash
# 克隆项目
git clone https://github.com/yourusername/cutting-stock-optimizer.git

# 安装依赖
composer install

# 确保字体文件存在
resources/fonts/SourceHanSansHWSC-Bold.otf
resources/fonts/SourceHanSansHWSC-Regular.otf
```

### 2. 基本使用

```php
// 定义产品需求
$products = [
    // [孔数, 左边距, 右边距, 数量]
    [8, 38, 38, 2],    // 产品1: 8孔，左右边距38mm，数量2
    [7, 20, 36, 9],    // 产品2: 7孔，左边距20mm，右边距36mm，数量9
    [3, 20, 20, 2],    // 产品3: 3孔，左右边距20mm，数量2
];

// 创建优化器实例
$optimizer = new CuttingStockOptimizer($products);

// 运行优化并显示过程
$optimizer->optimizeWithVisualization();

// 生成可视化图纸
$optimizer->visualizeCuttingPlan('cutting_plan.png');

// 获取分析结果
$analysis = $optimizer->analyzeResult();
```

## 配置说明

### 基础配置 (Config.php)

```php
class Config {
    // 材料总长度(mm)
    public const float STOCK_LENGTH = 4000.0;
    
    // 孔位间隔(mm)
    public const float HOLE_SPACING = 80.0;
    
    // 切割工具配置
    public const array CUTTING_TOOLS = [
        'normal_blade' => 3.0,  // 普通刀片宽度
        'wire' => 0.3          // 线切割线径
    ];
}
```

## 优化算法

### 评分机制

1. 材料利用率评分（20分）
   - 考虑当前切割后的整体材料利用率
   - 优先选择能提高整体利用率的切割方案

2. 切割连续性（8分/次）
   - 与已切割区域的衔接程度
   - 减少材料碎片化

3. 边缘优化（5分/次）
   - 优先利用材料边缘位置
   - 减少边角料浪费

4. 孔位密度（10分系数）
   - 考虑单位长度内的孔位数量
   - 优化加工效率

### 产品选择策略

1. 产品长度权重（2.5）
2. 孔位密度权重（15）
3. 边距比例权重（8）
4. 剩余数量权重（4）
5. 材料匹配度权重（10）

## 项目结构

```
project/
├── src/
│   ├── Config.php                # 配置类
│   ├── CuttingStockOptimizer.php # 主优化器类
│   ├── Material.php              # 材料类
│   └── Product.php               # 产品类
├── test/
│   └── CuttingStockTest.php      # 测试用例
├── resources/
│   └── fonts/                    # 字体文件
└── vendor/                       # 依赖包
```

## 输出示例

优化结果将包含：
- 每根材料的切割方案
- 材料利用率分析
- 可视化图纸（PNG格式）
- 详细的切割步骤说明

### 分析结果格式

```php
$analysis = [
    'normal_blade' => [
        'theoretical_min_materials' => float,  // 理论最小材料数量
        'actual_materials' => int,             // 实际使用材料数量
        'utilization_rate' => float            // 材料利用率(%)
    ],
    'wire' => [
        // 同上
    ]
];
```

## 测试

运行测试用例：
```bash
php test/CuttingStockTest.php
```