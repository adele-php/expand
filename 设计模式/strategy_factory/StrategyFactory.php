<?php


/**
 * @explain 策略工厂
 * 由此工厂方法直接产一个具体的策略对象，修正策略模式必须对外暴露策略类
 * 工厂模式：定义一个用于创建对象的接口
 */
class StrategyFactory{

    const STARTEGY_A = 'app\modules\manage\Module';

    public static function getStrategy($startegy){
        return new $startegy;
    }

}

