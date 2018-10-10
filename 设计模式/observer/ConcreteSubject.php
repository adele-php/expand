<?php


/*
 * 具体被观察者
 *  职责:谁能够观察,谁不能观察
 */
class ConcreteSubject extends Subject {

    public function doSomeThing($is_notify_obs = true){
        $data = '呵呵';
        $is_notify_obs && $this->notifyObservers($this, $data);
    }

}