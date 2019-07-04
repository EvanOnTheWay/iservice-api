<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-05-05 00:03:07
 */

namespace App\Scheduler\Wechat\WebRobot;

use App\Scheduler\Scheduler;
use App\Service\Wechat\WebRobot\ScheduleManager;
use App\Task\Wechat\WebRobot\SessionTask;

/**
 * Class SessionScheduler
 * @package App\Scheduler\Wechat\WebRobot
 */
class SessionScheduler extends Scheduler
{
    public function handle()
    {
        $isScheduleAble = true;
        $scheduleManager = new ScheduleManager();

        while ($isScheduleAble) {
            // 从"会话"调度队列中弹出一个"会话"
            $repId = $scheduleManager->popRepSessionQueue();

            // 如果"会话"调度队列为空，结束调度
            if ($repId === null) {
                $isScheduleAble = false;
            } else {
                // 如果"会话"调度队列非空空，创建任务
                (new SessionTask($repId))->deliver();
            }
        }
    }
}
