<?php

namespace CuttingStock;

use RuntimeException;

/**
 * 材料类
 */
class Material
{
    /** @var float 材料总长度 */
    protected float $length;

    /** @var array 已使用的切割区间 [[start, end, productId, toolType], ...] */
    protected array $usedSections;

    /** @var string 当前使用的切割工具类型 */
    protected string $toolType;

    /**
     * @param string $toolType 切割工具类型 ('normal_blade' 或 'wire')
     */
    public function __construct(string $toolType = 'normal_blade')
    {
        $this->length = Config::STOCK_LENGTH;
        $this->usedSections = [];
        $this->toolType = $toolType;
    }

    /**
     * 检查指定区间是否可以切割
     * 考虑切割工具的宽度
     *
     * @param float $start 起始位置
     * @param float $end 结束位置
     * @return bool 是否可以切割
     */
    public function canCut(float $start, float $end): bool
    {
        $epsilon = 0.0001;  // 浮点数比较容差
        $toolWidth = Config::CUTTING_TOOLS[$this->toolType];

        // 检查是否超出材料长度
        if ($start < -$epsilon || $end > $this->length + $epsilon) {
            return false;
        }

        // 检查是否与已有切割重叠，需要考虑刀具宽度
        foreach ($this->usedSections as $section) {
            list($usedStart, $usedEnd) = $section;
            // 考虑刀具宽度的安全间距
            $safeStart = $usedStart - $toolWidth;
            $safeEnd = $usedEnd + $toolWidth;
            if (!($end <= $safeStart + $epsilon || $start >= $safeEnd - $epsilon)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 执行切割操作
     *
     * @param float $start 起始位置
     * @param float $end 结束位置
     * @param string $productId 产品ID
     * @throws RuntimeException 当切割位置无效时抛出异常
     */
    public function cut(float $start, float $end, string $productId): void
    {
        if (!$this->canCut($start, $end)) {
            throw new RuntimeException("无效的切割位置");
        }

        $this->usedSections[] = [$start, $end, $productId, $this->toolType];
        usort($this->usedSections, fn($a, $b) => $a[0] <=> $b[0]);
    }

    /**
     * 获取材料总长度
     * 
     * @return float 材料总长度(mm)
     */
    public function getLength(): float
    {
        return $this->length;
    }

    /**
     * 获取当前使用的切割工具类型
     * 
     * @return string 切割工具类型 ('normal_blade' 或 'wire')
     */
    public function getToolType(): string
    {
        return $this->toolType;
    }

    /**
     * 获取切割工具宽度
     * 
     * @return float 切割工具宽度(mm)
     */
    public function getToolWidth(): float
    {
        return Config::CUTTING_TOOLS[$this->toolType];
    }

    /**
     * 获取已使用的切割区间
     *
     * @return array 切割区间数组 [[start, end, productId, toolType], ...]
     */
    public function getUsedSections(): array
    {
        return $this->usedSections;
    }

    /**
     * 计算材料利用率
     *
     * @return float 0-1之间的数值
     */
    public function getUtilizationRate(): float
    {
        $usedLength = array_sum(array_map(
            fn($section) => $section[1] - $section[0], 
            $this->usedSections
        ));
        return $usedLength / $this->length;
    }
}