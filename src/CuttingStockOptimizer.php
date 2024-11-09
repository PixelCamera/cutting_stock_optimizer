<?php

namespace CuttingStock;

use Exception;

/**
 * 切割库存优化器类
 * 用于计算和优化材料的切割方案
 */
class CuttingStockOptimizer
{
    /** @var array<string, Product> 产品列表，键为产品ID */
    protected array $products = [];
    
    /** @var array<string, int> 剩余待切割数量，键为产品ID */
    protected array $remaining = [];
    
    /** @var array<Material> 材料列表 */
    protected array $materials = [];
    
    /** @var array<string, array> 有效切割位置缓存，键为产品ID */
    protected array $validPositionsCache = [];
    
    /** @var array<string, float> 评分缓存，键为切割方案唯一标识 */
    protected array $scoreCache = [];

    /**
     * 构造函数
     * 
     * @param array $products 产品数据数组，每个元素包含 [孔位数组, 左边距, 右边距, 数量]
     */
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
     * 执行优化过程并提供可视化输出
     * 
     * 核心优化算法流程：
     * 1. 循环处理直到所有产品都被切割完成
     * 2. 每一步骤：
     *    - 显示当前剩余需求
     *    - 根据评分选择最优的产品切割顺序
     *    - 对每个产品尝试所有可能的切割位置
     *    - 选择得分最高的切割方案
     *    - 如果当前材料无法切割，则启用新材料
     * 
     * @throws Exception 当优化过程出现错误时抛出异常
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
     * 分析优化结果，计算材料使用效率
     * 
     * @return array{
     *     theoretical_min_materials: float,  // 理论最小材料数量
     *     actual_materials: int,             // 实际使用材料数量
     *     utilization_rate: float            // 材料利用率(%)
     * }
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
     * 生成切割方案的可视化图像
     * 
     * @param string $filename 输出图像文件名
     * @return bool 生成成功返回true
     * @throws Exception 当字体文件不存在、图像处理失败或GD函数调用失败时抛出异常
     */
    public function visualizeCuttingPlan(string $filename = 'cutting_plan.png'): bool
    {
        // 增加图像尺寸和边距
        $width = 2400;
        $height = (int)(count($this->materials) * 200 + 250);
        $margin = 100;

        // 设置字体路径
        $fontRegular = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Regular.otf';
        $fontBold = __DIR__ . '/../resources/fonts/SourceHanSansHWSC-Bold.otf';

        // 检查字体文件是否存在
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
        $title = "切割方案可视化图";
        if (imagettftext(
            $image,
            28,
            0,
            (int)round($width / 2 - 100),
            50,
            $black,
            $fontBold,
            $title
        ) === false) {
            throw new Exception("绘制文字失败");
        }

        // 绘制每根材料
        foreach ($this->materials as $i => $material) {
            $y = $margin + $i * 180;

            // 添加材料标签
            if (imagettftext(
                $image,
                16,
                0,
                20,
                $y + 40,
                $black,
                $fontBold,
                sprintf("材料 %d", $i + 1)
            ) === false) {
                throw new Exception("绘制材料标签失败");
            }

            // 绘制材料底色
            if (!imagefilledrectangle(
                $image,
                $margin,
                $y,
                $width - $margin,
                $y + 100,
                $lightGray
            )) {
                throw new Exception("绘制材料底色失败");
            }

            // 绘制切割区域
            foreach ($material->getUsedSections() as $section) {
                list($start, $end, $productId) = $section;
                $product = $this->products[$productId];

                // 计算切割区域坐标
                $xStart = (int)round($margin + $start * $scale);
                $xEnd = (int)round($margin + $end * $scale);
                
                // 计算边距标记位置
                $leftMarginX = (int)round($xStart + ($product->getLeftMargin() * $scale));
                $rightMarginX = (int)round($xEnd - ($product->getRightMargin() * $scale));

                // 绘制切割区域底色
                $color = $colors[$productId % count($colors)];
                $transparentColor = imagecolorallocatealpha(
                    $image,
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                    50
                );

                if (!imagefilledrectangle(
                    $image,
                    $xStart,
                    $y,
                    $xEnd,
                    $y + 100,
                    $transparentColor
                )) {
                    throw new Exception("绘制切割区域失败");
                }

                // 绘制产品分界线（加粗的黑色竖线）
                imageline($image, $xStart, $y - 5, $xStart, $y + 105, $black);
                imageline($image, $xStart + 1, $y - 5, $xStart + 1, $y + 105, $black);
                imageline($image, $xEnd, $y - 5, $xEnd, $y + 105, $black);
                imageline($image, $xEnd - 1, $y - 5, $xEnd - 1, $y + 105, $black);

                // 重新绘制孔位
                foreach (Config::HOLES as $hole) {
                    $holeX = (int)round($margin + $hole * $scale);
                    if ($hole >= $start && $hole <= $end) {
                        imagefilledellipse($image, $holeX, $y + 50, 10, 10, $black);
                    }
                }

                // 绘制边距标记
                // 绘制边距竖线
                imageline($image, $leftMarginX, $y + 10, $leftMarginX, $y + 90, $black);
                imageline($image, $rightMarginX, $y + 10, $rightMarginX, $y + 90, $black);

                // 绘制边距横线和箭头
                // 左边距
                imageline($image, $xStart, $y + 20, $leftMarginX, $y + 20, $black);
                imageline($image, $xStart, $y + 19, $xStart, $y + 21, $black);
                imageline($image, $leftMarginX, $y + 19, $leftMarginX, $y + 21, $black);
                // 在横线上方标注边距值
                if (imagettftext(
                    $image,
                    10,
                    0,
                    (int)round($xStart + (($leftMarginX - $xStart) / 2) - 10),
                    (int)round($y + 15),
                    $black,
                    $fontRegular,
                    sprintf("%.1f", $product->getLeftMargin())
                ) === false) {
                    throw new Exception("绘制左边距值失败");
                }

                // 右边距
                imageline($image, $rightMarginX, $y + 20, $xEnd, $y + 20, $black);
                imageline($image, $rightMarginX, $y + 19, $rightMarginX, $y + 21, $black);
                imageline($image, $xEnd, $y + 19, $xEnd, $y + 21, $black);
                
                // 在横线上方标注边距值
                if (imagettftext(
                    $image,
                    10,
                    0,
                    (int)round($rightMarginX + (($xEnd - $rightMarginX) / 2) - 10),
                    (int)round($y + 15),
                    $black,
                    $fontRegular,
                    sprintf("%.1f", $product->getRightMargin())
                ) === false) {
                    throw new Exception("绘制右边距值失败");
                }

                // 绘制产品信息
                $productLabel = sprintf("P%d", $productId + 1);
                $bbox = imagettfbbox(16, 0, $fontBold, $productLabel);
                if ($bbox === false) {
                    throw new Exception("计算文字边界失败");
                }
                
                $labelWidth = $bbox[2] - $bbox[0];
                $textX = (int)round($xStart + (($xEnd - $xStart) - $labelWidth) / 2);
                
                if (imagettftext(
                    $image,
                    16,
                    0,
                    $textX,
                    (int)round($y + 55),
                    $black,
                    $fontBold,
                    $productLabel
                ) === false) {
                    throw new Exception("绘制产品编号失败");
                }

                // 绘制详细信息
                $infoLabel = sprintf(
                    "%.1fm\n%d孔",
                    $end - $start,
                    $product->getHolesCount()
                );
                
                $bbox = imagettfbbox(12, 0, $fontRegular, $infoLabel);
                if ($bbox === false) {
                    throw new Exception("计算信息文字边界失败");
                }
                
                $labelWidth = $bbox[2] - $bbox[0];
                $textX = (int)round($xStart + (($xEnd - $xStart) - $labelWidth) / 2);
                
                if (imagettftext(
                    $image,
                    12,
                    0,
                    $textX,
                    (int)round($y + 75),
                    $black,
                    $fontRegular,
                    $infoLabel
                ) === false) {
                    throw new Exception("绘制详细信息失败");
                }
            }
        }

        // 添加统计信息
        $analysis = $this->analyzeResult();
        $info = sprintf(
            "利用率: %.1f%% | 实际材料: %d根 | 理论最小: %.1f根",
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
     * 
     * @return array<array{id: string, score: float}> 产品切割顺序数组，包含产品ID评分
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

        // 从左到右尝所有可能的起始孔位
        for ($i = 0; $i <= count(Config::HOLES) - $holesNeeded; $i++) {
            // 获取连续的孔位
            $selectedHoles = array_slice(Config::HOLES, $i, $holesNeeded);
            
            // 计算切割起始和结束位置
            $startPos = reset($selectedHoles) - $product->getLeftMargin();
            $endPos = end($selectedHoles) + $product->getRightMargin();

            // 检查是否在当前材料上可切割
            $currentMaterial = end($this->materials);
            if ($currentMaterial->canCut($startPos, $endPos)) {
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
     * 检查是否还有未切割的产品
     * 
     * @return bool 如果还有未切割产品返回true，否则返回false
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
}

