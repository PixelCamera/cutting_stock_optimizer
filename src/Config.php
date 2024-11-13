<?php
namespace CuttingStock;

/**
 * 配置类
 * 存储切割优化相关的全局常量和配置信息
 * 
 * @package CuttingStock
 */
class Config {
    /** @var float 材料总长度(mm) */
    public const float STOCK_LENGTH = 4000.0;
    
    /** @var float 孔位间隔(mm) */
    public const float HOLE_SPACING = 80.0;
    
    /** @var float 普通刀片厚度(mm) */
    public const float NORMAL_BLADE_WIDTH = 3.0;
    
    /** @var float 线切割线径(mm) */
    public const float WIRE_WIDTH = 0.3;
    
    /** @var array<int> 可用孔位坐标列表(mm) */
    public const array HOLES = [
        40, 120, 200, 280, 360, 440, 520, 600, 680, 760,
        840, 920, 1000, 1080, 1160, 1240, 1320, 1400, 1480, 1560,
        1640, 1720, 1800, 1880, 1960, 2040, 2120, 2200, 2280, 2360,
        2440, 2520, 2600, 2680, 2760, 2840, 2920, 3000, 3080, 3160,
        3240, 3320, 3400, 3480, 3560, 3640, 3720, 3800, 3880, 3960
    ];
    
    /** @var array<string, float> 切割工具配置 */
    public const array CUTTING_TOOLS = [
        'normal_blade' => self::NORMAL_BLADE_WIDTH,
        'wire' => self::WIRE_WIDTH
    ];
}