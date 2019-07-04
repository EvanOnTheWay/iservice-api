<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 16:22:47
 */

namespace Kernel\Foundation;

class Request
{
    private $origin = [];

    public function __construct(\Swoole\Http\Request $request)
    {
        $this->origin = $request;
    }

    public function get($key = null, $default = null)
    {
        return $this->arrayGet($this->origin->get, $key, $default);
    }

    public function post($key = null, $default = null)
    {
        return $this->arrayGet($this->origin->post, $key, $default);
    }

    public function header($key = null, $default = null)
    {
        return $this->arrayGet($this->origin->header, $key, $default);
    }

    public function server($key = null, $default = null)
    {
        return $this->arrayGet($this->origin->server, $key, $default);
    }

    public function cookie($key = null, $default = null)
    {
        return $this->arrayGet($this->origin->cookie, $key, $default);
    }

    public function json($key, $default = null)
    {
        $array = json_decode($this->origin->rawContent(), JSON_OBJECT_AS_ARRAY);

        if (json_last_error() === JSON_ERROR_NONE && isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }

    protected function arrayGet($array, $key = null, $default = null)
    {
        if (null === $key) {
            return $array;
        }

        return $array[$key] ?? $default;
    }
}
