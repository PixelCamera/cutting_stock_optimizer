<?php

namespace CuttingStock\Test;

require_once __DIR__ . '/../vendor/autoload.php';

use CuttingStock\CuttingStockOptimizer;
use Exception;

/**
 * 切割优化测试类
 * 用于测试切割优化算法的功能和性能
 *
 * @package CuttingStock\Test
 */
class CuttingStockTest
{
    /**
     * 运行切割优化测试
     * @throws Exception
     */
    public function run(): void
    {
        // 定义产品需求
        $products = [
            // [孔数, 左边距, 右边距, 数量]
            [8, 38, 38, 2],    // 8 孔 636
            [7, 20, 36, 9],    // 7 孔 536
            [3, 20, 20, 2],    // 3 孔 200
            [5, 20, 20, 2],    // 5 孔 200
            [8, 20, 20, 5],    // 8 孔 600
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

            foreach ($analysis as $toolType => $result) {
                $toolName = $toolType === 'normal_blade' ? "普通刀片(3mm)" : "线切割(0.3mm)";
                echo "\n{$toolName}:\n";
                echo sprintf("理论最小材料数: %.2f根\n", $result['theoretical_min_materials']);
                echo sprintf("实际使用材料数: %d根\n", $result['actual_materials']);
                echo sprintf("总体材料利用率: %.1f%%\n", $result['utilization_rate']);
            }
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