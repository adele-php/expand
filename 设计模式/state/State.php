<?php
/**
 * User: dyf
 */


/**
 * 抽象状态角色
 * 职责：
 *  1.负责对象状态的定义
 *  2.并且封装环境角色以实现状态切换
 * 注意点：
 *  1.适用于当某个对象在它的状态发生改变时，行为也发生比较大变化的场景，也就是说行为受约束的情况下。如：权限设计，人员状态不同，相同方法效果也不同
 *  2.对象的状态最好不要超过5个，容易产生类膨胀，不易维护。
 */
abstract class State{

    //定义环境角色供子类访问
    protected  $context;

    //设置环境角色
    public function setContext(Context $context){
        $this->context = $context;
    }

    abstract public function handle1();
    abstract public function handle2();
}

