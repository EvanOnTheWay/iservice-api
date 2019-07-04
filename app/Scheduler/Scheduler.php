<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-05-04 22:56:21
 */

namespace App\Scheduler;

abstract class Scheduler
{
    final protected function __construct()
    {
        // 限制继承类的实例化
    }

    final public static function getInstance()
    {
        return new static();
    }

    abstract public function handle();
}
