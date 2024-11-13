<?php

namespace CuttingStock;

/**
 * 产品类
 */
class Product
{
    /** @var int 产品需要的孔数 */
    protected int $holesCount;

    /** @var float 左侧边距 */
    protected float $leftMargin;

    /** @var float 右侧边距 */
    protected float $rightMargin;

    /** @var int 需要生产的数量 */
    protected int $quantity;

    /** @var string 产品唯一标识符 */
    protected string $id;

    /**
     * 产品构造函数
     *
     * @param int $holesCount 产品需要的孔数
     * @param float $leftMargin 左侧边距
     * @param float $rightMargin 右侧边距
     * @param int $quantity 需要生产的数量
     * @param string $id 产品唯一标识符
     */
    public function __construct(
        int    $holesCount,
        float  $leftMargin,
        float  $rightMargin,
        int    $quantity,
        string $id
    )
    {
        $this->holesCount = $holesCount;
        $this->leftMargin = $leftMargin;
        $this->rightMargin = $rightMargin;
        $this->quantity = $quantity;
        $this->id = $id;
    }

    /**
     * 获取产品需要的孔数
     */
    public function getHolesCount(): int
    {
        return $this->holesCount;
    }

    /**
     * 获取左侧边距
     */
    public function getLeftMargin(): float
    {
        return $this->leftMargin;
    }

    /**
     * 获取右侧边距
     */
    public function getRightMargin(): float
    {
        return $this->rightMargin;
    }

    /**
     * 获取需要生产的数量
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * 获取产品ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 计算产品总长度
     * 
     * @return float 产品总长度(mm)
     */
    public function calculateLength(): float
    {
        return $this->leftMargin + $this->rightMargin +
            ($this->holesCount - 1) * Config::HOLE_SPACING;
    }

    /**
     * 获取产品的尺寸信息
     */
    public function getDimensionInfo(): string
    {
        return sprintf(
            "%d孔 L=%.1fmm (左边距%.1f + %d孔间距 + 右边距%.1f)",
            $this->holesCount,
            $this->calculateLength(),
            $this->leftMargin,
            $this->holesCount - 1,
            $this->rightMargin
        );
    }
}