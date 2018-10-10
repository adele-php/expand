<?php


abstract class AbstractClass{
    
    //模板方法：实现对基本方法的调度，完成固定的逻辑
    public function template(){
        $this->base1();
        $this->base2();
        $this->base3();
    }
    
    //基本方法：由子类实现,并在模板方法中调用
    abstract function base1();
    abstract function base2();
    abstract function base3();

}