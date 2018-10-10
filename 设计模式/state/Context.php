<?php


/**
 * User: dyf
 * 上下文类
 * 职责：
 *  1.用于定义客户端需要的接口
 *  2.负责具体状态的切换
 * 约束：
 *  1.具有状态抽象角色定义的所有行为，具体执行使用行为委托
 */
class Context{
    public $current_state;

    public function setCurrentState(State $state){
        $this->current_state = $state;
    }
    //行为委托
    public function handle1()
    {
        $this->current_state->handle1();
    }
    //行为委托
    public function handle2()
    {
        $this->current_state->handle2();
    }

}