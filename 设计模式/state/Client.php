<?php


class Client{
    public function run(){
        $context = new Context();
        $context->setCurrentState(new ConcreteStateA());
        $context->handle1();
    }
}