<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-29 17:20:21
 */

namespace App\Service\Wechat\WebRobot\Exception;

use Exception;

class SessionException extends Exception
{
    /**
     * 请求的参数有误
     */
    const BAD_ARGUMENTS = 0;

    const PASS_TICKET_ERROR = -14;

    /**
     * 在手机端退出了网页微信
     */
    const LOGOUT_BY_MOBILE = 1100;

    /**
     * 本地的凭证信息已经失效
     * 服务器端认为网页端已经下线，或则网页端在别处登录
     * @see https://blog.csdn.net/u012478759/article/details/78376756
     */
    const LOGIN_OTHER_WHERE = 1101;

    const API_HOST_CHANGE = 1202;

    /**
     * 当前登录环境异常
     * 为了你的帐号安全，暂时不能登录web微信。你可以通过手机客户端或者windows微信登录。
     */
    const LOGIN_LIMITED = 1203;

    /**
     * 无效的联系人
     * 在向自己发送消息或向群众非好友发送消息时，会遇到这个错误码
     */
    const INVALID_CONTACT = 1204;

    /**
     * 接口调用频率过高
     * 遇到这个错误码要小心了，如果不出处理的话，离封号不远了
     */
    const API_FREQUENCY = 1205;

    /**
     * 异常处上下文
     *
     * @var array
     */
    protected $context;

    /**
     * SessionStatusException constructor.
     *
     * @param string $scene 异常场景名称
     * @param int $code 会话状态代码
     * @param array $context 异常处上下文
     */
    public function __construct(string $scene, int $code, $context = [])
    {
        $scene = "微信会话状态码异常:{$scene}";

        parent::__construct($scene, $code);

        $this->context = $context;
    }

    /**
     * 异常上下文
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
        ];
    }
}
