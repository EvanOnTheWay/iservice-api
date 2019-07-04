<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-28 18:08:09
 */

namespace App\Task\Wechat\WebRobot;

use App\Service\Wechat\WebRobot\Entity\Session;
use App\Service\Wechat\WebRobot\Exception\NetWorkException;
use App\Service\Wechat\WebRobot\Exception\ResponseException;
use App\Service\Wechat\WebRobot\Exception\SessionException;
use App\Service\Wechat\WebRobot\RequestManager;
use App\Service\Wechat\WebRobot\ScheduleManager;
use App\Service\Wechat\WebRobot\SessionManager;
use App\Task\Task;
use Kernel\Foundation\Logger;
use Kernel\Worker;

class SessionTask extends Task
{
    /**
     * 专员编号
     *
     * @var string
     */
    protected $repId;

    protected $context;

    /**
     * 网络错误时最大重试次数
     *
     * @var int
     */
    protected $maxRetry = 3;

    /**
     * SessionTask constructor.
     * @param string $repId
     */
    public function __construct(string $repId)
    {
        $this->repId = $repId;
    }

    public function handle()
    {
        $this->context = [
            'rep_id' => $this->repId,
            'worker_id' => Worker::getInstance()->getWorkerId(),
        ];

        // 从缓存服务器还原会话数据
        $sessionManager = new SessionManager($this->repId);
        if ($sessionManager->restore() === false) {
            Logger::warning('会话实时监控:会话数据未找到', $this->context);
            return;
        }

        try {
            $session = $sessionManager->session();
            switch ($session->getState()) {
                case Session::STATE_PENDING:
                case Session::STATE_SCANNED:
                case Session::STATE_LOGGING:
                    // 执行登录状态检查
                    Logger::info('会话实时监控:登录状态检查', array_merge(
                        $this->context,
                        [
                            'session_state' => $session->getStateString()
                        ]
                    ));
                    $requestManager = new RequestManager($session);
                    $requestManager->checkLogin();

                    // 完成登录状态检查，更新会话状态并继续会话调度
                    $this->nextSchedule($sessionManager);
                    break;
                case Session::STATE_ONLINE:
                    // 执行数据同步检查
                    Logger::info('会话实时监控:数据同步检查', $this->context);
                    $requestManager = new RequestManager($session);
                    $requestManager->checkSync();

                    // 完成数据同步检查， 更新会话状态并继续会话调度
                    $this->nextSchedule($sessionManager);
                    break;
                case Session::STATE_OFFLINE:
                    Logger::warning('会话实时监控:会话已经离线', $this->context);
                    break;
                case Session::STATE_EXPIRED:
                    Logger::warning('会话实时监控:二维码已过期', $this->context);
                    break;
            }
        } catch (NetWorkException $exception) {
            Logger::error('会话实时监控:网络请求异常', array_merge($this->context, $exception->toArray()));

            // 网络错误偶尔发生，更新会话状态并继续会话调度
            $this->nextSchedule($sessionManager);
        } catch (ResponseException $exception) {
            Logger::error('会话实时监控:接口响应异常', array_merge($this->context, $exception->toArray()));
            $this->setOffline($sessionManager);
        } catch (SessionException $exception) {
            Logger::error('会话实时监控:会话状态异常', array_merge($this->context, $exception->toArray()));
            $this->setOffline($sessionManager);
        }
    }

    protected function nextSchedule(SessionManager $sessionManager)
    {
        $sessionManager->store();

        $sessionScheduler = new ScheduleManager();
        $sessionScheduler->pushRepSessionQueue($this->repId);
    }

    protected function setOffline(SessionManager $sessionManager)
    {
        $sessionManager->session()->setState(Session::STATE_OFFLINE);
        $sessionManager->store();
    }
}
