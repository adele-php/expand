<?php


class Facade{
    private $a;
    private $b;
    private $c;
    private $context;

    public function __construct() {
        $this->a = new BusinessA();
        $this->b = new BusinessB();
        $this->c = new BusinessC();
        $this->context = new Context();
    }

    public function methodA(){
        $this->a->doSomething();
    }

    public function methodB(){
        $this->b->doSomething();
    }

    public function methodC(){
        $this->c->doSomething();
    }

    /*
     * fixme 这是不靠谱的设计,门面模式不能参与业务逻辑
     */
    public function errorMethod(){
        $this->a->doSomething();
        $this->c->doSomething();
    }

    /*
     * 正确的方式,使用封装类
     * 通过这样一次封装后，门面模式就不参与业务逻辑了。
     */
    public function rightMethod(){
        $this->context->doSomething();
    }


}