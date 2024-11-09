<?php

namespace CuttingStock;

use Exception;

class CuttingStockOptimizer
{
    /** @var array<string, Product> */
    protected array $products = [];
    
    /** @var array<string, int> */
    protected array $remaining = [];
    
    /** @var array<Material> */
    protected array $materials = [];
    
    /** @var array<string, array> */
    protected array $validPositionsCache = [];
    
    /** @var array<string, float> */
    protected array $scoreCache = [];

    public function __construct(array $products)
    {
        foreach ($products as $i => $productData) {
            list($holes, $leftMargin, $rightMargin, $quantity) = $productData;
            $product = new Product(
                $holes,
                $leftMargin,
                $rightMargin,
                $quantity,
                (string)$i
            );
            $this->products[$product->getId()] = $product;
            $this->remaining[$product->getId()] = $product->getQuantity();
        }

        $this->materials[] = new Material();
    }

    /**
     * 找出产品所有可能的切割位置，完全匹配 Python 版本的逻辑
     * 
     * @return array<array{0: float, 1: float, 2: array}>
     */
    protected function findValidPositions(Product $product): array
    {
        $cacheKey = $product->getId();

        if (isset($this->validPositionsCache[$cacheKey])) {
            return $this->validPositionsCache[$cacheKey];
        }

        $validPositions = [];
        $holesNeeded = $product->getHolesCount();

        // 从左到右尝试所有可能的起始孔位
        for ($i = 0; $i <= count(Config::HOLES) - $holesNeeded; $i++) {
            $selectedHoles = array_slice(Config::HOLES, $i, $holesNeeded);
            $startPos = reset($selectedHoles) - $product->getLeftMargin();
            $endPos = end($selectedHoles) + $product->getRightMargin();

            // 检查是否在当前材料上可切割
            $currentMaterial = end($this->materials);
            if ($currentMaterial->canCut($startPos, $endPos)) {
                $validPositions[] = [$startPos, $endPos, $selectedHoles];
            }
        }

        $this->validPositionsCache[$cacheKey] = $validPositions;
        return $validPositions;
    }

    /**
     * 评估切割位置的得分，对齐 Python 版本的评分标准
     */
    protected function evaluateCutPosition(float $startPos, float $endPos, array $holes, Material $material): float
    {
        $score = 0;
        $length = $endPos - $startPos;

        // 1. 材料利用率评分优化
        $usedLength = array_reduce($material->getUsedSections(), function ($sum, $section) {
            list($start, $end) = $section;
            return $sum + ($end - $start);
        }, 0);
        $materialUtilization = ($usedLength + $length) / Config::STOCK_LENGTH;
        $score += $materialUtilization * 20;

        // 2. 切割连续性评分优化
        foreach ($material->getUsedSections() as $section) {
            list($usedStart, $usedEnd) = $section;
            if (abs($startPos - $usedEnd) < 0.1) {
                $score += 8;
            }
            if (abs($endPos - $usedStart) < 0.1) {
                $score += 8;
            }
        }

        // 3. 边缘优化加强
        if ($startPos < 0.1) {
            $score += 5;
        }
        if (abs($endPos - Config::STOCK_LENGTH) < 0.1) {
            $score += 5;
        }

        // 4. 添加新的评分因素：孔位密度
        $holeDensity = count($holes) / $length;
        $score += $holeDensity * 10;

        return $score;
    }

