<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 15:37:31
 */

namespace Kernel\Component;

use Kernel\Foundation\Config;
use Kernel\Foundation\Logger;
use Redis;
use RedisException;

/**
 * Class RedisClient
 *
 * @mixin Redis
 * @package Kernel\Component
 */
class RedisClient
{
    /**
     * @var RedisClient
     */
    protected static $instance;

    protected $redis;

    public static function getInstance()
    {
        if (null === static::$instance) {
            self::$instance = new RedisClient();
            self::$instance->connect();
        }

        return static::$instance;
    }

    protected function connection()
    {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }

        return $this->connect();
    }

    protected function connect()
    {
        $config = Config::get('redis');
        $this->redis = new Redis();
        $this->redis->connect($config['host'], $config['port']);

        return $this->redis;
    }

    protected function reconnect()
    {
        $this->redis = null;
        return $this->connect();
    }

    public function __call($name, array $arguments)
    {
        try {
            $this->connection()->ping();
        } catch (RedisException $exception) {
            Logger::warning('RedisLosingConnection', [
                'command' => $name,
                'arguments' => $arguments,
                'exception' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'trace_string' => $exception->getTraceAsString(),
                ]
            ]);

            $this->reconnect();
        }

        return call_user_func_array([$this->connection(), $name], $arguments);
    }
}
