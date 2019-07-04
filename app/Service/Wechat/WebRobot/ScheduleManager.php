<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-05-29 10:52:15
 */

namespace App\Service\Wechat\WebRobot;

use App\Service\Wechat\WebRobot\Entity\Message\Message;
use App\Service\Wechat\WebRobot\Entity\Message\TextMessage;
use Kernel\Component\RedisClient;

class ScheduleManager
{
    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var int "会话"消息队列最小调度周期
     */
    protected $messageScheduleMinPeriod = 15;

    /**
     * @var int "会话"消息队列最大调度周期
     */
    protected $messageScheduleMaxPeriod = 30;

    /**
     * MessageManager constructor.
     */
    public function __construct()
    {
        $this->redis = RedisClient::getInstance();
    }

    /**
     * "会话"最后发送消息时间的缓存 KEY
     *
     * Redis 数据类型: Hash
     *
     * @return string
     */
    protected function getRepLastMessageTimeKey(): string
    {
        return 'wechat:web_robot:schedule:rep_last_message_time';
    }

    /**
     * 返回"会话"最后发送消息时间
     *
     * @param string $repId
     * @return int|null
     */
    protected function getRepLastMessageTime(string $repId): ?int
    {
        $key = $this->getRepLastMessageTimeKey();
        $time = $this->redis->hGet($key, $repId);

        return ($time === false) ? null : (int)$time;
    }

    /**
     * 设置"会话"最后发送消息时间
     *
     * @param string $repId
     * @param int $lastMessagingTime
     */
    protected function setRepLastMessageTime(string $repId, int $lastMessagingTime): void
    {
        $this->redis->hset($this->getRepLastMessageTimeKey(), $repId, $lastMessagingTime);
    }

    /**
     * 移除指定"会话"的最后发送消息时间
     *
     * @param string $repId
     */
    protected function delRepLastMessageTime(string $repId): void
    {
        $this->redis->hdel($this->getRepLastMessageTimeKey(), $repId);
    }

    /**
     * 获取本次调度要发送的消息
     *
     * @return TextMessage[]
     */
    public function getScheduleMessages(): array
    {
        $key = $this->getRepLastMessageTimeKey();

        $results = [];

        /**
         * $repId 专员编号
         * $lastTime 最后发送时间(timestamp)
         */
        $currentTime = time();
        $scheduleArr = $this->redis->hgetall($key);
        foreach ($scheduleArr as $repId => $repLastMessageTime) {
            if ($currentTime > $repLastMessageTime) {
                $message = $this->popRepMessagesQueue($repId);
                if (null !== $message) {
                    // 在消费每个专员的消息队列时，加入随机延时
                    $nextTime = $currentTime + rand($this->messageScheduleMinPeriod, $this->messageScheduleMaxPeriod);
                    $this->setRepLastMessageTime($repId, $nextTime);

                    $results[] = $message;
                }
            }
        }

        return $results;
    }

    /**
     * 返回"会话"消息队列的缓存 KEY
     *
     * Redis 数据类型: List
     *
     * @param string $repId
     * @return string
     */
    protected function getRepMessagesQueueKey(string $repId): string
    {
        return "wechat:web_robot:schedule:rep_messages_queue:{$repId}";
    }

    /**
     * 从"会话"消息队列弹出一个消息
     *
     * @param string $repId
     * @return TextMessage
     */
    protected function popRepMessagesQueue(string $repId): ?Message
    {
        $key = $this->getRepMessagesQueueKey($repId);
        $value = $this->redis->rpop($key);

        return $value === false ? null : unserialize($value);
    }

    /**
     * 向"会话"消息队列注入一个消息
     *
     * @param Message $message
     */
    public function pushRepMessagesQueue(Message $message): void
    {
        $repId = $message->getRepId();

        $key = $this->getRepMessagesQueueKey($repId);
        $this->redis->lpush($key, serialize($message));

        $now = time();
        $last = $this->getRepLastMessageTime($repId);

        // 如果没有最后调度时间或者距离最后调度已超过最大调度周期时，设置调度时间为当前时间
        if ($last === null || (($now - $last) > $this->messageScheduleMaxPeriod)) {
            $this->setRepLastMessageTime($repId, $now);
        }
    }

    /**
     * 清空指定"会话"消息队列
     *
     * @param string $repId
     */
    public function delRepMessagesQueue(string $repId): void
    {
        $this->delRepLastMessageTime($repId);

        $key = $this->getRepMessagesQueueKey($repId);
        $this->redis->del($key);
    }


    /**
     * 返回"会话"调度队列的缓存 KEY
     *
     * @return string
     */
    protected function repSessionQueueKey(): string
    {
        return 'wechat:web_robot:schedule:rep_session';
    }

    /**
     * 从"会话"调度队列弹出一个"会话"
     * @return string|null
     */
    public function popRepSessionQueue(): ?string
    {
        $key = $this->repSessionQueueKey();
        $value = $this->redis->rpop($key);

        return $value === false ? null : $value;
    }

    /**
     * 向"会话"调度队列注入一个"会话"
     *
     * @param string $repId
     */
    public function pushRepSessionQueue(string $repId): void
    {
        $key = $this->repSessionQueueKey();
        $this->redis->lpush($key, $repId);
    }
}
