<?php


class Client{
    public function run(){
        $subject = new ConcreteSubject();
        $observer = new ConcreteObserver();
        $subject->addObserver($observer);
        $subject->doSomeThing();
    }
}