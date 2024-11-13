<?php

namespace CuttingStock;

use Exception;

/**
 * 切割库存优化器类
 */
class CuttingStockOptimizer
{
    /** @var array<string, Product> 产品列表 */
    protected array $products = [];
    
    /** @var array<string, int> 剩余待切割数量 */
    protected array $remaining = [];
    
    /** @var array<string, array<Material>> 材料列表 */
    protected array $materials = [];
    
    /** @var array<string, array> 有效切割位置缓存 */
    protected array $validPositionsCache = [];
    
    /** @var array<string, float> 评分缓存 */
    protected array $scoreCache = [];

    /** @var string 当前使用的切割工具类型 */
    protected string $currentTool = 'normal_blade';

    /**
     * 构造函数
     */
    public function __construct(array $products)
    {
        foreach ($products as $i => $productData) {
            list($holes, $leftMargin, $rightMargin, $quantity) = $productData;
            $product = new Product($holes, $leftMargin, $rightMargin, $quantity, (string)$i);
            $this->products[$product->getId()] = $product;
            $this->remaining[$product->getId()] = $product->getQuantity();
        }

        // 初始化材料列表
        foreach (Config::CUTTING_TOOLS as $toolType => $width) {
            $this->materials[$toolType] = [new Material($toolType)];
        }
    }

    /**
     * 执行优化并可视化
     */
    public function optimizeWithVisualization(): void
    {
        foreach (Config::CUTTING_TOOLS as $toolType => $width) {
            echo "\n使用切割工具: " . ($toolType === 'normal_blade' ? "普通刀片(3mm)" : "线切割(0.3mm)") . "\n";
            echo str_repeat("-", 80) . "\n";
            
            $this->currentTool = $toolType;
            $this->optimizeWithTool();
            
            // 重置缓存，为下一个工具做准备
            $this->validPositionsCache = [];
            $this->scoreCache = [];
        }
    }

