<?php
namespace CuttingStock;

/**
 * 配置类
 * 存储全局常量和配置信息
 */
class Config {
    /** @var float 材料总长度 */
    public const float STOCK_LENGTH = 96.0;
    
    /** @var float 孔位间隔 */
    public const float HOLE_SPACING = 8.0;
    
    /** @var array<int> 可用孔位坐标列表 */
    public const array HOLES = [4, 12, 20, 28, 36, 44, 52, 60, 68, 76, 84, 92];
}