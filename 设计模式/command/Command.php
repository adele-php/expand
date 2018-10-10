<?php


abstract class Command{
    //每个命令类都比有一个执行命令的方法
    abstract function execute();

}