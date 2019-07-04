<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-05-29 16:16:14
 */

namespace App\Service\Wechat\WebRobot\Entity\Message;

abstract class Message
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $repId;

    /**
     * @var int
     */
    protected $batchId;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string
     */
    protected $remarkName;

    /**
     * @var int
     */
    protected $expiredAt;

    public function __construct()
    {
        // 默认当天 21:00 后放弃任务
        $this->expiredAt = strtotime(date('Y-m-d 21:00'));
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getRepId(): string
    {
        return $this->repId;
    }

    /**
     * @param string $repId
     */
    public function setRepId(string $repId): void
    {
        $this->repId = $repId;
    }

    /**
     * @return int
     */
    public function getBatchId(): int
    {
        return $this->batchId;
    }

    /**
     * @param int $batchId
     */
    public function setBatchId(int $batchId): void
    {
        $this->batchId = $batchId;
    }

    /**
     * @return string
     */
    public function getRemarkName(): string
    {
        return $this->remarkName;
    }

    /**
     * @param string $remarkName
     */
    public function setRemarkName(string $remarkName): void
    {
        $this->remarkName = $remarkName;
    }

    /**
     * @return int
     */
    public function getExpiredAt(): int
    {
        return $this->expiredAt;
    }

    /**
     * @param int $expiredAt
     */
    public function setExpiredAt(int $expiredAt): void
    {
        $this->expiredAt = $expiredAt;
    }
}
