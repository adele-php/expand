<?php


/*
 * 被观察者
 *  1.管理观察者
 *  2.通知观察者
 */
abstract class Subject{

    private $observers = [];
    //管理观察者
    public function addObserver(Observer $observer){
        $this->observers[] = $observer;
    }

    public function delObserver(Observer $observer){
        foreach ($this->observers as $k => $v){
            if($v === $observer){
                unset($this->observers[$k]);
            }
        }
    }

    //通知观察者
    public function notifyObservers(Subject $subject, $data){
        foreach ($this->observers as $k => $observer){
            $observer->update($subject, $data);
        }
    }

}