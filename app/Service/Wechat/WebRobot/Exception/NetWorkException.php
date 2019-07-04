<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-22 15:04:57
 */

namespace App\Service\Wechat\WebRobot\Exception;

use Exception;
use Throwable;

class NetWorkException extends Exception
{
    protected $context;

    /**
     * NetWorkException constructor.
     *
     * @param string $message
     * @param array $context
     * @param Throwable|null $previous
     */
    public function __construct(string $message, array $context, Throwable $previous = null)
    {
        parent::__construct($message, null, $previous);

        $this->context = $context;
    }

    /**
     * 异常处上下文
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
            'previous' => [
                'code' => $this->getPrevious()->getCode(),
                'message' => $this->getPrevious()->getMessage(),
                'trace_string' => $this->getPrevious()->getTraceAsString(),
            ]
        ];
    }
}