    /**
     * 使用当前工具执行优化
     */
    protected function optimizeWithTool(): void
    {
        $step = 1;
        $remainingForTool = $this->remaining;

        while ($this->hasRemainingProducts($remainingForTool)) {
            echo "\n步骤 $step:\n";
            $this->printRemainingProducts($remainingForTool);

            $bestProducts = $this->selectBestProductSequence($remainingForTool);
            $cutMade = false;
            $bestCut = null;
            $bestScore = PHP_FLOAT_MIN;
            $currentMaterial = end($this->materials[$this->currentTool]);

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
                $remainingForTool[$productId]--;
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
                $this->materials[$this->currentTool][] = new Material($this->currentTool);
                $this->validPositionsCache = [];
                $this->scoreCache = [];
            }

            $step++;
        }
    }

    /**
     * 检查是否还有未切割的产品
     */
    protected function hasRemainingProducts(array $remaining): bool
    {
        foreach ($remaining as $quantity) {
            if ($quantity > 0) return true;
        }
        return false;
    }

    /**
     * 为产品找到最接近的孔位
     */
    protected function findNearestHolesForProduct(Product $product, float $targetStartPos): ?array
    {
        $holesNeeded = $product->getHolesCount();
        $leftMargin = $product->getLeftMargin();
        $rightMargin = $product->getRightMargin();
        
        // 找到最接近的起始孔位
        $targetFirstHole = $targetStartPos + $leftMargin;
        $nearestHoleIndex = $this->findNearestHoleIndex($targetFirstHole);
        
        if ($nearestHoleIndex === null || 
            $nearestHoleIndex + $holesNeeded > count(Config::HOLES)) {
            return null;
        }
        
        $selectedHoles = array_slice(Config::HOLES, $nearestHoleIndex, $holesNeeded);
        $startPos = $selectedHoles[0] - $leftMargin;
        $endPos = end($selectedHoles) + $rightMargin;
        
        if ($startPos < 0 || $endPos > Config::STOCK_LENGTH) {
            return null;
        }
        
        return [
            'startPos' => $startPos,
            'endPos' => $endPos,
            'holes' => $selectedHoles
        ];
    }

    /**
     * 找到最接近的孔位索引
     */
    protected function findNearestHoleIndex(float $targetPos): ?int
    {
        $minDistance = PHP_FLOAT_MAX;
        $nearestIndex = null;

        foreach (Config::HOLES as $index => $holePos) {
            $distance = abs($holePos - $targetPos);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestIndex = $index;
            }
        }

        return $nearestIndex;
    }

    /**
     * 分析优化结果，计算材料使用效率
     */
    public function analyzeResult(): array
    {
        $result = [];
        foreach (Config::CUTTING_TOOLS as $toolType => $width) {
            $totalProductLength = 0;
            foreach ($this->products as $product) {
                $totalProductLength += $product->calculateLength() * $product->getQuantity();
            }

            $totalMaterialLength = count($this->materials[$toolType]) * Config::STOCK_LENGTH;
            $totalUsedLength = 0;

            foreach ($this->materials[$toolType] as $material) {
                foreach ($material->getUsedSections() as $section) {
                    list($start, $end) = $section;
                    $totalUsedLength += ($end - $start);
                }
            }

            $result[$toolType] = [
                'theoretical_min_materials' => ceil($totalProductLength / Config::STOCK_LENGTH),
                'actual_materials' => count($this->materials[$toolType]),
                'utilization_rate' => ($totalUsedLength / $totalMaterialLength) * 100
            ];
        }

        return $result;
    }

    /**
     * 生成切割方案的可视化图像
     */
    public function visualizeCuttingPlan(string $filename = 'cutting_plan.png'): bool
    {
        // 计算图像尺寸 - 考虑两种切割工具的材料数量
        $totalMaterials = 0;
        foreach (Config::CUTTING_TOOLS as $toolType => $width) {
            $totalMaterials += count($this->materials[$toolType]);
        }
        
        $width = 2400;
        $height = (int)($totalMaterials * 200 + 350); // 增加高度以容纳两种工具的标题
        $margin = 100;

        // 设置字体路径
        $fontRegular = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Regular.otf';
        $fontBold = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Bold.otf';

        // 检查字体文件
        if (!file_exists($fontRegular) || !file_exists($fontBold)) {
            throw new Exception("字体文件未找到");
        }

        // 创建图像
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new Exception("创建图像失败");
        }

        // 设置颜色
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $lightGray = imagecolorallocate($image, 230, 230, 230);
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

        // 计算比例尺
        $scale = ($width - 2 * $margin) / Config::STOCK_LENGTH;

        // 绘制标题
        imagettftext($image, 28, 0, (int)($width / 2 - 100), 50, $black, $fontBold, "切割方案可视化图");

        $currentY = $margin;

        // 为每种切割工具绘制方案
        foreach (Config::CUTTING_TOOLS as $toolType => $toolWidth) {
            // 绘制工具类型标题
            $toolName = $toolType === 'normal_blade' ? "普通刀片(3mm)" : "线切割(0.3mm)";
            imagettftext($image, 20, 0, $margin, $currentY + 30, $black, $fontBold, $toolName);
            $currentY += 60;

            // 绘制该工具类型的所有材料
            foreach ($this->materials[$toolType] as $i => $material) {
                // 添加材料标签和利用率信息
                $materialLabel = sprintf(
                    "材料 %d (利用率: %.1f%%)", 
                    $i + 1,
                    $material->getUtilizationRate() * 100
                );
                imagettftext($image, 16, 0, $margin, $currentY - 10, $black, $fontBold, $materialLabel);

                // 绘制材料底色 - 使用图像宽度而不是工具宽度
                imagefilledrectangle(
                    $image, 
                    $margin, 
                    $currentY, 
                    $width - $margin,  // 使用图像宽度
                    $currentY + 100, 
                    $lightGray
                );

                // 先绘制所有孔位（灰色）
                foreach (Config::HOLES as $hole) {
                    $holeX = (int)round($margin + $hole * $scale);
                    // 绘制孔位标记
                    imagefilledellipse($image, $holeX, $currentY + 50, 8, 8, $black);
                    // 在孔位上方添加坐标标注
                    $holeLabel = sprintf("%.0f", $hole);
                    $holeLabelBox = imagettfbbox(8, 0, $fontRegular, $holeLabel);
                    $holeLabelX = $holeX - ($holeLabelBox[2] - $holeLabelBox[0]) / 2;
                    imagettftext(
                        $image,
                        8,
                        0,
                        (int)round($holeLabelX),
                        $currentY + 75,
                        $black,
                        $fontRegular,
                        $holeLabel
                    );
                }

                // 绘制刻度线（每500mm一个刻度）
                for ($pos = 0; $pos <= Config::STOCK_LENGTH; $pos += 500) {
                    $x = (int)round($margin + $pos * $scale);
                    // 绘制刻度线
                    imageline($image, $x, $currentY + 100, $x, $currentY + 110, $black);
                    // 添加刻度值
                    $scaleLabel = (string)$pos;
                    $scaleLabelBox = imagettfbbox(8, 0, $fontRegular, $scaleLabel);
                    $scaleLabelX = $x - ($scaleLabelBox[2] - $scaleLabelBox[0]) / 2;
                    imagettftext(
                        $image,
                        8,
                        0,
                        (int)round($scaleLabelX),
                        $currentY + 120,
                        $black,
                        $fontRegular,
                        $scaleLabel
                    );
                }

                // 绘制材料长度标注
                $lengthLabel = sprintf("总长: %.0fmm", Config::STOCK_LENGTH);
                imagettftext(
                    $image,
                    10,
                    0,
                    $width - $margin + 10,  // 使用图像宽度
                    $currentY + 50,
                    $black,
                    $fontRegular,
                    $lengthLabel
                );

                // 绘制切割区域
                foreach ($material->getUsedSections() as $section) {
                    list($start, $end, $productId) = $section;
                    $product = $this->products[$productId];

                    // 计算坐标
                    $xStart = (int)round($margin + $start * $scale);
                    $xEnd = (int)round($margin + $end * $scale);
                    $sectionWidth = $xEnd - $xStart;

                    // 绘制切割区域
                    $color = $colors[$productId % count($colors)];
                    imagefilledrectangle($image, $xStart, $currentY, $xEnd, $currentY + 100, $color);

                    // 计算区域是否足够宽以显示完整信息
                    $minWidthForFullInfo = 200; // 显示完整信息所需的最小宽度（像素）
                    
                    if ($sectionWidth >= $minWidthForFullInfo) {
                        // 区域较宽，显示完整信息
                        $productInfo = sprintf(
                            "P%d (%d孔 %.0fmm)",
                            $productId + 1,
                            $product->getHolesCount(),
                            $end - $start
                        );
                    } else {
                        // 区域较窄，只显示产品编号
                        $productInfo = sprintf("P%d", $productId + 1);
                    }
                    
                    // 计算文字位置，确保在切割区域内居中
                    $bbox = imagettfbbox(12, 0, $fontRegular, $productInfo);
                    $textWidth = $bbox[2] - $bbox[0];
                    $textX = $xStart + (($xEnd - $xStart) - $textWidth) / 2;
                    
                    // 绘制产品信息
                    imagettftext(
                        $image,
                        12,
                        0,
                        (int)round($textX),
                        $currentY + 30,
                        $black,
                        $fontBold,
                        $productInfo
                    );

                    // 如果区域较窄，在下方显示孔数信息
                    if ($sectionWidth < $minWidthForFullInfo) {
                        $holeInfo = sprintf("%d孔", $product->getHolesCount());
                        $bbox = imagettfbbox(10, 0, $fontRegular, $holeInfo);
                        $textWidth = $bbox[2] - $bbox[0];
                        $textX = $xStart + (($xEnd - $xStart) - $textWidth) / 2;
                        
                        imagettftext(
                            $image,
                            10,
                            0,
                            (int)round($textX),
                            $currentY + 45,
                            $black,
                            $fontRegular,
                            $holeInfo
                        );
                    }

                    // 在底部显示起始和结束位置（如果空间足够）
                    if ($sectionWidth >= $minWidthForFullInfo) {
                        $positionLabel = sprintf("%.0f-%.0f", $start, $end);
                    } else {
                        $positionLabel = sprintf("%.0f", $end - $start);
                    }
                    
                    $bbox = imagettfbbox(10, 0, $fontRegular, $positionLabel);
                    $textWidth = $bbox[2] - $bbox[0];
                    $textX = $xStart + (($xEnd - $xStart) - $textWidth) / 2;
                    
                    imagettftext(
                        $image,
                        10,
                        0,
                        (int)round($textX),
                        $currentY + 95,
                        $black,
                        $fontRegular,
                        $positionLabel
                    );

                    // 重新绘制该区域内的孔位（调整位置）
                    foreach (Config::HOLES as $hole) {
                        $holeX = (int)round($margin + $hole * $scale);
                        if ($holeX >= $xStart && $holeX <= $xEnd) {
                            // 绘制孔位标记
                            imagefilledellipse($image, $holeX, $currentY + 60, 8, 8, $black);
                            // 在孔位下方添加坐标标注
                            $holeLabel = sprintf("%.0f", $hole);
                            $holeLabelBox = imagettfbbox(8, 0, $fontRegular, $holeLabel);
                            $holeLabelX = $holeX - ($holeLabelBox[2] - $holeLabelBox[0]) / 2;
                            imagettftext(
                                $image,
                                8,
                                0,
                                (int)round($holeLabelX),
                                $currentY + 85,
                                $black,
                                $fontRegular,
                                $holeLabel
                            );
                        }
                    }

                    // 绘制边距标记和尺寸（调整位置）
                    $leftMarginX = $xStart + (int)round($product->getLeftMargin() * $scale);
                    $rightMarginX = $xEnd - (int)round($product->getRightMargin() * $scale);

                    // 绘制边距线
                    imageline($image, $xStart, $currentY + 40, $leftMarginX, $currentY + 40, $black);
                    imageline($image, $rightMarginX, $currentY + 40, $xEnd, $currentY + 40, $black);
                    
                    // 绘制垂直边距线
                    imageline($image, $leftMarginX, $currentY + 35, $leftMarginX, $currentY + 45, $black);
                    imageline($image, $rightMarginX, $currentY + 35, $rightMarginX, $currentY + 45, $black);

                    // 标注边距值（调整位置）
                    $leftMarginLabel = sprintf("%.1f", $product->getLeftMargin());
                    $rightMarginLabel = sprintf("%.1f", $product->getRightMargin());
                    
                    // 左边距标注
                    imagettftext(
                        $image, 
                        10, 
                        0, 
                        $xStart + 5, 
                        $currentY + 35, 
                        $black, 
                        $fontRegular, 
                        $leftMarginLabel
                    );
                    
                    // 右边距标注
                    $rightMarginBox = imagettfbbox(10, 0, $fontRegular, $rightMarginLabel);
                    imagettftext(
                        $image, 
                        10, 
                        0, 
                        $rightMarginX - ($rightMarginBox[2] - $rightMarginBox[0]) - 5, 
                        $currentY + 35, 
                        $black, 
                        $fontRegular, 
                        $rightMarginLabel
                    );
                }

                // 在材料底部添加已使用区域的汇总信息
                $usedSections = $material->getUsedSections();
                $usedLength = array_reduce($usedSections, function ($sum, $section) {
                    list($start, $end) = $section;
                    return $sum + ($end - $start);
                }, 0);
                
                $unusedLength = Config::STOCK_LENGTH - $usedLength;
                $summary = sprintf(
                    "已用: %.1fmm (%.1f%%) | 未用: %.1fmm (%.1f%%)",
                    $usedLength,
                    ($usedLength / Config::STOCK_LENGTH) * 100,
                    $unusedLength,
                    ($unusedLength / Config::STOCK_LENGTH) * 100
                );
                
                imagettftext(
                    $image,
                    10,
                    0,
                    $margin,
                    $currentY + 140,
                    $black,
                    $fontRegular,
                    $summary
                );

                // 绘制工具信息
                $toolInfo = sprintf(
                    "切割工具: %s (宽度: %.1fmm)",
                    $toolType === 'normal_blade' ? "普通刀片" : "线切割",
                    Config::CUTTING_TOOLS[$toolType]
                );
                imagettftext(
                    $image,
                    10,
                    0,
                    $width - $margin - 200,
                    $currentY - 10,
                    $black,
                    $fontRegular,
                    $toolInfo
                );

                $currentY += 180;
            }

            // 添加该工具的统计信息
            $analysis = $this->analyzeResult()[$toolType];
            $info = sprintf(
                "%s - 利用率: %.1f%% | 实际材料: %d根 | 理论最小: %.1f根",
                $toolName,
                $analysis['utilization_rate'],
                $analysis['actual_materials'],
                $analysis['theoretical_min_materials']
            );
            imagettftext($image, 14, 0, $margin, $currentY - 10, $black, $fontBold, $info);
            $currentY += 60;
        }

        // 保存图像
        if (!imagepng($image, $filename)) {
            throw new Exception("保存图像失败");
        }
        
        imagedestroy($image);
        return true;
    }

    /**
     * 选择最优的产品切割顺序
     * 
     * 评分标准：
     * 1. 产品长度 (length * 2.5)：优先切割较长的产品
     * 2. 孔位密度 (holesDensity * 15)：优先切割孔位密度大的产品
     * 3. 边距比 ((1 - marginRatio) * 8)：优先切割边距较小的产品
     * 4. 剩余数量 (quantity * 4)：优先切割数量多的产品
     * 5. 材料利用率 ((length / STOCK_LENGTH) * 10)：优先切割更符合材料长度的产品
     */
    protected function selectBestProductSequence(array $remainingForTool): array
    {
        $sequence = [];
        foreach ($remainingForTool as $productId => $quantity) {
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
     * 打印剩余待切割产品的信息
     */
    protected function printRemainingProducts(array $remainingForTool): void
    {
        foreach ($remainingForTool as $productId => $quantity) {
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
    }

    /**
     * 找出产品所有可能的切割位置
     * 
     * @param Product $product 要切割的产品
     * @return array<array{0: float, 1: float, 2: array}> 返回可能的切割位置数组，每个元素包含 [起始位置, 结束位置, 孔位数组]
     */
    protected function findValidPositions(Product $product): array
    {
        $cacheKey = $product->getId();

        // 如果已经缓存过该产品的有效位置，直接返回缓存结果
        if (isset($this->validPositionsCache[$cacheKey])) {
            return $this->validPositionsCache[$cacheKey];
        }

        $validPositions = [];
        $holesNeeded = $product->getHolesCount();

        // 从左到右尝试所有可能的起始孔位
        for ($i = 0; $i <= count(Config::HOLES) - $holesNeeded; $i++) {
            // 获取连续的孔位
            $selectedHoles = array_slice(Config::HOLES, $i, $holesNeeded);
            
            // 计算切割起始和结束位置
            $startPos = reset($selectedHoles) - $product->getLeftMargin();
            $endPos = end($selectedHoles) + $product->getRightMargin();

            // 检查是否在材料范围内
            if ($startPos >= 0 && $endPos <= Config::STOCK_LENGTH) {
                $validPositions[] = [$startPos, $endPos, $selectedHoles];
            }
        }

        // 缓存结果
        $this->validPositionsCache[$cacheKey] = $validPositions;
        return $validPositions;
    }

    /**
     * 评估切割位置的得分
     * 
     * 评分因素：
     * 1. 材料利用率 (20分)：当前材料的总体利用率
     * 2. 切割连续性 (8分/次)：与已切割区域的衔接程度
     * 3. 边缘优化 (5分/次)：是否紧贴材料边缘
     * 4. 孔位密度 (10分系数)：单位长度内的孔位数量
     * 
     * @param float $startPos 起始位置
     * @param float $endPos 结束位置
     * @param array $holes 孔位组
     * @param Material $material 材料对象
     * @return float 评分结果
     */
    protected function evaluateCutPosition(float $startPos, float $endPos, array $holes, Material $material): float
    {
        $score = 0;
        $length = $endPos - $startPos;

        // 1. 材料利用率分优化
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
}

