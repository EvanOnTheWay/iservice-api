<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 22:46:17
 */

namespace App\Controller\Wechat;

use App\Service\Wechat\WebRobot\Entity\Message\TextMessage;
use App\Service\Wechat\WebRobot\Entity\Session;
use App\Service\Wechat\WebRobot\Exception\NetWorkException;
use App\Service\Wechat\WebRobot\Exception\ResponseException;
use App\Service\Wechat\WebRobot\RequestManager;
use App\Service\Wechat\WebRobot\ScheduleManager;
use App\Service\Wechat\WebRobot\SessionManager;
use App\Task\Wechat\WebRobot\SessionTask;
use App\Utils\ResponseWrapper;
use Kernel\Foundation\Request;

class WebRobotController
{
    /**
     * 获取登录状态
     *
     * @param Request $request
     * @return array
     */
    public static function getLoginState(Request $request)
    {
        $repId = $request->json('rep_id', '');
        if ($repId === '') {
            return ResponseWrapper::badRequest('请输入正确的专员ID');
        }

        $sessionManager = new SessionManager($repId);
        if ($sessionManager->restore() === false) {
            return ResponseWrapper::notFound('未找到该专员的微信会话');
        }

        $session = $sessionManager->session();

        return ResponseWrapper::success([
            'state' => $session->getState(),
            'description' => $session->getStateString()
        ]);
    }

    /**
     * 获取登录二维码
     *
     * @param Request $request
     * @return array
     */
    public static function getLoginQrcode(Request $request)
    {
        $repId = $request->json('rep_id', '');
        if ($repId === '') {
            return ResponseWrapper::badRequest('请输入正确的专员ID');
        }

        // 新建微信会话
        $sessionManager = new SessionManager($repId);
        if ($sessionManager->restore()) {
            $state = $sessionManager->session()->getState();

            if ($state === Session::STATE_LOGGING) {
                return ResponseWrapper::forbidden('存在正在登录的微信会话，请稍后再尝试');
            }

            if ($state === Session::STATE_ONLINE) {
                return ResponseWrapper::forbidden('专员微信已登录网页微信，请勿重复操作');
            }
        }

        // 新请求管理器
        $requestManager = new RequestManager($sessionManager->session());
        try {
            $qrcode = $requestManager->getQrcode();

            $sessionManager->store();

            (new SessionTask($repId))->deliver();

            return [
                'code' => 200,
                'data' => [
                    'rep_id' => $repId,
                    'qrcode' => $qrcode
                ]
            ];
        } catch (NetWorkException $exception) {
            return ResponseWrapper::gatewayTimeout('无法连接到微信服务器，请稍后重新尝试');
        } catch (ResponseException $exception) {
            return ResponseWrapper::gatewayTimeout('无法识别微信服务器响应，请联系技术人员');
        }
    }

    /**
     * 发送文本消息
     *
     * @param Request $request
     * @return array
     */
    public static function sendTextMessage(Request $request)
    {
        $repId = $request->json('rep_id', '');
        if ($repId === '') {
            return ResponseWrapper::badRequest('专员编号不能为空');
        }

        $messages = $request->json('messages', []);
        if (empty($messages) || !is_array($messages)) {
            return ResponseWrapper::badRequest('消息列表不能为空');
        }

        $sessionManager = new SessionManager($repId);
        if ($sessionManager->restore() === false) {
            return ResponseWrapper::forbidden('未找到该专员的微信会话');
        }

        // 根据会话状态执行相应逻辑
        $state = $sessionManager->session()->getState();
        if ($state !== Session::STATE_ONLINE) {
            return ResponseWrapper::forbidden('该专员的微信会话未建立');
        }

        // 开始投递任务
        $messageManager = new ScheduleManager();
        foreach ($messages as $message) {
            // 创建文本消息
            $textMessage = new TextMessage();
            $textMessage->setRepId($repId);
            $textMessage->setId($message['id']);
            $textMessage->setBatchId($message['batch_id']);
            $textMessage->setContent($message['content']);
            $textMessage->setRemarkName($message['remark_name']);

            // 投递到消息队列
            $messageManager->pushRepMessagesQueue($textMessage);
        }

        return ResponseWrapper::success();
    }
}
