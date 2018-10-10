<?php


/*
 * 观察者
 *  1.接受消息，并处理
 */
abstract class Observer{

   abstract public function update($subject, $data);

}