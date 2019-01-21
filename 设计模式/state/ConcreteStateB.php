<?php


/*
 * 具体状态角色
 * 职责：
 *  1.本状态下能做的事
 *  2.如何过渡到其他状态
 */
class ConcreteStateB extends State{

    //本状态下必须处理的逻辑
    public function handle1(){
        //设置状态
        $this->context->setCurrentState(new ConcreteStateA);
        //过渡状态
        $this->context->handle1();
    }

    public function handle2(){


    }




}