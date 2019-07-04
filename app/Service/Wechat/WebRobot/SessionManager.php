<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-22 11:51:22
 */

namespace App\Service\Wechat\WebRobot;

use App\Service\Wechat\WebRobot\Entity\Session;
use Kernel\Component\RedisClient;

class SessionManager
{
    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var string
     */
    protected $cacheKey;

    /**
     * @var string
     */
    protected $cacheTtl = 12 * 3600;

    /**
     * @param string $repId
     */
    public function __construct(string $repId)
    {
        $this->redis = RedisClient::getInstance();
        $this->session = new Session($repId);
        $this->cacheKey = "wechat.web_robot:session:{$repId}";
    }

    /**
     * @return Session
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * 保存一个会话
     */
    public function store(): void
    {
        $this->redis->set($this->cacheKey, serialize($this->session), $this->cacheTtl);
    }

    /**
     * 还原一个会话
     *
     * @return bool
     */
    public function restore(): bool
    {
        if ($this->redis->exists($this->cacheKey) > 0) {
            $cache = $this->redis->get($this->cacheKey);
            $this->session = unserialize($cache);
            return true;
        }
        return false;
    }

    /**
     * 销毁一个会话
     */
    public function destroy(): void
    {
        $this->redis->expire($this->cacheKey, -1);
    }
}
