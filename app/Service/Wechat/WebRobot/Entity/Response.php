<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-25 11:57:08
 */

namespace App\Service\Wechat\WebRobot\Entity;

use Psr\Http\Message\ResponseInterface;

class Response
{
    /**
     * @var array
     */
    protected $originRequest;

    /**
     * @var array
     */
    protected $originResponse;

    /**
     * HttpResponse constructor.
     * @param ResponseInterface $response
     * @param array $originRequest
     */
    public function __construct(ResponseInterface $response, array $originRequest = [])
    {
        $this->originResponse = [
            'status_code' => (int)$response->getStatusCode(),
            'headers' => (array)$response->getHeaders(),
            'body' => (string)$response->getBody()
        ];

        $this->originRequest = (array)$originRequest;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->originResponse['status_code'];
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->originResponse['headers'];
    }

    /**
     * @param string $key
     * @return string|array
     */
    public function getHeader(string $key)
    {
        $data = $this->originResponse['headers'][$key] ?? [];

        return implode(', ', $data);
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->originResponse['body'];
    }

    /**
     * @return array
     */
    public function getOriginRequest(): array
    {
        return $this->originRequest;
    }

    /**
     * @return array
     */
    public function getOriginResponse(): array
    {
        return $this->originResponse;
    }

    /**
     * @return array
     */
    public function getContext()
    {
        return [
            'request' => $this->originRequest,
            'response' => $this->originResponse,
        ];
    }

    /**
     * 解析 JSON 格式的响应内容
     *
     * @return array
     */
    public function toJsonArray(): array
    {
        $array = json_decode($this->getBody(), JSON_OBJECT_AS_ARRAY);

        return json_last_error() === JSON_ERROR_NONE ? $array : [];
    }
}
