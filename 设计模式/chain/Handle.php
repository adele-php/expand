<?php


abstract class Handle{
    private $next_handle;

    public function handleMessage(Request $request){
        //判断是否有权力处理
        if($this->getHandleLevel() == $request->getRequestLevel()){
            $response = $this->realHandle();
        }else{
            //传递到下一个对象
            if($this->next_handle){
                $response = $this->next_handle->handleMessage($request);
            }else{
                $response = '不处理啦';
            }
        }
    }

    //得到处理的等级
    abstract function getHandleLevel();
    abstract function realHandle();

    public function setNext(Handle $next_handle){
        $this->next_handle = $next_handle;
    }


}