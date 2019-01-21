<?php

//受限制的门面
class FacadeLimit{
    private $facade;

    public function __construct() {
        $this->facade = new Facade();
    }

    public function methodA(){
        $this->facade->methodA();
    }


}