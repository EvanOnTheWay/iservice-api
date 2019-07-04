<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 16:11:20
 */

namespace Kernel;

use Swoole\Http\Server;

/**
 * Class Worker
 * @package Kernel
 */
final class Worker
{
    /**
     * @var Worker
     */
    private static $instance;

    /**
     * @var Server
     */
    private $serverInstance;

    /**
     * @var int
     */
    private $workerId;

    /**
     * 初始化 Worker 实例
     *
     * @param Server $serverInstance
     * @param int $workerId
     */
    final public static function initialize(Server $serverInstance, int $workerId)
    {
        if (null === self::$instance) {
            self::$instance = new Worker();
            self::$instance->workerId = $workerId;
            self::$instance->serverInstance = $serverInstance;
        }
    }

    /**
     * 返回 Worker 实例
     *
     * @return Worker
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * 返回 Server 实例
     *
     * @return Server
     */
    public function getServerInstance()
    {
        return self::$instance->serverInstance;
    }

    /**
     * 返回 WorkerId
     *
     * @return int
     */
    public function getWorkerId()
    {
        return self::$instance->workerId;
    }
}