    /**
     * 选择最优的产品切割顺序
     * 
     * @return array<array{id: string, score: float}>
     */
    protected function selectBestProductSequence(): array
    {
        $sequence = [];
        foreach ($this->remaining as $productId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $product = $this->products[$productId];
            $length = $product->calculateLength();
            $holesDensity = $product->getHolesCount() / $length;
            $marginRatio = ($product->getLeftMargin() + $product->getRightMargin()) / $length;

            $score = (
                $length * 2.5 +
                $holesDensity * 15 +
                (1 - $marginRatio) * 8 +
                $quantity * 4 +
                ($length / Config::STOCK_LENGTH) * 10
            );

            $sequence[] = [
                'id' => $productId,
                'score' => $score
            ];
        }

        usort($sequence, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $sequence;
    }

    /**
     * 检查是否还有未切割的产品
     */
    protected function hasRemainingProducts(): bool
    {
        foreach ($this->remaining as $quantity) {
            if ($quantity > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 分析优化结果
     * 
     * @return array{theoretical_min_materials: float, actual_materials: int, utilization_rate: float}
     */
    public function analyzeResult(): array
    {
        $totalProductLength = 0;
        foreach ($this->products as $product) {
            $totalProductLength += $product->calculateLength() * $product->getQuantity();
        }

        $totalMaterialLength = count($this->materials) * Config::STOCK_LENGTH;
        $totalUsedLength = 0;

        foreach ($this->materials as $material) {
            foreach ($material->getUsedSections() as $section) {
                list($start, $end) = $section;
                $totalUsedLength += ($end - $start);
            }
        }

        return [
            'theoretical_min_materials' => ceil($totalProductLength / Config::STOCK_LENGTH),
            'actual_materials' => count($this->materials),
            'utilization_rate' => ($totalUsedLength / $totalMaterialLength) * 100
        ];
    }

    /**
     * 带可视化的优化过程
     *
     * @throws Exception
     */
    public function optimizeWithVisualization(): void
    {
        $step = 1;

        while ($this->hasRemainingProducts()) {
            echo "\n步骤 $step:\n";
            echo "当前剩余需求:\n";

            foreach ($this->remaining as $productId => $quantity) {
                if ($quantity > 0) {
                    $product = $this->products[$productId];
                    printf(
                        "  产品%s: %d个 (%d孔, 边距%.1f/%.1f)\n",
                        $productId,
                        $quantity,
                        $product->getHolesCount(),
                        $product->getLeftMargin(),
                        $product->getRightMargin()
                    );
                }
            }

            $bestProducts = $this->selectBestProductSequence();
            $cutMade = false;
            $bestCut = null;
            $bestScore = PHP_FLOAT_MIN;
            $currentMaterial = end($this->materials);

            foreach ($bestProducts as $productInfo) {
                $productId = $productInfo['id'];
                $product = $this->products[$productId];
                $validPositions = $this->findValidPositions($product);

                foreach ($validPositions as $position) {
                    list($startPos, $endPos, $selectedHoles) = $position;

                    if (!$currentMaterial->canCut($startPos, $endPos)) {
                        continue;
                    }

                    $score = $this->evaluateCutPosition(
                        $startPos,
                        $endPos,
                        $selectedHoles,
                        $currentMaterial
                    );

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestCut = [$productId, $startPos, $endPos, $selectedHoles];
                    }
                }
            }

            if ($bestCut !== null) {
                list($productId, $startPos, $endPos) = $bestCut;
                $currentMaterial->cut($startPos, $endPos, $productId);
                $this->remaining[$productId]--;
                $cutMade = true;

                printf(
                    "\n执行切割: 产品%s (%.1f - %.1f)\n",
                    $productId,
                    $startPos,
                    $endPos
                );
            }

            if (!$cutMade) {
                echo "\n开始使用新材料\n";
                $this->materials[] = new Material();
                $this->validPositionsCache = [];
                $this->scoreCache = [];
            }

            $step++;
        }
    }

    /**
     * 生成切割方案的可视化图像
     * 
     * @param string $filename 输出文件名
     * @return bool
     * @throws Exception
     */
    public function visualizeCuttingPlan(string $filename = 'cutting_plan.png'): bool
    {
        // 增加图像尺寸和边距
        $width = 1800;  // 加宽
        $height = (int)(count($this->materials) * 200 + 250);  // 增加每个材料的高度和总高度
        $margin = 100;  // 增加边距

        // 设置字体路径
        $fontRegular = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Regular.otf';
        $fontBold = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Bold.otf';

        // 检查字体文件是否存在
        if (!file_exists($fontRegular) || !file_exists($fontBold)) {
            throw new Exception("字体文件未找到");
        }

        // 创建图像
        $image = imagecreatetruecolor($width, $height);

        // 设置颜色
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $lightGray = imagecolorallocate($image, 230, 230, 230);
        $transparentGray = imagecolorallocatealpha($image, 128, 128, 128, 80);
        $colors = [
            imagecolorallocate($image, 255, 153, 153), // 红色
            imagecolorallocate($image, 153, 255, 153), // 绿色
            imagecolorallocate($image, 153, 153, 255), // 蓝色
            imagecolorallocate($image, 255, 255, 153), // 黄色
            imagecolorallocate($image, 255, 153, 255), // 紫色
            imagecolorallocate($image, 153, 255, 255), // 青色
        ];

        // 填充白色背景
        imagefill($image, 0, 0, $white);

        // 绘制标题
        $title = "切割方案可视化图";
        imagettftext(
            $image,
            28,          // 更大的字体
            0,
            $width / 2 - 150,
            70,          // 向下移动标题
            $black,
            $fontBold,
            $title
        );

        // 计算比例尺
        $scale = ($width - 2 * $margin) / Config::STOCK_LENGTH;

        // 绘制每根材料
        foreach ($this->materials as $i => $material) {
            // 计垂直位置
            $y = (int)round($margin + 50 + $i * 180);  // 增加垂直间距

            // 添加材料标签（左侧）
            imagettftext(
                $image,
                16,
                0,
                20,
                $y + 40,
                $black,
                $fontBold,
                sprintf("材料 %d", $i + 1)
            );

            // 绘制材料底色
            imagefilledrectangle(
                $image,
                $margin,
                $y,
                $width - $margin,
                $y + 100,
                $lightGray
            );

            // 绘制标尺和刻度
            for ($x = 0; $x <= Config::STOCK_LENGTH; $x += 10) {
                $xPos = (int)round($margin + $x * $scale);
                imageline($image, $xPos, $y - 5, $xPos, $y, $black);
                imagettftext(
                    $image,
                    10,
                    0,
                    $xPos - 15,
                    $y - 10,
                    $black,
                    $fontRegular,
                    (string)$x
                );
            }

            // 绘制孔位
            foreach (Config::HOLES as $hole) {
                $holeX = (int)round($margin + $hole * $scale);
                $holeY = (int)round($y + 50);
                imageline($image, $holeX, $y, $holeX, $y + 100, $black);
                imagefilledellipse($image, $holeX, $holeY, 8, 8, $black);

                // 添加孔位标记
                imagettftext(
                    $image,
                    10,
                    0,
                    $holeX - 10,
                    $y + 120,
                    $black,
                    $fontRegular,
                    (string)$hole
                );
            }

            // 绘制切割区域
            foreach ($material->getUsedSections() as $section) {
                list($start, $end, $productId) = $section;
                $xStart = (int)round($margin + $start * $scale);
                $xEnd = (int)round($margin + $end * $scale);
                $sectionWidth = $xEnd - $xStart;

                // 绘制切割区域底色
                imagefilledrectangle(
                    $image,
                    $xStart,
                    $y,
                    $xEnd,
                    $y + 100,
                    $colors[$productId % count($colors)]
                );

                $product = $this->products[$productId];

                // 获取产品的孔位
                $productHoles = array_slice(Config::HOLES, 0, $product->getHolesCount());
                foreach ($productHoles as $hole) {
                    if ($hole >= $start && $hole <= $end) {
                        $holeX = (int)round($margin + $hole * $scale);
                        $holeY = (int)round($y + 50);
                        // 绘制产品孔位（用白色圆圈突出显示）
                        imagefilledellipse($image, $holeX, $holeY, 12, 12, $white);
                        imageellipse($image, $holeX, $holeY, 12, 12, $black);
                        imagefilledellipse($image, $holeX, $holeY, 6, 6, $black);
                    }
                }

                // 边距标识优化（使用半透明效果）
                $leftMarginX = (int)round($xStart + ($product->getLeftMargin() * $scale));
                $rightMarginX = (int)round($xEnd - ($product->getRightMargin() * $scale));

                // 绘制边距区域
                imagefilledrectangle($image, $xStart, $y, $leftMarginX, $y + 100, $transparentGray);
                imagefilledrectangle($image, $rightMarginX, $y, $xEnd, $y + 100, $transparentGray);

                // 优化产品标签显示
                $label = sprintf(
                    "产品 %d\n长度: %.1fm\n孔数: %d\n边距: %.1f/%.1fm",
                    $productId + 1,
                    $end - $start,
                    $product->getHolesCount(),
                    $product->getLeftMargin(),
                    $product->getRightMargin()
                );

                // 计算文字位置，确保在切割区域内居中
                $labelBox = imagettfbbox(12, 0, $fontRegular, $label);
                $labelWidth = $labelBox[2] - $labelBox[0];
                $labelX = (int)round($xStart + ($sectionWidth - $labelWidth) / 2);

                imagettftext(
                    $image,
                    12,
                    0,
                    $labelX,
                    $y + 40,
                    $black,
                    $fontRegular,
                    $label
                );
            }
        }

        // 添加图例说明
        $legendY = (int)round($height - 120);
        $legendX = $margin;
        $legendSpacing = 200;

        // 绘制图例标题
        imagettftext($image, 14, 0, $legendX, $legendY, $black, $fontBold, "图例说明:");

        // 绘制孔位示例
        $holeX = (int)round($legendX + 20);
        $holeY = (int)round($legendY + 30);
        imagefilledellipse($image, $holeX, $holeY, 12, 12, $white);
        imageellipse($image, $holeX, $holeY, 12, 12, $black);
        imagefilledellipse($image, $holeX, $holeY, 6, 6, $black);
        imagettftext($image, 12, 0, $holeX + 20, $holeY + 5, $black, $fontRegular, "产品孔位");

        // 绘制边距示例
        $marginX = (int)round($legendX + $legendSpacing);
        imagefilledrectangle(
            $image,
            $marginX,
            (int)round($legendY + 20),
            (int)round($marginX + 40),
            (int)round($legendY + 40),
            $transparentGray
        );
        imagettftext($image, 12, 0, $marginX + 50, $legendY + 35, $black, $fontRegular, "边距区域");

        // 添加统计信息
        $analysis = $this->analyzeResult();
        $info = sprintf(
            "总利用率: %.1f%% | 材料数量: %d根 | 理论最小材料数: %.1f根",
            $analysis['utilization_rate'],
            $analysis['actual_materials'],
            $analysis['theoretical_min_materials']
        );
        imagettftext(
            $image,
            14,
            0,
            $margin,
            $height - 30,
            $black,
            $fontBold,
            $info
        );

        // 保存图像
        imagepng($image, $filename);
        imagedestroy($image);

        return true;
    }
}

