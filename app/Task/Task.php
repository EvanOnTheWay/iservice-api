<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-23 21:51:56
 */

namespace App\Task;

use Kernel\Worker;

abstract class Task
{
    /**
     * 投递任务
     *
     * @return false|int
     */
    final public function deliver()
    {
        return Worker::getInstance()
            ->getServerInstance()
            ->task([$this, 'handle']);
    }

    /**
     * 处理任务
     *
     * @return mixed
     */
    abstract public function handle();
}