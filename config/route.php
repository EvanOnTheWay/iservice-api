<?php
/**
 * Created by PhpStorm.
 * User: wenpeng
 * Date: 2017/12/13
 * Time: 下午8:43
 */

use App\Controller\BaseController;
use App\Controller\Wechat\WebRobotController;

return [
    // 基础接口
    'status' => [BaseController::class, 'status'],

    // 微信相关接口
    'wechatWebRobot/getLoginState' => [WebRobotController::class, 'getLoginState'],
    'wechatWebRobot/getLoginQrcode' => [WebRobotController::class, 'getLoginQrcode'],
    'wechatWebRobot/sendTextMessage' => [WebRobotController::class, 'sendTextMessage'],
];