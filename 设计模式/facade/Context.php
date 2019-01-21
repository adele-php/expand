<?php


class Context{
    private $a;
    private $c;

    public function __construct() {
        $this->a = new BusinessA();
        $this->c = new BusinessC();
    }

    public function doSomething(){
        //业务逻辑
        $this->a->doSomething();
        $this->c->doSomething();
    }

}