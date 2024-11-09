<?php

namespace CuttingStock;

use RuntimeException;

/**
 * 材料类
 * 用于表示和管理单根材料的切割情况
 */
class Material
{
    /** @var float 材料总长度 */
    protected float $length;

    /** @var array 已使用的切割区间 [[start, end, productId], ...] */
    protected array $usedSections;

    public function __construct()
    {
        $this->length = Config::STOCK_LENGTH;
        $this->usedSections = [];
    }

    /**
     * 检查指定区间是否可以切割
     *
     * @param float $start 起始位置
     * @param float $end 结束位置
     * @return bool 是否可以切割
     */
    public function canCut(float $start, float $end): bool
    {
        $epsilon = 0.0001;  // 浮点数比较容差

        // 检查是否超出材料长度
        if ($start < -$epsilon || $end > $this->length + $epsilon) {
            return false;
        }

        // 检查是否与已有切割重叠
        foreach ($this->usedSections as $section) {
            list($usedStart, $usedEnd) = $section;
            if (!($end <= $usedStart + $epsilon || $start >= $usedEnd - $epsilon)) {
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
     * @param int $productId 产品ID
     * @throws RuntimeException 当切割位置无效时抛出异常
     */
    public function cut(float $start, float $end, int $productId): void
    {
        if (!$this->canCut($start, $end)) {
            throw new RuntimeException(sprintf(
                "无效的切割位置: 起点 %.2f, 终点 %.2f, 产品 %d",
                $start,
                $end,
                $productId
            ));
        }

        $this->usedSections[] = [$start, $end, $productId];
        $this->sortSections();
    }

    /**
     * 对切割区间按起始位置排序
     */
    protected function sortSections(): void
    {
        usort($this->usedSections, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
    }

    /**
     * 获取已使用的切割区间
     *
     * @return array 切割区间数组 [[start, end, productId], ...]
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
        $usedLength = 0;
        foreach ($this->usedSections as $section) {
            list($start, $end) = $section;
            $usedLength += ($end - $start);
        }
        return $usedLength / $this->length;
    }

    /**
     * 获取最大剩余空间长度
     *
     * @return float 最大剩余空间长度
     */
    public function getMaxRemainingSpace(): float
    {
        $maxSpace = 0;
        $lastEnd = 0;

        foreach ($this->usedSections as $section) {
            list($start, $end) = $section;
            $maxSpace = max($maxSpace, $start - $lastEnd);
            $lastEnd = $end;
        }

        return max($maxSpace, $this->length - $lastEnd);
    }

    /**
     * 添加预分配检查方法
     *
     * @param array $products 产品数组
     * @return bool 是否可以预分配
     */
    public function canPreallocate(array $products): bool
    {
        $totalLength = 0;
        foreach ($products as $product) {
            $totalLength += $product->calculateLength();
        }
        
        return $totalLength <= ($this->length - $this->getUsedLength());
    }

    /**
     * 获取已使用长度
     *
     * @return float 已使用长度
     */
    public function getUsedLength(): float
    {
        return array_reduce($this->usedSections, function($sum, $section) {
            list($start, $end) = $section;
            return $sum + ($end - $start);
        }, 0.0);
    }

    /**
     * 获取最大连续可用空间
     *
     * @return float 最大连续可用空间
     */
    public function getMaxContinuousSpace(): float
    {
        if (empty($this->usedSections)) {
            return $this->length;
        }
        
        $maxSpace = 0;
        $lastEnd = 0;
        
        foreach ($this->usedSections as $section) {
            list($start, $end) = $section;
            $maxSpace = max($maxSpace, $start - $lastEnd);
            $lastEnd = $end;
        }
        
        return max($maxSpace, $this->length - $lastEnd);
    }
}