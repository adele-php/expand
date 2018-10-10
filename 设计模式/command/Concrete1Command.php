<?php


class Concrete1Command extends Command {
    private $receiver;

    public function __construct(Receiver $receiver){
        $this->receiver = $receiver;
    }

    //每个命令类都比有一个执行命令的方法
    public function execute(){
        $this->receiver->doSomeThing();
    }

}