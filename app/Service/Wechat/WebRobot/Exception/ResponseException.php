<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-05-24 11:17:42
 */

namespace App\Service\Wechat\WebRobot\Exception;

use Exception;
use Throwable;

class ResponseException extends Exception
{
    protected $context;

    /**
     * BadResponseException constructor.
     *
     * @param string $message
     * @param array $context
     * @param Throwable|null $previous
     */
    public function __construct(string $message, array $context, Throwable $previous = null)
    {
        $this->context = $context;

        parent::__construct($message);
    }

    /**
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
