<?php

namespace CuttingStock;

/**
 * 产品类
 * 用于表示需要切割的产品，包含孔数、边距等信息
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
     * 包括左右边距和孔位之间的间距
     */
    public function calculateLength(): float
    {
        if ($this->holesCount === 1) {
            return $this->leftMargin + $this->rightMargin;
        }
        return $this->leftMargin + $this->rightMargin +
            ($this->holesCount - 1) * Config::HOLE_SPACING;
    }
}