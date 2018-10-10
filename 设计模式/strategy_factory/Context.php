<?php


/*
 * 封装上下文
 */
class Context
{
    protected $strategy;
    function __construct($strategy)
    {
        $this->strategy = $strategy;
    }

    public function doAction()
    {
        $this->strategy->algrithmInterface();
    }
}