<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-28 18:40:35
 */

namespace App\Scheduler\Wechat\WebRobot;

use App\Scheduler\Scheduler;
use App\Service\Wechat\WebRobot\ScheduleManager;
use App\Task\Wechat\WebRobot\MessageTask;

class MessageScheduler extends Scheduler
{
    public function handle()
    {
        $scheduleManager = new ScheduleManager();
        $scheduleMessages = $scheduleManager->getScheduleMessages();

        foreach ($scheduleMessages as $message) {
            (new MessageTask($message))->deliver();
        }
    }
}
