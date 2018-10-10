<?php


class Client{

    public function run(){
        $receive = new ConcreteReceiver1();
        $command = new Concrete1Command($receive);
        
        $invoker = new Invoker();
        $invoker->setCommand($command);
        $invoker->action();
    }

}