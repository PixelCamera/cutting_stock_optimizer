<?php
namespace CuttingStock\Test;

require_once __DIR__ . '/../vendor/autoload.php';

use CuttingStock\CuttingStockOptimizer;
use Exception;

class CuttingStockTest {
    /**
     * 运行切割优化测试
     * @throws Exception
     */
    public function run(): void 
    {
        // 定义产品需求
        $products = [
            // [孔数, 左边距, 右边距, 数量]
            [1, 2, 2, 15],    // P1: 简单的单孔产品
            [2, 3, 3, 25],    // P2: 标准双孔产品
            [3, 4, 4, 10],    // P3: 三孔产品
            [4, 5, 5, 8],     // P4: 四孔产品
            [2, 6, 4, 12],    // P5: 不对称边距的双孔产品
            [3, 3, 6, 18],    // P6: 不对称边距的三孔产品
            [1, 4, 4, 30],    // P7: 大边距单孔产品，大量需求
            [4, 3, 3, 5],     // P8: 紧凑型四孔产品
            [2, 5, 5, 20],    // P9: 大边距双孔产品
            [3, 2, 2, 15],    // P10: 紧凑型三孔产品
        ];

        try {
            // 打印测试信息
            echo "开始切割优化测试\n";
            echo "产品需求:\n";
            foreach ($products as $i => $product) {
                list($holes, $left, $right, $quantity) = $product;
                echo sprintf("产品P%d: %d孔, 左右边距%.1f/%.1f, 数量%d\n",
                    $i + 1, $holes, $left, $right, $quantity
                );
            }

            echo "\n" . str_repeat("=", 80) . "\n\n";

            // 创建优化器实例并运行
            $optimizer = new CuttingStockOptimizer($products);

            // 运行优化并显示过程
            $optimizer->optimizeWithVisualization();

            // 生成可视化图片
            echo "\n生成可视化图片...\n";
            $outputPath = __DIR__ . '/cutting_plan.png';
            $optimizer->visualizeCuttingPlan($outputPath);
            echo "可视化图片已保存到: $outputPath\n";

            // 获取并显示分析结果
            $analysis = $optimizer->analyzeResult();
            echo "\n优化结果分析:\n";
            echo sprintf("理论最小材料数: %.2f根\n", $analysis['theoretical_min_materials']);
            echo sprintf("实际使用材料数: %d根\n", $analysis['actual_materials']);
            echo sprintf("总体材料利用率: %.1f%%\n", $analysis['utilization_rate']);
        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}

// 运行测试
try {
    $test = new CuttingStockTest();
    $test->run();
} catch (Exception $e) {
    echo "测试执行失败: " . $e->getMessage() . "\n";
    exit(1);
}