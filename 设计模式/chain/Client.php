<?php


class Client{

    public function run(){
        $handle1 = new ConcreteHandle();
        $handle2 = new Concrete2Handle();
        $handle1->setNext($handle2);
        $handle1->handleMessage(new Request());
    }

}