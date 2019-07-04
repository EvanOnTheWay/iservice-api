<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-28 18:10:26
 */

namespace App\Task\Wechat\WebRobot;

use App\Service\Wechat\WebRobot\Entity\Message\Message;
use App\Service\Wechat\WebRobot\Entity\Message\TextMessage;
use App\Service\Wechat\WebRobot\Entity\Session;
use App\Service\Wechat\WebRobot\Exception\NetWorkException;
use App\Service\Wechat\WebRobot\Exception\ResponseException;
use App\Service\Wechat\WebRobot\Exception\SessionException;
use App\Service\Wechat\WebRobot\RequestManager;
use App\Service\Wechat\WebRobot\ScheduleManager;
use App\Service\Wechat\WebRobot\SessionManager;
use App\Task\Task;
use Kernel\Component\MysqlClient;
use Kernel\Foundation\Logger;
use Kernel\Worker;

class MessageTask extends Task
{
    /**
     * 消息任务尚未执行
     */
    const TASK_EXECUTE_PENDING = 0;

    /**
     * 消息任务正在执行
     */
    const TASK_EXECUTE_RUNNING = 1;

    /**
     * 消息任务执行成功
     */
    const TASK_EXECUTE_SUCCESS = 2;

    /**
     * 消息任务执行失败
     */
    const TASK_EXECUTE_FAILURE = 3;

    /**
     * 消息批次正在执行
     */
    const BATCH_EXECUTE_RUNNING = 1;

    /**
     * 消息批次执行中止
     */
    const BATCH_EXECUTE_ABORTED = 3;

    /**
     * 消息实体
     *
     * @var Message
     */
    protected $message;

    protected $context;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function handle()
    {
        $this->context = [
            'rep_id' => $this->message->getRepId(),
            'worker_id' => Worker::getInstance()->getWorkerId(),
        ];

        $repId = $this->message->getRepId();

        // 从缓存服务器还原会话数据
        $sessionManager = new SessionManager($repId);
        if ($sessionManager->restore() === false) {
            $this->updateBatchAborted('会话状态异常:会话未建立');
            return;
        }

        $session = $sessionManager->session();
        if ($session->getState() !== Session::STATE_ONLINE) {
            $this->updateBatchAborted("会话状态异常:{$session->getStateString()}");
            return;
        }

        // 通过联系人的"备注名"换取微信"用户名"
        $username = $session->getContactUsername($this->message->getRemarkName());
        if ($username === null) {
            $this->updateTaskState(self::TASK_EXECUTE_FAILURE, '通过备注名称未找到对应的微信联系人');
            return;
        }

        try {
            if ($this->message instanceof TextMessage) {
                $this->updateTaskState(self::TASK_EXECUTE_RUNNING);

                $requestManager = new RequestManager($session);
                $wechatMessageId = $requestManager->sendText($username, $this->message->getContent());

                $this->updateTaskState(self::TASK_EXECUTE_SUCCESS, "消息发送成功, 远程消息ID:{$wechatMessageId}");
            } else {
                $this->updateTaskState(self::TASK_EXECUTE_FAILURE, '不支持的消息格式');
            }
        } catch (NetWorkException $exception) {
            // 发生网络问题时，因为不确定消息是否已送达，故这里需要把消息标记为失败，以避免重发
            Logger::error('消息队列监控:网络传输异常', array_merge($this->context, $exception->toArray()));
            $this->updateTaskState(self::TASK_EXECUTE_FAILURE, '消息发送失败, 网络传输异常');
        } catch (ResponseException $exception) {
            Logger::error('消息队列监控:接口返回错误', array_merge($this->context, $exception->toArray()));
            $this->updateTaskState(self::TASK_EXECUTE_FAILURE, '消息发送失败, 微信服务异常');
        } catch (SessionException $exception) {
            Logger::error('消息队列监控:会话状态异常', array_merge($this->context, $exception->toArray()));
            // 回滚消息状态为"待发送"
            $this->updateTaskState(self::TASK_EXECUTE_PENDING);

            // 停止消息批次的继续执行
            $this->updateBatchAborted("会话状态异常:{$session->getStateString()}");
        }
    }

    public function updateTaskState(int $state, string $comment = '')
    {
        // 更新数据库中消息对应的执行状态
        $mysql = MysqlClient::getInstance();
        if ($state === self::TASK_EXECUTE_PENDING || $state === self::TASK_EXECUTE_RUNNING) {
            $taskState = $mysql->prepare('update `wechat_mass_message_task` set `execute_state`=? where `id`=?');
            $taskState->execute([self::TASK_EXECUTE_PENDING, $this->message->getId()]);
        } else {
            $taskState = $mysql->prepare('update `wechat_mass_message_task` set `execute_state`=?, `execute_comment`=?, `executed_at`=? where `id`=?');
            $taskState->execute([self::TASK_EXECUTE_SUCCESS, $comment, date('Y-m-d H:i:s'), $this->message->getId()]);

            // 检查批次的状态，按需更新
            $taskCount = $mysql->prepare('select count(`id`) from `wechat_mass_message_task` where `batch_id`=? and `execute_state`=?');
            $taskCount->execute([$this->message->getBatchId(), self::BATCH_EXECUTE_RUNNING]);
            if ((int)$taskCount->fetchColumn() === 0) {
                // 消息全部执行完成，标记批次为执行完成
                $prepare = $mysql->prepare('update `wechat_mass_message_batch` set `execute_state`=2 where `id`=?');
                $prepare->execute([$this->message->getBatchId()]);
            }
        }
    }

    /**
     * 停止消息批次的继续执行
     *
     * @param string $comment
     */
    protected function updateBatchAborted(string $comment)
    {
        // 清除"会话"的消息队列
        $scheduleManager = new ScheduleManager();
        $scheduleManager->delRepMessagesQueue($this->message->getRepId());

        MysqlClient::getInstance()
            ->prepare('update `wechat_mass_message_batch` set `execute_state`=? where `id`=?')
            ->execute([self::BATCH_EXECUTE_ABORTED, $this->message->getBatchId()]);
    }
}
